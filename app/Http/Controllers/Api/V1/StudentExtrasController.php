<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\SchoolInformation;
use App\Models\Student;
use App\Services\Api\StudentAccess;
use App\Services\Billing\StudentFeeStatementService;
use App\Services\ResultAccessService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use App\Services\Results\ResultDisplay;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Extra parent / student features for the app:
 *  - teachers   : form teacher(s) and subject teachers with contact details
 *  - performance: average per term and per subject across terms (for charts)
 *  - learning   : e-learning courses, assignments/homework and grades
 *  - receipt    : the payment statement PDF the portal prints
 *
 * Every endpoint honours the same parent/child link as StudentController,
 * and performance only uses terms whose results the family may see
 * (the unpaid-fees result hold applies).
 */
class StudentExtrasController extends ApiController
{
    public function __construct(protected StudentFeeStatementService $statements) {}

    // ── Teachers ────────────────────────────────────────────────────────

    public function teachers(Request $request, int $student)
    {
        if ($deny = $this->deny($request, $student)) return $deny;

        $row = StudentAccess::row($student);
        if (!$row || !$row->class_id) return $this->ok(['class_teachers' => [], 'subject_teachers' => []]);

        $classId = (int) $row->class_id;
        $termId = (int) ($row->term_id ?: $this->currentTermId());
        $sessionId = (int) ($row->session_id ?: $this->currentSessionId());
        $share = (bool) config('services.mobile.share_teacher_contacts', true);

        $formTeachers = DB::table('classteacher')->where('schoolclassid', $classId)
            ->where('sessionid', $sessionId)
            ->orderByRaw('termid = ? desc', [$termId])->orderByDesc('id')
            ->pluck('staffid')->unique()->values();

        $subjects = function (bool $byTerm) use ($classId, $termId, $sessionId) {
            return DB::table('subjectclass as sjc')
                ->join('subjectteacher as st', 'st.id', '=', 'sjc.subjectteacherid')
                ->join('subject as s', 's.id', '=', 'sjc.subjectid')
                ->where('sjc.schoolclassid', $classId)->where('st.sessionid', $sessionId)
                ->when($byTerm, fn ($q) => $q->where('st.termid', $termId))
                ->orderBy('s.subject')
                ->get(['st.staffid', 's.subject', 's.subject_code']);
        };
        $subjectRows = $subjects(true);
        if ($subjectRows->isEmpty()) $subjectRows = $subjects(false);

        $staffIds = $formTeachers->merge($subjectRows->pluck('staffid'))->unique()->filter()->values();
        $people = $this->staffCards($staffIds, $share);

        return $this->ok([
            'class_name'     => $row->class_name ?? null,
            'class_teachers' => $formTeachers->map(fn ($id) => $people[$id] ?? null)->filter()->values(),
            'subject_teachers' => $subjectRows->groupBy('staffid')->map(function ($rows, $id) use ($people) {
                $p = $people[$id] ?? null;
                if (!$p) return null;
                $p['subjects'] = $rows->pluck('subject')->unique()->values();
                return $p;
            })->filter()->sortBy('name')->values(),
            'contacts_shared' => $share,
        ]);
    }

    protected function staffCards($ids, bool $share): array
    {
        if ($ids->isEmpty()) return [];

        $users = DB::table('users')->whereIn('id', $ids)->get(['id', 'name', 'email', 'phone_number', 'gender'])->keyBy('id');
        $bio = Schema::hasTable('staffbioinfo')
            ? DB::table('staffbioinfo')->whereIn('userid', $ids)->orderByDesc('id')->get(['userid', 'title', 'phonenumber', 'email', 'job_title'])->unique('userid')->keyBy('userid')
            : collect();
        $pics = Schema::hasTable('staffpicture')
            ? DB::table('staffpicture')->whereIn('staffId', $ids)->orderByDesc('id')->get(['staffId', 'picture'])->unique('staffId')->keyBy('staffId')
            : collect();

        $out = [];
        foreach ($ids as $id) {
            $u = $users[$id] ?? null;
            if (!$u) continue;
            $b = $bio[$id] ?? null;
            $out[$id] = [
                'id'        => (int) $id,
                'name'      => trim(($b->title ?? '') . ' ' . $u->name),
                'job_title' => $b->job_title ?? null,
                'photo_url' => $this->staffPhotoUrl($pics[$id]->picture ?? null),
                'email'     => $share ? ($b->email ?? null ?: $u->email) : null,
                'phone'     => $share ? ($b->phonenumber ?? null ?: $u->phone_number) : null,
            ];
        }
        return $out;
    }

