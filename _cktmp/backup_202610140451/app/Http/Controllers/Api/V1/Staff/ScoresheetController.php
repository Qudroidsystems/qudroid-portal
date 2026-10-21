<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Api\V1\Concerns\RespondsWithApi;
use App\Http\Controllers\MyScoreSheetController;
use App\Exports\RecordsheetExport;
use App\Imports\ScoresheetImport;
use App\Models\Assessment;
use App\Models\Broadsheets;
use App\Models\Schoolclass;
use App\Models\ScoresheetLock;
use App\Models\Subjectclass;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Score entry from the app. Extends the web score-sheet controller so the
 * totals, grades, cumulative figures (cum / cum_ave) and the four position
 * columns are calculated by exactly the same code as on the portal.
 *
 * A teacher may only open and edit the subject classes assigned to them;
 * staff with "Update my-subject" + admin score-entry rights are not given
 * extra reach here (admins keep using the portal for that).
 */
class ScoresheetController extends MyScoreSheetController
{
    use RespondsWithApi;

    /**
     * GET staff/scoresheets/{subjectclass}?term_id=&session_id=[&recalculate=1]
     */
    public function sheet(Request $request, int $subjectclass)
    {
        $ctx = $this->context($request, $subjectclass);
        if ($ctx instanceof \Illuminate\Http\JsonResponse) return $ctx;

        [$sjc, $staffId, $classId, $termId, $sessionId] = $ctx;

        $schoolclass = Schoolclass::with('classcategories')->find($classId);
        $assessments = $this->assessmentsFor($schoolclass);

        if ($request->boolean('recalculate')) {
            $sheets = $this->getBroadsheets($staffId, $termId, $sessionId, $classId, $subjectclass);
            if ($sheets->isNotEmpty()) {
                $this->updateClassMetrics($subjectclass, $staffId, $termId, $sessionId);
                $this->computeDynamicTotals($sheets, $assessments, $schoolclass, $termId, $sessionId);
                $this->updateSubjectPositions($subjectclass, $staffId, $termId, $sessionId);
                $this->updateClassPositions($classId, $termId, $sessionId);
            }
        }

        return $this->ok($this->payload($sjc, $staffId, $classId, $termId, $sessionId, $schoolclass, $assessments));
    }

    /**
     * POST staff/scoresheets/score
     * { broadsheet_id, assessment_id, score, is_sub?, sub_assessment_id? }
     */
    public function saveScore(Request $request)
    {
        $request->validate(['broadsheet_id' => 'required|integer']);
        $b = Broadsheets::find((int) $request->broadsheet_id);
        if (!$b) return $this->fail('Record not found.', 404, 'not_found');
        if (!$this->ownsSubjectclass($request, (int) $b->subjectclass_id)) {
            return $this->fail('This score sheet is not assigned to you.', 403, 'forbidden');
        }

        $res = $this->singleUpdateScore($request);
        return $this->wrap($res);
    }

    /**
     * POST staff/scoresheets/{subjectclass}/bulk
     * { term_id, session_id, scores: [ { id: broadsheet_id, assessments: { "<assessment_id>": score } } ] }
     * Sub-assessments: add assessment_id + is_sub=true and key the scores by sub-assessment id.
     */
    public function saveBulk(Request $request, int $subjectclass)
    {
        $ctx = $this->context($request, $subjectclass);
        if ($ctx instanceof \Illuminate\Http\JsonResponse) return $ctx;
        [$sjc, $staffId, $classId, $termId, $sessionId] = $ctx;

        $ids = collect($request->input('scores', []))->pluck('id')->map(fn ($v) => (int) $v);
        $foreign = Broadsheets::whereIn('id', $ids)->where('subjectclass_id', '!=', $subjectclass)->exists();
        if ($foreign) return $this->fail('Some rows do not belong to this score sheet.', 422, 'invalid_rows');

        $request->merge([
            'term_id' => $termId, 'session_id' => $sessionId, 'subjectclass_id' => $subjectclass,
            'staff_id' => $staffId, 'schoolclass_id' => $classId,
        ]);

        $res = $this->bulkUpdateScores($request);
        $body = $res->getData(true);
        if (empty($body['success'])) return $this->wrap($res);

        $schoolclass = Schoolclass::with('classcategories')->find($classId);
        $payload = $this->payload($sjc, $staffId, $classId, $termId, $sessionId, $schoolclass, $this->assessmentsFor($schoolclass));

        return $this->ok($payload, $body['message'] ?? 'Scores saved.', 200, isset($body['warnings']) ? ['warnings' => $body['warnings']] : []);
    }

