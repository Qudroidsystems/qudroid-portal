<?php

namespace App\Http\Controllers\Academy;

use App\Http\Controllers\Controller;
use App\Models\Academy\Cohort;
use App\Models\Academy\Course;
use App\Models\Academy\Enrollment;
use App\Models\Academy\Student;
use App\Services\Academy\EnrollmentService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EnrollmentController extends Controller
{
    public function __construct(protected EnrollmentService $service) {}

    public function index(Request $request)
    {
        $enrollments = Enrollment::with('student.user', 'course', 'cohort')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('course'), fn ($q) => $q->where('course_id', $request->course))
            ->when($request->input('access') === 'locked', fn ($q) => $q->where('access_granted', false)->whereIn('status', Enrollment::HOLDS_SEAT))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%' . $request->q . '%';
                $q->whereHas('student', fn ($s) => $s->where('student_no', 'like', $term)->orWhereHas('user', fn ($u) => $u->where('name', 'like', $term)));
            })
            ->latest('enrolled_at')->paginate(25)->withQueryString();

        return view('modules.academy.enrollments.index', [
            'enrollments' => $enrollments,
            'courses'     => Course::withTrashed()->orderBy('title')->pluck('title', 'id'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'student_id'          => ['required', 'exists:academy_students,id'],
            'cohort_id'           => ['required', 'exists:academy_cohorts,id'],
            'discount_amount'     => ['nullable', 'numeric', 'min:0'],
            'waive_prerequisites' => ['nullable', 'boolean'],
            'notes'               => ['nullable', 'string', 'max:2000'],
        ]);

        $enrollment = $this->service->enroll(
            Student::findOrFail($data['student_id']),
            Cohort::findOrFail($data['cohort_id']),
            $request->user(),
            ['discount_amount' => $data['discount_amount'] ?? 0, 'waive_prerequisites' => $request->boolean('waive_prerequisites'), 'notes' => $data['notes'] ?? null],
        );

        return back()->with('success', $enrollment->access_granted
            ? 'Enrolled. There is no fee to pay, so course access is open.'
            : 'Enrolled. Course access opens when payment is confirmed.');
    }

    public function grantAccess(Request $request, Enrollment $enrollment)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);
        $this->service->grantAccess($enrollment, $request->user(), $data['reason']);

        return back()->with('success', 'Course access opened.');
    }

    public function complete(Request $request, Enrollment $enrollment)
    {
        $this->service->complete($enrollment, $request->user());
        $certificate = $enrollment->certificate()->where('status', 'issued')->first();
        $blocker = $certificate ? null : app(\App\Services\Academy\CertificateService::class)->blocker($enrollment->fresh());

        return back()->with('success', 'Marked completed.' . ($certificate ? " Certificate {$certificate->serial} issued." : ($blocker && $enrollment->course->certificate_enabled ? " No certificate: {$blocker}" : '')));
    }

    public function end(Request $request, Enrollment $enrollment)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['withdrawn', 'cancelled'])],
            'note'   => ['nullable', 'string', 'max:500'],
        ]);
        $this->service->end($enrollment, $request->user(), $data['status'], $data['note'] ?? null);

        return back()->with('success', 'Enrollment ' . $data['status'] . '.');
    }
}
