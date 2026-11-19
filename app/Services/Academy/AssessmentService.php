<?php

namespace App\Services\Academy;

use App\Models\Academy\Assessment;
use App\Models\Academy\AssessmentComment;
use App\Models\Academy\Attempt;
use App\Models\Academy\Enrollment;
use App\Models\Academy\Submission;
use App\Models\User;
use App\Services\Messaging\PortalNotifier;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Taking quizzes, handing in assignments, marking, and the enrollment's grade.
 *
 *  - Only an active enrollment with course access can attempt or submit.
 *  - A quiz with a time limit is checked on the server: answers that arrive
 *    after the limit (plus a short grace) are not counted.
 *  - Work marked in class (practicals, participation, presentations) has no
 *    hand-in: the instructor enters each student's score.
 *  - Students and instructors can comment on a piece of work, on a line of code.
 *  - The grade is the weighted average of each assessment's result (a quiz
 *    counts its best attempt). Assessments not yet done count as 0. If no
 *    assessment has a weight, they count equally.
 */
class AssessmentService
{
    // ── Quizzes ─────────────────────────────────────────────────────────

    public function start(Assessment $quiz, Enrollment $enrollment): Attempt
    {
        $this->mayWork($quiz, $enrollment);
        $this->check(!$quiz->isQuiz(), 'This is not a quiz.');
        $this->check($quiz->questions()->doesntExist(), 'This quiz has no questions yet.');
        $this->check($quiz->isPastDue(), 'This quiz closed on ' . $quiz->due_at?->format('j M Y, g:ia') . '.');

        return DB::transaction(function () use ($quiz, $enrollment) {
            $mine = Attempt::where('assessment_id', $quiz->id)->where('enrollment_id', $enrollment->id)->lockForUpdate()->get();

            // Carry on with an unfinished attempt rather than starting another.
            if ($open = $mine->firstWhere('submitted_at', null)) {
                return $open;
            }
            $this->check($quiz->max_attempts > 0 && $mine->count() >= $quiz->max_attempts, 'You have used all your attempts.');

            return Attempt::create([
                'assessment_id' => $quiz->id,
                'enrollment_id' => $enrollment->id,
                'attempt_no'    => $mine->count() + 1,
                'started_at'    => now(),
            ]);
        });
    }

    /** @param array<int|string, mixed> $answers question id => answer */
    public function submit(Attempt $attempt, array $answers): Attempt
    {
        $quiz = $attempt->assessment;
        $this->mayWork($quiz, $attempt->enrollment);
        $this->check($attempt->isSubmitted(), 'This attempt has already been submitted.');

        $deadline = $attempt->deadline();
        $timedOut = $deadline && now()->gt($deadline->copy()->addSeconds(Attempt::GRACE_SECONDS));

        $questions = $quiz->questions()->get();
        $kept = [];
        $marks = [];
        foreach ($questions as $q) {
            $answer = $timedOut ? null : ($answers[$q->id] ?? null);
            if (is_string($answer)) {
                $answer = $q->type === 'code' ? mb_substr(rtrim($answer), 0, 50000) : mb_substr(trim($answer), 0, $q->isManual() ? 10000 : 500);
                if (trim($answer) === '') $answer = null;
            }
            if ($answer !== null && $answer !== '' && $answer !== []) $kept[$q->id] = $answer;
            $marks[$q->id] = $q->isManual() ? null : $q->award($answer);
        }

        $needsReview = !$timedOut && $questions->contains(fn ($q) => $q->isManual() && isset($kept[$q->id]));
        $attempt->update([
            'answers'      => $kept,
            'submitted_at' => now(),
            'timed_out'    => $timedOut,
            'needs_review' => $needsReview,
        ] + $this->totals($marks, $questions->sum(fn ($q) => (float) $q->points)));

        $this->recompute($attempt->enrollment);

        if ($needsReview) {
            PortalNotifier::toUsers($this->teacherIds($attempt->enrollment), $quiz->type_label . ' to mark: ' . $quiz->title,
                $attempt->enrollment->student->user->name . ' has written answers waiting.',
                route('academy.assessments.results', [$quiz->course_id, $quiz]), 'course');
        }

        return $attempt->fresh();
    }

