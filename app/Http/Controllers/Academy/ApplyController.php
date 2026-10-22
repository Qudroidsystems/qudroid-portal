<?php

namespace App\Http\Controllers\Academy;

use App\Http\Controllers\Controller;
use App\Models\Academy\Application;
use App\Models\Academy\Cohort;
use App\Models\Academy\Course;
use App\Services\Academy\ApplicationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The public Apply pages (no sign-in needed). Signed-in students use the
 * same pages and skip the personal details.
 */
class ApplyController extends Controller
{
    public function __construct(protected ApplicationService $applications) {}

    public function index()
    {
        $courses = Course::published()
            ->with(['cohorts' => fn ($q) => $q->acceptingApplications()->orderBy('starts_on')])
            ->whereHas('cohorts', fn ($q) => $q->acceptingApplications())
            ->orderBy('title')->get();

        return view('apply.index', compact('courses'));
    }

    public function create(Cohort $cohort)
    {
        abort_unless($cohort->acceptingApplications(), 404);
        $cohort->load('course');

        return view('apply.form', ['cohort' => $cohort, 'student' => $this->student()]);
    }

    public function store(Request $request, Cohort $cohort)
    {
        abort_unless($cohort->acceptingApplications(), 404);

        // Honeypot: a field people never see. Bots fill it; pretend it worked.
        if (filled($request->input('website'))) {
            return redirect()->route('apply.index');
        }

        $student = $this->student();
        abort_if(!$student && $request->user(), 403, 'Only students and new applicants can apply.');

        $rules = [
            'motivation'       => ['required', 'string', 'min:20', 'max:2000'],
            'experience_level' => ['required', Rule::in(array_keys(Application::EXPERIENCE))],
            'has_laptop'       => ['required', 'boolean'],
        ];
        if (!$student) {
            $rules += [
                'name'            => ['required', 'string', 'max:150'],
                'email'           => ['required', 'email:rfc', 'max:190'],
                'phone_number'    => ['required', 'string', 'max:30'],
                'gender'          => ['nullable', Rule::in(['male', 'female'])],
                'education_level' => ['nullable', 'string', 'max:80'],
                'occupation'      => ['nullable', 'string', 'max:120'],
                'referral_source' => ['nullable', 'string', 'max:120'],
                'consent'         => ['accepted'],
            ];
        }
        $data = $request->validate($rules);
        $answers = array_intersect_key($data, ['motivation' => 1, 'experience_level' => 1, 'has_laptop' => 1]);

        $application = $student
            ? $this->applications->applyAsStudent($student, $cohort, $answers, $request->ip())
            : $this->applications->applyAsGuest($cohort, $data, $answers, $request->ip());

        // Only this browser may see the thank-you page.
        $request->session()->put('applied', $application->reference);

        return redirect()->route('apply.done', $application->reference);
    }

    public function done(Request $request, string $reference)
    {
        abort_unless($request->session()->get('applied') === $reference, 404);
        $application = Application::with('course', 'cohort', 'student.user')->where('reference', $reference)->firstOrFail();

        return view('apply.done', compact('application'));
    }

    protected function student(): ?\App\Models\Academy\Student
    {
        return auth()->user() ? \App\Models\Academy\Student::where('user_id', auth()->id())->first() : null;
    }
}
