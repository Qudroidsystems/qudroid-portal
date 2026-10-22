<?php

namespace App\Http\Controllers\Academy;

use App\Http\Controllers\Controller;
use App\Models\Academy\Assessment;
use App\Models\Academy\Attempt;
use App\Models\Academy\Course;
use App\Models\Academy\Enrollment;
use App\Models\Academy\Question;
use App\Models\Academy\Submission;
use App\Services\Academy\AssessmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Teachers: build quizzes and assignments, see results, and mark them. */
class AssessmentController extends Controller
{
    public function __construct(protected AssessmentService $assessments) {}

    public function create(Request $request, Course $course)
    {
        $type = $request->query('type') === 'assignment' ? 'assignment' : 'quiz';

        return view('modules.academy.assessments.form', [
            'course'     => $course,
            'assessment' => new Assessment(['type' => $type, 'pass_mark' => 50, 'max_score' => 100, 'max_attempts' => $type === 'quiz' ? 1 : 0, 'show_answers' => true, 'weight' => 0]),
        ]);
    }

    public function store(Request $request, Course $course)
    {
        $data = $this->validated($request);
        $assessment = $course->assessments()->create($data + [
            'position'   => (int) $course->assessments()->max('position') + 1,
            'created_by' => $request->user()->id,
        ]);
        $this->assessments->recomputeCourse($course->id);

        return redirect()->route('academy.assessments.edit', [$course, $assessment])
            ->with('success', $assessment->isQuiz() ? 'Quiz created. Now add its questions.' : 'Assignment created.');
    }

    public function edit(Course $course, Assessment $assessment)
    {
        $this->belongs($course, $assessment);

        return view('modules.academy.assessments.form', [
            'course'     => $course,
            'assessment' => $assessment->load('questions'),
            'taken'      => $assessment->attempts()->exists() || $assessment->submissions()->exists(),
        ]);
    }

    public function update(Request $request, Course $course, Assessment $assessment)
    {
        $this->belongs($course, $assessment);
        $data = $this->validated($request, $assessment);
        if ($data['is_published'] && $assessment->isQuiz() && $assessment->questions()->doesntExist()) {
            throw ValidationException::withMessages(['is_published' => 'Add at least one question before publishing the quiz.']);
        }
        $assessment->update($data);
        $this->assessments->recomputeCourse($course->id);

        return back()->with('success', 'Saved.');
    }

    public function destroy(Course $course, Assessment $assessment)
    {
        $this->belongs($course, $assessment);
        if ($assessment->attempts()->exists() || $assessment->submissions()->exists()) {
            return back()->with('error', 'Students have already taken this. Unpublish it instead, so their results are kept.');
        }
        $assessment->delete();
        $this->assessments->recomputeCourse($course->id);

        return redirect()->route('academy.content.index', $course)->with('success', 'Deleted.');
    }

    // ── Questions ───────────────────────────────────────────────────────

    public function storeQuestion(Request $request, Course $course, Assessment $assessment)
    {
        $this->belongs($course, $assessment);
        abort_unless($assessment->isQuiz(), 404);
        $assessment->questions()->create($this->question($request) + ['position' => (int) $assessment->questions()->max('position') + 1]);

        return back()->with('success', 'Question added.');
    }

    public function updateQuestion(Request $request, Course $course, Assessment $assessment, Question $question)
    {
        $this->belongs($course, $assessment);
        abort_unless($question->assessment_id === $assessment->id, 404);
        $question->update($this->question($request));

        return back()->with('success', 'Question updated. Attempts already marked keep their marks.');
    }

    public function destroyQuestion(Course $course, Assessment $assessment, Question $question)
    {
        $this->belongs($course, $assessment);
        abort_unless($question->assessment_id === $assessment->id, 404);
        if ($assessment->attempts()->exists()) {
            return back()->with('error', 'Students have already attempted this quiz, so its questions can\'t be removed.');
        }
        $question->delete();

        return back()->with('success', 'Question removed.');
    }

    // ── Results and marking ─────────────────────────────────────────────

    public function results(Course $course, Assessment $assessment)
    {
        $this->belongs($course, $assessment);
        $enrollments = Enrollment::with('student.user', 'cohort')->where('course_id', $course->id)
            ->whereIn('status', ['active', 'completed'])->where('access_granted', true)->get()
            ->sortBy(fn ($e) => [$e->cohort->name, $e->student->user->name])->values();

        return view('modules.academy.assessments.results', [
            'course'      => $course,
            'assessment'  => $assessment,
            'enrollments' => $enrollments,
            'attempts'    => $assessment->attempts()->whereNotNull('submitted_at')->orderByDesc('id')->get()->groupBy('enrollment_id'),
            'submissions' => $assessment->submissions()->get()->keyBy('enrollment_id'),
        ]);
    }

    public function attempt(Course $course, Assessment $assessment, Attempt $attempt)
    {
        $this->belongs($course, $assessment);
        abort_unless($attempt->assessment_id === $assessment->id, 404);

        return view('modules.academy.assessments.attempt', [
            'course' => $course, 'assessment' => $assessment, 'attempt' => $attempt->load('enrollment.student.user'),
            'questions' => $assessment->questions()->get(),
        ]);
    }