    // ── Performance ─────────────────────────────────────────────────────

    public function performance(Request $request, int $student)
    {
        if ($deny = $this->deny($request, $student)) return $deny;
        if (!ResultDisplay::viewerMaySee($request->user(), $student)) {
            return $this->fail('The school has not made results available here yet.', 403, 'not_released');
        }

        $rows = DB::table('broadsheet_records as br')->join('broadsheets as b', 'b.broadsheet_record_id', '=', 'br.id')
            ->join('subject as sj', 'sj.id', '=', 'br.subject_id')
            ->join('schoolterm as t', 't.id', '=', 'b.term_id')->join('schoolsession as ss', 'ss.id', '=', 'br.session_id')
            ->leftJoin('schoolclass as c', 'c.id', '=', 'br.schoolclass_id')
            ->where('br.student_id', $student)->where('b.total', '>', 0)
            ->orderBy('br.session_id')->orderBy('b.term_id')
            ->get(['br.session_id', 'b.term_id', 't.term', 'ss.session', 'c.schoolclass', 'sj.subject', 'b.total', 'b.avg', 'b.grade']);

        $access = app(ResultAccessService::class);
        $terms = [];
        $hidden = 0;
        foreach ($rows->groupBy(fn ($r) => $r->session_id . '-' . $r->term_id) as $group) {
            $f = $group->first();
            $chk = $access->check($student, (int) $f->term_id, (int) $f->session_id, false);
            if (empty($chk['allowed'])) { $hidden++; continue; }

            $terms[] = [
                'session_id' => (int) $f->session_id,
                'session'    => $f->session,
                'term_id'    => (int) $f->term_id,
                'term'       => $f->term,
                'label'      => $this->shortTerm($f->term) . ' ' . $this->shortSession($f->session),
                'class_name' => $f->schoolclass,
                'average'    => round($group->avg(fn ($r) => (float) $r->total), 1),
                'class_average' => ($ca = $group->filter(fn ($r) => $r->avg !== null)->avg(fn ($r) => (float) $r->avg)) !== null ? round($ca, 1) : null,
                'best'       => ($b = $group->sortByDesc(fn ($r) => (float) $r->total)->first()) ? ['subject' => $b->subject, 'score' => (float) $b->total] : null,
                'subjects'   => $group->mapWithKeys(fn ($r) => [$r->subject => (float) $r->total]),
                'grades'     => $group->countBy(fn ($r) => strtoupper(substr((string) $r->grade, 0, 1)) ?: '?'),
            ];
        }

        $termsC = collect($terms);
        $subjectNames = $termsC->flatMap(fn ($t) => array_keys($t['subjects']->all()))->unique()->sort()->values();
        $subjects = $subjectNames->map(function ($name) use ($termsC) {
            $points = $termsC->map(fn ($t) => $t['subjects'][$name] ?? null)->values();
            $vals = $points->filter(fn ($v) => $v !== null)->values();
            $trend = $vals->count() >= 2 ? round($vals->last() - $vals[$vals->count() - 2], 1) : null;
            return [
                'subject' => $name,
                'scores'  => $points,
                'latest'  => $vals->last(),
                'average' => $vals->count() ? round($vals->avg(), 1) : null,
                'trend'   => $trend,
            ];
        })->sortByDesc('latest')->values();

        $last = $termsC->last();
        $prev = $termsC->count() > 1 ? $termsC[$termsC->count() - 2] : null;

        return $this->ok([
            'terms'    => $termsC->map(fn ($t) => collect($t)->except('subjects')->all())->values(),
            'subjects' => $subjects,
            'summary'  => [
                'latest_average' => $last['average'] ?? null,
                'change'         => $last && $prev ? round($last['average'] - $prev['average'], 1) : null,
                'best_average'   => $termsC->max('average'),
                'strongest'      => $subjects->sortByDesc('average')->first()['subject'] ?? null,
                'needs_attention'=> $subjects->filter(fn ($s) => $s['latest'] !== null)->sortBy('latest')->first()['subject'] ?? null,
                'hidden_terms'   => $hidden,
            ],
        ]);
    }

