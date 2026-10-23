<?php

namespace App\Http\Controllers\Api\V1\Academy;

use App\Http\Controllers\Academy\ContentController;
use App\Http\Controllers\Api\V1\ApiController;
use App\Models\Academy\Assessment;
use App\Models\Academy\Attempt;
use App\Models\Academy\Certificate;
use App\Models\Academy\Course;
use App\Models\Academy\Enrollment;
use App\Models\Academy\Lesson;
use App\Models\Academy\LessonProgress;
use App\Models\Academy\Student;
use App\Models\Academy\Submission;
use App\Services\Academy\AssessmentService;
use App\Services\Academy\LearningService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * The Academy for students in the app: my courses, lessons, quizzes and
 * assignments, certificates, and the public catalogue. Access follows the
 * web: full lessons need an enrollment with course access (granted once
 * the fee threshold is paid); preview lessons are open to all.
 */
class AcademyApiController extends ApiController
{
    public function __construct(protected LearningService $learning, protected AssessmentService $assessments) {}

    public function my(Request $request)
    {
        if ($deny = $this->deny($request)) return $deny;
        $student = Student::where('user_id', $request->user()->id)->first();
        $list = $student ? $student->enrollments()->with('course', 'cohort', 'certificate')->whereNotIn('status', ['cancelled'])->latest('id')->get() : collect();

        return $this->ok($list->map(fn ($e) => $this->enrollment($e))->values(), null, 200, [
            'applications' => $student ? $student->applications()->with('course', 'cohort')->latest('id')->get()->map(fn ($a) => [
                'id' => $a->id, 'reference' => $a->reference, 'status' => $a->status, 'course' => $a->course?->title, 'cohort' => $a->cohort?->name,
            ])->values() : [],
        ]);
    }

    public function showEnrollment(Request $request, Enrollment $enrollment)
    {
        if (!$this->own($request, $enrollment)) return $this->fail('Enrollment not found.', 404, 'not_found');
        $enrollment->load('course', 'cohort', 'certificate');
        $done = LessonProgress::where('enrollment_id', $enrollment->id)->whereNotNull('completed_at')->pluck('lesson_id')->flip();
        $lessons = $this->learning->outline($enrollment->course, false);
        $open = $enrollment->canLearn();

        $modules = $lessons->groupBy(fn ($l) => $l->section_id ?? 0)->map(fn ($group) => [
            'id' => $group->first()->section_id, 'title' => $group->first()->section?->title ?? 'More lessons',
            'lessons' => $group->map(fn ($l) => ['id' => $l->id, 'title' => $l->title, 'kind' => $l->type, 'kind_label' => $l->type_label,
                'minutes' => $l->duration_minutes, 'done' => $done->has($l->id), 'locked' => !$open && !$l->is_preview, 'preview' => $l->is_preview])->values(),
        ])->values();

        $results = $this->assessments->results($enrollment);
        $assessments = Assessment::where('course_id', $enrollment->course_id)->where('is_published', true)->orderBy('position')->get()
            ->map(fn ($a) => $this->assessmentRow($a, $enrollment, $results[$a->id] ?? null));

        $att = $enrollment->attendance()->get()->countBy('status');
        $marked = (int) $att->sum();

        return $this->ok($this->enrollment($enrollment) + [
            'modules' => $modules,
            'assessments' => $assessments->values(),
            'attendance' => ['present' => (int) ($att['present'] ?? 0), 'late' => (int) ($att['late'] ?? 0), 'absent' => (int) ($att['absent'] ?? 0),
                'excused' => (int) ($att['excused'] ?? 0), 'rate' => $marked ? (int) round((($att['present'] ?? 0) + ($att['late'] ?? 0)) * 100 / $marked) : null],
        ]);
    }

