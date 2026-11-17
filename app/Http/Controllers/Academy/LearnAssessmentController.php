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
            'submission' => $enrollment ? $assessment->submissions()->with('comments.user')->where('enrollment_id', $enrollment->id)->first() : null,
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

        $attempt->load('comments.user');

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
        $request->validate(self::handInRules());
        $this->assessments->handIn($assessment, $enrollment, $request->input('body'), $request->file('file'), $request->input('code'));

        return back()->with('success', 'Handed in. Your instructor has been told.');
    }

    public static function handInRules(): array
    {
        return [
            'body' => ['nullable', 'string', 'max:50000'],
            'code' => ['nullable', 'string', 'max:200000'],
            'file' => ['nullable', 'file', 'mimes:' . ContentController::FILE_TYPES . ',html,css,js,ts,json,py,php,java,dart,kt,swift,md,txt,sql,c,cpp,cs,go,rb,sol,ipynb', 'max:' . (int) config('qudroid.academy.max_upload_kb', 51200)],
        ];
    }

    /** The student's comment (or question) on their own work. */
    public function comment(Request $request, Course $course, Assessment $assessment)
    {
        [$enrollment] = $this->context($request, $course, $assessment, true);
        $data = $request->validate(['body' => ['required', 'string', 'max:5000'], 'line' => ['nullable', 'integer', 'min:1']]);
        $submission = Submission::where('assessment_id', $assessment->id)->where('enrollment_id', $enrollment->id)->first();
        abort_unless($submission, 404, 'Hand in your work first.');
        $this->assessments->comment($submission, $request->user(), $data['body'], $data['line'] ?? null);

        return redirect()->to(route('academy.learn.assessment', [$course, $assessment]) . '#comments')->with('success', 'Comment sent to your instructor.');
    }

    public function commentAttempt(Request $request, Course $course, Attempt $attempt)
    {
        $this->mine($request, $course, $attempt);
        abort_unless($attempt->isSubmitted(), 404);
        $data = $request->validate(['body' => ['required', 'string', 'max:5000'], 'question_id' => ['nullable', 'integer']]);
        $this->assessments->comment($attempt, $request->user(), $data['body'], null, $data['question_id'] ?? null);

        return back()->with('success', 'Comment sent to your instructor.');
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
