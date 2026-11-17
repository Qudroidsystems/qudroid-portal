<?php

namespace App\Http\Controllers\Academy;

use App\Http\Controllers\Controller;
use App\Models\Academy\Application;
use App\Models\Academy\Course;
use App\Services\Academy\ApplicationService;
use Illuminate\Http\Request;

/** Academy staff reviewing online applications, and students withdrawing their own. */
class ApplicationController extends Controller
{
    public function __construct(protected ApplicationService $applications) {}

    public function index(Request $request)
    {
        $status = $request->query('status', 'submitted');
        $q = trim((string) $request->query('q'));

        $applications = Application::with('student.user', 'course', 'cohort', 'choices.course')
            ->when(array_key_exists($status, Application::STATUSES), fn ($w) => $w->where('status', $status))
            ->when($request->integer('course_id'), fn ($w, $id) => $w->whereHas('choices', fn ($c) => $c->where('course_id', $id)))
            ->when($q !== '', fn ($w) => $w->where(fn ($s) => $s->where('reference', 'like', "%{$q}%")
                ->orWhereHas('student.user', fn ($u) => $u->where('name', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%")->orWhere('phone_number', 'like', "%{$q}%"))))
            ->orderBy($status === 'submitted' ? 'created_at' : 'updated_at', $status === 'submitted' ? 'asc' : 'desc')
            ->paginate(25)->withQueryString();

        return view('modules.academy.applications.index', [
            'applications' => $applications,
            'status'       => $status,
            'counts'       => Application::selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status'),
            'courses'      => Course::orderBy('title')->pluck('title', 'id'),
        ]);
    }

    public function show(Application $application)
    {
        $application->load('student.user', 'student.enrollments.course', 'course', 'cohort', 'reviewer', 'enrollment', 'choices.course', 'choices.cohort', 'choices.enrollment', 'invoice.allocations.payment');

        return view('modules.academy.applications.show', [
            'application' => $application,
            'others'      => $application->student->applications()->with('course', 'choices.course')->whereKeyNot($application->id)->get(),
            'gateways'    => app(\App\Services\Finance\PaymentService::class)->onlineGateways(),
        ]);
    }

    public function admit(Request $request, Application $application)
    {
        $data = $request->validate([
            'decision_note' => ['nullable', 'string', 'max:500'],
            'choices'       => ['nullable', 'array'],
            'choices.*'     => ['integer'],
        ]);
        [$application, $problem] = $this->applications->admit($application, $request->user(), $data['decision_note'] ?? null,
            $request->has('choices') ? $data['choices'] : null);

        $back = redirect()->route('academy.applications.show', $application);
        $name = $application->student->user->name;
        $no = $application->student->student_no;

        return $problem
            ? $back->with('error', "{$name} is admitted ({$no}), but some classes could not be booked. {$problem} Enroll them on another class from their student record.")
            : $back->with('success', "{$name} is admitted with admission number {$no} and enrolled. The invoice and a link to pay have been e-mailed.");
    }

    public function decline(Request $request, Application $application)
    {
        $data = $request->validate(['decision_note' => ['required', 'string', 'max:500']]);
        $this->applications->decline($application, $request->user(), $data['decision_note']);

        return redirect()->route('academy.applications.index')->with('success', "Application {$application->reference} declined.");
    }

    public function withdraw(Request $request, Application $application)
    {
        abort_unless($application->student->user_id === $request->user()->id, 404);
        $this->applications->withdraw($application);

        return redirect()->route('academy.my')->with('success', "Application {$application->reference} withdrawn.");
    }
}
