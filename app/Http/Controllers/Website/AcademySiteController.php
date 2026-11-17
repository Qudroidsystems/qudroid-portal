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
use Illuminate\Validation\Rule;

/**
 * Courses on the website and the course cart. People add one class per
 * course to the cart and register for them all at once: that is one
 * admission request, reviewed by academy staff. When every course in the
 * cart is set to "take payment at registration" the request is admitted
 * straight away (which gives the admission number, enrols the person and
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

    /** The older "Register" link for one class: it now goes through the cart. */
    public function register(Request $request, string $slug, Cohort $cohort)
    {
        $this->courseFor($slug, $cohort);
        $this->putInCart($request, $cohort);

        return redirect()->route('site.academy.cart');
    }

    // ── The cart ────────────────────────────────────────────────────────

    public function cart(Request $request)
    {
        $items = $this->cartItems($request);

        return view('site.cart', [
            'items'   => $items,
            'totals'  => $this->totals($items),
            'student' => $this->student(),
            'instant' => $items->isNotEmpty() && $items->every(fn ($c) => $c->course->instant_enrollment),
        ]);
    }

    public function add(Request $request, Cohort $cohort)
    {
        $cohort->load('course');
        abort_unless($cohort->course && $cohort->course->status === 'published' && $cohort->acceptingApplications(), 404);
        $replaced = $this->putInCart($request, $cohort);

        if ($request->boolean('checkout')) {
            return redirect()->route('site.academy.cart');
        }

        return back()->with('cart_added', $replaced
            ? "Changed your class for {$cohort->course->title} to {$cohort->name}."
            : "{$cohort->course->title} is in your cart.");
    }

    public function remove(Request $request, Cohort $cohort)
    {
        $request->session()->put('course_cart', array_values(array_diff($this->cartIds($request), [$cohort->id])));

        return redirect()->route('site.academy.cart');
    }

    /** Register for everything in the cart: one admission request. */
    public function checkout(Request $request)
    {
        if (filled($request->input('website'))) {   // honeypot
            return redirect()->route('site.academy');
        }
        $items = $this->cartItems($request);
        if ($items->isEmpty()) {
            return redirect()->route('site.academy.cart')->withErrors(['cart' => 'Your cart is empty. Add a course first.']);
        }

        $application = $this->submit($request, $items);
        $request->session()->forget('course_cart');

        return redirect()->route('site.academy.registered', $application->reference);
    }

    /** The older one-class form posts here; it registers just that class. */
    public function store(Request $request, string $slug, Cohort $cohort)
    {
        $course = $this->courseFor($slug, $cohort);
        if (filled($request->input('website'))) {   // honeypot
            return redirect()->route('site.academy.course', $course->slug);
        }
        $application = $this->submit($request, collect([$cohort->load('course')]));

        return redirect()->route('site.academy.registered', $application->reference);
    }

    /** @param \Illuminate\Support\Collection<int, Cohort> $cohorts */
    protected function submit(Request $request, $cohorts): Application
    {
        $student = $this->student();
        if (!$student && $request->user()) {
            throw \Illuminate\Validation\ValidationException::withMessages(['email' => 'You are signed in with a staff account. Sign out to register as a student.']);
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
            ? $this->applications->applyAsStudent($student, $cohorts, $answers, $request->ip())
            : $this->applications->applyAsGuest($cohorts, $data, $answers, $request->ip());

        // Courses that take payment at registration are admitted straight away.
        $first = $cohorts->first()->course;
        if ($cohorts->every(fn ($c) => $c->course->instant_enrollment) && ($by = $this->registrar($first))) {
            try {
                [$application, $problem] = $this->applications->admit($application, $by, 'Registered and admitted on the website.');
                if ($problem) Log::warning('Website registration could not enrol', ['application' => $application->reference, 'problem' => $problem]);
            } catch (\Throwable $e) {
                Log::error('Website registration could not admit', ['application' => $application->reference, 'error' => $e->getMessage()]);
            }
        }

        $request->session()->put('site_registered', $application->reference);

        return $application;
    }

    public function registered(Request $request, string $reference)
    {
        abort_unless($request->session()->get('site_registered') === $reference, 404);
        $application = Application::with('choices.course', 'choices.cohort', 'student.user', 'invoice')->where('reference', $reference)->firstOrFail();
        $invoice = $application->invoice && $application->invoice->isOpen() && $application->invoice->balanceMinor() > 0 ? $application->invoice : null;

        return view('site.registered', [
            'application' => $application,
            'invoice' => $invoice,
            'payUrl' => $invoice ? ApplicationService::payUrl($invoice) : null,
        ]);
    }

    protected function courseFor(string $slug, Cohort $cohort): Course
    {
        $course = Course::published()->where('slug', $slug)->firstOrFail();
        abort_unless($cohort->course_id === $course->id && $cohort->acceptingApplications(), 404);

        return $course;
    }

    /** @return array<int> */
    protected function cartIds(Request $request): array
    {
        return array_values(array_unique(array_map('intval', (array) $request->session()->get('course_cart', []))));
    }

    /**
     * What is in the cart that can still be registered for. Classes that
     * closed since they were added drop out quietly.
     *
     * @return \Illuminate\Support\Collection<int, Cohort>
     */
    protected function cartItems(Request $request)
    {
        $ids = $this->cartIds($request);
        if (!$ids) return collect();
        $items = Cohort::with('course')->whereKey($ids)->get()
            ->filter(fn ($c) => $c->course && $c->course->status === 'published' && !$c->course->trashed() && $c->acceptingApplications())
            ->sortBy(fn ($c) => array_search($c->id, $ids, true))->values();
        $request->session()->put('course_cart', $items->pluck('id')->all());

        return $items;
    }

    /** Add a class; another class of the same course is swapped out. Returns whether one was. */
    protected function putInCart(Request $request, Cohort $cohort): bool
    {
        $items = $this->cartItems($request);
        $other = $items->first(fn ($c) => $c->course_id === $cohort->course_id && $c->id !== $cohort->id);
        $ids = $items->reject(fn ($c) => $c->course_id === $cohort->course_id)->pluck('id')->push($cohort->id)->unique()->values()->all();
        if (count($ids) > ApplicationService::MAX_COURSES) {
            throw \Illuminate\Validation\ValidationException::withMessages(['cart' => 'You can register for up to ' . ApplicationService::MAX_COURSES . ' courses at a time.']);
        }
        $request->session()->put('course_cart', $ids);

        return (bool) $other;
    }

    /** Fee totals by currency, e.g. ['NGN' => '250000.00']. */
    protected function totals($items): array
    {
        return $items->groupBy(fn ($c) => $c->course->currency)
            ->map(fn ($g) => \App\Support\Money::major($g->sum(fn ($c) => \App\Support\Money::minor($c->effectiveFee()))))->all();
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