    /** A teacher marks essays (and may adjust any other mark). @param array<int|string, float|string|null> $given */
    public function gradeAttempt(Attempt $attempt, array $given, User $by): Attempt
    {
        $this->check(!$attempt->isSubmitted(), 'The student has not submitted this attempt yet.');
        $questions = $attempt->assessment->questions()->get()->keyBy('id');
        $marks = (array) $attempt->marks;

        foreach ($given as $qid => $points) {
            if (!$questions->has($qid) || $points === null || $points === '') continue;
            $marks[$qid] = max(0, min((float) $questions[$qid]->points, (float) $points));
        }
        $this->check(collect($marks)->containsStrict(null), 'Give a mark for every essay answer.');

        $attempt->update(['needs_review' => false, 'graded_by' => $by->id, 'graded_at' => now()]
            + $this->totals($marks, $questions->sum(fn ($q) => (float) $q->points)));
        $this->recompute($attempt->enrollment);

        PortalNotifier::toUsers([$attempt->enrollment->student->user_id], 'Quiz marked: ' . $attempt->assessment->title,
            'Your score is ' . rtrim(rtrim(number_format((float) $attempt->percent, 1), '0'), '.') . '%.',
            route('academy.learn.assessment', [$attempt->assessment->course_id, $attempt->assessment_id]), 'course');

        return $attempt;
    }

    /** The attempt that counts: the best marked one, else the latest submitted. */
    public function bestAttempt(Assessment $quiz, Enrollment $enrollment): ?Attempt
    {
        return Attempt::where('assessment_id', $quiz->id)->where('enrollment_id', $enrollment->id)
            ->whereNotNull('submitted_at')->orderBy('needs_review')->orderByDesc('percent')->orderByDesc('id')->first();
    }

    // ── Assignments ─────────────────────────────────────────────────────

    /** Hand in (or hand in again) an assignment: a written answer, code and/or a file, as the assignment asks. */
    public function handIn(Assessment $assignment, Enrollment $enrollment, ?string $body, ?UploadedFile $file, ?string $code = null): Submission
    {
        $this->mayWork($assignment, $enrollment);
        $this->check(!$assignment->isAssignment(), $assignment->isManual() ? 'This is marked in class; there is nothing to hand in.' : 'This is not an assignment.');
        $body = $assignment->wantsText() && trim((string) $body) !== '' ? trim($body) : null;
        $code = $assignment->wantsCode() && trim((string) $code) !== '' ? rtrim((string) $code) : null;
        $file = $assignment->acceptsFile() ? $file : null;
        $this->check($body === null && $code === null && $file === null, match ($assignment->answer_format) {
            'code'      => 'Write your code' . ($assignment->acceptsFile() ? ' or attach a file.' : '.'),
            'text_code' => 'Write your answer or code' . ($assignment->acceptsFile() ? ', or attach a file.' : '.'),
            'file'      => 'Attach your file.',
            default     => 'Write your answer' . ($assignment->acceptsFile() ? ' or attach a file.' : '.'),
        });
        $this->check($code !== null && mb_strlen($code) > 200000, 'Your code is too long (200,000 characters at most). Attach it as a file instead.');

        $existing = Submission::where('assessment_id', $assignment->id)->where('enrollment_id', $enrollment->id)->first();
        $this->check($existing && $existing->status === 'graded', 'This has been graded. Ask your instructor if you need to resubmit.');

        $data = ['body' => $body, 'code' => $code, 'code_language' => $code !== null ? $assignment->code_language : null, 'status' => 'submitted', 'submitted_at' => now()];
        if ($file) {
            $data['file_path'] = $file->store('academy/submissions/' . $assignment->id, 'local');
            $data['file_name'] = mb_substr($file->getClientOriginalName(), 0, 255);
            if ($existing?->file_path) Storage::disk('local')->delete($existing->file_path);
        }

        $submission = $existing ? tap($existing)->update($data) : Submission::create($data + [
            'assessment_id' => $assignment->id, 'enrollment_id' => $enrollment->id,
        ]);

        PortalNotifier::toUsers($this->teacherIds($enrollment), 'Assignment handed in: ' . $assignment->title,
            $enrollment->student->user->name . ($submission->isLate() ? ' (late)' : ''),
            route('academy.assessments.results', [$assignment->course_id, $assignment]), 'course');

        return $submission;
    }

