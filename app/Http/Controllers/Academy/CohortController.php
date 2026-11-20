<?php

namespace App\Http\Controllers\Academy;

use App\Http\Controllers\Controller;
use App\Models\Academy\Cohort;
use App\Models\Academy\Course;
use App\Models\Academy\Student;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CohortController extends Controller
{
    public function create(Course $course)
    {
        $n = $course->cohorts()->count() + 1;

        return view('modules.academy.cohorts.form', [
            'course'      => $course,
            'cohort'      => new Cohort(['code' => $course->code . '-' . now()->format('y') . '-' . str_pad((string) $n, 2, '0', STR_PAD_LEFT), 'name' => $course->title . ' — Cohort ' . $n, 'status' => 'planned']),
            'instructors' => $this->instructors(),
            'places'      => $this->places(),
        ]);
    }

    public function store(Request $request, Course $course)
    {
        $cohort = $course->cohorts()->create($this->validated($request));

        return redirect()->route('academy.cohorts.show', $cohort)->with('success', 'Cohort created.');
    }

    public function show(Cohort $cohort)
    {
        $cohort->load('course', 'instructor');
        $enrollments = $cohort->enrollments()->with('student.user', 'accessGrantedBy', 'invoices')->orderByRaw("case status when 'active' then 0 when 'pending' then 1 when 'completed' then 2 else 3 end")->get();

        // Students who can still be added to this cohort.
        $candidates = Student::with('user')->whereIn('status', Student::CAN_ENROLL)
            ->whereNotIn('id', $enrollments->filter->isOpen()->pluck('student_id'))
            ->get()->sortBy('user.name');

        return view('modules.academy.cohorts.show', compact('cohort', 'enrollments', 'candidates'));
    }

    public function edit(Cohort $cohort)
    {
        return view('modules.academy.cohorts.form', ['course' => $cohort->course, 'cohort' => $cohort, 'instructors' => $this->instructors(), 'places' => $this->places()]);
    }

    public function update(Request $request, Cohort $cohort)
    {
        $cohort->update($this->validated($request, $cohort));

        return redirect()->route('academy.cohorts.show', $cohort)->with('success', 'Cohort updated.');
    }

    /** Campuses (check-in places) a class can be held at. */
    protected function places()
    {
        return \App\Models\Checkin\Place::where('is_active', true)->orderBy('name')->get();
    }

    protected function instructors()
    {
        return User::where('user_type', User::TYPE_STAFF)->where('is_disabled', false)->orderBy('name')->pluck('name', 'id');
    }

    protected function validated(Request $request, ?Cohort $cohort = null): array
    {
        $data = $request->validate([
            'code'                   => ['required', 'string', 'max:40', 'alpha_dash', Rule::unique('academy_cohorts', 'code')->ignore($cohort?->id)],
            'name'                   => ['required', 'string', 'max:150'],
            'starts_on'              => ['nullable', 'date'],
            'ends_on'                => ['nullable', 'date', 'after_or_equal:starts_on'],
            'registration_opens_on'  => ['nullable', 'date'],
            'registration_closes_on' => ['nullable', 'date', 'after_or_equal:registration_opens_on'],
            'capacity'               => ['nullable', 'integer', 'min:1', 'max:5000'],
            'delivery_mode'          => ['nullable', Rule::in(array_keys(Course::MODES))],
            'fee_override'           => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'instructor_id'          => ['nullable', 'exists:users,id'],
            'schedule'               => ['nullable', 'string', 'max:255'],
            'place_id'               => ['nullable', 'exists:checkin_places,id'],
            'checkin_mode'           => ['nullable', Rule::in(array_keys(Cohort::CHECKIN_MODES))],
            'checkin_opens_minutes'  => ['nullable', 'integer', 'min:0', 'max:240'],
            'late_after_minutes'     => ['nullable', 'integer', 'min:0', 'max:240'],
            'status'                 => ['required', Rule::in(array_keys(Cohort::STATUSES))],
            'yearbook_note'          => ['nullable', 'string', 'max:1000'],
            'yearbook_photo_file'    => ['nullable', 'image', 'max:8192'],
            'remove_yearbook_photo'  => ['nullable', 'boolean'],
        ]);
        $data['in_yearbook'] = $request->boolean('in_yearbook');
        if ($request->hasFile('yearbook_photo_file')) {
            $data['yearbook_photo'] = $request->file('yearbook_photo_file')->store('website/yearbook', 'public');
        } elseif ($request->boolean('remove_yearbook_photo')) {
            $data['yearbook_photo'] = null;
        }
        unset($data['yearbook_photo_file'], $data['remove_yearbook_photo']);
        $data['code'] = strtoupper($data['code']);
        $data['checkin_mode'] = $data['checkin_mode'] ?? 'either';
        $data['checkin_opens_minutes'] = (int) ($data['checkin_opens_minutes'] ?? 15);
        $data['late_after_minutes'] = (int) ($data['late_after_minutes'] ?? 15);
        $data['auto_absent'] = $request->boolean('auto_absent');
        if ($data['checkin_mode'] === 'gps' && empty($data['place_id'])) {
            throw \Illuminate\Validation\ValidationException::withMessages(['place_id' => 'Choose the campus: GPS check-in needs to know where the class is.']);
        }

        if ($cohort && !empty($data['capacity'])) {
            $taken = $cohort->seatsTaken();
            if ($data['capacity'] < $taken) {
                throw \Illuminate\Validation\ValidationException::withMessages(['capacity' => "{$taken} students already hold a seat."]);
            }
        }

        return $data;
    }

    /** Every student's result on each published quiz and assignment, and their grade. */
    public function gradebook(Cohort $cohort, \App\Services\Academy\AssessmentService $assessments)
    {
        $cohort->load('course');
        $columns = $cohort->course->assessments()->where('is_published', true)->get();
        $enrollments = $cohort->enrollments()->with('student.user', 'certificate')
            ->whereIn('status', ['active', 'completed'])->where('access_granted', true)->get()
            ->sortBy(fn ($e) => $e->student->user->name)->values();

        $results = $enrollments->mapWithKeys(fn ($e) => [$e->id => $assessments->results($e)]);

        return view('modules.academy.cohorts.gradebook', compact('cohort', 'columns', 'enrollments', 'results'));
    }
}
