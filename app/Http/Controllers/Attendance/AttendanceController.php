<?php

namespace App\Http\Controllers\Attendance;

use App\Http\Controllers\Controller;
use App\Models\Academy\Attendance;
use App\Models\Academy\ClassSession;
use App\Models\Academy\Cohort;
use App\Services\Academy\AttendanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/** Class sessions and registers for academy cohorts. */
class AttendanceController extends Controller
{
    public function __construct(protected AttendanceService $attendance) {}

    /** Today's and upcoming sessions, and registers still to take. */
    public function index(Request $request)
    {
        $user = $request->user();
        $all = $user->can('Manage academy') || $user->can('View attendance');
        // Instructors see only their own classes.
        $scope = fn ($q) => $all ? $q : $q->whereHas('cohort', fn ($c) => $c->where('instructor_id', $user->id));
        $mine = fn ($q) => $all ? $q : $q->where('instructor_id', $user->id);

        return view('modules.attendance.index', [
            'today'    => $scope(ClassSession::with('cohort.course'))->whereDate('date', today())->where('status', '!=', 'cancelled')->orderBy('starts_at')->get(),
            'overdue'  => $scope(ClassSession::with('cohort.course'))->whereDate('date', '<', today())->where('status', 'scheduled')->orderByDesc('date')->limit(20)->get(),
            'upcoming' => $scope(ClassSession::with('cohort.course'))->whereDate('date', '>', today())->where('status', 'scheduled')->orderBy('date')->orderBy('starts_at')->limit(10)->get(),
            'cohorts'  => $mine(Cohort::with('course'))->whereIn('status', ['open', 'running'])->withCount('sessions')->orderBy('name')->get(),
        ]);
    }

    /** A cohort's sessions and everyone's attendance rate. */
    public function cohort(Request $request, Cohort $cohort)
    {
        abort_unless($this->attendance->canView($request->user(), $cohort), 403);
        $cohort->load('course', 'instructor', 'place');

        return view('modules.attendance.cohort', [
            'cohort'      => $cohort,
            'sessions'    => $cohort->sessions()->withCount(['attendance as present_count' => fn ($q) => $q->whereIn('status', ['present', 'late'])])->get(),
            'enrollments' => $cohort->enrollments()->with('student.user')->whereIn('status', ['active', 'pending', 'completed'])->get()->sortBy(fn ($e) => $e->student->user->name),
            'canTake'     => $this->attendance->canTake($request->user(), $cohort),
        ]);
    }

    public function store(Request $request, Cohort $cohort)
    {
        abort_unless($this->attendance->canTake($request->user(), $cohort), 403);
        $data = $request->validate([
            'date'      => ['required', 'date'],
            'starts_at' => ['nullable', 'date_format:H:i'],
            'ends_at'   => ['nullable', 'date_format:H:i', 'after:starts_at'],
            'topic'     => ['nullable', 'string', 'max:200'],
        ]);
        $cohort->sessions()->create($data);

        return back()->with('success', 'Session added.');
    }

    public function schedule(Request $request, Cohort $cohort)
    {
        abort_unless($this->attendance->canTake($request->user(), $cohort), 403);
        $data = $request->validate([
            'from'       => ['required', 'date'],
            'to'         => ['required', 'date'],
            'weekdays'   => ['required', 'array'],
            'weekdays.*' => ['integer', 'between:0,6'],
            'starts_at'  => ['nullable', 'date_format:H:i'],
            'ends_at'    => ['nullable', 'date_format:H:i', 'after:starts_at'],
        ], ['weekdays.required' => 'Pick at least one day of the week.']);

        $made = $this->attendance->schedule($cohort, Carbon::parse($data['from']), Carbon::parse($data['to']),
            array_map('intval', $data['weekdays']), $data['starts_at'] ?? null, $data['ends_at'] ?? null);

        return back()->with('success', $made ? "{$made} sessions added." : 'No new sessions: those dates are already scheduled.');
    }

