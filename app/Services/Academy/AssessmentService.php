<?php

namespace App\Services\Academy;

use App\Models\Academy\Assessment;
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
            if (is_string($answer)) $answer = mb_substr(trim($answer), 0, $q->isManual() ? 10000 : 500);
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
            PortalNotifier::toUsers($this->teacherIds($attempt->enrollment), 'Quiz to mark: ' . $quiz->title,
                $attempt->enrollment->student->user->name . ' has essay answers waiting.',
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

    public function handIn(Assessment $assignment, Enrollment $enrollment, ?string $body, ?UploadedFile $file): Submission
    {
        $this->mayWork($assignment, $enrollment);
        $this->check($assignment->isQuiz(), 'This is not an assignment.');
        $body = trim((string) $body) === '' ? null : trim($body);
        $this->check($body === null && $file === null, 'Write your answer or attach a file.');

        $existing = Submission::where('assessment_id', $assignment->id)->where('enrollment_id', $enrollment->id)->first();
        $this->check($existing && $existing->status === 'graded', 'This assignment has been graded. Ask your instructor if you need to resubmit.');

        $data = ['body' => $body, 'status' => 'submitted', 'submitted_at' => now()];
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