    public function lesson(Request $request, Lesson $lesson)
    {
        [$enrollment, $teacher, $deny] = $this->lessonContext($request, $lesson);
        if ($deny) return $deny;
        if ($enrollment) $this->learning->viewed($enrollment, $lesson);
        $order = $this->learning->outline($lesson->course, $teacher)->pluck('id')->values();
        $i = $order->search($lesson->id);

        return $this->ok([
            'id' => $lesson->id, 'title' => $lesson->title, 'kind' => $lesson->type, 'kind_label' => $lesson->type_label,
            'body_html' => $lesson->bodyHtml(), 'video_url' => $lesson->video_url, 'embed_url' => $lesson->embedUrl(), 'link_url' => $lesson->external_url,
            'file_url' => $lesson->file_path ? route('api.v1.academy.lessons.file', $lesson) : null, 'file_name' => $lesson->file_name, 'file_size' => $lesson->file_size,
            'minutes' => $lesson->duration_minutes,
            'done' => $enrollment ? LessonProgress::where('enrollment_id', $enrollment->id)->where('lesson_id', $lesson->id)->whereNotNull('completed_at')->exists() : false,
            'can_complete' => (bool) $enrollment,
            'prev_id' => $i !== false && $i > 0 ? $order[$i - 1] : null, 'next_id' => $i !== false ? ($order[$i + 1] ?? null) : null,
            'course' => ['id' => $lesson->course->id, 'title' => $lesson->course->title],
            'enrollment_id' => $enrollment?->id,
            'web_url' => route('academy.learn.lesson', [$lesson->course, $lesson]),
        ]);
    }

    /** {done?: true} → {progress} */
    public function complete(Request $request, Lesson $lesson)
    {
        [$enrollment, , $deny] = $this->lessonContext($request, $lesson);
        if ($deny) return $deny;
        if (!$enrollment) return $this->fail('Only students on the course track progress.', 403, 'not_enrolled');

        return $this->ok(['progress' => $this->learning->setCompleted($enrollment, $lesson, $request->boolean('done', true))]);
    }

    public function lessonFile(Request $request, Lesson $lesson)
    {
        [, , $deny] = $this->lessonContext($request, $lesson);
        abort_if($deny || !$lesson->file_path || !Storage::disk('local')->exists($lesson->file_path), 404);

        return Storage::disk('local')->download($lesson->file_path, $lesson->file_name);
    }

    public function assessment(Request $request, Assessment $assessment)
    {
        [$enrollment, $deny] = $this->assessmentContext($request, $assessment);
        if ($deny) return $deny;
        $attempts = $assessment->attempts()->where('enrollment_id', $enrollment->id)->orderByDesc('attempt_no')->get();
        $submission = $assessment->submissions()->where('enrollment_id', $enrollment->id)->first();

        $reason = null;
        if (!$enrollment->canLearn() || $enrollment->status !== 'active') $reason = $enrollment->status === 'completed' ? 'This course is finished.' : 'Your course access opens once your payment is confirmed.';
        elseif ($assessment->isPastDue()) $reason = 'This closed on ' . $assessment->due_at->format('j M Y, g:ia') . '.';
        elseif ($assessment->isQuiz() && $assessment->max_attempts > 0 && $attempts->whereNotNull('submitted_at')->count() >= $assessment->max_attempts && !$attempts->firstWhere('submitted_at', null)) $reason = 'You have used all your attempts.';
        elseif (!$assessment->isQuiz() && $submission?->status === 'graded') $reason = 'This assignment has been graded.';

        return $this->ok($this->assessmentRow($assessment, $enrollment, $this->assessments->results($enrollment)[$assessment->id] ?? null) + [
            'instructions_html' => $assessment->instructionsHtml(),
            'time_limit_minutes' => $assessment->time_limit_minutes, 'attempts_allowed' => $assessment->max_attempts ?: null,
            'questions_count' => $assessment->isQuiz() ? $assessment->questions()->count() : null,
            'pass_mark' => $assessment->pass_mark, 'weight' => (float) $assessment->weight,
            'attempts' => $attempts->map(fn ($a) => $this->attemptRow($a))->values(),
            'submission' => $submission ? [
                'status' => $submission->status, 'status_label' => $submission->status_label, 'body' => $submission->body, 'file_name' => $submission->file_name,
                'submitted_at' => $submission->submitted_at?->toIso8601String(), 'late' => $submission->isLate(),
                'score' => $submission->score !== null ? (float) $submission->score : null, 'max_score' => (float) $assessment->max_score,
                'percent' => $submission->percent !== null ? (float) $submission->percent : null, 'feedback' => $submission->feedback,
            ] : null,
            'can_start' => $reason === null && $assessment->isQuiz(),
            'can_hand_in' => $reason === null && !$assessment->isQuiz(),
            'reason_if_not' => $reason,
        ]);
    }