    /** Grade, or return it for changes (the student can then resubmit). */
    public function gradeSubmission(Submission $submission, User $by, ?float $score, ?string $feedback, bool $return = false): Submission
    {
        $max = (float) $submission->assessment->max_score;
        if ($return) {
            $this->check(trim((string) $feedback) === '', 'Say what needs changing.');
            $submission->update(['status' => 'returned', 'feedback' => trim($feedback), 'score' => null, 'percent' => null, 'graded_by' => $by->id, 'graded_at' => now()]);
        } else {
            $this->check($score === null || $score < 0 || $score > $max, 'The score must be between 0 and ' . rtrim(rtrim(number_format($max, 2), '0'), '.') . '.');
            $submission->update([
                'status' => 'graded', 'score' => $score, 'percent' => $max > 0 ? round($score * 100 / $max, 2) : 0,
                'feedback' => trim((string) $feedback) ?: null, 'graded_by' => $by->id, 'graded_at' => now(),
            ]);
        }
        $this->recompute($submission->enrollment);

        $a = $submission->assessment;
        PortalNotifier::toUsers([$submission->enrollment->student->user_id],
            ($return ? 'Assignment returned: ' : 'Assignment graded: ') . $a->title,
            $return ? 'Your instructor has asked for changes.' : 'Your score is ' . rtrim(rtrim(number_format((float) $submission->percent, 1), '0'), '.') . '%.',
            route('academy.learn.assessment', [$a->course_id, $a]), 'course');

        return $submission;
    }

    // ── Marked in class ─────────────────────────────────────────────────

    /**
     * Enter scores for an assessment marked in class (practical, participation,
     * presentation...). A blank score leaves that student as they were.
     *
     * @param  array<int|string, array{score?:mixed, feedback?:?string}>  $scores  enrollment id => score and feedback
     * @return int how many scores were saved
     */
    public function recordScores(Assessment $assessment, array $scores, User $by): int
    {
        $this->check(!$assessment->isManual(), 'Scores are entered here only for work marked in class.');
        $max = (float) $assessment->max_score;
        $enrollments = Enrollment::with('student')->where('course_id', $assessment->course_id)->whereIn('status', ['active', 'completed'])
            ->whereKey(array_keys($scores))->get()->keyBy('id');
        $saved = 0;

        foreach ($scores as $enrollmentId => $row) {
            $enrollment = $enrollments->get((int) $enrollmentId);
            $score = $row['score'] ?? null;
            if (!$enrollment || $score === null || $score === '') continue;
            $this->check(!is_numeric($score) || (float) $score < 0 || (float) $score > $max,
                'Each score must be between 0 and ' . rtrim(rtrim(number_format($max, 2), '0'), '.') . '.');
            $feedback = trim((string) ($row['feedback'] ?? '')) ?: null;

            $submission = Submission::firstOrNew(['assessment_id' => $assessment->id, 'enrollment_id' => $enrollment->id]);
            $changed = !$submission->exists || (float) $submission->score !== (float) $score || $submission->feedback !== $feedback;
            $submission->fill([
                'status' => 'graded', 'score' => (float) $score, 'percent' => $max > 0 ? round((float) $score * 100 / $max, 2) : 0,
                'feedback' => $feedback, 'submitted_at' => $submission->submitted_at ?? now(), 'graded_by' => $by->id, 'graded_at' => now(),
            ])->save();
            if (!$changed) continue;

            $saved++;
            $this->recompute($enrollment);
            PortalNotifier::toUsers([$enrollment->student->user_id], 'Score: ' . $assessment->title,
                'You scored ' . rtrim(rtrim(number_format((float) $submission->percent, 1), '0'), '.') . '%.',
                route('academy.learn.assessment', [$assessment->course_id, $assessment]), 'course');
        }

        return $saved;
    }

    // ── Comments ────────────────────────────────────────────────────────

