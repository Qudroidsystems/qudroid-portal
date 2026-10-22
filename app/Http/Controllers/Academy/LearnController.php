<?php

namespace App\Http\Controllers\Academy;

use App\Http\Controllers\Controller;
use App\Models\Academy\Course;
use App\Models\Academy\Lesson;
use App\Services\Academy\LearningService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/** The learning page: the course outline beside the lesson being studied. */
class LearnController extends Controller
{
    public function __construct(protected LearningService $learning) {}

    /** Open the course where the learner left off (or at the first lesson). */
    public function course(Request $request, Course $course)
    {
        [$enrollment, $teacher, $outline] = $this->context($request, $course);
        $open = $outline->filter(fn ($l) => $this->learning->canOpen($request->user(), $l, $enrollment, $teacher))->values();

        if ($open->isEmpty()) {
            return redirect()->route('academy.courses.show', $course)
                ->with('error', $enrollment && !$enrollment->canLearn() ? 'Your course access opens once your payment is confirmed.' : 'There are no lessons you can open yet.');
        }

        $done = $enrollment ? $enrollment->progress()->whereNotNull('completed_at')->pluck('lesson_id') : collect();
        $next = $open->first(fn ($l) => !$done->contains($l->id)) ?? $open->first();

        return redirect()->route('academy.learn.lesson', [$course, $next]);
    }

    public function lesson(Request $request, Course $course, Lesson $lesson)
    {
        abort_unless($lesson->course_id === $course->id, 404);
        [$enrollment, $teacher, $outline] = $this->context($request, $course);
        $user = $request->user();

        if (!$this->learning->canOpen($user, $lesson, $enrollment, $teacher)) {
            return redirect()->route('academy.courses.show', $course)->with('error', $enrollment && !$enrollment->canLearn()
                ? 'Your course access opens once your payment is confirmed.'
                : 'This lesson is for enrolled students.');
        }

        $learning = $enrollment && $enrollment->canLearn();
        if ($learning) $this->learning->viewed($enrollment, $lesson);

        $open = $outline->filter(fn ($l) => $this->learning->canOpen($user, $l, $enrollment, $teacher))->values();
        $index = $open->search(fn ($l) => $l->id === $lesson->id);

        return view('modules.academy.learn.lesson', [
            'course'     => $course,
            'lesson'     => $lesson,
            'outline'    => $outline,
            'openIds'    => $open->pluck('id'),
            'doneIds'    => $learning ? $enrollment->progress()->whereNotNull('completed_at')->pluck('lesson_id') : collect(),
            'enrollment' => $enrollment,
            'learning'   => $learning,
            'teacher'    => $teacher,
            'prev'       => $index > 0 ? $open[$index - 1] : null,
            'next'       => $index !== false && $index < $open->count() - 1 ? $open[$index + 1] : null,
        ]);
    }

    public function complete(Request $request, Course $course, Lesson $lesson)
    {
        abort_unless($lesson->course_id === $course->id, 404);
        $enrollment = $this->learning->enrollmentFor($request->user(), $course);
        abort_unless($enrollment && $enrollment->canLearn() && $enrollment->status === 'active' && $lesson->is_published, 403);

        $done = $request->boolean('done', true);
        $this->learning->setCompleted($enrollment, $lesson, $done);

        if ($done && $request->boolean('next') && $request->filled('next_id')) {
            return redirect()->route('academy.learn.lesson', [$course, (int) $request->next_id]);
        }

        return back();
    }

    public function download(Request $request, Course $course, Lesson $lesson)
    {
        abort_unless($lesson->course_id === $course->id && $lesson->file_path, 404);
        [$enrollment, $teacher] = $this->context($request, $course);
        abort_unless($this->learning->canOpen($request->user(), $lesson, $enrollment, $teacher), 403);
        abort_unless(Storage::disk('local')->exists($lesson->file_path), 404);

        return Storage::disk('local')->download($lesson->file_path, $lesson->file_name);
    }

    protected function context(Request $request, Course $course): array
    {
        $user = $request->user();
        $teacher = $this->learning->isTeacher($user, $course);
        abort_unless($teacher || $course->isPublished(), 404);

        return [$this->learning->enrollmentFor($user, $course), $teacher, $this->learning->outline($course, $teacher)];
    }
}