    /**
     * GET staff/scoresheets/{subjectclass}/excel?term_id=&session_id=
     * The same protected template the portal's "Download" button produces.
     */
    public function excelDownload(Request $request, int $subjectclass)
    {
        $ctx = $this->context($request, $subjectclass);
        if ($ctx instanceof \Illuminate\Http\JsonResponse) return $ctx;
        [$sjc, $staffId, $classId, $termId, $sessionId] = $ctx;

        $slug = fn ($v, $d) => trim(preg_replace('/[^a-zA-Z0-9-]+/', '_', (string) ($v ?: $d)), '_');
        $term = DB::table('schoolterm')->where('id', $termId)->value('term');
        $session = DB::table('schoolsession')->where('id', $sessionId)->value('session');
        $filename = $slug($sjc->subject, 'subject') . '_' . $slug($sjc->class_name, 'class') . '_'
            . $slug($term, 'term') . '_' . $slug($session, 'session') . '_scoresheet.xlsx';

        try {
            $export = new RecordsheetExport($classId, $subjectclass, $termId, $sessionId, $staffId);
            return Excel::download($export, $filename);
        } catch (\Throwable $e) {
            Log::error('API scoresheet export failed', ['sjc' => $subjectclass, 'error' => $e->getMessage()]);
            return $this->fail('Could not build the spreadsheet: ' . $e->getMessage(), 500, 'export_failed');
        }
    }

    /**
     * POST staff/scoresheets/{subjectclass}/excel  (multipart: file, term_id, session_id)
     * Imports a filled template and returns the refreshed sheet.
     */
    public function excelUpload(Request $request, int $subjectclass)
    {
        $ctx = $this->context($request, $subjectclass);
        if ($ctx instanceof \Illuminate\Http\JsonResponse) return $ctx;
        [$sjc, $staffId, $classId, $termId, $sessionId] = $ctx;

        $request->validate(['file' => 'required|file|mimes:xlsx,xls|max:10240']);

        $schoolclass = Schoolclass::with('classcategories')->find($classId);
        $assessments = $this->assessmentsFor($schoolclass);
        $before = $this->payload($sjc, $staffId, $classId, $termId, $sessionId, $schoolclass, $assessments);
        if (!$before['editable']) {
            return $this->fail($before['lock_message'] ?? 'This score sheet is locked.', 423, 'locked');
        }

        $importer = new ScoresheetImport([
            'subjectclass_id' => $subjectclass, 'staff_id' => $staffId, 'term_id' => $termId,
            'session_id' => $sessionId, 'schoolclass_id' => $classId,
        ]);

        try {
            $importer->validateExcelFile($request->file('file'));
        } catch (\Throwable $e) {
            return $this->fail($e->getMessage(), 422, 'invalid_file');
        }

        try {
            Excel::import($importer, $request->file('file'));
        } catch (\Throwable $e) {
            Log::error('API scoresheet import failed', ['sjc' => $subjectclass, 'error' => $e->getMessage()]);
            return $this->fail('Import failed: ' . $e->getMessage(), 422, 'import_failed');
        } finally {
            if ($request->hasSession()) session()->forget(['import_progress', 'import_status', 'import_message']);
        }

        $failures = array_values(array_map(
            fn ($f) => is_array($f) ? $f : ['message' => (string) $f],
            (array) $importer->getFailures()
        ));
        $count = (int) $importer->getSuccessCount();

        if ($count === 0 && $failures) {
            $lines = array_map(fn ($f) => (isset($f['row']) ? 'Row ' . $f['row'] . ': ' : '') . implode('; ', (array) ($f['errors'] ?? $f['message'] ?? '')), $failures);
            return $this->fail('No records were imported. Check the file and try again.', 422, 'import_failed', ['rows' => array_values($lines)]);
        }

        // Make sure totals and positions reflect the imported scores.
        $sheets = $this->getBroadsheets($staffId, $termId, $sessionId, $classId, $subjectclass);
        if ($sheets->isNotEmpty()) {
            $this->updateClassMetrics($subjectclass, $staffId, $termId, $sessionId);
            $this->computeDynamicTotals($sheets, $assessments, $schoolclass, $termId, $sessionId);
            $this->updateSubjectPositions($subjectclass, $staffId, $termId, $sessionId);
            $this->updateClassPositions($classId, $termId, $sessionId);
        }

        $message = $failures
            ? "Imported {$count} record(s) with " . count($failures) . ' warning(s).'
            : "Imported {$count} record(s).";

        return $this->ok(
            $this->payload($sjc, $staffId, $classId, $termId, $sessionId, $schoolclass, $assessments),
            $message, 200, ['imported' => $count, 'warnings' => $failures]
        );
    }

