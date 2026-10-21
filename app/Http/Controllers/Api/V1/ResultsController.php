<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\ViewStudentReportController;
use App\Models\ReportApproval;
use App\Services\ClassResultsLoader;
use App\Services\Reporting\ReportApprovalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Class broadsheets and report-card approval for staff.
 *
 * Who sees what (same rules as the portal):
 *  - class teachers: their own classes; they submit report cards
 *    ("Submit report cards");
 *  - "View report-approvals" / "Approve report cards" / "View student-report":
 *    every class; approvers approve, return or re-open.
 *
 * Broadsheet figures come from ClassResultsLoader, which uses exactly the
 * same maths as the web broadsheet (BF, Cum, Cum Ave, grades, GPA).
 */
class ResultsController extends ApiController
{
    public function __construct(protected ReportApprovalService $svc) {}

    /** GET results/board?term_id&session_id — classes with readiness and approval status. */
    public function board(Request $request)
    {
        [$termId, $sessionId] = $this->period($request);
        $user = $request->user();

        $only = $this->seesAll($user) ? null : $this->myClassIds($user, $sessionId);
        if ($only !== null && !$only) return $this->ok([], null, 200, $this->boardMeta($user, $termId, $sessionId));

        $board = ReportApprovalService::available() ? $this->svc->board($termId, $sessionId, $only) : collect();

        return $this->ok($board->map(fn ($r) => [
            'class_id'          => (int) $r->class_id,
            'class_name'        => $r->name,
            'teacher'           => $r->teacher,
            'students'          => (int) $r->students,
            'subjects'          => (int) $r->subjects,
            'entries'           => (int) $r->entries,
            'vetted'            => (int) $r->vetted,
            'vetted_pct'        => (int) $r->vetted_pct,
            'teacher_comments'  => (int) $r->t_comments,
            'principal_comments'=> (int) $r->p_comments,
            'ready'             => (bool) $r->ready,
            'status'            => $r->status,
            'status_label'      => ReportApproval::STATUSES[$r->status][0] ?? ucfirst($r->status),
            'approval_id'       => $r->approval?->id,
        ])->values(), null, 200, $this->boardMeta($user, $termId, $sessionId));
    }