    protected function shortTerm(?string $t): string
    {
        $t = strtolower((string) $t);
        return str_contains($t, 'first') || str_contains($t, '1') ? 'T1'
            : (str_contains($t, 'second') || str_contains($t, '2') ? 'T2'
            : (str_contains($t, 'third') || str_contains($t, '3') ? 'T3' : Str::limit((string) $t, 6, '')));
    }

    protected function shortSession(?string $s): string
    {
        return preg_match('/(\d{2})(\d{2})\D+(\d{2})?(\d{2})/', (string) $s, $m) ? "{$m[2]}/{$m[4]}" : (string) $s;
    }

    // ── AI learning & career guidance ──────────────────────────────────

    /** GET students/{id}/guidance — the stored or freshly built guidance. */
    public function guidance(Request $request, int $student)
    {
        return $this->guidanceReply($request, $student, false);
    }

    /** POST students/{id}/guidance/refresh — rebuild (limited per term by the admin). */
    public function refreshGuidance(Request $request, int $student)
    {
        return $this->guidanceReply($request, $student, true);
    }

    protected function guidanceReply(Request $request, int $student, bool $refresh)
    {
        if ($deny = $this->deny($request, $student)) return $deny;
        if (!ResultDisplay::viewerMaySee($request->user(), $student)) {
            return $this->fail('The school has not made results available here yet.', 403, 'not_released');
        }

        $settings = \App\Models\AiGuidanceSetting::current();
        $user = $request->user();
        $self = (int) $user->student_id === $student;
        if (!$settings->enabled || ($self && !$settings->for_students) || (!$self && !$settings->for_parents && !$user->can('View student'))) {
            return $this->fail('Learning guidance is not switched on by the school.', 403, 'guidance_disabled');
        }

        $allowed = $this->allowedTerms($student);
        $data = app(\App\Services\Ai\GuidanceService::class)->forStudent($student, $allowed, $refresh, $user->id);

        return $this->ok($data);
    }

    /** Terms with results the family may see, oldest first: [['session_id','term_id','label']]. */
    protected function allowedTerms(int $student): array
    {
        $rows = DB::table('broadsheet_records as br')->join('broadsheets as b', 'b.broadsheet_record_id', '=', 'br.id')
            ->join('schoolterm as t', 't.id', '=', 'b.term_id')->join('schoolsession as ss', 'ss.id', '=', 'br.session_id')
            ->where('br.student_id', $student)->where('b.total', '>', 0)
            ->groupBy('br.session_id', 'b.term_id', 't.term', 'ss.session')
            ->orderBy('br.session_id')->orderBy('b.term_id')
            ->get(['br.session_id', 'b.term_id', 't.term', 'ss.session']);

        $access = class_exists(ResultAccessService::class) ? app(ResultAccessService::class) : null;
        $out = [];
        foreach ($rows as $r) {
            if ($access) {
                $chk = $access->check($student, (int) $r->term_id, (int) $r->session_id, false);
                if (empty($chk['allowed'])) continue;
            }
            $out[] = ['session_id' => (int) $r->session_id, 'term_id' => (int) $r->term_id, 'label' => $r->term . ' ' . $r->session];
        }
        return $out;
    }

