<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\ViewStudentReportController;
use App\Models\Schoolsession;
use App\Models\Schoolterm;
use App\Models\Student;
use App\Services\Api\StudentAccess;
use App\Services\Api\TimetableGrid;
use App\Services\Billing\StudentFeeStatementService;
use App\Services\ResultAccessService;
use Carbon\Carbon;
use App\Services\Results\ResultDisplay;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Student data shared by the parent and student faces of the app:
 * the list of "my" students, and per student — summary, results, report
 * card, fees, attendance and timetable.
 *
 * Mirrors ParentPortalController so the app and the web portal show the
 * same figures, including the result hold for unpaid fees.
 */
class StudentController extends ApiController
{
    public function __construct(protected StudentFeeStatementService $statements) {}

    /** Children (parent) and/or self (student), each with a short summary. */
    public function index(Request $request)
    {
        $rows = StudentAccess::mine($request->user());

        return $this->ok($rows->map(fn ($c) => $this->card($c, $request->boolean('summary', true)))->values());
    }

    public function show(Request $request, int $student)
    {
        if ($deny = $this->deny($request, $student)) return $deny;

        $row = StudentAccess::mine($request->user())->firstWhere('id', $student) ?? StudentAccess::row($student);
        if (!$row) return $this->fail('Student not found.', 404, 'not_found');

        $s = Student::find($student);
        $card = $this->card($row, true);
        $card['profile'] = [
            'gender'        => $s->gender ?? null,
            'date_of_birth' => $s->dateofbirth ?? null,
            'admission_date'=> optional($s->admission_date)->toDateString(),
            'blood_group'   => $s->blood_group ?? null,
            'genotype'      => $s->genotype ?? null,
            'house'         => $s->sport_house ?? null,
            'address'       => $s->home_address2 ?? $s->permanent_address ?? null,
            'status'        => $s->student_status ?? null,
        ];

        return $this->ok($card);
    }

    // ── Results ─────────────────────────────────────────────────────────

    /**
     * Terms that have results, each flagged allowed / on hold; plus the
     * scores for one term (?term_id=&session_id=, default the latest).
     */
    public function results(Request $request, int $student)
    {
        if ($deny = $this->deny($request, $student)) return $deny;

        $terms = DB::table('broadsheet_records as br')->join('broadsheets as b', 'b.broadsheet_record_id', '=', 'br.id')
            ->join('schoolterm as t', 't.id', '=', 'b.term_id')->join('schoolsession as ss', 'ss.id', '=', 'br.session_id')
            ->leftJoin('schoolclass as c', 'c.id', '=', 'br.schoolclass_id')->leftJoin('schoolarm as a', 'a.id', '=', 'c.arm')
            ->where('br.student_id', $student)
            ->groupBy('br.session_id', 'b.term_id', 'br.schoolclass_id', 't.term', 'ss.session', 'c.schoolclass', 'a.arm')
            ->orderByDesc('br.session_id')->orderByDesc('b.term_id')
            ->get(['br.session_id', 'b.term_id', 'br.schoolclass_id as class_id', 't.term', 'ss.session',
                   DB::raw("TRIM(CONCAT(COALESCE(c.schoolclass,''), ' ', COALESCE(a.arm,''))) as class_name"),
                   DB::raw('COUNT(*) as subjects'), DB::raw('SUM(b.vettedstatus = 1) as vetted'),
                   DB::raw('ROUND(AVG(b.total), 1) as average')]);

        $access = app(ResultAccessService::class);
        $visible = ResultDisplay::viewerMaySee($request->user(), $student);
        $terms = $terms->map(function ($t) use ($access, $student, $visible) {
            $chk = $visible
                ? $access->check($student, (int) $t->term_id, (int) $t->session_id, false)
                : ['allowed' => false, 'reason' => 'not_released', 'message' => 'The school has not made results available here yet.'];
            return [
                'session_id'   => (int) $t->session_id,
                'session'      => $t->session,
                'term_id'      => (int) $t->term_id,
                'term'         => $t->term,
                'class_id'     => (int) $t->class_id,
                'class_name'   => $t->class_name,
                'subjects'     => (int) $t->subjects,
                'average'      => $this->num($t->average, 1),
                'ready'        => (int) $t->vetted >= (int) $t->subjects,
                'allowed'      => !empty($chk['allowed']),
                'not_released' => ($chk['reason'] ?? '') === 'not_released',
                'hold_message' => empty($chk['allowed']) ? ($chk['message'] ?? 'This result is on hold.') : null,
                'amount_owed'  => (float) ($chk['owed'] ?? 0),
            ];
        });

        $selected = $terms->first(fn ($t) => $t['term_id'] == $request->get('term_id') && $t['session_id'] == $request->get('session_id'))
            ?? $terms->first();

        $detail = null;
        if ($selected && $selected['allowed']) {
            $detail = $this->termDetail($student, $selected);
        }

        return $this->ok([
            'terms'    => $terms->values(),
            'selected' => $selected,
            'result'   => $detail,
        ]);
    }

