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
        $scope = fn ($q) => $all ? $q : $q->whereHas('cohort', fn ($c) => $c->where('instructor_id', $user->id));

        return view('modules.attendance.index', [
            'today'    => $scope(ClassSession::with('cohort.course'))->whereDate('date', today())->where('status', '!=', 'cancelled')->orderBy('starts_at')->get(),
            'overdue'  => $scope(ClassSession::with('cohort.course'))->whereDate('date', '<', today())->where('status', 'scheduled')->orderByDesc('date')->limit(20)->get(),
            'upcoming' => $scope(ClassSession::with('cohort.course'))->whereDate('date', '>', today())->where('status', 'scheduled')->orderBy('date')->orderBy('starts_at')->limit(10)->get(),
            'cohorts'  => $scope(Cohort::with('course'))->whereIn('status', ['open', 'running'])->withCount('sessions')->orderBy('name')->get(),
        ]);
    }

    /** A cohort's sessions and everyone's attendance rate. */
    public function cohort(Request $request, Cohort $cohort)
    {
        abort_unless($this->attendance->canView($request->user(), $cohort), 403);
        $cohort->load('course', 'instructor');

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

        return view('modules.attendance.register', [
            'session'  => $session,
            'roster'   => $this->attendance->roster($session),
            'marks'    => $session->attendance()->get()->keyBy('enrollment_id'),
            'canTake'  => $this->attendance->canTake($request->user(), $session->cohort),
        ]);
    }

    public function save(Request $request, ClassSession $session)
    {
        $request->validate(['marks' => ['required', 'array'], 'topic' => ['nullable', 'string', 'max:200']]);
        if ($request->filled('topic')) $session->update(['topic' => $request->input('topic')]);
        $saved = $this->attendance->takeRegister($session, (array) $request->input('marks'), $request->user());

        return redirect()->route('attendance.cohort', $session->cohort_id)->with('success', "Register saved for {$saved} students.");
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