    /**
     * GET staff/scoresheets/{subjectclass}/marksheet?term_id=&session_id=
     * Printable marks sheet (PDF).
     */
    public function marksheet(Request $request, int $subjectclass)
    {
        $ctx = $this->context($request, $subjectclass);
        if ($ctx instanceof \Illuminate\Http\JsonResponse) return $ctx;
        [, $staffId, $classId, $termId, $sessionId] = $ctx;

        $request->merge([
            'subjectclass_id' => $subjectclass, 'staff_id' => $staffId, 'term_id' => $termId,
            'session_id' => $sessionId, 'schoolclass_id' => $classId,
        ]);

        $res = $this->downloadMarksSheet($request);
        if ($res instanceof \Illuminate\Http\JsonResponse) {
            $b = $res->getData(true);
            return $this->fail($b['message'] ?? 'Could not build the marks sheet.', $res->getStatusCode(), 'marksheet_failed');
        }
        return $res;
    }

    // ── helpers ─────────────────────────────────────────────────────────

    /** Validates term/session and that the subject class belongs to the user. */
    protected function context(Request $request, int $subjectclass)
    {
        $request->validate([
            'term_id'    => 'required|integer|exists:schoolterm,id',
            'session_id' => 'required|integer|exists:schoolsession,id',
        ]);

        $sjc = DB::table('subjectclass as sjc')
            ->join('subjectteacher as st', 'st.id', '=', 'sjc.subjectteacherid')
            ->join('subject as s', 's.id', '=', 'sjc.subjectid')
            ->leftJoin('schoolclass as c', 'c.id', '=', 'sjc.schoolclassid')
            ->leftJoin('schoolarm as a', 'a.id', '=', 'c.arm')
            ->where('sjc.id', $subjectclass)
            ->first(['sjc.id', 'sjc.schoolclassid', 'sjc.teacher_editing_enabled', 'st.staffid', 's.subject', 's.subject_code',
                     DB::raw("TRIM(CONCAT(COALESCE(c.schoolclass,''), ' ', COALESCE(a.arm,''))) as class_name")]);

        if (!$sjc) return $this->fail('Score sheet not found.', 404, 'not_found');
        if ((int) $sjc->staffid !== (int) $request->user()->id) {
            return $this->fail('This score sheet is not assigned to you.', 403, 'forbidden');
        }

        return [$sjc, (int) $sjc->staffid, (int) $sjc->schoolclassid, (int) $request->term_id, (int) $request->session_id];
    }

    protected function ownsSubjectclass(Request $request, int $subjectclass): bool
    {
        return DB::table('subjectclass as sjc')->join('subjectteacher as st', 'st.id', '=', 'sjc.subjectteacherid')
            ->where('sjc.id', $subjectclass)->where('st.staffid', $request->user()->id)->exists();
    }

    protected function assessmentsFor(?Schoolclass $schoolclass)
    {
        if (!$schoolclass || $schoolclass->classcategories->isEmpty()) return collect();
        return Assessment::whereIn('classcategory_id', $schoolclass->classcategories->pluck('id'))
            ->with('subAssessments')->orderBy('id')->get();
    }