    /**
     * One term's result as the app gets it (subjects, summary, comments,
     * `display` switches) — also used by the parent portal's results page.
     * $t: ['term_id','session_id','class_id'].
     */
    public function detailFor(int $student, array $t): array
    {
        return $this->termDetail($student, $t);
    }

    protected function termDetail(int $student, array $t): array
    {
        $rows = DB::table('broadsheets as b')->join('broadsheet_records as br', 'br.id', '=', 'b.broadsheet_record_id')
            ->join('subject as sj', 'sj.id', '=', 'br.subject_id')
            ->where('br.student_id', $student)->where('br.session_id', $t['session_id'])
            ->where('br.schoolclass_id', $t['class_id'])->where('b.term_id', $t['term_id'])
            ->orderBy('sj.subject')
            ->get(['b.id', 'sj.subject', 'sj.subject_code', 'b.total', 'b.bf', 'b.cum', 'b.cum_ave', 'b.grade', 'b.remark',
                   'b.subject_position_class as position', 'b.subject_position_class_total as position_total',
                   'b.arm_position', 'b.arm_position_cum', 'b.avg as class_average', 'b.cmin', 'b.cmax']);

        // Continuous-assessment breakdown (dynamic assessments), when present.
        $parts = collect();
        if ($rows->isNotEmpty() && Schema::hasTable('broadsheet_assessment_scores')) {
            $parts = DB::table('broadsheet_assessment_scores as s')->join('assessments as a', 'a.id', '=', 's.assessment_id')
                ->whereIn('s.broadsheet_id', $rows->pluck('id'))
                ->orderBy('a.id')
                ->get(['s.broadsheet_id', 'a.name', 'a.max_score', 's.score'])
                ->groupBy('broadsheet_id');
        }

        $subjects = $rows->map(fn ($r) => [
            'subject'       => $r->subject,
            'subject_code'  => $r->subject_code,
            'assessments'   => ($parts[$r->id] ?? collect())->map(fn ($p) => [
                'name' => $p->name, 'max_score' => $this->num($p->max_score), 'score' => $this->num($p->score),
            ])->values(),
            'total'         => $this->num($r->total),
            'bf'            => $this->num($r->bf),
            'cum'           => $this->num($r->cum),
            'cum_average'   => $this->num($r->cum_ave),
            'grade'         => $r->grade,
            'remark'        => $r->remark,
            'position'      => $r->position,
            'position_label'   => ResultDisplay::ordinal($r->position),
            'position_total'   => ResultDisplay::ordinal($r->position_total),
            'arm_position'     => ResultDisplay::ordinal($r->arm_position),
            'arm_position_cum' => ResultDisplay::ordinal($r->arm_position_cum),
            'class_average' => $this->num($r->class_average),
            'lowest'        => $this->num($r->cmin),
            'highest'       => $this->num($r->cmax),
        ]);

        $profile = Schema::hasTable('studentpersonalityprofiles')
            ? DB::table('studentpersonalityprofiles')->where('studentid', $student)
                ->where('termid', $t['term_id'])->where('sessionid', $t['session_id'])->orderByDesc('id')->first()
            : null;

        $total = $rows->sum(fn ($r) => (float) $r->total);

        $summary = ResultDisplay::summary($student, $t['term_id'], $t['session_id'], $t['class_id']);
        $summary['class_position_label'] = ResultDisplay::ordinal($summary['class_position']);
        $summary['arm_position_label'] = ResultDisplay::ordinal($summary['arm_position']);

        return ResultDisplay::apply([
            'summary'       => $summary,
            'subjects'      => $subjects->values(),
            'total_score'   => round($total, 2),
            'average'       => $rows->count() ? round($total / $rows->count(), 2) : null,
            'subject_count' => $rows->count(),
            'class_teacher_comment' => $profile->classteachercomment ?? null,
            'principal_comment'     => $profile->principalscomment ?? null,
            'times_absent'          => $profile->no_of_times_school_absent ?? null,
            'report_card_url'       => route('api.v1.students.report-card', [
                'student' => $student, 'session' => $t['session_id'], 'term' => $t['term_id'], 'class' => $t['class_id'],
            ]),
        ]);
    }