    /**
     * GET results/classes/{class}/broadsheet?term_id&session_id&basis=cum_ave|total
     * One row per student with every subject, averages and class position.
     */
    public function broadsheet(Request $request, int $class)
    {
        [$termId, $sessionId] = $this->period($request);
        if ($deny = $this->denyClass($request, $class, $sessionId)) return $deny;

        $basis = $request->get('basis') === 'total' ? 'total' : 'cum_ave';
        $data = app(ClassResultsLoader::class)->load($termId, $sessionId, [$class], $basis);

        $subjects = collect($data['subjects'])->sortBy('subject_name', SORT_NATURAL)->values();
        $rows = collect($data['rows'])->filter(fn ($r) => $r['num_subjects'] > 0 || !empty($r['subjects']));

        // Class position on the chosen basis (ties share a position: 1, 2, 2, 4).
        $measure = $basis === 'total' ? 'term_ave' : 'cum_ave';
        $sorted = $rows->sortByDesc($measure)->values();
        $positions = [];
        $prev = null; $pos = 0;
        foreach ($sorted as $i => $r) {
            if ($prev === null || $r[$measure] < $prev) { $pos = $i + 1; $prev = $r[$measure]; }
            $positions[$r['id']] = $pos;
        }

        $classRow = $data['classes'][$class] ?? null;
        // Subject positions as stored by the portal (all arms): by cumulative or by term total.
        $subjectPositions = DB::table('broadsheets as b')->join('broadsheet_records as br', 'br.id', '=', 'b.broadsheet_record_id')
            ->where('br.schoolclass_id', $class)->where('br.session_id', $sessionId)->where('b.term_id', $termId)
            ->get(['br.student_id', 'br.subject_id', 'b.cum', 'b.subject_position_class', 'b.subject_position_class_total'])
            ->keyBy(fn ($p) => $p->student_id . '-' . $p->subject_id);
        $posKey = $basis === 'total' ? 'subject_position_class_total' : 'subject_position_class';
        $out = $sorted->map(fn ($r) => [
            'student_id'   => $r['id'],
            'admission_no' => $r['admissionno'],
            'name'         => trim(($r['lastname'] ?? '') . ' ' . ($r['firstname'] ?? '')),
            'gender'       => $r['gender'],
            'photo_url'    => $this->studentPhotoUrl($r['picture'] ?? null),
            'subjects'     => (object) collect($r['subjects'])->map(fn ($s, $sid) => [
                'total'    => $this->num($s['total']),
                'cum'      => $this->num($subjectPositions[$r['id'] . '-' . $sid]->cum ?? null),
                'cum_ave'  => $this->num($s['cum_ave']),
                'grade'    => $s['grade'],
                'position' => \App\Services\Results\ResultDisplay::ordinal($subjectPositions[$r['id'] . '-' . $sid]->{$posKey} ?? null),
            ])->all(),
            'num_subjects' => $r['num_subjects'],
            'total_term'   => $this->num($r['total_term'], 1),
            'term_average' => $this->num($r['term_ave'], 1),
            'total_cum'    => $this->num($r['total_cum'], 1),
            'cum_average'  => $this->num($r['cum_ave'], 1),
            'gpa'          => $this->num($r['gpa']),
            'position'     => $positions[$r['id']] ?? null,
        ]);

        $avg = $sorted->count() ? round($sorted->avg($measure), 1) : null;

        return $this->ok([
            'class_id'   => $class,
            'class_name' => $classRow ? trim(($classRow->schoolclass ?? '') . ' ' . ($classRow->arm_name ?? '')) : null,
            'term_id'    => $termId,
            'term'       => DB::table('schoolterm')->where('id', $termId)->value('term'),
            'session_id' => $sessionId,
            'session'    => DB::table('schoolsession')->where('id', $sessionId)->value('session'),
            'basis'      => $basis,
            'class_average' => $avg,
            'subjects'   => $subjects->map(fn ($s) => [
                'subject_id' => (int) $s['subject_id'], 'name' => $s['subject_name'], 'code' => $s['subject_code'] ?: null,
            ])->values(),
            'students'   => $out->values(),
            'class_size' => $sorted->count(),
            'gpa'        => \App\Services\Results\ResultDisplay::supportsGpa() && \App\Services\Results\ResultDisplay::show('summary', 'gpa'),
        ]);
    }

    /** GET results/classes/{class}/approval?term_id&session_id — approval record, readiness and students. */
    public function approval(Request $request, int $class)
    {
        [$termId, $sessionId] = $this->period($request);
        if ($deny = $this->denyClass($request, $class, $sessionId)) return $deny;
        if (!ReportApprovalService::available()) return $this->fail('Report approval is not set up on this portal.', 404, 'not_found');

        $a = $this->svc->record($class, $termId, $sessionId);
        return $this->ok($this->presentApproval($request, $a));
    }

    public function submit(Request $request, int $approval)
    {
        $a = ReportApproval::find($approval);
        if (!$a) return $this->fail('Not found.', 404, 'not_found');
        $user = $request->user();
        if (!$this->svc->canSubmit($user, $a->schoolclass_id, $a->session_id)) {
            return $this->fail('Only the class teacher can submit this class.', 403, 'forbidden');
        }
        if ($a->status === 'approved') return $this->fail('These report cards are already approved.', 422, 'closed');

        $r = $this->svc->readiness($a->schoolclass_id, $a->term_id, $a->session_id);
        if (!$r) return $this->fail('There are no results for this class and term yet.', 422, 'no_results');
        if (!$r->ready && !$request->boolean('force')) {
            return $this->fail(($r->entries - $r->vetted) . ' subject score(s) are not vetted yet. Get them vetted first, or submit anyway.', 422, 'not_vetted');
        }
        $cfg = ReportApprovalService::settings()->config ?? [];
        if (!empty($cfg['require_comments']) && $r->t_comments < $r->students) {
            return $this->fail('Class teacher comments are missing for ' . ($r->students - $r->t_comments) . ' student(s).', 422, 'comments_missing');
        }

        $this->svc->submit($a, $user, $request->input('note'));
        return $this->ok($this->presentApproval($request, $a->fresh()), 'Submitted for approval. The principal has been notified.');
    }