    public function register(Request $request, ClassSession $session)
    {
        $session->load('cohort.course', 'takenBy');
        abort_unless($this->attendance->canView($request->user(), $session->cohort), 403);

        $event = $session->checkinEvent;

        return view('modules.attendance.register', [
            'session'  => $session,
            'roster'   => $this->attendance->roster($session),
            'marks'    => $session->attendance()->get()->keyBy('enrollment_id'),
            'canTake'  => $this->attendance->canTake($request->user(), $session->cohort),
            'event'    => $event,
            // How and when each student checked in (QR, GPS, badge), by user id.
            'records'  => $event ? $event->records()->get()->keyBy('user_id') : collect(),
        ]);
    }

    public function save(Request $request, ClassSession $session)
    {
        $request->validate(['marks' => ['required', 'array'], 'topic' => ['nullable', 'string', 'max:200']]);
        if ($request->filled('topic')) $session->update(['topic' => $request->input('topic')]);
        $saved = $this->attendance->takeRegister($session, (array) $request->input('marks'), $request->user());

        return redirect()->route('attendance.cohort', $session->cohort_id)->with('success', "Register saved for {$saved} students.");
    }

    /** Remind the students who haven't checked in yet to do so now. */
    public function remind(Request $request, ClassSession $session)
    {
        abort_unless($this->attendance->canTake($request->user(), $session->cohort), 403);
        abort_if($session->status === 'cancelled' || !$session->date->isToday(), 422, 'You can only remind students on the day of the class.');
        $event = app(\App\Services\Checkin\CheckinService::class)->forClassSession($session, $request->user());
        $session->load('cohort.course', 'cohort.place');
        $ids = $this->attendance->roster($session)->filter(fn ($e) => $e->status === 'active')->map(fn ($e) => $e->student->user_id)
            ->diff($event->records()->pluck('user_id'))->diff(Attendance::where('class_session_id', $session->id)->join('academy_enrollments', 'academy_enrollments.id', '=', 'academy_attendance.enrollment_id')
                ->join('academy_students', 'academy_students.id', '=', 'academy_enrollments.student_id')->pluck('academy_students.user_id'))->values()->all();
        \App\Services\Messaging\PortalNotifier::toUsers($ids, 'Check in now: ' . $session->cohort->course->title,
            $session->cohort->name . ($session->timeRange() ? ' (' . $session->timeRange() . ')' : '') . '. Open the app and check in'
                . ($session->cohort->place ? ' at ' . $session->cohort->place->name : '') . '.',
            route('checkin.index'), 'course', null, ['level' => 'urgent']);

        return back()->with('success', $ids ? count($ids) . ' ' . \Illuminate\Support\Str::plural('student', count($ids)) . ' reminded to check in.' : 'Everyone has checked in.');
    }

    /** Every session's mark for every student of a class, as a spreadsheet. */
    public function export(Request $request, Cohort $cohort)
    {
        abort_unless($this->attendance->canView($request->user(), $cohort), 403);
        $sessions = $cohort->sessions()->where('status', '!=', 'cancelled')->get();
        $enrollments = $cohort->enrollments()->with('student.user')->whereIn('status', ['active', 'pending', 'completed'])->get()->sortBy(fn ($e) => $e->student->user->name);
        $marks = Attendance::whereIn('class_session_id', $sessions->pluck('id'))->get()->groupBy('enrollment_id')->map(fn ($g) => $g->pluck('status', 'class_session_id'));
        $short = ['present' => 'P', 'late' => 'L', 'absent' => 'A', 'excused' => 'E'];
        $name = 'attendance-' . \Illuminate\Support\Str::slug($cohort->course->title . ' ' . $cohort->name) . '-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($sessions, $enrollments, $marks, $short) {
            $out = fopen('php://output', 'w');
            fputcsv($out, array_merge(['Student', 'Admission no.'], $sessions->map(fn ($s) => $s->date->format('Y-m-d') . ($s->starts_at ? ' ' . substr((string) $s->starts_at, 0, 5) : ''))->all(), ['Present', 'Late', 'Absent', 'Excused', 'Rate %']));
            foreach ($enrollments as $e) {
                $m = $marks->get($e->id, collect());
                $n = fn ($k) => $m->filter(fn ($v) => $v === $k)->count();
                fputcsv($out, array_merge([$e->student->user->name, $e->student->student_no],
                    $sessions->map(fn ($s) => $short[$m[$s->id] ?? ''] ?? '')->all(),
                    [$n('present'), $n('late'), $n('absent'), $n('excused'), $e->attendance_percent !== null ? (float) $e->attendance_percent : '']));
            }
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv']);
    }

