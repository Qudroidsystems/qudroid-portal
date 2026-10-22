<?php

namespace App\Http\Controllers\Academy;

use App\Http\Controllers\Controller;
use App\Models\Academy\Course;
use App\Models\Academy\Enrollment;
use App\Models\Academy\Programme;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CourseController extends Controller
{
    public function index(Request $request)
    {
        $manager = $request->user()->can('Manage academy');

        $courses = Course::with('programme')
            ->withCount(['cohorts', 'enrollments as active_enrollments' => fn ($q) => $q->whereIn('status', Enrollment::HOLDS_SEAT)])
            ->when(!$manager, fn ($q) => $q->published())
            ->when($manager && $request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('programme'), fn ($q) => $q->where('programme_id', $request->programme))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('title', 'like', '%' . $request->q . '%')->orWhere('code', 'like', '%' . $request->q . '%')))
            ->orderBy('title')->paginate(20)->withQueryString();

        return view('modules.academy.courses.index', [
            'courses'    => $courses,
            'programmes' => Programme::orderBy('name')->pluck('name', 'id'),
            'manager'    => $manager,
        ]);
    }

    public function create()
    {
        return view('modules.academy.courses.form', $this->formData(new Course([
            'level' => 'beginner', 'delivery_mode' => 'hybrid', 'currency' => config('qudroid.academy.currency', 'NGN'), 'status' => 'draft',
        ])));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $course = Course::create($data + ['created_by' => $request->user()->id]);
        $course->prerequisites()->sync($request->input('prerequisites', []));

        return redirect()->route('academy.courses.show', $course)->with('success', 'Course created. Add a cohort when you are ready to take registrations.');
    }

    public function show(Request $request, Course $course)
    {
        $manager = $request->user()->can('Manage academy');
        abort_unless($manager || $course->isPublished() || $request->user()->can('Manage course content'), 404);

        $course->load('programme', 'prerequisites', 'creator');
        $cohorts = $course->cohorts()->with('instructor')
            ->withCount(['enrollments as seats' => fn ($q) => $q->whereIn('status', Enrollment::HOLDS_SEAT)])
            ->when(!$manager, fn ($q) => $q->whereNotIn('status', ['cancelled']))
            ->get();

        $learning = app(\App\Services\Academy\LearningService::class);
        $teacher = $learning->isTeacher($request->user(), $course);
        $outline = $learning->outline($course, $teacher);
        $enrollment = $learning->enrollmentFor($request->user(), $course);

        return view('modules.academy.courses.show', compact('course', 'cohorts', 'manager', 'teacher', 'outline', 'enrollment'));
    }

    public function edit(Course $course)
    {
        return view('modules.academy.courses.form', $this->formData($course->load('prerequisites')));
    }

    public function update(Request $request, Course $course)
    {
        $course->update($this->validated($request, $course));
        $course->prerequisites()->sync(array_diff($request->input('prerequisites', []), [$course->id]));

        return redirect()->route('academy.courses.show', $course)->with('success', 'Course updated. Fee changes apply to new enrollments only.');
    }

    /** Courses with enrollments are archived (kept for history); others are removed. */
    public function destroy(Course $course)
    {
        if ($course->enrollments()->exists()) {
            $course->update(['status' => 'archived']);

            return redirect()->route('academy.courses.show', $course)->with('success', 'The course has enrollments, so it was archived instead of deleted.');
        }
        $course->delete();

        return redirect()->route('academy.courses.index')->with('success', 'Course deleted.');
    }

    protected function formData(Course $course): array
    {
        return [
            'course'     => $course,
            'programmes' => Programme::where('is_active', true)->orWhere('id', $course->programme_id)->orderBy('name')->pluck('name', 'id'),
            'others'     => Course::where('id', '!=', $course->id ?? 0)->orderBy('title')->pluck('title', 'id'),
        ];
    }

    protected function validated(Request $request, ?Course $course = null): array
    {
        $data = $request->validate([
            'programme_id'   => ['nullable', 'exists:academy_programmes,id'],
            'code'           => ['required', 'string', 'max:30', 'alpha_dash', Rule::unique('academy_courses', 'code')->ignore($course?->id)],
            'title'          => ['required', 'string', 'max:200'],
            'summary'        => ['nullable', 'string', 'max:500'],
            'description'    => ['nullable', 'string', 'max:20000'],
            'level'          => ['required', Rule::in(array_keys(Course::LEVELS))],
            'delivery_mode'  => ['required', Rule::in(array_keys(Course::MODES))],
            'duration_weeks' => ['nullable', 'integer', 'min:1', 'max:520'],
            'fee'            => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'currency'       => ['required', 'string', 'size:3'],
            'installments_allowed' => ['nullable', 'boolean'],
            'min_deposit'    => ['nullable', 'numeric', 'min:0', 'lte:fee'],
            'status'         => ['required', Rule::in(array_keys(Course::STATUSES))],
            'pass_grade'     => ['nullable', 'integer', 'min:0', 'max:100'],
            'certificate_enabled' => ['nullable', 'boolean'],
            'prerequisites'  => ['nullable', 'array'],
            'prerequisites.*' => ['integer', 'exists:academy_courses,id'],
        ]);
        unset($data['prerequisites']);
        $data['code'] = strtoupper($data['code']);
        $data['currency'] = strtoupper($data['currency']);
        $data['installments_allowed'] = $request->boolean('installments_allowed');
        if (!$data['installments_allowed']) $data['min_deposit'] = null;
        $data['certificate_enabled'] = $request->boolean('certificate_enabled', $course?->certificate_enabled ?? true);
        $data['pass_grade'] ??= $course?->pass_grade ?? 50;

        return $data;
    }
}
