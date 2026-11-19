<?php

namespace App\Http\Middleware;

use App\Models\Academy\Cohort;
use App\Models\Academy\Course;
use App\Services\Academy\LearningService;
use Closure;
use Illuminate\Http\Request;

/**
 * Teaching pages (course content, assessments, marking, gradebooks) are only for
 * academy staff and the instructors of that course's classes. Instructors are
 * assigned to a class on its edit page.
 */
class TeachesCourse
{
    public function __construct(protected LearningService $learning) {}

    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        $course = $request->route('course');
        $cohort = $request->route('cohort');

        if ($course instanceof Course) {
            abort_unless($user && $this->learning->isTeacher($user, $course), 403, 'Only academy staff and the instructors of this course can open this page.');
        } elseif ($cohort instanceof Cohort) {
            abort_unless($user && $this->learning->teachesCohort($user, $cohort), 403, 'Only academy staff and this class\'s instructor can open this page.');
        }

        return $next($request);
    }
}
