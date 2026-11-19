<?php

namespace App\Services\Academy;

use App\Models\Academy\Attendance;
use App\Models\Academy\ClassSession;
use App\Models\Academy\Cohort;
use App\Models\Academy\Enrollment;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Class sessions and registers.
 *
 *  - Academy staff take any register; an instructor with Manage attendance
 *    takes the registers of the cohorts they teach.
 *  - The register lists students whose enrollment is active (or pending:
 *    they may attend before their payment clears).
 *  - Attendance rate = (present + late) / sessions marked, leaving out
 *    excused absences and cancelled sessions.
 */
class AttendanceService
{
    public function canTake(User $user, Cohort $cohort): bool
    {
        return $user->can('Manage academy') || ($user->can('Manage attendance') && $cohort->instructor_id === $user->id);
    }

    public function canView(User $user, Cohort $cohort): bool
    {
        return $this->canTake($user, $cohort) || $user->can('View attendance') || $cohort->instructor_id === $user->id;
    }

    /**
     * Add a session on each chosen weekday between two dates. Skips dates that
     * already have a session at the same start time.
     *
     * @param  int[]  $weekdays  0 = Sunday … 6 = Saturday
     */
    public function schedule(Cohort $cohort, Carbon $from, Carbon $to, array $weekdays, ?string $starts, ?string $ends, ?string $topic = null): int
    {
        $this->check($to->lt($from), 'to', 'The end date is before the start date.');
        $this->check($from->diffInDays($to) > 366, 'to', 'Schedule at most a year at a time.');
        $this->check(!$weekdays, 'weekdays', 'Pick at least one day of the week.');

        $existing = $cohort->sessions()->get()->map(fn ($s) => $s->date->toDateString() . '|' . substr((string) $s->starts_at, 0, 5))->flip();
        $made = 0;
        for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
            if (!in_array($d->dayOfWeek, $weekdays, true) || $existing->has($d->toDateString() . '|' . substr((string) $starts, 0, 5))) continue;
            $cohort->sessions()->create(['date' => $d->toDateString(), 'starts_at' => $starts, 'ends_at' => $ends, 'topic' => $topic]);
            $made++;
        }