    /** Start a quiz attempt, or carry on with the open one. */
    public function start(Request $request, Assessment $assessment)
    {
        [$enrollment, $deny] = $this->assessmentContext($request, $assessment);
        if ($deny) return $deny;
        try {
            $attempt = $this->assessments->start($assessment, $enrollment);
        } catch (ValidationException $e) {
            return $this->fail(collect($e->errors())->flatten()->first(), 422, 'cannot_start');
        }

        return $this->ok($this->openAttempt($attempt), null, $attempt->wasRecentlyCreated ? 201 : 200);
    }

    public function attempt(Request $request, Attempt $attempt)
    {
        if (!$this->ownAttempt($request, $attempt)) return $this->fail('Attempt not found.', 404, 'not_found');

        return $this->ok($attempt->isSubmitted() ? $this->result($attempt) : $this->openAttempt($attempt));
    }

    /** Autosave: {answers: {question_id: option index | [indexes] | text}} — merged into what is saved. */
    public function save(Request $request, Attempt $attempt)
    {
        if (!$this->ownAttempt($request, $attempt)) return $this->fail('Attempt not found.', 404, 'not_found');
        if ($attempt->isSubmitted()) return $this->fail('This attempt has already been submitted.', 409, 'submitted');
        $request->validate(['answers' => ['required', 'array']]);
        $ids = $attempt->assessment->questions()->pluck('id')->map(fn ($id) => (string) $id)->all();
        $given = collect($request->input('answers'))->only($ids)->map(fn ($v) => is_string($v) ? mb_substr($v, 0, 10000) : $v)->all();
        $attempt->update(['answers' => array_replace((array) $attempt->answers, $given)]);

        return $this->ok(['saved_at' => now()->toIso8601String(), 'seconds_left' => $attempt->secondsLeft()]);
    }

    /** {answers?} — anything sent is merged with the autosave, then marked. */
    public function submit(Request $request, Attempt $attempt)
    {
        if (!$this->ownAttempt($request, $attempt)) return $this->fail('Attempt not found.', 404, 'not_found');
        $answers = array_replace((array) $attempt->answers, (array) $request->input('answers', []));
        try {
            $attempt = $this->assessments->submit($attempt, $answers);
        } catch (ValidationException $e) {
            return $this->fail(collect($e->errors())->flatten()->first(), 422, 'cannot_submit');
        }
        $msg = $attempt->timed_out ? 'Time ran out before your answers arrived, so this attempt scored 0.'
            : ($attempt->needs_review ? 'Submitted. Your instructor will mark the written answers.' : 'Submitted.');

        return $this->ok($this->result($attempt) + ['message' => $msg], $msg);
    }