    public function gradeAttempt(Request $request, Course $course, Assessment $assessment, Attempt $attempt)
    {
        $this->belongs($course, $assessment);
        abort_unless($attempt->assessment_id === $assessment->id, 404);
        $this->assessments->gradeAttempt($attempt, (array) $request->input('marks', []), $request->user());

        return redirect()->route('academy.assessments.results', [$course, $assessment])->with('success', 'Marked.');
    }

    public function gradeSubmission(Request $request, Course $course, Assessment $assessment, Submission $submission)
    {
        $this->belongs($course, $assessment);
        abort_unless($submission->assessment_id === $assessment->id, 404);
        $data = $request->validate(['score' => ['nullable', 'numeric'], 'feedback' => ['nullable', 'string', 'max:5000']]);
        $this->assessments->gradeSubmission($submission, $request->user(), isset($data['score']) ? (float) $data['score'] : null,
            $data['feedback'] ?? null, $request->input('action') === 'return');

        return back()->with('success', $request->input('action') === 'return' ? 'Returned to the student.' : 'Graded.');
    }

    public function download(Course $course, Assessment $assessment, Submission $submission)
    {
        $this->belongs($course, $assessment);
        abort_unless($submission->assessment_id === $assessment->id && $submission->file_path && Storage::disk('local')->exists($submission->file_path), 404);

        return Storage::disk('local')->download($submission->file_path, $submission->file_name);
    }

    // ── Helpers ─────────────────────────────────────────────────────────

    protected function belongs(Course $course, Assessment $assessment): void
    {
        abort_unless($assessment->course_id === $course->id, 404);
    }

    protected function validated(Request $request, ?Assessment $assessment = null): array
    {
        $data = $request->validate([
            'type'               => [$assessment ? 'prohibited' : 'required', Rule::in(array_keys(Assessment::TYPES))],
            'title'              => ['required', 'string', 'max:200'],
            'instructions'       => ['nullable', 'string', 'max:50000'],
            'weight'             => ['required', 'numeric', 'min:0', 'max:100'],
            'pass_mark'          => ['required', 'integer', 'min:0', 'max:100'],
            'max_score'          => ['nullable', 'numeric', 'min:1', 'max:1000'],
            'max_attempts'       => ['nullable', 'integer', 'min:0', 'max:100'],
            'time_limit_minutes' => ['nullable', 'integer', 'min:1', 'max:600'],
            'due_at'             => ['nullable', 'date'],
        ]);

        $quiz = ($assessment?->type ?? $data['type']) === 'quiz';
        $data['max_score'] = $quiz ? 100 : ($data['max_score'] ?? 100);
        $data['max_attempts'] = $quiz ? (int) ($data['max_attempts'] ?? 1) : 0;
        $data['time_limit_minutes'] = $quiz ? ($data['time_limit_minutes'] ?? null) : null;
        $data['shuffle'] = $quiz && $request->boolean('shuffle');
        $data['show_answers'] = $quiz && $request->boolean('show_answers');
        // A new quiz waits for its questions before it can be published.
        $data['is_published'] = $request->boolean('is_published') && !($quiz && !$assessment);

        return $data;
    }

    protected function question(Request $request): array
    {
        $type = $request->input('type');
        $data = $request->validate([
            'type'        => ['required', Rule::in(array_keys(Question::TYPES))],
            'question'    => ['required', 'string', 'max:10000'],
            'points'      => ['required', 'numeric', 'min:0.5', 'max:100'],
            'options'     => ['nullable', 'array', 'max:8'],
            'options.*'   => ['nullable', 'string', 'max:500'],
            'correct'     => ['nullable', 'array'],
            'correct.*'   => ['integer', 'min:0', 'max:7'],
            'accepted'    => [$type === 'short_answer' ? 'required' : 'nullable', 'string', 'max:2000'],
            'explanation' => ['nullable', 'string', 'max:5000'],
        ], ['accepted.required' => 'List at least one accepted answer.']);

        $out = ['type' => $type, 'question' => $data['question'], 'points' => $data['points'], 'explanation' => $data['explanation'] ?? null,
            'options' => null, 'correct' => null, 'accepted_answers' => null];

        if (in_array($type, ['single', 'multiple', 'boolean'], true)) {
            // Keep the option numbers the teacher ticked, dropping blank options.
            $options = $type === 'boolean' ? [0 => 'True', 1 => 'False'] : array_filter(array_map(fn ($o) => trim((string) $o), $data['options'] ?? []), fn ($o) => $o !== '');
            $correct = array_values(array_intersect(array_map('intval', $data['correct'] ?? []), array_keys($options)));
            $map = array_flip(array_keys($options));
            $out['options'] = array_values($options);
            $out['correct'] = array_map(fn ($i) => $map[$i], $correct);

            if (count($out['options']) < 2) throw ValidationException::withMessages(['options' => 'Give at least two options.']);
            if (!$out['correct']) throw ValidationException::withMessages(['correct' => 'Tick the correct answer.']);
            if ($type !== 'multiple' && count($out['correct']) > 1) throw ValidationException::withMessages(['correct' => 'Tick only one correct answer, or choose "Several correct answers".']);
        } elseif ($type === 'short_answer') {
            $out['accepted_answers'] = array_values(array_filter(array_map('trim', preg_split('/\R/', $data['accepted'])), fn ($a) => $a !== ''));
        }

        return $out;
    }
}
