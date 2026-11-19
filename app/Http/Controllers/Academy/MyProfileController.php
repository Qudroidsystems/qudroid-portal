<?php

namespace App\Http\Controllers\Academy;

use App\Http\Controllers\Controller;
use App\Models\Academy\Enrollment;
use App\Models\Academy\Student;
use App\Models\Website\StudentProject;
use App\Models\Website\Testimonial;
use App\Services\Website\Showcase;
use Illuminate\Http\Request;

/** My academy → My public profile: a student's page on the website, their projects, and testimonials they write. */
class MyProfileController extends Controller
{
    public function __construct(protected Showcase $showcase) {}

    protected function student(Request $request): Student
    {
        $student = Student::where('user_id', $request->user()->id)->first();
        abort_unless($student, 404, 'Only students have a public profile.');

        return $student;
    }

    public function show(Request $request)
    {
        $student = $this->student($request)->load('user');
        $profile = $this->showcase->profileFor($student);
        $edit = $request->filled('project') && $profile->exists ? $profile->projects()->find($request->integer('project')) : null;

        return view('modules.academy.my-profile', [
            'student' => $student, 'p' => $profile, 'project' => $edit,
            'courses' => Enrollment::where('student_id', $student->id)->with('course')->get()->pluck('course')->filter()->unique('id'),
            'testimonials' => Testimonial::where('student_id', $student->id)->latest()->get(),
        ]);
    }

    public function update(Request $request)
    {
        $student = $this->student($request);
        $data = $request->validate(Showcase::profileRules() + ['share' => ['nullable', 'boolean']]);
        $profile = $this->showcase->saveProfile($this->showcase->profileFor($student), $data, $request, $request->boolean('share'), $request->user());

        return back()->with('success', match ($profile->status) {
            'pending' => 'Saved. Staff will check your profile and put it on the website.',
            'published' => 'Saved. Your profile is on the website.',
            'hidden' => 'Saved. Staff have hidden your profile for now.',
            default => 'Saved. Your profile is not on the website.',
        });
    }

    public function storeProject(Request $request)
    {
        $profile = $this->ensureProfile($request);
        $this->showcase->saveProject($profile, null, $request->validate(Showcase::projectRules()), $request);

        return redirect()->route('academy.my.profile')->with('success', 'Project added.');
    }

    public function updateProject(Request $request, StudentProject $project)
    {
        $profile = $this->ensureProfile($request);
        abort_unless($project->profile_id === $profile->id, 404);
        $this->showcase->saveProject($profile, $project, $request->validate(Showcase::projectRules()), $request);

        return redirect()->route('academy.my.profile')->with('success', 'Project saved.');
    }

    public function destroyProject(Request $request, StudentProject $project)
    {
        $profile = $this->ensureProfile($request);
        abort_unless($project->profile_id === $profile->id, 404);
        $project->delete();

        return redirect()->route('academy.my.profile')->with('success', 'Project removed.');
    }

    public function storeTestimonial(Request $request)
    {
        $student = $this->student($request)->load('user');
        $courseIds = Enrollment::where('student_id', $student->id)->pluck('course_id')->all();
        $data = $request->validate([
            'quote' => ['required', 'string', 'min:20', 'max:1500'],
            'rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'role' => ['nullable', 'string', 'max:120'],
            'course_id' => ['nullable', 'integer', \Illuminate\Validation\Rule::in($courseIds)],
            'consent' => ['accepted'],
        ]);
        $this->showcase->submitTestimonial('student', $data + ['name' => $student->user->name], $request, 'student', $request->user(), $student);

        return back()->with('success', 'Thank you. Staff will publish your words on the website after a quick look.');
    }

    /** Projects need a saved profile (draft is fine: nothing shows until they share it). */
    protected function ensureProfile(Request $request)
    {
        $student = $this->student($request);
        $profile = $this->showcase->profileFor($student);
        if (!$profile->exists) $profile = $this->showcase->saveProfile($profile, [], $request, null, $request->user());

        return $profile;
    }
}