    /**
     * Comment on a student's work. The student and the course's teachers can
     * comment; the other side is told. $line points at a line of code.
     */
    public function comment(Submission|Attempt $work, User $by, string $body, ?int $line = null, ?int $questionId = null): AssessmentComment
    {
        $body = trim($body);
        $this->check($body === '', 'Write a comment.');
        $this->check(mb_strlen($body) > 5000, 'Keep comments under 5,000 characters.');
        $enrollment = $work->enrollment;
        $assessment = $work->assessment;
        $student = $enrollment->student->user_id === $by->id;
        $this->check(!$student && !app(LearningService::class)->teachesEnrollment($by, $enrollment), 'You cannot comment on this work.');
        if ($questionId !== null) {
            $this->check(!($work instanceof Attempt) || !$assessment->questions()->whereKey($questionId)->exists(), 'That question is not on this quiz.');
        }

        $comment = AssessmentComment::create([
            ($work instanceof Submission ? 'submission_id' : 'attempt_id') => $work->id,
            'user_id'     => $by->id,
            'body'        => $body,
            'line'        => $line && $line > 0 ? $line : null,
            'question_id' => $questionId,
        ]);

        $url = $student
            ? ($work instanceof Submission ? route('academy.assessments.results', [$assessment->course_id, $assessment]) . '#sub-' . $work->id : route('academy.assessments.attempt', [$assessment->course_id, $assessment, $work]))
            : ($work instanceof Submission ? route('academy.learn.assessment', [$assessment->course_id, $assessment]) : route('academy.learn.attempt', [$assessment->course_id, $work]));
        $to = $student
            ? array_values(array_unique(array_merge($this->teacherIds($enrollment), $work->comments()->where('user_id', '!=', $by->id)->pluck('user_id')->all())))
            : [$enrollment->student->user_id];
        PortalNotifier::toUsers(array_diff($to, [$by->id]), ($student ? $by->name . ' commented: ' : 'Comment on your work: ') . $assessment->title,
            ($line ? 'Line ' . $line . ': ' : '') . \Illuminate\Support\Str::limit($body, 140), $url, 'course');

        return $comment;
    }

    // ── Grade ───────────────────────────────────────────────────────────

    /**
     * Each published assessment's result for an enrollment, in percent
     * (null = not done or not marked yet).
     *
     * @return array<int, ?float> assessment id => percent
     */
    public function results(Enrollment $enrollment): array
    {
        $assessments = Assessment::where('course_id', $enrollment->course_id)->where('is_published', true)->get();
        $attempts = Attempt::where('enrollment_id', $enrollment->id)->whereNotNull('submitted_at')->where('needs_review', false)
            ->selectRaw('assessment_id, max(percent) as best')->groupBy('assessment_id')->pluck('best', 'assessment_id');
        $submissions = Submission::where('enrollment_id', $enrollment->id)->where('status', 'graded')->pluck('percent', 'assessment_id');

        return $assessments->mapWithKeys(fn ($a) => [$a->id => ($a->isQuiz() ? $attempts[$a->id] ?? null : $submissions[$a->id] ?? null)])
            ->map(fn ($v) => $v === null ? null : (float) $v)->all();
    }

    public function recompute(Enrollment $enrollment): ?float
    {
        $assessments = Assessment::where('course_id', $enrollment->course_id)->where('is_published', true)->get();
        if ($assessments->isEmpty()) {
            $enrollment->forceFill(['grade_percent' => null])->save();
            return null;
        }

        $results = $this->results($enrollment);
        $weighted = $assessments->sum(fn ($a) => (float) $a->weight) > 0;
        $total = 0.0;
        $weights = 0.0;
        foreach ($assessments as $a) {
            $w = $weighted ? (float) $a->weight : 1.0;
            $total += $w * ($results[$a->id] ?? 0);
            $weights += $w;
        }
        $grade = $weights > 0 ? round($total / $weights, 2) : null;
        $enrollment->forceFill(['grade_percent' => $grade])->save();

        return $grade;
    }

    public function recomputeCourse(int $courseId): void
    {
        Enrollment::where('course_id', $courseId)->whereIn('status', ['active', 'completed'])->each(fn ($e) => $this->recompute($e));
    }

    // ── Helpers ─────────────────────────────────────────────────────────

    protected function mayWork(Assessment $assessment, Enrollment $enrollment): void
    {
        $this->check($assessment->course_id !== $enrollment->course_id || !$assessment->is_published, 'This assessment is not available.');
        $this->check($enrollment->status !== 'active' || !$enrollment->access_granted,
            $enrollment->status === 'completed' ? 'This course is finished.' : 'Your course access opens once your payment is confirmed.');
    }

    protected function totals(array $marks, float $max): array
    {
        $score = round(array_sum(array_map('floatval', array_filter($marks, fn ($m) => $m !== null))), 2);

        return ['marks' => $marks, 'score' => $score, 'max_score' => $max, 'percent' => $max > 0 ? round($score * 100 / $max, 2) : 0];
    }

    /** The cohort's instructor, or academy staff when there is none. */
    protected function teacherIds(Enrollment $enrollment): array
    {
        $instructor = $enrollment->cohort?->instructor_id;

        return $instructor ? [$instructor] : PortalNotifier::userIdsWithPermission('Manage academy');
    }

    protected function check(bool $failed, string $message): void
    {
        if ($failed) {
            throw ValidationException::withMessages(['assessment' => $message]);
        }
    }
}