    protected function payload(object $sjc, int $staffId, int $classId, int $termId, int $sessionId, ?Schoolclass $schoolclass, $assessments): array
    {
        $rows = $this->getBroadsheets($staffId, $termId, $sessionId, $classId, (int) $sjc->id);

        $lock = ScoresheetLock::where(['subjectclass_id' => $sjc->id, 'term_id' => $termId, 'session_id' => $sessionId, 'is_active' => true])->first();
        $editingEnabled = (bool) (Subjectclass::find($sjc->id)?->teacher_editing_enabled ?? true);
        $isSenior = $schoolclass && $schoolclass->classcategories->isNotEmpty()
            ? (bool) ($schoolclass->classcategories->first()->is_senior ?? false) : false;

        return [
            'subjectclass_id' => (int) $sjc->id,
            'subject'         => $sjc->subject,
            'subject_code'    => $sjc->subject_code,
            'class_id'        => $classId,
            'class_name'      => $sjc->class_name,
            'term_id'         => $termId,
            'term'            => DB::table('schoolterm')->where('id', $termId)->value('term'),
            'session_id'      => $sessionId,
            'session'         => DB::table('schoolsession')->where('id', $sessionId)->value('session'),
            'is_senior'       => $isSenior,
            'editable'        => $editingEnabled && !$lock,
            'lock_message'    => $lock ? ($lock->reason ?? $lock->lock_reason ?? 'This score sheet has been locked by an administrator.')
                : (!$editingEnabled ? 'Teacher editing has been disabled for this subject by an administrator.' : null),
            'assessments'     => $assessments->map(fn ($a) => [
                'id' => (int) $a->id, 'name' => $a->name, 'max_score' => (float) $a->max_score,
                'sub_assessments' => $a->subAssessments->map(fn ($s) => [
                    'id' => (int) $s->id, 'name' => $s->name, 'max_score' => (float) $s->max_score,
                ])->values(),
            ])->values(),
            'students' => $rows->map(function ($b) use ($assessments) {
                $scores = [];
                foreach ($assessments as $a) {
                    $s = $b->assessmentScores->firstWhere('assessment_id', $a->id);
                    $scores[(string) $a->id] = $s ? (float) $s->score : null;
                }
                $subs = [];
                foreach ($b->subAssessmentScores as $s) {
                    $subs[(string) $s->sub_assessment_id] = (float) $s->score;
                }
                return [
                    'broadsheet_id'    => (int) $b->id,
                    'student_id'       => (int) $b->student_id,
                    'admission_no'     => $b->admissionno,
                    'first_name'       => $b->fname,
                    'last_name'        => $b->lname,
                    'other_name'       => $b->mname,
                    'photo_url'        => $this->studentPhotoUrl($b->picture),
                    'scores'           => (object) $scores,
                    'sub_scores'       => (object) $subs,
                    'total'            => $this->num($b->total),
                    'bf'               => $this->num($b->bf),
                    'cum'              => $this->num($b->cum),
                    'cum_average'      => $this->num($b->cum_ave),
                    'grade'            => $b->grade,
                    'remark'           => $b->remark,
                    'position'         => $b->position,
                    'arm_position'     => $b->arm_position,
                    'class_average'    => $this->num($b->avg),
                    'locked'           => (bool) $b->is_locked,
                    'vetted'           => (int) $b->vettedstatus === 1,
                ];
            })->values(),
        ];
    }

    /** Re-shape a web-controller JSON response into the API envelope. */
    protected function wrap($res)
    {
        $body = $res->getData(true);
        $status = $res->getStatusCode();
        if (!empty($body['success'])) {
            // The web controller returns some figures as decimal strings ("55.00").
            $data = is_array($body['data'] ?? null)
                ? array_map(fn ($v) => is_string($v) && is_numeric($v) ? (float) $v : $v, $body['data'])
                : ($body['data'] ?? null);
            return $this->ok($data, $body['message'] ?? 'Saved.');
        }
        // The web controller reports some failures with HTTP 200.
        if ($status < 400) $status = 422;
        $code = !empty($body['locked']) || $status === 423 ? 'locked' : 'save_failed';
        return $this->fail($body['message'] ?? 'Could not save.', $status, $code, (array) ($body['errors'] ?? []));
    }
}