    /** Attendance across classes: filter by course, class, dates and "below X%"; download; remind low attenders. */
    public function report(Request $request)
    {
        $user = $request->user();
        $all = $user->can('Manage academy') || $user->can('View attendance');
        $f = $request->validate([
            'course_id' => ['nullable', 'integer'], 'cohort_id' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'], 'to' => ['nullable', 'date'], 'below' => ['nullable', 'numeric', 'min:1', 'max:100'],
        ]);
        $mine = $all ? null : Cohort::where('instructor_id', $user->id)->pluck('id')->all();
        $rows = $this->attendance->report($mine, $f['course_id'] ?? null, $f['cohort_id'] ?? null,
            !empty($f['from']) ? Carbon::parse($f['from']) : null, !empty($f['to']) ? Carbon::parse($f['to']) : null,
            isset($f['below']) ? (float) $f['below'] : null);

        if ($request->query('export')) {
            return response()->streamDownload(function () use ($rows) {
                $out = fopen('php://output', 'w');
                fputcsv($out, ['Student', 'Admission no.', 'Course', 'Class', 'Present', 'Late', 'Absent', 'Excused', 'Rate %']);
                foreach ($rows as $r) fputcsv($out, [$r['name'], $r['student_no'], $r['course'], $r['cohort'], $r['present'], $r['late'], $r['absent'], $r['excused'], $r['rate']]);
                fclose($out);
            }, 'attendance-report-' . now()->format('Y-m-d') . '.csv', ['Content-Type' => 'text/csv']);
        }

        $cohorts = Cohort::with('course')->when($mine !== null, fn ($q) => $q->whereIn('id', $mine))->whereIn('status', ['open', 'running', 'completed'])->orderBy('name')->get();

        return view('modules.attendance.report', [
            'rows' => $rows, 'filters' => $f, 'cohorts' => $cohorts,
            'courses' => $cohorts->pluck('course')->filter()->unique('id')->sortBy('title'),
            'summary' => [
                'students' => $rows->count(),
                'average' => ($rated = $rows->whereNotNull('rate'))->isNotEmpty() ? round($rated->avg('rate'), 1) : null,
                'low' => $rows->filter(fn ($r) => $r['rate'] !== null && $r['rate'] < 75)->count(),
            ],
        ]);
    }

    /** Send a note to students about their attendance (e.g. everyone below 75%). */
    public function nudge(Request $request)
    {
        $d = $request->validate(['enrollments' => ['required', 'array', 'max:500'], 'enrollments.*' => ['integer'], 'message' => ['nullable', 'string', 'max:500']]);
        $sent = 0;
        foreach (\App\Models\Academy\Enrollment::with('cohort', 'course', 'student')->whereKey($d['enrollments'])->get() as $e) {
            if (!$this->attendance->canTake($request->user(), $e->cohort)) continue;
            $rate = $e->attendance_percent !== null ? rtrim(rtrim(number_format((float) $e->attendance_percent, 1), '0'), '.') . '%' : 'low';
            \App\Services\Messaging\PortalNotifier::toUsers([$e->student->user_id], 'Your attendance in ' . $e->course->title,
                'Your attendance is ' . $rate . '. ' . (trim((string) ($d['message'] ?? '')) ?: 'Please attend your classes and check in each time. Talk to your instructor if something is stopping you.'),
                route('academy.my'), 'course');
            $sent++;
        }

        return back()->with('success', $sent ? $sent . ' ' . \Illuminate\Support\Str::plural('student', $sent) . ' notified.' : 'Nobody was notified.');
    }

    public function cancel(Request $request, ClassSession $session)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:300']]);
        $this->attendance->cancel($session, $request->user(), $data['reason']);

        return back()->with('success', 'Session cancelled.');
    }

    public function destroy(Request $request, ClassSession $session)
    {
        abort_unless($this->attendance->canTake($request->user(), $session->cohort), 403);
        if ($session->attendance()->exists()) {
            return back()->with('error', 'The register has been taken for this session. Cancel it instead.');
        }
        $session->delete();

        return back()->with('success', 'Session removed.');
    }
}