    /** The printable report card (PDF), same as the web portal. */
    public function reportCard(Request $request, int $student, int $session, int $term, int $class)
    {
        if ($deny = $this->deny($request, $student)) return $deny;
        if (!ResultDisplay::viewerMaySee($request->user(), $student) || !ResultDisplay::show('summary', 'report_card')) {
            return $this->fail('The school has not made report cards available here.', 403, 'not_released');
        }

        $chk = app(ResultAccessService::class)->check($student, $term, $session, false);
        if (empty($chk['allowed'])) {
            return $this->fail($chk['message'] ?? 'This result is on hold.', 403, 'result_on_hold');
        }

        $pdf = app(ViewStudentReportController::class)->renderReportCardPdf($student, $class, $session, $term);
        if (!$pdf) return $this->fail('The report card could not be generated.', 404, 'not_found');

        $s = Student::find($student);
        $name = preg_replace('/[^A-Za-z0-9_-]+/', '_', ($s->firstname ?? '') . '_' . ($s->lastname ?? '')) ?: 'student';

        return response($pdf, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="Report_Card_' . $name . '.pdf"',
        ]);
    }

    // ── Fees ────────────────────────────────────────────────────────────

    public function fees(Request $request, int $student)
    {
        if ($deny = $this->deny($request, $student)) return $deny;

        [$sessions, $terms] = $this->periods($student);
        [$sessionId, $termId] = $this->pickPeriod($request, $student, $sessions, $terms);

        $st = $this->statements->buildStatement(Student::find($student), $termId ?: null, $sessionId ?: null);

        return $this->ok([
            'session_id' => $sessionId,
            'term_id'    => $termId,
            'sessions'   => $sessions,
            'terms'      => $terms,
            'class'      => isset($st['class']) && $st['class'] ? ['id' => $st['class']->id ?? null, 'name' => $st['class']->schoolclass ?? null] : null,
            'message'    => $st['statementError'] ?? null,
            'totals'     => array_map('floatval', (array) ($st['totals'] ?? [])),
            'arrears'    => [
                'has_arrears'   => (bool) ($st['arrears']['has_arrears'] ?? false),
                'total_arrears' => (float) ($st['arrears']['total_arrears'] ?? 0),
                'bills'         => collect($st['arrears']['bills'] ?? [])->map(fn ($b) => [
                    'title'   => $b['title'] ?? null,
                    'term'    => $b['term_name'] ?? null,
                    'session' => $b['session_name'] ?? null,
                    'balance' => (float) ($b['balance'] ?? $b['amount'] ?? 0),
                ])->values(),
            ],
            'bills' => collect($st['bills'] ?? [])->map(fn ($b) => [
                'id'               => $b['id'],
                'title'            => $b['title'],
                'description'      => $b['description'],
                'category'         => $b['category'],
                'due_date'         => $b['due_date'],
                'is_overdue'       => (bool) $b['is_overdue'],
                'original_amount'  => (float) $b['original_amount'],
                'amount_payable'   => (float) $b['adjusted_amount'],
                'savings'          => (float) $b['total_savings'],
                'savings_labels'   => array_values(array_filter(array_merge([$b['scholarship_label'] ?? null], (array) ($b['discount_labels'] ?? [])))),
                'amount_paid'      => (float) $b['amount_paid'],
                'balance'          => (float) $b['balance'],
                'progress'         => (float) $b['progress'],
                'status'           => $b['status'],
            ])->values(),
            'payments' => collect($st['paymentHistory'] ?? [])->map(fn ($p) => [
                'id'            => $p->id,
                'paid_at'       => $p->paid_at ? Carbon::parse($p->paid_at)->toIso8601String() : null,
                'bill'          => $p->bill_title,
                'amount'        => (float) $p->amount_paid,
                'balance_after' => (float) $p->balance_after,
                'method'        => $p->method,
                'reference'     => $p->reference,
                'invoice_no'    => $p->invoice_no,
                'status'        => $p->status,
            ])->values(),
        ]);
    }

    // ── Attendance ──────────────────────────────────────────────────────

    public function attendance(Request $request, int $student)
    {
        if ($deny = $this->deny($request, $student)) return $deny;

        [$sessions, $terms] = $this->periods($student);
        [$sessionId, $termId] = $this->pickPeriod($request, $student, $sessions, $terms);

        $rows = DB::table('student_attendance')->where('student_id', $student)
            ->where('term_id', $termId)->where('session_id', $sessionId)
            ->orderByDesc('attendance_date')->orderBy('period')
            ->get(['attendance_date', 'period', 'status', 'notes', 'time_in', 'time_out']);

        $counts = $rows->countBy('status');
        $present = (int) ($counts['present'] ?? 0) + (int) ($counts['late'] ?? 0);

        return $this->ok([
            'session_id' => $sessionId,
            'term_id'    => $termId,
            'sessions'   => $sessions,
            'terms'      => $terms,
            'summary'    => [
                'records'    => $rows->count(),
                'present'    => (int) ($counts['present'] ?? 0),
                'late'       => (int) ($counts['late'] ?? 0),
                'absent'     => (int) ($counts['absent'] ?? 0),
                'excused'    => (int) ($counts['excused'] ?? 0),
                'sick_leave' => (int) ($counts['sick_leave'] ?? 0),
                'rate'       => $rows->count() ? round($present / $rows->count() * 100, 1) : null,
            ],
            'days' => $rows->groupBy(fn ($r) => (string) $r->attendance_date)->map(fn ($day, $date) => [
                'date'    => $date,
                'periods' => $day->map(fn ($r) => [
                    'period' => $r->period, 'status' => $r->status, 'notes' => $r->notes,
                    'time_in' => $r->time_in, 'time_out' => $r->time_out,
                ])->values(),
            ])->values(),
        ]);
    }

    // ── Timetable ───────────────────────────────────────────────────────

    public function timetable(Request $request, int $student)
    {
        if ($deny = $this->deny($request, $student)) return $deny;

        $child = StudentAccess::row($student);
        $empty = ['published' => false, 'days' => [], 'periods' => [], 'slots' => []];
        if (!$child || !$child->class_id || !Schema::hasTable('timetable_settings')) return $this->ok($empty);

        $q = DB::table('timetable_settings')->where('schoolclass_id', $child->class_id)->where('is_active', 1);
        if ($child->session_id) $q->where('session_id', $child->session_id);
        $hasPublished = Schema::hasColumn('timetable_settings', 'is_published');
        if ($hasPublished) $q->orderByDesc('is_published');
        $setting = $q->orderByDesc('id')->first();

        if (!$setting || ($hasPublished && !$setting->is_published)) return $this->ok($empty);

        return $this->ok(TimetableGrid::forSetting($setting));
    }

    // ── helpers ─────────────────────────────────────────────────────────

    protected function deny(Request $request, int $student)
    {
        if (!StudentAccess::canView($request->user(), $student)) {
            return $this->fail('This student is not linked to your account.', 403, 'forbidden');
        }
        return null;
    }

    /** One student for the home screen. */
    protected function card(object $c, bool $withSummary): array
    {
        $out = [
            'id'           => (int) $c->id,
            'first_name'   => $c->firstname,
            'last_name'    => $c->lastname,
            'other_name'   => $c->othername,
            'full_name'    => trim(implode(' ', array_filter([$c->firstname, $c->othername, $c->lastname]))),
            'admission_no' => $c->admissionNo,
            'relationship' => $c->relationship ?? null,
            'class_id'     => $c->class_id ? (int) $c->class_id : null,
            'class_name'   => $c->class_name ?: null,
            'term_id'      => $c->term_id ? (int) $c->term_id : null,
            'session_id'   => $c->session_id ? (int) $c->session_id : null,
            'photo_url'    => $this->studentPhotoUrl($c->picture ?? null),
        ];

        if (!$withSummary) return $out;

        $termId = (int) $c->term_id; $sessionId = (int) $c->session_id;

        $balance = null;
        try {
            $st = $this->statements->buildStatement(Student::find($c->id), $termId ?: null, $sessionId ?: null);
            $balance = (float) ($st['totals']['outstanding'] ?? 0) + (float) ($st['arrears']['total_arrears'] ?? 0);
        } catch (\Throwable $e) {}

        $att = DB::table('student_attendance')->where('student_id', $c->id)->where('term_id', $termId)->where('session_id', $sessionId)
            ->selectRaw("SUM(status IN ('present','late')) as present, COUNT(*) as total, SUM(status = 'absent') as absent")->first();

        $latest = DB::table('broadsheet_records as br')->join('broadsheets as b', 'b.broadsheet_record_id', '=', 'br.id')
            ->where('br.student_id', $c->id)->orderByDesc('br.session_id')->orderByDesc('b.term_id')
            ->first(['br.session_id', 'b.term_id']);

        $out['summary'] = [
            'fee_balance'     => $balance,
            'attendance_rate' => $att && $att->total ? round($att->present / $att->total * 100) : null,
            'days_absent'     => (int) ($att->absent ?? 0),
            'latest_result'   => $latest ? [
                'term_id'    => (int) $latest->term_id,
                'session_id' => (int) $latest->session_id,
                'label'      => Schoolterm::where('id', $latest->term_id)->value('term') . ' · ' . Schoolsession::where('id', $latest->session_id)->value('session'),
            ] : null,
        ];

        return $out;
    }

    /** Sessions the student has records in (plus the current one), and all terms. */
    protected function periods(int $studentId): array
    {
        $ids = collect()
            ->merge(DB::table('studentclass')->where('studentId', $studentId)->pluck('sessionid'))
            ->merge(Schema::hasTable('student_bill_payment_book') ? DB::table('student_bill_payment_book')->where('student_id', $studentId)->pluck('session_id') : [])
            ->merge(DB::table('broadsheet_records')->where('student_id', $studentId)->pluck('session_id'))
            ->merge(Schoolsession::where('status', 'Current')->pluck('id'))
            ->map(fn ($v) => (int) $v)->filter()->unique();

        return [
            Schoolsession::whereIn('id', $ids)->orderByDesc('id')->get(['id', 'session', 'status']),
            Schoolterm::orderBy('id')->get(['id', 'term']),
        ];
    }

    /** ?session_id=&term_id=, else the student's current placement, else the school's current period. */
    protected function pickPeriod(Request $request, int $studentId, $sessions, $terms): array
    {
        $row = StudentAccess::row($studentId);
        $sessionId = (int) ($request->get('session_id') ?: ($row->session_id ?? null) ?: $this->currentSessionId() ?: ($sessions->first()->id ?? 0));
        $termId    = (int) ($request->get('term_id') ?: ($row->term_id ?? null) ?: $this->currentTermId() ?: ($terms->first()->id ?? 0));
        return [$sessionId, $termId];
    }
}
