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
        return view('modules.academy.cohorts.form', ['course' => $cohort->course, 'cohort' => $cohort, 'instructors' => $this->instructors()]);
    }

    public function update(Request $request, Cohort $cohort)
    {
        $cohort->update($this->validated($request, $cohort));

        return redirect()->route('academy.cohorts.show', $cohort)->with('success', 'Cohort updated.');
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
            'status'                 => ['required', Rule::in(array_keys(Cohort::STATUSES))],
        ]);
        $data['code'] = strtoupper($data['code']);

        if ($cohort && !empty($data['capacity'])) {
            $taken = $cohort->seatsTaken();
            if ($data['capacity'] < $taken) {
                throw \Illuminate\Validation\ValidationException::withMessages(['capacity' => "{$taken} students already hold a seat."]);
            }
        }

        return $data;
    }
}