    // ── E-learning / homework ───────────────────────────────────────────

    public function learning(Request $request, int $student)
    {
        if ($deny = $this->deny($request, $student)) return $deny;

        $empty = ['courses' => [], 'assignments' => [], 'summary' => ['courses' => 0, 'due' => 0, 'overdue' => 0, 'graded' => 0]];
        if (!Schema::hasTable('lms_enrollments') || !Schema::hasTable('lms_courses')) return $this->ok($empty);

        $courses = DB::table('lms_enrollments as e')->join('lms_courses as c', 'c.id', '=', 'e.course_id')
            ->leftJoin('users as u', 'u.id', '=', 'c.teacher_id')
            ->where('e.student_id', $student)->where('c.is_published', true)
            ->orderByDesc('e.updated_at')
            ->get(['c.id', 'c.title', 'c.code', 'u.name as teacher', 'e.progress_percent', 'e.status']);

        if ($courses->isEmpty()) return $this->ok($empty);

        $assignments = Schema::hasTable('lms_assignments') ? DB::table('lms_assignments as a')
            ->join('lms_courses as c', 'c.id', '=', 'a.course_id')
            ->leftJoin('lms_assignment_submissions as s', function ($j) use ($student) {
                $j->on('s.assignment_id', '=', 'a.id')->where('s.student_id', $student);
            })
            ->whereIn('a.course_id', $courses->pluck('id'))->where('a.is_published', true)
            ->orderByRaw('a.due_at is null, a.due_at desc')
            ->limit(200)
            ->get(['a.id', 'a.title', 'a.instructions', 'a.max_score', 'a.due_at', 'c.title as course',
                   's.status as submission_status', 's.score', 's.feedback', 's.submitted_at', 's.graded_at'])
            : collect();

        $now = now();
        $items = $assignments->map(function ($a) use ($now) {
            $due = $a->due_at ? Carbon::parse($a->due_at) : null;
            $submitted = $a->submitted_at !== null || in_array($a->submission_status, ['submitted', 'graded', 'returned'], true);
            $state = $a->score !== null ? 'graded'
                : ($submitted ? 'submitted' : ($due && $due->isPast() ? 'overdue' : 'pending'));
            return [
                'id'           => (int) $a->id,
                'title'        => $a->title,
                'course'       => $a->course,
                'instructions' => $a->instructions ? Str::limit(trim(strip_tags($a->instructions)), 400) : null,
                'due_at'       => $due?->toIso8601String(),
                'max_score'    => $this->num($a->max_score),
                'score'        => $this->num($a->score),
                'feedback'     => $a->feedback,
                'submitted_at' => $a->submitted_at ? Carbon::parse($a->submitted_at)->toIso8601String() : null,
                'state'        => $state,
            ];
        });

        $quiz = Schema::hasTable('lms_quiz_attempts') ? DB::table('lms_quiz_attempts as a')
            ->join('lms_quizzes as q', 'q.id', '=', 'a.quiz_id')
            ->where('a.student_id', $student)->whereIn('q.course_id', $courses->pluck('id'))
            ->groupBy('q.course_id')->get(['q.course_id', DB::raw('ROUND(AVG(a.percent),1) as avg')])->keyBy('course_id') : collect();

        return $this->ok([
            'courses' => $courses->map(fn ($c) => [
                'id'        => (int) $c->id,
                'title'     => $c->title,
                'code'      => $c->code,
                'teacher'   => $c->teacher,
                'progress'  => (int) round((float) $c->progress_percent),
                'status'    => $c->status,
                'quiz_average' => isset($quiz[$c->id]) ? (float) $quiz[$c->id]->avg : null,
                'pending'   => $items->where('course', $c->title)->whereIn('state', ['pending', 'overdue'])->count(),
            ])->values(),
            'assignments' => $items->values(),
            'summary' => [
                'courses' => $courses->count(),
                'due'     => $items->where('state', 'pending')->count(),
                'overdue' => $items->where('state', 'overdue')->count(),
                'graded'  => $items->where('state', 'graded')->count(),
            ],
        ]);
    }

