<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Api\V1\ApiController;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * A teacher's subject assignments (subject × class × term) with score-entry
 * progress — the app's way into each score sheet. Same figures as the
 * portal's "My Subjects" page.
 */
class SubjectController extends ApiController
{
    public function index(Request $request)
    {
        $user = $request->user();
        $sessionId = (int) ($request->get('session_id') ?: $this->currentSessionId());
        $termId = $request->filled('term_id') ? (int) $request->term_id : null;

        $rows = DB::table('subjectteacher as st')
            ->join('subject as s', 's.id', '=', 'st.subjectid')
            ->leftJoin('subjectclass as sjc', 'sjc.subjectteacherid', '=', 'st.id')
            ->leftJoin('schoolclass as sc', 'sc.id', '=', 'sjc.schoolclassid')
            ->leftJoin('schoolarm as arm', 'arm.id', '=', 'sc.arm')
            ->leftJoin('schoolterm as t', 't.id', '=', 'st.termid')
            ->leftJoin('schoolsession as ss', 'ss.id', '=', 'st.sessionid')
            ->where('st.staffid', $user->id)
            ->when($sessionId, fn ($q) => $q->where('st.sessionid', $sessionId))
            ->when($termId, fn ($q) => $q->where('st.termid', $termId))
            ->orderBy('st.termid')->orderBy('s.subject')->orderBy('sc.schoolclass')->orderBy('arm.arm')
            ->get([
                'st.id as subjectteacher_id', 'sjc.id as subjectclass_id', 's.id as subject_id', 's.subject', 's.subject_code',
                'sc.id as schoolclass_id', 'sc.schoolclass', 'arm.arm', 'st.termid', 't.term', 'st.sessionid', 'ss.session',
            ]);

        $rows = $this->attachProgress($rows);

        $withClass = $rows->whereNotNull('subjectclass_id');

        return $this->ok($rows->map(fn ($r) => [
            'subjectteacher_id' => (int) $r->subjectteacher_id,
            'subjectclass_id'   => $r->subjectclass_id ? (int) $r->subjectclass_id : null,
            'subject_id'        => (int) $r->subject_id,
            'subject'           => $r->subject,
            'subject_code'      => $r->subject_code,
            'class_id'          => $r->schoolclass_id ? (int) $r->schoolclass_id : null,
            'class_name'        => trim(($r->schoolclass ?? '') . ' ' . ($r->arm ?? '')) ?: null,
            'term_id'           => (int) $r->termid,
            'term'              => $r->term,
            'session_id'        => (int) $r->sessionid,
            'session'           => $r->session,
            'students'          => $r->students,
            'entered'           => $r->entered,
            'vetted'            => $r->vetted,
            'progress'          => $r->progress,
            'status'            => $r->status,
        ])->values(), null, 200, [
            'session_id'  => $sessionId,
            'subjects'    => $rows->pluck('subject_id')->unique()->count(),
            'classes'     => $withClass->pluck('schoolclass_id')->unique()->count(),
            'students'    => (int) $withClass->sum('students'),
            'progress'    => $withClass->count() ? (int) round($withClass->avg('progress')) : 0,
            'periods'     => $this->periods($user->id),
        ]);
    }

    /** Sessions (newest first) and their terms in which this teacher had subjects — for the history picker. */
    protected function periods(int $staffId): array
    {
        $rows = DB::table('subjectteacher as st')
            ->join('schoolsession as ss', 'ss.id', '=', 'st.sessionid')
            ->join('schoolterm as t', 't.id', '=', 'st.termid')
            ->where('st.staffid', $staffId)
            ->distinct()->orderByDesc('st.sessionid')->orderBy('st.termid')
            ->get(['st.sessionid', 'ss.session', 'ss.status', 'st.termid', 't.term']);

        return $rows->groupBy('sessionid')->map(fn ($g) => [
            'session_id' => (int) $g->first()->sessionid,
            'session'    => $g->first()->session,
            'current'    => strcasecmp((string) $g->first()->status, 'Current') === 0,
            'terms'      => $g->map(fn ($r) => ['id' => (int) $r->termid, 'name' => $r->term])->unique('id')->values(),
        ])->values()->all();
    }

    /** Same counting rules as MySubjectController::attachProgress(). */
    protected function attachProgress(Collection $rows): Collection
    {
        $ids = $rows->pluck('subjectclass_id')->filter()->unique()->values();

        $registered = $ids->isEmpty() ? collect() : DB::table('subjectRegistrationStatus')
            ->whereIn('subjectclassid', $ids)
            ->groupBy('subjectclassid', 'termid', 'sessionid')
            ->get(['subjectclassid', 'termid', 'sessionid', DB::raw('COUNT(DISTINCT studentid) as n')])
            ->keyBy(fn ($r) => "{$r->subjectclassid}_{$r->termid}_{$r->sessionid}");

        $sheets = $ids->isEmpty() ? collect() : DB::table('broadsheets as b')
            ->join('broadsheet_records as br', 'br.id', '=', 'b.broadsheet_record_id')
            ->whereIn('b.subjectclass_id', $ids)
            ->groupBy('b.subjectclass_id', 'b.term_id', 'br.session_id')
            ->get(['b.subjectclass_id', 'b.term_id', 'br.session_id',
                   DB::raw('COUNT(DISTINCT br.student_id) as row_count'),
                   DB::raw('COUNT(DISTINCT CASE WHEN b.total > 0 THEN br.student_id END) as entered'),
                   DB::raw('SUM(CASE WHEN b.vettedstatus = 1 THEN 1 ELSE 0 END) as vetted')])
            ->keyBy(fn ($r) => "{$r->subjectclass_id}_{$r->term_id}_{$r->session_id}");

        return $rows->map(function ($r) use ($registered, $sheets) {
            $key = "{$r->subjectclass_id}_{$r->termid}_{$r->sessionid}";
            $reg = (int) ($registered[$key]->n ?? 0);
            $sheet = $sheets[$key] ?? null;
            $students = $r->subjectclass_id ? max($reg, (int) ($sheet->row_count ?? 0)) : 0;
            $entered = (int) ($sheet->entered ?? 0);

            $r->students = $students;
            $r->entered  = min($entered, $students ?: $entered);
            $r->vetted   = (int) ($sheet->vetted ?? 0);
            $r->progress = $students > 0 ? (int) round($r->entered / $students * 100) : 0;
            $r->status   = !$r->subjectclass_id ? 'unlinked'
                : ($students === 0 ? 'no_students'
                : ($r->progress >= 100 ? 'complete' : ($r->entered > 0 ? 'in_progress' : 'not_started')));
            return $r;
        });
    }
}
