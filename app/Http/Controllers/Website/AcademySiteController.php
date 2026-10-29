<?php

namespace App\Http\Controllers\Website;

use App\Http\Controllers\Controller;
use App\Models\Academy\Application;
use App\Models\Academy\Cohort;
use App\Models\Academy\Course;
use App\Models\Academy\Student;
use App\Models\User;
use App\Services\Academy\ApplicationService;
use App\Services\Website\Content;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;

/**
 * Courses on the website, and registering for one. Registering is an
 * Academy application. For courses set to "take payment at registration"
 * the application is admitted straight away (which enrols the person and
 * issues the fee invoice) and they go on to pay online.
 */
class AcademySiteController extends Controller
{
    public function __construct(protected ApplicationService $applications) {}

    public function index()
    {
        return view('site.academy', ['courses' => Content::courses()]);
    }

    public function course(string $slug)
    {
        $course = Course::published()->where('slug', $slug)
            ->with(['cohorts' => fn ($q) => $q->acceptingApplications()->orderBy('starts_on'), 'programme'])
            ->firstOrFail();

        return view('site.course', ['c' => $course, 'student' => $this->student()]);
    }

    public function register(string $slug, Cohort $cohort)
    {
        $course = $this->courseFor($slug, $cohort);

        return view('site.register', ['c' => $course, 'cohort' => $cohort, 'student' => $this->student()]);
    }

    public function store(Request $request, string $slug, Cohort $cohort)
    {
        $course = $this->courseFor($slug, $cohort);
        if (filled($request->input('website'))) {   // honeypot
            return redirect()->route('site.academy.course', $course->slug);
        }

        $student = $this->student();
        if (!$student && $request->user()) {
            return back()->withErrors(['email' => 'You are signed in with a staff account. Sign out to register as a student.']);
        }

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
        $data = $request->validate($rules, ['motivation.min' => 'Tell us a little more (at least 20 characters).'], [
            'motivation' => 'reason for joining', 'experience_level' => 'experience', 'has_laptop' => 'laptop answer', 'phone_number' => 'phone number', 'consent' => 'agreement',
        ]);
        $answers = array_intersect_key($data, ['motivation' => 1, 'experience_level' => 1, 'has_laptop' => 1]);

        $application = $student
            ? $this->applications->applyAsStudent($student, $cohort, $answers, $request->ip())
            : $this->applications->applyAsGuest($cohort, $data, $answers, $request->ip());

        if ($course->instant_enrollment && ($by = $this->registrar($course))) {
            try {
                [$application, $problem] = $this->applications->admit($application, $by, 'Registered and admitted on the website.');
                if ($problem) Log::warning('Website registration could not enrol', ['application' => $application->reference, 'problem' => $problem]);
            } catch (\Throwable $e) {
                Log::error('Website registration could not admit', ['application' => $application->reference, 'error' => $e->getMessage()]);
            }
        }

        $request->session()->put('site_registered', $application->reference);

        return redirect()->route('site.academy.registered', $application->reference);
    }

    public function registered(Request $request, string $reference)
    {
        abort_unless($request->session()->get('site_registered') === $reference, 404);
        $application = Application::with('course', 'cohort', 'student.user', 'enrollment.invoices')->where('reference', $reference)->firstOrFail();
        $invoice = $application->enrollment?->invoices->firstWhere(fn ($i) => $i->isOpen() && $i->balanceMinor() > 0);

        return view('site.registered', [
            'application' => $application,
            'invoice' => $invoice,
            'payUrl' => $invoice ? URL::temporarySignedRoute('site.pay', now()->addDays(14), ['invoice' => $invoice->id]) : null,
        ]);
    }

    protected function courseFor(string $slug, Cohort $cohort): Course
    {
        $course = Course::published()->where('slug', $slug)->firstOrFail();
        abort_unless($cohort->course_id === $course->id && $cohort->acceptingApplications(), 404);

        return $course;
    }

    protected function student(): ?Student
    {
        return auth()->user() ? Student::where('user_id', auth()->id())->first() : null;
    }

    /** Who instant registrations are admitted by: the course's creator, else the first super administrator. */
    protected function registrar(Course $course): ?User
    {
        return User::find($course->created_by)
            ?? User::role(config('qudroid.super_admin_role', 'Super Administrator'))->orderBy('id')->first();
    }
}