        return $made;
    }

    /** Enrollments that belong on this session's register. */
    public function roster(ClassSession $session): Collection
    {
        return Enrollment::with('student.user')->where('cohort_id', $session->cohort_id)
            ->where(fn ($q) => $q->whereIn('status', ['active', 'pending'])
                ->orWhereIn('id', Attendance::where('class_session_id', $session->id)->select('enrollment_id')))
            ->get()->sortBy(fn ($e) => $e->student->user->name)->values();
    }

    /** @param array<int|string, array{status?: string, note?: ?string}> $marks enrollment id => mark */
    public function takeRegister(ClassSession $session, array $marks, User $by): int
    {
        $this->check(!$this->canTake($by, $session->cohort), 'register', 'Only academy staff or this cohort\'s instructor can take the register.');
        $this->check($session->status === 'cancelled', 'register', 'This session was cancelled.');
        $this->check($session->date->isAfter(today()), 'register', 'You can take the register on or after the day of the session.');

        $roster = $this->roster($session)->keyBy('id');
        $saved = 0;
        DB::transaction(function () use ($session, $marks, $by, $roster, &$saved) {
            foreach ($marks as $enrollmentId => $mark) {
                $status = $mark['status'] ?? null;
                if (!$roster->has((int) $enrollmentId) || !isset(Attendance::STATUSES[$status])) continue;
                Attendance::updateOrCreate(
                    ['class_session_id' => $session->id, 'enrollment_id' => (int) $enrollmentId],
                    ['status' => $status, 'note' => mb_substr(trim((string) ($mark['note'] ?? '')), 0, 300) ?: null, 'marked_by' => $by->id]
                );
                $saved++;
            }
            $session->update(['status' => 'held', 'register_taken_at' => now(), 'register_taken_by' => $by->id]);
        });
        $this->check($saved === 0, 'register', 'Mark at least one student.');

        $roster->each(fn ($e) => $this->recompute($e));

        return $saved;
    }

    public function cancel(ClassSession $session, User $by, string $reason): void
    {
        $this->check(!$this->canTake($by, $session->cohort), 'reason', 'You can\'t change this cohort\'s sessions.');
        $this->check(trim($reason) === '', 'reason', 'Give a reason.');

        $session->update(['status' => 'cancelled', 'cancel_reason' => mb_substr(trim($reason), 0, 300)]);
        $this->recomputeCohort($session->cohort);
    }

    public function recompute(Enrollment $enrollment): ?float
    {
        $counts = Attendance::where('enrollment_id', $enrollment->id)
            ->whereHas('session', fn ($s) => $s->where('status', 'held'))
            ->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');

        $counted = ($counts['present'] ?? 0) + ($counts['late'] ?? 0) + ($counts['absent'] ?? 0);
        $rate = $counted ? round((($counts['present'] ?? 0) + ($counts['late'] ?? 0)) * 100 / $counted, 2) : null;
        $enrollment->forceFill(['attendance_percent' => $rate])->save();

        return $rate;
    }

    /**
     * One student's attendance on a class: the counts, the rate, and every session
     * with their mark (null while it hasn't happened or wasn't marked).
     *
     * @return array{rate: ?float, counts: array<string,int>, sessions: \Illuminate\Support\Collection}
     */
    public function forEnrollment(Enrollment $enrollment): array
    {
        $marks = Attendance::where('enrollment_id', $enrollment->id)->get()->keyBy('class_session_id');
        $events = \App\Models\Checkin\Event::where('subject_type', (new ClassSession)->getMorphClass())
            ->whereIn('subject_id', ClassSession::where('cohort_id', $enrollment->cohort_id)->select('id'))->pluck('id', 'subject_id');
        $records = \App\Models\Checkin\Record::whereIn('event_id', $events->values())->where('user_id', $enrollment->student->user_id)->get()->keyBy('event_id');
        $sessions = ClassSession::where('cohort_id', $enrollment->cohort_id)->orderBy('date')->orderBy('starts_at')->get()
            ->map(function (ClassSession $s) use ($marks, $events, $records) {
                $mark = $marks->get($s->id);
                $rec = $events->has($s->id) ? $records->get($events[$s->id]) : null;

                return [
                    'id' => $s->id, 'date' => $s->date, 'time' => $s->timeRange(), 'topic' => $s->topic, 'session_status' => $s->status,
                    'status' => $s->status === 'cancelled' ? 'cancelled' : $mark?->status, 'note' => $mark?->note,
                    'method' => $rec?->method, 'checked_in_at' => $rec?->checked_in_at,
                ];
            });
        $counts = collect(Attendance::STATUSES)->map(fn ($_, $k) => $sessions->where('status', $k)->count())->all();

        return ['rate' => $enrollment->attendance_percent !== null ? (float) $enrollment->attendance_percent : null, 'counts' => $counts, 'sessions' => $sessions];
    }

    /**
     * The attendance report: one row per student and class, counted over a date range.
     * $cohortIds limits it (null: every class). Rate = (present + late) / (present + late + absent).
     *
     * @return \Illuminate\Support\Collection<int, array>
     */
    public function report(?array $cohortIds, ?int $courseId, ?int $cohortId, ?Carbon $from, ?Carbon $to, ?float $below = null)
    {
        $enrollments = Enrollment::with('student.user', 'course', 'cohort')
            ->whereIn('status', ['active', 'pending', 'completed'])
            ->when($cohortIds !== null, fn ($q) => $q->whereIn('cohort_id', $cohortIds))
            ->when($courseId, fn ($q) => $q->where('course_id', $courseId))
            ->when($cohortId, fn ($q) => $q->where('cohort_id', $cohortId))
            ->get();
        if ($enrollments->isEmpty()) return collect();

        $counts = Attendance::query()->whereIn('enrollment_id', $enrollments->pluck('id'))
            ->whereHas('session', fn ($s) => $s->where('status', 'held')
                ->when($from, fn ($q) => $q->whereDate('date', '>=', $from))->when($to, fn ($q) => $q->whereDate('date', '<=', $to)))
            ->selectRaw('enrollment_id, status, count(*) as n')->groupBy('enrollment_id', 'status')->get()
            ->groupBy('enrollment_id')->map(fn ($g) => $g->pluck('n', 'status'));

        return $enrollments->map(function (Enrollment $e) use ($counts) {
            $c = $counts->get($e->id, collect());
            $present = (int) ($c['present'] ?? 0);
            $late = (int) ($c['late'] ?? 0);
            $absent = (int) ($c['absent'] ?? 0);
            $excused = (int) ($c['excused'] ?? 0);
            $counted = $present + $late + $absent;

            return [
                'enrollment' => $e, 'name' => $e->student->user->name, 'student_no' => $e->student->student_no,
                'course' => $e->course?->title, 'cohort' => $e->cohort?->name,
                'present' => $present, 'late' => $late, 'absent' => $absent, 'excused' => $excused,
                'rate' => $counted ? round(($present + $late) * 100 / $counted, 1) : null,
            ];
        })->when($below !== null, fn ($rows) => $rows->filter(fn ($r) => $r['rate'] !== null && $r['rate'] < $below))
            ->sortBy([['course', 'asc'], ['cohort', 'asc'], ['name', 'asc']])->values();
    }

    public function recomputeCohort(Cohort $cohort): void
    {
        $cohort->enrollments()->each(fn ($e) => $this->recompute($e));
    }

    protected function check(bool $failed, string $field, string $message): void
    {
        if ($failed) {
            throw ValidationException::withMessages([$field => $message]);
        }
    }
}