    // ── Payment statement PDF ───────────────────────────────────────────

    public function receipt(Request $request, int $student)
    {
        if ($deny = $this->deny($request, $student)) return $deny;

        $s = Student::find($student);
        if (!$s) return $this->fail('Student not found.', 404, 'not_found');

        $row = StudentAccess::row($student);
        $sessionId = (int) ($request->get('session_id') ?: ($row->session_id ?? null) ?: $this->currentSessionId());
        $termId = (int) ($request->get('term_id') ?: ($row->term_id ?? null) ?: $this->currentTermId());

        $st = $this->statements->buildStatement($s, $termId ?: null, $sessionId ?: null);
        if (empty($st['class'])) {
            return $this->fail('No class or fee record found for the selected term and session.', 404, 'not_found');
        }

        $school = SchoolInformation::first();
        $termName = $st['term']->term ?? 'Term';
        $sessionName = $st['session']->session ?? 'Session';

        $pdf = Pdf::loadView('student.payments.receipt-pdf', [
            'student'        => $s,
            'bills'          => $st['bills'],
            'totals'         => $st['totals'],
            'paymentHistory' => $st['paymentHistory'],
            'termName'       => $termName,
            'sessionName'    => $sessionName,
            'schoolInfo'     => $school,
            'logoBase64'     => $this->dataUri($school && $school->school_logo ? [
                storage_path('app/public/' . $school->school_logo), public_path('storage/' . $school->school_logo), public_path($school->school_logo),
            ] : [], '<svg xmlns="http://www.w3.org/2000/svg" width="80" height="80"><rect width="80" height="80" rx="40" fill="#0f2342"/></svg>'),
            'pictureBase64'  => $this->dataUri(($p = DB::table('studentpicture')->where('studentid', $student)->orderByDesc('id')->value('picture')) ? [
                public_path('storage/student_avatars/' . basename($p)), storage_path('app/public/student_avatars/' . basename($p)),
                public_path('storage/' . $p), storage_path('app/public/' . $p),
            ] : [], '<svg xmlns="http://www.w3.org/2000/svg" width="80" height="95"><rect width="80" height="95" fill="#e2e8f0"/></svg>'),
            'receiptNo'      => 'RCP-' . strtoupper(substr(md5($student . '-' . $termId . '-' . $sessionId), 0, 8)),
            'className'      => $st['class']->schoolclass,
            'generatedAt'    => Carbon::now()->format('d M Y, h:i A'),
        ])->setPaper('A4', 'portrait')->setOptions([
            'dpi' => 150, 'defaultFont' => 'DejaVu Sans', 'isRemoteEnabled' => true, 'isHtml5ParserEnabled' => true,
        ]);

        $name = 'Payment_Statement_' . Str::slug($s->admissionNo ?? 'student') . '_' . Str::slug($termName) . '.pdf';

        return response($pdf->output(), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $name . '"',
        ]);
    }

    protected function dataUri(array $paths, string $fallbackSvg): string
    {
        foreach ($paths as $path) {
            if (is_file($path) && filesize($path) > 100) {
                return 'data:' . (mime_content_type($path) ?: 'image/jpeg') . ';base64,' . base64_encode(file_get_contents($path));
            }
        }
        return 'data:image/svg+xml;base64,' . base64_encode($fallbackSvg);
    }

    protected function deny(Request $request, int $student)
    {
        if (!StudentAccess::canView($request->user(), $student)) {
            return $this->fail('This student is not linked to your account.', 403, 'forbidden');
        }
        return null;
    }
}