    /** Assignment: multipart {body?, file?} */
    public function handIn(Request $request, Assessment $assessment)
    {
        [$enrollment, $deny] = $this->assessmentContext($request, $assessment);
        if ($deny) return $deny;
        $request->validate([
            'body' => ['nullable', 'string', 'max:50000'],
            'file' => ['nullable', 'file', 'mimes:' . ContentController::FILE_TYPES . ',html,css,js,json,py,php,java,dart,kt,swift,md', 'max:' . (int) config('qudroid.academy.max_upload_kb', 51200)],
        ]);
        try {
            $s = $this->assessments->handIn($assessment, $enrollment, $request->input('body'), $request->file('file'));
        } catch (ValidationException $e) {
            return $this->fail(collect($e->errors())->flatten()->first(), 422, 'cannot_hand_in');
        }

        return $this->ok(['status' => $s->status, 'status_label' => $s->status_label, 'submitted_at' => $s->submitted_at?->toIso8601String(), 'late' => $s->isLate()],
            'Handed in. Your instructor has been told.', 201);
    }

    public function certificates(Request $request)
    {
        $student = Student::where('user_id', $request->user()->id)->first();
        $list = $student ? Certificate::with('course')->where('student_id', $student->id)->latest('issued_at')->get() : collect();

        return $this->ok($list->map(fn ($c) => $this->certificate($c))->values());
    }

    /** The catalogue: ?q= */
    public function courses(Request $request)
    {
        if ($deny = $this->deny($request)) return $deny;
        $q = Course::published()->with(['cohorts' => fn ($c) => $c->orderBy('starts_on')]);
        if ($s = trim((string) $request->get('q'))) $q->where(fn ($w) => $w->where('title', 'like', "%{$s}%")->orWhere('code', 'like', "%{$s}%")->orWhere('summary', 'like', "%{$s}%"));
        $page = $q->orderBy('title')->paginate(min(50, max(5, (int) $request->get('per_page', 20))));

        return $this->paged($page, fn ($c) => $this->courseItem($c));
    }

    public function course(Request $request, Course $course)
    {
        if ($deny = $this->deny($request)) return $deny;
        if (!$course->isPublished()) return $this->fail('Course not found.', 404, 'not_found');
        $course->load(['cohorts' => fn ($c) => $c->with('instructor')->orderBy('starts_on')]);
        $preview = Lesson::where('course_id', $course->id)->where('is_published', true)->where('is_preview', true)->orderBy('position')->get(['id', 'title', 'type']);
        $mine = $this->learning->enrollmentFor($request->user(), $course);

        return $this->ok($this->courseItem($course) + [
            'description_html' => $course->description ? \Illuminate\Support\Str::markdown($course->description, ['html_input' => 'strip', 'allow_unsafe_links' => false]) : null,
            'lessons_count' => Lesson::where('course_id', $course->id)->where('is_published', true)->count(),
            'preview_lessons' => $preview->map(fn ($l) => ['id' => $l->id, 'title' => $l->title, 'kind' => $l->type])->values(),
            'my_enrollment_id' => $mine?->id,
        ]);
    }

    // ── Shapes ──────────────────────────────────────────────────────────

    protected function enrollment(Enrollment $e): array
    {
        $next = null;
        if ($e->canLearn()) {
            $done = LessonProgress::where('enrollment_id', $e->id)->whereNotNull('completed_at')->pluck('lesson_id')->all();
            $l = $this->learning->outline($e->course, false)->first(fn ($l) => !in_array($l->id, $done, true));
            $next = $l ? ['id' => $l->id, 'title' => $l->title] : null;
        }

        return [
            'id' => $e->id, 'status' => $e->status, 'status_label' => $e->status_label, 'progress' => (int) $e->progress_percent,
            'grade' => $e->grade_percent !== null ? (float) $e->grade_percent : null, 'access_granted' => (bool) $e->access_granted,
            'course' => ['id' => $e->course->id, 'code' => $e->course->code, 'title' => $e->course->title, 'summary' => $e->course->summary,
                'cover_url' => null, 'level' => $e->course->level, 'level_label' => $e->course->level_label, 'pass_grade' => $e->course->pass_grade],
            'cohort' => $e->cohort ? ['id' => $e->cohort->id, 'name' => $e->cohort->name, 'starts_on' => $e->cohort->starts_on?->toDateString(), 'ends_on' => $e->cohort->ends_on?->toDateString()] : null,
            'next_lesson' => $next,
            'certificate' => $e->certificate && $e->certificate->isValid() ? $this->certificate($e->certificate) : null,
            'web_url' => route('academy.learn.course', $e->course),
        ];
    }

