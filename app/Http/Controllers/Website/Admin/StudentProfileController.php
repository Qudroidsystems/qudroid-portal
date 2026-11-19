<?php

namespace App\Http\Controllers\Website\Admin;

use App\Http\Controllers\Controller;
use App\Models\Academy\Enrollment;
use App\Models\Academy\Student;
use App\Models\Website\StudentProfile;
use App\Models\Website\StudentProject;
use App\Services\Website\Showcase;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Website → Students: approve, hide, feature and edit students' public profiles. */
class StudentProfileController extends Controller
{
    public function __construct(protected Showcase $showcase) {}

    public function index(Request $request)
    {
        $status = $request->query('status');
        $profiles = StudentProfile::with('student.user')->withCount('projects')
            ->when(isset(StudentProfile::STATUSES[$status]), fn ($q) => $q->where('status', $status))
            ->orderByRaw("case status when 'pending' then 0 when 'published' then 1 else 2 end")->ordered()->get();

        return view('modules.website.students.index', [
            'profiles' => $profiles, 'status' => $status,
            'counts' => StudentProfile::selectRaw('status, count(*) n')->groupBy('status')->pluck('n', 'status'),
            // Students who don't have a profile yet, for "Add a student".
            'without' => Student::with('user')->whereDoesntHave('profile')->whereHas('enrollments', fn ($e) => $e->whereIn('status', ['active', 'completed']))
                ->get()->sortBy(fn ($s) => $s->user?->name)->values(),
        ]);
    }

    /** Start a profile for a student (staff fill it in for them). */
    public function store(Request $request)
    {
        $data = $request->validate(['student_id' => ['required', 'integer', Rule::exists('academy_students', 'id')]]);
        $student = Student::with('user')->findOrFail($data['student_id']);
        $profile = $student->profile ?? $this->showcase->saveProfile($this->showcase->profileFor($student), [], $request, null, $request->user(), true);

        return redirect()->route('website.students.edit', $profile)->with('success', 'Fill in the profile. It goes on the website once the student has agreed and you publish it.');
    }

    public function edit(Request $request, StudentProfile $profile)
    {
        $profile->load('student.user', 'projects.course');

        return view('modules.website.students.form', [
            'p' => $profile,
            'project' => $request->filled('project') ? $profile->projects->firstWhere('id', $request->integer('project')) : null,
            'courses' => Enrollment::where('student_id', $profile->student_id)->with('course')->get()->pluck('course')->filter()->unique('id'),
            'studies' => $this->showcase->studies($profile->student),
        ]);
    }

    public function update(Request $request, StudentProfile $profile)
    {
        $data = $request->validate(Showcase::profileRules() + [
            'status' => ['required', Rule::in(array_keys(StudentProfile::STATUSES))],
            'featured' => ['nullable', 'boolean'],
            'position' => ['nullable', 'integer', 'min:0', 'max:999'],
            'consent' => ['nullable', 'boolean'],
        ]);
        // Staff can record that the student agreed (e.g. on a signed form); un-ticking withdraws it.
        $share = $request->boolean('consent') ? true : ($profile->consented_at ? false : null);
        $this->showcase->saveProfile($profile, $data, $request, $share, $request->user(), true);
        $profile->position = (int) ($data['position'] ?? 0);
        $profile->save();
        $this->showcase->review($profile, $share === false ? 'draft' : $data['status'], $request->user(), $request->boolean('featured'));

        return back()->with('success', $profile->isPublic() ? 'Saved. The profile is on the website.' : 'Saved.');
    }

    /** One-click approve / hide from the list. */
    public function review(Request $request, StudentProfile $profile)
    {
        $data = $request->validate(['status' => ['required', Rule::in(['published', 'hidden'])]]);
        $this->showcase->review($profile, $data['status'], $request->user());

        return back()->with('success', $data['status'] === 'published' ? $profile->name() . ' is on the website.' : $profile->name() . ' is hidden.');
    }

    public function destroy(StudentProfile $profile)
    {
        $profile->delete();

        return redirect()->route('website.students.index')->with('success', 'Profile removed from the website.');
    }

    public function storeProject(Request $request, StudentProfile $profile)
    {
        $this->showcase->saveProject($profile, null, $request->validate(Showcase::projectRules()), $request);

        return redirect()->route('website.students.edit', $profile)->with('success', 'Project added.');
    }

    public function updateProject(Request $request, StudentProfile $profile, StudentProject $project)
    {
        abort_unless($project->profile_id === $profile->id, 404);
        $this->showcase->saveProject($profile, $project, $request->validate(Showcase::projectRules()), $request);

        return redirect()->route('website.students.edit', $profile)->with('success', 'Project saved.');
    }

    public function destroyProject(StudentProfile $profile, StudentProject $project)
    {
        abort_unless($project->profile_id === $profile->id, 404);
        $project->delete();

        return redirect()->route('website.students.edit', $profile)->with('success', 'Project removed.');
    }
}