    public function approve(Request $request, int $approval)
    {
        if ($deny = $this->denyApprover($request)) return $deny;
        $a = ReportApproval::find($approval);
        if (!$a) return $this->fail('Not found.', 404, 'not_found');

        $this->svc->approve($a, $request->user(), $request->input('note'));
        return $this->ok($this->presentApproval($request, $a->fresh()),
            'Approved.' . (ReportApprovalService::enforced() ? ' Students and parents can now see these results.' : ''));
    }

    public function returnBack(Request $request, int $approval)
    {
        if ($deny = $this->denyApprover($request)) return $deny;
        $request->validate(['note' => 'required|string|max:500']);
        $a = ReportApproval::find($approval);
        if (!$a) return $this->fail('Not found.', 404, 'not_found');

        $this->svc->returnToTeacher($a, $request->user(), $request->note);
        return $this->ok($this->presentApproval($request, $a->fresh()), 'Returned to the class teacher with your note.');
    }

    public function reopen(Request $request, int $approval)
    {
        if ($deny = $this->denyApprover($request)) return $deny;
        $a = ReportApproval::find($approval);
        if (!$a) return $this->fail('Not found.', 404, 'not_found');

        $this->svc->reopen($a, $request->user(), $request->input('note'));
        return $this->ok($this->presentApproval($request, $a->fresh()), 'Approval withdrawn.');
    }

    /** POST results/approve-all { term_id, session_id } — every submitted class. */
    public function approveAll(Request $request)
    {
        if ($deny = $this->denyApprover($request)) return $deny;
        [$termId, $sessionId] = $this->period($request);
        $list = ReportApproval::where('term_id', $termId)->where('session_id', $sessionId)->where('status', 'submitted')->get();
        foreach ($list as $a) $this->svc->approve($a, $request->user(), 'Approved in bulk');
        return $this->ok(['approved' => $list->count()], $list->count() . ' class(es) approved.');
    }