    protected function assessmentRow(Assessment $a, Enrollment $e, ?float $percent): array
    {
        if ($a->isQuiz()) {
            $mine = $a->attempts()->where('enrollment_id', $e->id)->get();
            $status = $mine->contains(fn ($t) => !$t->isSubmitted()) ? 'in_progress'
                : ($mine->contains(fn ($t) => $t->needs_review) ? 'submitted' : ($mine->isNotEmpty() ? 'graded' : ($a->isPastDue() ? 'missed' : 'open')));
        } else {
            $s = $a->submissions()->where('enrollment_id', $e->id)->first();
            $status = $s ? ['submitted' => 'submitted', 'returned' => 'returned', 'graded' => 'graded'][$s->status] ?? $s->status : ($a->isPastDue() ? 'missed' : 'open');
        }

        return ['id' => $a->id, 'title' => $a->title, 'kind' => $a->type, 'kind_label' => $a->type_label, 'due_at' => $a->due_at?->toIso8601String(),
            'status' => $status, 'percent' => $percent, 'passed' => $percent === null ? null : $percent >= (float) ($a->pass_mark ?? 0),
            'web_url' => route('academy.learn.assessment', [$a->course_id, $a])];
    }

    protected function attemptRow(Attempt $t): array
    {
        return ['id' => $t->id, 'number' => $t->attempt_no, 'started_at' => $t->started_at?->toIso8601String(), 'submitted_at' => $t->submitted_at?->toIso8601String(),
            'score' => $t->score !== null ? (float) $t->score : null, 'max_score' => $t->max_score !== null ? (float) $t->max_score : null,
            'percent' => $t->percent !== null ? (float) $t->percent : null,
            'status' => !$t->isSubmitted() ? 'in_progress' : ($t->needs_review ? 'needs_marking' : ($t->timed_out ? 'timed_out' : 'marked'))];
    }

    /** The questions, without answers. */
    protected function openAttempt(Attempt $t): array
    {
        $a = $t->assessment;
        $questions = $a->questions()->get();
        if ($a->shuffle) $questions = $questions->shuffle($t->id); // same order as on the web

        return ['attempt_id' => $t->id, 'number' => $t->attempt_no, 'started_at' => $t->started_at?->toIso8601String(),
            'ends_at' => $t->deadline()?->toIso8601String(), 'seconds_left' => $t->secondsLeft(),
            'questions' => $questions->map(fn ($q) => [
                'id' => $q->id, 'kind' => $q->type, 'text' => $q->question, 'points' => (float) $q->points,
                'options' => $q->isChoice() ? collect((array) $q->options)->map(fn ($text, $i) => ['id' => $i, 'text' => (string) $text])->values() : [],
            ])->values(),
            'answers' => (object) ((array) $t->answers)];
    }

    protected function result(Attempt $t): array
    {
        $a = $t->assessment;
        $marks = (array) $t->marks;
        $show = $a->show_answers && !$t->needs_review;

        return $this->attemptRow($t) + ['attempt_id' => $t->id, 'timed_out' => (bool) $t->timed_out,
            'questions' => $a->questions()->get()->map(fn ($q) => [
                'id' => $q->id, 'kind' => $q->type, 'text' => $q->question, 'points' => (float) $q->points,
                'options' => $q->isChoice() ? collect((array) $q->options)->map(fn ($text, $i) => ['id' => $i, 'text' => (string) $text])->values() : [],
                'your_answer' => ((array) $t->answers)[$q->id] ?? null,
                'mark' => array_key_exists($q->id, $marks) && $marks[$q->id] !== null ? (float) $marks[$q->id] : null,
                'correct' => $show ? ($q->isChoice() ? array_map('intval', (array) $q->correct) : ($q->isManual() ? null : (array) $q->accepted_answers)) : null,
                'explanation' => $show ? $q->explanation : null,
            ])->values()];
    }

