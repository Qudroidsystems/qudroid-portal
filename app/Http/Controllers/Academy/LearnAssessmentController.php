<?php

namespace App\Http\Controllers\Academy;

use App\Http\Controllers\Controller;
use App\Models\Academy\Assessment;
use App\Models\Academy\Attempt;
use App\Models\Academy\Course;
use App\Models\Academy\Submission;
use App\Services\Academy\AssessmentService;
use App\Services\Academy\LearningService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/** Students: take quizzes and hand in assignments. */
class LearnAssessmentController extends Controller
{
    public function __construct(protected AssessmentService $assessments, protected LearningService $learning) {}

    public function show(Request $request, Course $course, Assessment $assessment)
    {
        [$enrollment, $teacher] = $this->context($request, $course, $assessment);

        return view('modules.academy.learn.assessment', [
            'course'     => $course,
            'assessment' => $assessment,
            'enrollment' => $enrollment,
            'teacher'    => $teacher,
            'attempts'   => $enrollment ? $assessment->attempts()->where('enrollment_id', $enrollment->id)->orderByDesc('attempt_no')->get() : collect(),
            'submission' => $enrollment ? $assessment->submissions()->where('enrollment_id', $enrollment->id)->first() : null,
            'questionCount' => $assessment->questions()->count(),
        ]);
    }

    public function start(Request $request, Course $course, Assessment $assessment)
    {
        [$enrollment] = $this->context($request, $course, $assessment, true);
        $attempt = $this->assessments->start($assessment, $enrollment);

        return redirect()->route('academy.learn.attempt', [$course, $attempt]);
    }

    /** The quiz itself while it is open; the marked result once submitted. */
    public function attempt(Request $request, Course $course, Attempt $attempt)
    {
        $this->mine($request, $course, $attempt);
        $assessment = $attempt->assessment;
        $questions = $assessment->questions()->get();
        if ($assessment->shuffle && !$attempt->isSubmitted()) {
            $questions = $questions->shuffle($attempt->id); // same order every time this attempt is opened
        }

        return view('modules.academy.learn.attempt', compact('course', 'assessment', 'attempt', 'questions'));
    }

    public function submit(Request $request, Course $course, Attempt $attempt)
    {
        $this->mine($request, $course, $attempt);
        $attempt = $this->assessments->submit($attempt, (array) $request->input('answers', []));

        return redirect()->route('academy.learn.attempt', [$course, $attempt])
            ->with($attempt->timed_out ? 'error' : 'success', $attempt->timed_out ? 'Time ran out before your answers arrived, so this attempt scored 0.' : 'Submitted.');
    }

    public function handIn(Request $request, Course $course, Assessment $assessment)
    {
        [$enrollment] = $this->context($request, $course, $assessment, true);
        $request->validate([
            'body' => ['nullable', 'string', 'max:50000'],
            'file' => ['nullable', 'file', 'mimes:' . ContentController::FILE_TYPES . ',html,css,js,json,py,php,java,dart,kt,swift,md', 'max:' . (int) config('qudroid.academy.max_upload_kb', 51200)],
        ]);
        $this->assessments->handIn($assessment, $enrollment, $request->input('body'), $request->file('file'));

        return back()->with('success', 'Handed in. Your instructor has been told.');
    }

    public function file(Request $request, Course $course, Assessment $assessment)
    {
        [$enrollment] = $this->context($request, $course, $assessment, true);
        $submission = Submission::where('assessment_id', $assessment->id)->where('enrollment_id', $enrollment->id)->firstOrFail();
        abort_unless($submission->file_path && Storage::disk('local')->exists($submission->file_path), 404);

        return Storage::disk('local')->download($submission->file_path, $submission->file_name);
    }

    /** @return array{0: ?\App\Models\Academy\Enrollment, 1: bool} */
    protected function context(Request $request, Course $course, Assessment $assessment, bool $needEnrollment = false): array
    {
        abort_unless($assessment->course_id === $course->id, 404);
        $teacher = $this->learning->isTeacher($request->user(), $course);
        abort_unless($teacher || ($course->isPublished() && $assessment->is_published), 404);

        $enrollment = $this->learning->enrollmentFor($request->user(), $course);
        $canLearn = $enrollment && $enrollment->canLearn();
        abort_unless($teacher || $canLearn, 403, 'This is for students on the course.');
        abort_if($needEnrollment && !$canLearn, 403);

        return [$canLearn ? $enrollment : null, $teacher];
    }

    protected function mine(Request $request, Course $course, Attempt $attempt): void
    {
        abort_unless($attempt->assessment->course_id === $course->id && $attempt->enrollment->student->user_id === $request->user()->id, 404);
    }
}