    /** GET results/approvals/{id}/preview/{student} — report card PDF before release. */
    public function preview(Request $request, int $approval, int $student)
    {
        $a = ReportApproval::find($approval);
        if (!$a) return $this->fail('Not found.', 404, 'not_found');
        if ($deny = $this->denyClass($request, $a->schoolclass_id, $a->session_id)) return $deny;

        $pdf = app(ViewStudentReportController::class)->renderReportCardPdf($student, $a->schoolclass_id, $a->session_id, $a->term_id);
        if (!$pdf) return $this->fail('The report card could not be generated.', 404, 'not_found');

        return response($pdf, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="Report_Card_Preview.pdf"']);
    }

    // ── helpers ─────────────────────────────────────────────────────────

    protected function presentApproval(Request $request, ReportApproval $a): array
    {
        $user = $request->user();
        $r = $this->svc->readiness($a->schoolclass_id, $a->term_id, $a->session_id);

        $students = DB::table('broadsheet_records as br')->join('broadsheets as b', 'b.broadsheet_record_id', '=', 'br.id')
            ->join('studentRegistration as s', 's.id', '=', 'br.student_id')
            ->where('br.schoolclass_id', $a->schoolclass_id)->where('br.session_id', $a->session_id)->where('b.term_id', $a->term_id)
            ->groupBy('s.id', 's.firstname', 's.lastname', 's.admissionNo')
            ->orderBy('s.lastname')->orderBy('s.firstname')
            ->get(['s.id', 's.firstname', 's.lastname', 's.admissionNo',
                   DB::raw('COUNT(*) as entries'), DB::raw('SUM(b.vettedstatus = 1) as vetted'), DB::raw('ROUND(AVG(b.total), 1) as average')]);

        $comments = DB::table('studentpersonalityprofiles')->where('schoolclassid', $a->schoolclass_id)
            ->where('termid', $a->term_id)->where('sessionid', $a->session_id)
            ->get(['studentid', 'classteachercomment', 'principalscomment'])->keyBy('studentid');

        $names = DB::table('users')->whereIn('id', collect($a->history ?? [])->pluck('by')->merge([$a->submitted_by, $a->reviewed_by])->filter()->unique())->pluck('name', 'id');

        return [
            'id'            => $a->id,
            'class_id'      => (int) $a->schoolclass_id,
            'label'         => $this->svc->label($a),
            'term_id'       => (int) $a->term_id,
            'session_id'    => (int) $a->session_id,
            'status'        => $a->status,
            'status_label'  => ReportApproval::STATUSES[$a->status][0] ?? ucfirst($a->status),
            'submitted_by'  => $names[$a->submitted_by] ?? null,
            'submitted_at'  => optional($a->submitted_at)->toIso8601String(),
            'submit_note'   => $a->submit_note,
            'reviewed_by'   => $names[$a->reviewed_by] ?? null,
            'reviewed_at'   => optional($a->reviewed_at)->toIso8601String(),
            'review_note'   => $a->review_note,
            'enforced'      => ReportApprovalService::enforced(),
            'can_submit'    => $a->status !== 'approved' && $this->svc->canSubmit($user, $a->schoolclass_id, $a->session_id),
            'can_approve'   => $this->can($user, 'Approve report cards'),
            'readiness'     => $r ? [
                'students' => (int) $r->students, 'entries' => (int) $r->entries, 'vetted' => (int) $r->vetted,
                'vetted_pct' => (int) $r->vetted_pct, 'teacher_comments' => (int) $r->t_comments,
                'principal_comments' => (int) $r->p_comments, 'ready' => (bool) $r->ready,
            ] : null,
            'history' => collect($a->history ?? [])->reverse()->values()->map(fn ($h) => [
                'action' => $h['action'] ?? '', 'by' => $names[$h['by'] ?? 0] ?? null, 'note' => $h['note'] ?? null, 'at' => $h['at'] ?? null,
            ]),
            'students' => $students->map(fn ($s) => [
                'id'      => (int) $s->id,
                'name'    => trim($s->lastname . ' ' . $s->firstname),
                'admission_no' => $s->admissionNo,
                'entries' => (int) $s->entries,
                'vetted'  => (int) $s->vetted,
                'average' => $this->num($s->average, 1),
                'teacher_comment'   => $comments[$s->id]->classteachercomment ?? null,
                'principal_comment' => $comments[$s->id]->principalscomment ?? null,
            ])->values(),
        ];
    }

    protected function boardMeta($user, int $termId, int $sessionId): array
    {
        return [
            'term_id' => $termId, 'session_id' => $sessionId,
            'can_approve' => $this->can($user, 'Approve report cards'),
            'enforced' => ReportApprovalService::available() && ReportApprovalService::enforced(),
            'terms' => DB::table('schoolterm')->orderBy('id')->get(['id', 'term']),
            'sessions' => DB::table('schoolsession')->orderByDesc('id')->get(['id', 'session']),
        ];
    }

    protected function period(Request $request): array
    {
        return [
            (int) ($request->get('term_id') ?: $request->input('term_id') ?: $this->currentTermId()),
            (int) ($request->get('session_id') ?: $request->input('session_id') ?: $this->currentSessionId()),
        ];
    }

    protected function seesAll($user): bool
    {
        return $this->can($user, 'Approve report cards') || $this->can($user, 'View report-approvals') || $this->can($user, 'View student-report');
    }

    protected function myClassIds($user, int $sessionId): array
    {
        return DB::table('classteacher')->where('staffid', $user->id)->where('sessionid', $sessionId)
            ->pluck('schoolclassid')->map(fn ($v) => (int) $v)->unique()->values()->all();
    }

    protected function denyClass(Request $request, int $classId, int $sessionId)
    {
        $user = $request->user();
        if ($this->seesAll($user) || in_array($classId, $this->myClassIds($user, $sessionId), true)) return null;
        return $this->fail('You can only open your own classes.', 403, 'forbidden');
    }

    protected function denyApprover(Request $request)
    {
        return $this->can($request->user(), 'Approve report cards') ? null : $this->fail('Only the principal can do this.', 403, 'forbidden');
    }

    protected function can($user, string $perm): bool
    {
        try { return $user->can($perm); } catch (\Throwable $e) { return false; }
    }
}