    protected function certificate(Certificate $c): array
    {
        return ['id' => $c->id, 'number' => $c->serial, 'status' => $c->status, 'valid' => $c->isValid(), 'course' => $c->course?->title,
            'issued_on' => $c->issued_at?->toDateString(), 'pdf_url' => null, 'verify_url' => $c->verifyUrl(), 'web_url' => route('certificates.show', $c)];
    }

    protected function courseItem(Course $c): array
    {
        $cohorts = $c->cohorts->filter->acceptingApplications();

        return ['id' => $c->id, 'code' => $c->code, 'title' => $c->title, 'summary' => $c->summary, 'level' => $c->level, 'level_label' => $c->level_label,
            'mode' => $c->delivery_mode, 'mode_label' => $c->mode_label, 'weeks' => $c->duration_weeks, 'cover_url' => null]
            + $this->money('fee', $c->fee, $c->currency) + ['installments' => (bool) $c->installments_allowed,
            'cohorts' => $cohorts->map(fn ($h) => ['id' => $h->id, 'name' => $h->name, 'starts_on' => $h->starts_on?->toDateString(), 'ends_on' => $h->ends_on?->toDateString(),
                'seats_left' => $h->seatsLeft(), 'fee_label' => Money::format($h->effectiveFee(), $c->currency), 'apply_url' => route('apply.create', $h)])->values(),
            'web_url' => route('academy.courses.show', $c)];
    }

    // ── Access ──────────────────────────────────────────────────────────

    protected function deny(Request $request)
    {
        $u = $request->user();

        return $u->can('View academy') || $u->can('Manage academy') || $u->can('Manage course content') ? null : $this->fail('You do not have access to the Academy.', 403, 'forbidden');
    }

    protected function own(Request $request, Enrollment $e): bool
    {
        return $e->student && $e->student->user_id === $request->user()->id;
    }

    protected function ownAttempt(Request $request, Attempt $t): bool
    {
        return $t->enrollment?->student?->user_id === $request->user()->id;
    }

    /** @return array{0: ?Enrollment, 1: bool, 2: mixed} */
    protected function lessonContext(Request $request, Lesson $lesson): array
    {
        if ($deny = $this->deny($request)) return [null, false, $deny];
        $course = $lesson->course;
        $teacher = $this->learning->isTeacher($request->user(), $course);
        if (!$teacher && (!$course->isPublished() || !$lesson->is_published)) return [null, false, $this->fail('Lesson not found.', 404, 'not_found')];
        $enrollment = $this->learning->enrollmentFor($request->user(), $course);
        $enrollment = $enrollment && $enrollment->canLearn() ? $enrollment : null;
        if (!$this->learning->canOpen($request->user(), $lesson, $enrollment, $teacher)) {
            return [null, $teacher, $this->fail('This lesson opens once you are enrolled and your fee is confirmed.', 403, 'locked')];
        }

        return [$enrollment, $teacher, null];
    }

    /** @return array{0: ?Enrollment, 1: mixed} */
    protected function assessmentContext(Request $request, Assessment $a): array
    {
        if ($deny = $this->deny($request)) return [null, $deny];
        if (!$a->is_published || !$a->course?->isPublished()) return [null, $this->fail('Not found.', 404, 'not_found')];
        $enrollment = $this->learning->enrollmentFor($request->user(), $a->course);
        if (!$enrollment || !$enrollment->canLearn()) return [null, $this->fail('This is for students on the course.', 403, 'locked')];

        return [$enrollment, null];
    }
}
