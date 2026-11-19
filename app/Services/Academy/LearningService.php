<?php

namespace App\Services\Academy;

use App\Models\Academy\Course;
use App\Models\Academy\Enrollment;
use App\Models\Academy\Lesson;
use App\Models\Academy\LessonProgress;
use App\Models\Academy\Student;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Who may open which lesson, and each enrollment's progress.
 *
 *  - academy staff (Manage academy, or Manage all course content) and the
 *    instructors of the course's classes see every lesson, published or not.
 *    An instructor builds and marks only the courses they teach a class on,
 *    and sees only the students of their own classes;
 *  - a student whose enrollment has course access sees published lessons;
 *  - anyone who can see the course sees published preview lessons.
 */
class LearningService
{
    /** Academy staff who may build and mark every course and see every class. */
    public function teachesAll(User $user): bool
    {
        return $user->can('Manage academy') || $user->can('Manage all course content');
    }

    /** May build this course's content and assessments and mark its students' work. */
    public function isTeacher(User $user, Course $course): bool
    {
        return $this->teachesAll($user) || $course->cohorts()->where('instructor_id', $user->id)->exists();
    }

    /** The classes of this course whose students this person teaches; null means all of them. */
    public function taughtCohortIds(User $user, Course $course): ?array
    {
        return $this->teachesAll($user) ? null : $course->cohorts()->where('instructor_id', $user->id)->pluck('id')->all();
    }

    /** May see and mark this student's work (a student of one of their classes). */
    public function teachesEnrollment(User $user, Enrollment $enrollment): bool
    {
        return $this->teachesAll($user) || (int) $enrollment->cohort?->instructor_id === $user->id;
    }

    public function teachesCohort(User $user, \App\Models\Academy\Cohort $cohort): bool
    {
        return $this->teachesAll($user) || (int) $cohort->instructor_id === $user->id;
    }

    /** Courses this person may build and mark: every course for academy staff, else the ones they teach. */
    public function taughtCourses(User $user)
    {
        $q = Course::query();

        return $this->teachesAll($user) ? $q : $q->whereHas('cohorts', fn ($c) => $c->where('instructor_id', $user->id));
    }

    /** The user's most relevant enrollment on this course (one with access first). */
    public function enrollmentFor(User $user, Course $course): ?Enrollment
    {
        $student = Student::where('user_id', $user->id)->first();
        if (!$student) return null;

        return Enrollment::where('student_id', $student->id)->where('course_id', $course->id)
            ->whereIn('status', ['active', 'completed', 'pending'])
            ->orderByDesc('access_granted')->orderByDesc('id')->first();
    }

    public function canOpen(User $user, Lesson $lesson, ?Enrollment $enrollment, bool $teacher): bool
    {
        if ($teacher) return true;
        if (!$lesson->is_published || !$lesson->course->isPublished()) return false;

        return $lesson->is_preview || ($enrollment && $enrollment->canLearn());
    }

    /** Lessons a learner sees in the outline, in order (lessons without a section last). */
    public function outline(Course $course, bool $teacher): Collection
    {
        return Lesson::where('course_id', $course->id)
            ->when(!$teacher, fn ($q) => $q->where('is_published', true))
            ->with('section')
            ->get()
            ->sortBy(fn ($l) => [$l->section ? 0 : 1, $l->section?->position ?? 0, $l->section_id ?? 0, $l->position, $l->id])
            ->values();
    }

    public function viewed(Enrollment $enrollment, Lesson $lesson): void
    {
        LessonProgress::firstOrCreate(['enrollment_id' => $enrollment->id, 'lesson_id' => $lesson->id], ['first_viewed_at' => now()]);
        $enrollment->forceFill(['last_activity_at' => now()])->save();
    }

    public function setCompleted(Enrollment $enrollment, Lesson $lesson, bool $done): int
    {
        $row = LessonProgress::firstOrCreate(['enrollment_id' => $enrollment->id, 'lesson_id' => $lesson->id], ['first_viewed_at' => now()]);
        $row->update(['completed_at' => $done ? ($row->completed_at ?? now()) : null]);

        return $this->recompute($enrollment);
    }

    /** Percentage of the course's published lessons this enrollment has completed. */
    public function recompute(Enrollment $enrollment, bool $touch = true): int
    {
        $published = Lesson::where('course_id', $enrollment->course_id)->where('is_published', true)->pluck('id');
        $done = $published->isEmpty() ? 0 : LessonProgress::where('enrollment_id', $enrollment->id)
            ->whereNotNull('completed_at')->whereIn('lesson_id', $published)->count();
        $percent = $published->isEmpty() ? 0 : (int) floor($done * 100 / $published->count());

        $enrollment->forceFill(['progress_percent' => $percent] + ($touch ? ['last_activity_at' => now()] : []))->save();

        return $percent;
    }

    /** Refresh every enrollment on a course after lessons are published, unpublished or deleted. */
    public function recomputeCourse(Course $course): void
    {
        Enrollment::where('course_id', $course->id)->whereIn('status', ['active', 'completed'])
            ->each(fn (Enrollment $e) => $this->recompute($e, false));
    }
}
