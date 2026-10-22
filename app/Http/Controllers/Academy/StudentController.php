<?php

namespace App\Http\Controllers\Academy;

use App\Http\Controllers\Controller;
use App\Models\Academy\Cohort;
use App\Models\Academy\Enrollment;
use App\Models\Academy\Student;
use App\Models\User;
use App\Services\Messaging\PortalNotifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StudentController extends Controller
{
    public function index(Request $request)
    {
        $students = Student::with('user')
            ->withCount(['enrollments as open_enrollments' => fn ($q) => $q->whereIn('status', Enrollment::HOLDS_SEAT)])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%' . $request->q . '%';
                $q->where(fn ($w) => $w->where('student_no', 'like', $term)
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', $term)->orWhere('email', 'like', $term)->orWhere('phone_number', 'like', $term)));
            })
            ->latest('id')->paginate(25)->withQueryString();

        $counts = Student::selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');

        return view('modules.academy.students.index', compact('students', 'counts'));
    }

    public function create()
    {
        return view('modules.academy.students.form', ['student' => new Student(['status' => 'admitted']), 'user' => new User()]);
    }

    /**
     * Creates the student's sign-in account (user type "student", role
     * Student, temporary password) together with their academy record.
     */
    public function store(Request $request)
    {
        $data = $this->validated($request);
        $password = $data['password'] ?: Str::password(10, symbols: false);

        $student = DB::transaction(function () use ($data, $password, $request) {
            $user = User::create([
                'name'          => $data['name'],
                'email'         => $data['email'],
                'phone_number'  => $data['phone_number'] ?? null,
                'gender'        => $data['gender'] ?? null,
                'date_of_birth' => $data['date_of_birth'] ?? null,
                'address'       => $data['address'] ?? null,
                'user_type'     => User::TYPE_STUDENT,
                'password'      => $password,
            ]);
            $user->forceFill(['must_change_password' => true])->save();
            $user->assignRole('Student');

            return Student::create($this->profile($data) + [
                'user_id'     => $user->id,
                'student_no'  => Student::nextNumber(),
                'status'      => $data['status'],
                'admitted_at' => $data['status'] === 'applicant' ? null : now(),
                'admitted_by' => $data['status'] === 'applicant' ? null : $request->user()->id,
            ]);
        });

        PortalNotifier::toUsers([$student->user_id], 'Welcome to ' . config('qudroid.name'), 'Your student number is ' . $student->student_no . '.', route('academy.my'), 'course');

        return redirect()->route('academy.students.show', $student)
            ->with('success', "Student {$student->student_no} created.")
            ->with('temp_password', $data['password'] ? null : $password);
    }

    public function show(Student $student)
    {
        $student->load('user', 'admittedBy');

        return view('modules.academy.students.show', [
            'student'     => $student,
            'enrollments' => $student->enrollments()->with('course', 'cohort', 'accessGrantedBy', 'enrolledBy', 'invoices')->get(),
            'cohorts'     => Cohort::with('course')->whereIn('status', Cohort::ENROLLABLE)->orderBy('starts_on')->get(),
        ]);
    }

    public function edit(Student $student)
    {
        return view('modules.academy.students.form', ['student' => $student, 'user' => $student->user]);
    }

    public function update(Request $request, Student $student)
    {
        $data = $this->validated($request, $student);

        DB::transaction(function () use ($data, $student) {
            $student->user->update([
                'name'          => $data['name'],
                'email'         => $data['email'],
                'phone_number'  => $data['phone_number'] ?? null,
                'gender'        => $data['gender'] ?? null,
                'date_of_birth' => $data['date_of_birth'] ?? null,
                'address'       => $data['address'] ?? null,
            ]);
            $student->update($this->profile($data));
        });

        return redirect()->route('academy.students.show', $student)->with('success', 'Student updated.');
    }

    /** Admit, suspend, reinstate, mark completed or withdraw. */
    public function status(Request $request, Student $student)
    {
        $data = $request->validate(['status' => ['required', Rule::in(array_keys(Student::STATUSES))]]);
        $changes = ['status' => $data['status']];
        if ($student->status === 'applicant' && $data['status'] !== 'applicant' && !$student->admitted_at) {
            $changes += ['admitted_at' => now(), 'admitted_by' => $request->user()->id];
        }
        $student->update($changes);

        if ($changes['status'] === 'admitted' && isset($changes['admitted_at'])) {
            PortalNotifier::toUsers([$student->user_id], 'You have been admitted', 'Welcome to the academy. Your student number is ' . $student->student_no . '.', route('academy.my'), 'course');
        }

        return back()->with('success', 'Status changed to ' . $student->status_label . '.');
    }

    protected function profile(array $data): array
    {
        return collect($data)->only([
            'education_level', 'institution', 'occupation', 'emergency_contact_name', 'emergency_contact_phone',
            'guardian_name', 'guardian_phone', 'referral_source', 'notes',
        ])->all();
    }

    protected function validated(Request $request, ?Student $student = null): array
    {
        return $request->validate([
            'name'                    => ['required', 'string', 'max:150'],
            'email'                   => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($student?->user_id)],
            'phone_number'            => ['nullable', 'string', 'max:30'],
            'gender'                  => ['nullable', Rule::in(['male', 'female'])],
            'date_of_birth'           => ['nullable', 'date', 'before:today'],
            'address'                 => ['nullable', 'string', 'max:500'],
            'status'                  => [$student ? 'nullable' : 'required', Rule::in(['applicant', 'admitted'])],
            'password'                => [$student ? 'prohibited' : 'nullable', 'string', 'min:8', 'max:100'],
            'education_level'         => ['nullable', 'string', 'max:80'],
            'institution'             => ['nullable', 'string', 'max:150'],
            'occupation'              => ['nullable', 'string', 'max:120'],
            'emergency_contact_name'  => ['nullable', 'string', 'max:150'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:30'],
            'guardian_name'           => ['nullable', 'string', 'max:150'],
            'guardian_phone'          => ['nullable', 'string', 'max:30'],
            'referral_source'         => ['nullable', 'string', 'max:120'],
            'notes'                   => ['nullable', 'string', 'max:5000'],
        ]) + ['password' => null];
    }
}
