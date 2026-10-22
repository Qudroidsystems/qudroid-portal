<?php

namespace App\Http\Controllers\Academy;

use App\Http\Controllers\Controller;
use App\Models\Academy\Cohort;
use App\Models\Academy\Course;
use App\Models\Academy\Enrollment;
use App\Models\Academy\Student;
use Illuminate\Http\Request;

/** Academy home: the overview for academy staff, "My academy" for everyone else. */
class AcademyController extends Controller
{
    public function index(Request $request)
    {
        if (!$request->user()->can('Manage academy')) {
            return redirect()->route('academy.my');
        }

        $students = Student::selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');

        return view('modules.academy.index', [
            'students'       => $students,
            'courses'        => Course::published()->count(),
            'draftCourses'   => Course::where('status', 'draft')->count(),
            'openCohorts'    => Cohort::with('course', 'instructor')->withCount(['enrollments as seats' => fn ($q) => $q->whereIn('status', Enrollment::HOLDS_SEAT)])
                                    ->whereIn('status', Cohort::ENROLLABLE)->orderBy('starts_on')->limit(8)->get(),
            'pending'        => Enrollment::where('status', 'pending')->count(),
            'active'         => Enrollment::where('status', 'active')->count(),
            'recent'         => Enrollment::with('student.user', 'course', 'cohort')->latest('enrolled_at')->limit(8)->get(),
            'applicants'     => Student::with('user')->where('status', 'applicant')->latest()->limit(6)->get(),
        ]);
    }

    /** A student's own courses, and the cohorts an instructor teaches. */
    public function my(Request $request)
    {
        $user = $request->user();
        $student = Student::where('user_id', $user->id)->first();

        return view('modules.academy.my', [
            'student'     => $student,
            'enrollments' => $student ? $student->enrollments()->with('course', 'cohort.instructor', 'invoices')->get() : collect(),
            'teaching'    => Cohort::with('course')->withCount(['enrollments as seats' => fn ($q) => $q->whereIn('status', Enrollment::HOLDS_SEAT)])
                                ->where('instructor_id', $user->id)->whereNotIn('status', ['cancelled'])->orderByDesc('starts_on')->get(),
        ]);
    }
}
