<?php

namespace App\Http\Controllers\Website;

use App\Http\Controllers\Controller;
use App\Models\Academy\Cohort;
use App\Models\Academy\Course;
use App\Models\Academy\Enrollment;
use App\Models\Website\StudentProfile;
use App\Models\Website\Testimonial;
use App\Services\Website\Showcase;
use Illuminate\Http\Request;

/** The website's student showcase, student profiles, and testimonials pages. */
class ShowcaseController extends Controller
{
    public function __construct(protected Showcase $showcase) {}

    /** Our students: a grid of published profiles, filtered by course or a search. */
    public function students(Request $request)
    {
        $course = $request->filled('course') ? Course::published()->where('slug', $request->query('course'))->first() : null;
        $q = trim((string) $request->query('q', ''));
        // Alumni: finished a course with us. Current: studying now.
        $show = in_array($request->query('show'), ['alumni', 'current'], true) ? $request->query('show') : null;
        $profiles = StudentProfile::published()->ordered()
            ->with(['student.user', 'student.enrollments' => fn ($e) => $e->whereIn('status', ['active', 'completed'])->with('course', 'cohort')])
            ->withCount('projects')
            ->when($course, fn ($w) => $w->whereHas('student.enrollments', fn ($e) => $e->where('course_id', $course->id)->whereIn('status', ['active', 'completed'])))
            ->when($show === 'alumni', fn ($w) => $w->whereHas('student.enrollments', fn ($e) => $e->where('status', 'completed')))
            ->when($show === 'current', fn ($w) => $w->whereHas('student.enrollments', fn ($e) => $e->where('status', 'active')))
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('headline', 'like', "%{$q}%")->orWhere('skills', 'like', "%{$q}%")
                ->orWhereHas('student.user', fn ($u) => $u->where('name', 'like', "%{$q}%"))))
            ->get();

        // Only courses that someone on the page has studied.
        $courses = Course::published()->whereHas('enrollments', fn ($e) => $e->whereIn('status', ['active', 'completed'])
            ->whereHas('student.profile', fn ($p) => $p->where('status', 'published')->whereNotNull('consented_at')))->orderBy('title')->get();

        return view('site.students', [
            'profiles' => $profiles, 'courses' => $courses, 'course' => $course, 'q' => $q, 'show' => $show,
            'hasYearbook' => Cohort::where('in_yearbook', true)->exists(),
            'stats' => [
                'students' => StudentProfile::published()->count(),
                'projects' => \App\Models\Website\StudentProject::whereHas('profile', fn ($p) => $p->published())->count(),
                'courses' => $courses->count(),
            ],
            'quotes' => Testimonial::published()->where('kind', 'student')->ordered()->limit(3)->get(),
        ]);
    }

    public function student(StudentProfile $profile)
    {
        abort_unless($profile->isPublic(), 404);
        $profile->load('student.user', 'projects.course');

        return view('site.student', [
            'p' => $profile,
            'studies' => $this->showcase->studies($profile->student),
            'quotes' => Testimonial::published()->where('student_id', $profile->student_id)->ordered()->get(),
            'more' => StudentProfile::published()->whereKeyNot($profile->id)->with('student.user')->inRandomOrder()->limit(3)->get(),
        ]);
    }

    /**
     * The yearbook: classes staff chose to show, newest year first. Each lists its
     * graduates who agreed to be on the website; others are only counted.
     */
    public function yearbook(Request $request)
    {
        $cohorts = Cohort::where('in_yearbook', true)->with('course')
            ->withCount(['enrollments as graduates_count' => fn ($e) => $e->where('status', 'completed')])->get()
            ->filter(fn ($c) => $c->course && !$c->course->trashed());
        $profiles = StudentProfile::published()->with('student.user')
            ->whereHas('student.enrollments', fn ($e) => $e->where('status', 'completed')->whereIn('cohort_id', $cohorts->pluck('id')))->get()->keyBy('student_id');
        $grads = Enrollment::where('status', 'completed')->whereIn('cohort_id', $cohorts->pluck('id'))->whereIn('student_id', $profiles->keys())->get(['cohort_id', 'student_id'])->groupBy('cohort_id');

        $years = $cohorts->map(function ($c) use ($grads, $profiles) {
            $c->setRelation('graduates', ($grads[$c->id] ?? collect())->map(fn ($e) => $profiles[$e->student_id] ?? null)->filter()->sortBy(fn ($p) => $p->name())->values());

            return $c;
        })->sortByDesc(fn ($c) => [$c->classOf(), $c->ends_on?->timestamp ?? 0])->groupBy(fn ($c) => $c->classOf() ?? 'Earlier')->toBase();
        $year = $request->query('year');
        if ($year && $years->has($year)) $shown = $years->only([$year]); else { $shown = $years; $year = null; }

        return view('site.yearbook', ['years' => $shown, 'allYears' => $years->keys(), 'year' => $year,
            'totals' => ['classes' => $cohorts->count(), 'graduates' => $cohorts->sum('graduates_count')]]);
    }

    /** All testimonials, or only students' / clients'. */
    public function testimonials(?string $kind = null)
    {
        $kind = in_array($kind, ['students', 'clients'], true) ? rtrim($kind, 's') : null;
        $all = Testimonial::published()->ordered()->with('course')->when($kind, fn ($q) => $q->where('kind', $kind))->get();

        return view('site.testimonials', [
            'kind' => $kind,
            'students' => $all->where('kind', 'student')->values(),
            'clients' => $all->where('kind', 'client')->values(),
            'average' => ($rated = $all->whereNotNull('rating'))->isNotEmpty() ? round($rated->avg('rating'), 1) : null,
            'profiles' => StudentProfile::published()->whereIn('student_id', $all->pluck('student_id')->filter())->pluck('slug', 'student_id'),
        ]);
    }

    /** A client writes a testimonial from the link staff sent them (signed, no sign-in). */
    public function share(Request $request)
    {
        return view('site.share-story', ['org' => $request->query('org'), 'action' => $request->fullUrl()]);
    }

    public function submitShare(Request $request)
    {
        if (filled($request->input('website'))) return redirect()->route('site.testimonials');   // honeypot
        $data = $request->validate(Showcase::testimonialRules(true) + ['consent' => ['accepted']], [], ['organisation' => 'company']);
        $this->showcase->submitTestimonial('client', $data, $request, 'link');

        return view('site.share-story', ['done' => true, 'org' => null, 'action' => null]);
    }
}
