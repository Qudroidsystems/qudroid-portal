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
use App\Services\Academy\LearningService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Teachers: build quizzes, tests, exams, assignments, projects, coding exercises and in-class assessments; see results; mark them. */
class AssessmentController extends Controller
{
    public function __construct(protected AssessmentService $assessments) {}

    public function create(Request $request, Course $course)
    {
        $kind = $request->query('kind', $request->query('type', 'quiz'));
        $kind = array_key_exists($kind, Assessment::KINDS) ? $kind : 'quiz';
        $type = Assessment::typeOfKind($kind);

        return view('modules.academy.assessments.form', [
            'course'     => $course,
            'assessment' => new Assessment([
                'type' => $type, 'kind' => $kind, 'pass_mark' => 50, 'max_score' => 100, 'max_attempts' => $type === 'quiz' ? 1 : 0,
                'show_answers' => $kind === 'quiz', 'shuffle' => in_array($kind, ['test', 'exam'], true), 'weight' => 0,
                'answer_format' => $kind === 'coding' ? 'code' : 'text', 'code_language' => $kind === 'coding' ? 'javascript' : null, 'allow_file' => true,
            ]),
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
            ->with('success', $assessment->isQuiz() ? $assessment->type_label . ' created. Now add its questions.' : $assessment->type_label . ' created.');
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
        // Instructors see the students of their own classes; academy staff see everyone.
        $cohorts = app(LearningService::class)->taughtCohortIds(request()->user(), $course);
        $enrollments = Enrollment::with('student.user', 'cohort')->where('course_id', $course->id)
            ->when($cohorts !== null, fn ($q) => $q->whereIn('cohort_id', $cohorts))
            ->whereIn('status', ['active', 'completed'])->where('access_granted', true)->get()
            ->sortBy(fn ($e) => [$e->cohort->name, $e->student->user->name])->values();

        return view('modules.academy.assessments.results', [
            'course'      => $course,
            'assessment'  => $assessment,
            'enrollments' => $enrollments,
            'attempts'    => $assessment->attempts()->whereNotNull('submitted_at')->orderByDesc('id')->get()->groupBy('enrollment_id'),
            'submissions' => $assessment->submissions()->with('comments.user')->get()->keyBy('enrollment_id'),
        ]);
    }

    /** Scores for work marked in class, for everyone at once. */
    public function scores(Request $request, Course $course, Assessment $assessment)
    {
        $this->belongs($course, $assessment);
        abort_unless($assessment->isManual(), 404);
        $request->validate(['scores' => ['required', 'array'], 'scores.*.score' => ['nullable'], 'scores.*.feedback' => ['nullable', 'string', 'max:2000']]);
        $scores = (array) $request->input('scores');
        $cohorts = app(LearningService::class)->taughtCohortIds($request->user(), $course);
        if ($cohorts !== null) {
            $mine = Enrollment::where('course_id', $course->id)->whereIn('cohort_id', $cohorts)->whereKey(array_keys($scores))->pluck('id')->all();
            $scores = array_intersect_key($scores, array_flip($mine));
        }
        $n = $this->assessments->recordScores($assessment, $scores, $request->user());

        return back()->with('success', $n ? $n . ' ' . \Illuminate\Support\Str::plural('score', $n) . ' saved. Students have been told.' : 'Nothing changed.');
    }

    /** A teacher's comment on a student's work (optionally on a line of code). */
    public function comment(Request $request, Course $course, Assessment $assessment, Submission $submission)
    {
        $this->belongs($course, $assessment);
        abort_unless($submission->assessment_id === $assessment->id, 404);
        $this->mayMark($submission);
        $data = $request->validate(['body' => ['required', 'string', 'max:5000'], 'line' => ['nullable', 'integer', 'min:1']]);
        $this->assessments->comment($submission, $request->user(), $data['body'], $data['line'] ?? null);

        return redirect()->to(route('academy.assessments.results', [$course, $assessment]) . '#sub-' . $submission->id)->with('success', 'Comment sent.')->with('open_sub', $submission->id);
    }

    public function commentAttempt(Request $request, Course $course, Assessment $assessment, Attempt $attempt)
    {
        $this->belongs($course, $assessment);
        abort_unless($attempt->assessment_id === $assessment->id, 404);
        $this->mayMark($attempt);
        $data = $request->validate(['body' => ['required', 'string', 'max:5000'], 'line' => ['nullable', 'integer', 'min:1'], 'question_id' => ['nullable', 'integer']]);
        $this->assessments->comment($attempt, $request->user(), $data['body'], $data['line'] ?? null, $data['question_id'] ?? null);

        return back()->with('success', 'Comment sent.');
    }

    public function attempt(Course $course, Assessment $assessment, Attempt $attempt)
    {
        $this->belongs($course, $assessment);
        abort_unless($attempt->assessment_id === $assessment->id, 404);
        $this->mayMark($attempt);

        return view('modules.academy.assessments.attempt', [
            'course' => $course, 'assessment' => $assessment, 'attempt' => $attempt->load('enrollment.student.user', 'comments.user'),
            'questions' => $assessment->questions()->get(),
        ]);
    }

    public function gradeAttempt(Request $request, Course $course, Assessment $assessment, Attempt $attempt)
    {
        $this->belongs($course, $assessment);
        abort_unless($attempt->assessment_id === $assessment->id, 404);
        $this->mayMark($attempt);
        $this->assessments->gradeAttempt($attempt, (array) $request->input('marks', []), $request->user());

        return redirect()->route('academy.assessments.results', [$course, $assessment])->with('success', 'Marked.');
    }

    public function gradeSubmission(Request $request, Course $course, Assessment $assessment, Submission $submission)
    {
        $this->belongs($course, $assessment);
        abort_unless($submission->assessment_id === $assessment->id, 404);
        $this->mayMark($submission);
        $data = $request->validate(['score' => ['nullable', 'numeric'], 'feedback' => ['nullable', 'string', 'max:5000']]);
        $this->assessments->gradeSubmission($submission, $request->user(), isset($data['score']) ? (float) $data['score'] : null,
            $data['feedback'] ?? null, $request->input('action') === 'return');

        return back()->with('success', $request->input('action') === 'return' ? 'Returned to the student.' : 'Graded.');
    }

    public function download(Course $course, Assessment $assessment, Submission $submission)
    {
        $this->belongs($course, $assessment);
        abort_unless($submission->assessment_id === $assessment->id && $submission->file_path && Storage::disk('local')->exists($submission->file_path), 404);
        $this->mayMark($submission);

        return Storage::disk('local')->download($submission->file_path, $submission->file_name);
    }

    // ── Helpers ─────────────────────────────────────────────────────────

    /** Instructors mark only the students of their own classes. */
    protected function mayMark(Submission|Attempt $work): void
    {
        abort_unless(app(LearningService::class)->teachesEnrollment(request()->user(), $work->enrollment), 403, 'This student is not in a class you teach.');
    }

    protected function belongs(Course $course, Assessment $assessment): void
    {
        abort_unless($assessment->course_id === $course->id, 404);
    }

    protected function validated(Request $request, ?Assessment $assessment = null): array
    {
        // Older forms (and the API) send "type" instead of "kind".
        if (!$assessment && !$request->filled('kind') && $request->filled('type')) {
            $request->merge(['kind' => $request->input('type') === 'manual' ? 'other' : $request->input('type')]);
        }
        $kinds = $assessment
            ? array_keys(array_filter(Assessment::KINDS, fn ($k) => $k[1] === $assessment->type))   // the kind can change within its type
            : array_keys(Assessment::KINDS);
        $data = $request->validate([
            'kind'               => [$assessment ? 'nullable' : 'required', Rule::in($kinds)],
            'title'              => ['required', 'string', 'max:200'],
            'answer_format'      => ['nullable', Rule::in(array_keys(Assessment::ANSWER_FORMATS))],
            'code_language'      => ['nullable', Rule::in(array_keys(Assessment::LANGUAGES))],
            'starter_code'       => ['nullable', 'string', 'max:50000'],
            'instructions'       => ['nullable', 'string', 'max:50000'],
            'weight'             => ['required', 'numeric', 'min:0', 'max:100'],
            'pass_mark'          => ['required', 'integer', 'min:0', 'max:100'],
            'max_score'          => ['nullable', 'numeric', 'min:1', 'max:1000'],
            'max_attempts'       => ['nullable', 'integer', 'min:0', 'max:100'],
            'time_limit_minutes' => ['nullable', 'integer', 'min:1', 'max:600'],
            'due_at'             => ['nullable', 'date'],
        ]);

        $data['kind'] = $data['kind'] ?? $assessment?->kind_key;
        $type = $assessment?->type ?? Assessment::typeOfKind($data['kind']);
        if (!$assessment) $data['type'] = $type;
        $quiz = $type === 'quiz';
        $assignment = $type === 'assignment';
        $data['answer_format'] = $assignment ? ($data['answer_format'] ?? 'text') : 'text';
        $data['code_language'] = $assignment && in_array($data['answer_format'], ['code', 'text_code'], true) ? ($data['code_language'] ?? 'plain') : null;
        $data['starter_code'] = $data['code_language'] ? ($data['starter_code'] ?? null) : null;
        $data['allow_file'] = $assignment && ($data['answer_format'] === 'file' || $request->boolean('allow_file'));
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
            'language'    => ['nullable', Rule::in(array_keys(Assessment::LANGUAGES))],
            'explanation' => ['nullable', 'string', 'max:5000'],
        ], ['accepted.required' => 'List at least one accepted answer.']);

        $out = ['type' => $type, 'question' => $data['question'], 'points' => $data['points'], 'explanation' => $data['explanation'] ?? null,
            'options' => null, 'correct' => null, 'accepted_answers' => null, 'language' => $type === 'code' ? ($data['language'] ?? 'plain') : null];

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
