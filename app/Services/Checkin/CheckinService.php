<?php

namespace App\Services\Checkin;

use App\Mail\CheckinTicketMail;
use App\Models\Academy\Attendance;
use App\Models\Academy\ClassSession;
use App\Models\Academy\Enrollment;
use App\Models\Checkin\Event;
use App\Models\Checkin\Guest;
use App\Models\Checkin\Place;
use App\Models\Checkin\Record;
use App\Models\Hr\Employee;
use App\Models\Meetings\Meeting;
use App\Models\User;
use App\Services\Academy\AttendanceService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * QR check-in.
 *
 * Two ways in, usable together:
 *  - The door screen shows a QR that changes every WINDOW seconds (an HMAC of the event's
 *    secret and the time). Scanning it with a phone checks that person in. A photo of it
 *    stops working within about a minute.
 *  - Someone at the door scans people's personal badge QR (staff and students) or their
 *    ticket QR (registered guests).
 *
 * Class check-ins are written to the academy register as present or late.
 */
class CheckinService
{
    public const WINDOW = 20;        // seconds per door code
    public const WINDOWS_OK = 3;     // accept the current code and the two before it

    public function __construct(protected AttendanceService $attendance) {}

    // ── Codes ───────────────────────────────────────────────────────

    public function screenToken(Event $event, ?int $window = null): string
    {
        $window ??= intdiv(now()->getTimestamp(), self::WINDOW);

        return substr(hash_hmac('sha256', $event->id . ':' . $window, $event->secret), 0, 12);
    }

    public function validScreenToken(Event $event, string $token): bool
    {
        $now = intdiv(now()->getTimestamp(), self::WINDOW);
        for ($i = 0; $i < self::WINDOWS_OK; $i++) {
            if (hash_equals($this->screenToken($event, $now - $i), $token)) return true;
        }

        return false;
    }

    /** Seconds until the door code changes. */
    public function secondsLeft(): int
    {
        return self::WINDOW - (now()->getTimestamp() % self::WINDOW);
    }

    public static function badgeSig(int $userId): string
    {
        return substr(hash_hmac('sha256', 'badge:' . $userId, (string) config('app.key')), 0, 16);
    }

    /** What a person's own QR holds. */
    public static function badgePayload(User $user): string
    {
        return 'QD1:U:' . $user->id . ':' . self::badgeSig($user->id);
    }

    public static function guestPayload(Guest $guest): string
    {
        return 'QD1:G:' . $guest->code;
    }

    /** A scanned badge or ticket → ['user' => User] or ['guest' => Guest], or null when it isn't one of ours. */
    public function resolve(string $payload, Event $event): ?array
    {
        $payload = trim($payload);
        if (preg_match('/^QD1:U:(\d+):([a-f0-9]{16})$/', $payload, $m)) {
            if (!hash_equals(self::badgeSig((int) $m[1]), $m[2])) return null;
            $u = User::find((int) $m[1]);

            return $u ? ['user' => $u] : null;
        }
        // Tickets: the full payload, or the code typed in by hand.
        if (preg_match('/^(?:QD1:G:)?([A-Z0-9]{10})$/', strtoupper($payload), $m)) {
            $g = Guest::where('event_id', $event->id)->where('code', $m[1])->first();

            return $g ? ['guest' => $g] : null;
        }

        return null;
    }

    // ── Events for things that already exist ───────────────────────

    protected function make(array $attrs): Event
    {
        return new Event($attrs + ['secret' => Str::random(40), 'status' => 'open']);
    }

    public function forClassSession(ClassSession $session, User $by): Event
    {
        $existing = Event::where('subject_type', $session->getMorphClass())->where('subject_id', $session->id)->first();
        if ($existing) return $existing;
        $cohort = $session->cohort()->with('course')->first();
        $date = $session->date->toDateString();
        $starts = Carbon::parse($date . ' ' . ($session->starts_at ? substr((string) $session->starts_at, 0, 5) : '09:00'));
        $ends = $session->ends_at ? Carbon::parse($date . ' ' . substr((string) $session->ends_at, 0, 5)) : $starts->copy()->addHours(3);
        $e = $this->make(['kind' => 'class', 'title' => trim(($cohort->course->title ?? 'Class') . ' · ' . ($cohort->name ?? '') . ($session->topic ? ' · ' . $session->topic : ''), ' ·'),
            'starts_at' => $starts, 'ends_at' => $ends, 'audience' => 'invited', 'opens_minutes' => 30, 'late_minutes' => 15, 'created_by' => $by->id]);
        $e->subject()->associate($session);
        $e->save();

        return $e;
    }

    public function forMeeting(Meeting $meeting, User $by): Event
    {
        $existing = Event::where('subject_type', $meeting->getMorphClass())->where('subject_id', $meeting->id)->first();
        if ($existing) return $existing;
        $e = $this->make(['kind' => 'meeting', 'title' => $meeting->title, 'starts_at' => $meeting->starts_at, 'ends_at' => $meeting->ends_at,
            'location' => $meeting->whereLabel(), 'audience' => 'invited', 'opens_minutes' => 30, 'late_minutes' => 10, 'created_by' => $by->id]);
        $e->subject()->associate($meeting);
        $e->save();

        return $e;
    }

    /** Today's (or a given day's) clock-in for an office. */
    public function staffDay(Place $place, ?Carbon $day = null): Event
    {
        $day = ($day ?? today())->copy()->startOfDay();

        // whereDate: SQLite keeps the date with a time, so a plain match would miss it.
        $existing = Event::where('place_id', $place->id)->whereDate('day', $day)->first();
        if ($existing) return $existing;

        return Event::create(['place_id' => $place->id, 'day' => $day->toDateString(),
            'kind' => 'staff', 'title' => $place->name . ' · ' . $day->format('D j M'), 'location' => $place->address ?: $place->name,
            'lat' => $place->lat, 'lng' => $place->lng, 'radius_m' => $place->radius_m ?? 150, 'require_location' => (bool) $place->require_location,
            'starts_at' => Carbon::parse($day->toDateString() . ' ' . substr((string) $place->work_starts, 0, 5)),
            'ends_at' => Carbon::parse($day->toDateString() . ' ' . substr((string) $place->work_ends, 0, 5))->addHours(6)->min($day->copy()->endOfDay()),
            'opens_minutes' => 180, 'late_minutes' => $place->grace_minutes ?? 15, 'audience' => 'staff', 'secret' => Str::random(40), 'status' => 'open',
        ]);
    }

    // ── Who ─────────────────────────────────────────────────────────

    public function isAdmin(User $user): bool
    {
        return $user->hasRole('Super Administrator');
    }

    /** Can this person run the door (screen, scanner, marking, the list)? */
    public function canRun(User $user, Event $event): bool
    {
        if ($this->isAdmin($user)) return true;
        if ($event->created_by === $user->id || $event->people()->where('users.id', $user->id)->wherePivot('role', 'host')->exists()) return $user->can('Run check-in');

        return match ($event->kind) {
            'class' => $event->subject && $this->attendance->canTake($user, $event->subject->cohort),
            'meeting' => $event->subject && ($event->subject->organizer_id === $user->id || $user->can('Manage meetings')),
            'staff' => $user->can('Manage check-in places') || $user->can('Manage employees'),
            default => false,
        };
    }

    protected function isStaff(User $user): bool
    {
        $emp = Employee::where('user_id', $user->id)->first();

        return $emp ? $emp->status === 'active' : $user->isStaff();
    }

    /** The class enrollment this person checks in under, if any. */
    public function enrollmentFor(Event $event, User $user): ?Enrollment
    {
        if ($event->kind !== 'class' || !$event->subject) return null;

        return Enrollment::where('cohort_id', $event->subject->cohort_id)->whereIn('status', ['active', 'pending'])
            ->whereHas('student', fn ($s) => $s->where('user_id', $user->id))->first();
    }

    /** Null when this person may check in here, or the reason they can't. */
    public function refusal(Event $event, User $user): ?string
    {
        $ok = match ($event->kind) {
            'class' => (bool) $this->enrollmentFor($event, $user),
            'meeting' => $event->subject && ($event->subject->organizer_id === $user->id
                || $event->subject->attendees()->where('user_id', $user->id)->where('response', '!=', 'declined')->exists()
                || $event->people()->where('users.id', $user->id)->exists()),
            'staff' => $this->isStaff($user),
            default => match ($event->audience) {
                'everyone' => true,
                'staff' => $this->isStaff($user),
                'students' => $user->isStudent(),
                'registered' => $event->guests()->whereNull('cancelled_at')->where(fn ($g) => $g->where('user_id', $user->id)->orWhere('email', $user->email))->exists(),
                default => $event->people()->where('users.id', $user->id)->exists(),
            },
        };
        if ($ok) return null;

        return match ($event->kind) {
            'class' => 'You are not enrolled in this class.',
            'meeting' => 'You are not invited to this meeting.',
            'staff' => 'Only staff clock in here.',
            default => $event->audience === 'registered' ? 'You have not registered for this event.' : 'This check-in is not open to you.',
        };
    }

    // ── Checking in ─────────────────────────────────────────────────

    public static function distance(float $lat1, float $lng1, float $lat2, float $lng2): int
    {
        $r = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return (int) round(2 * $r * asin(min(1, sqrt($a))));
    }

    protected function fail(string $message, string $field = 'code'): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }

    /**
     * Check someone in (or, at a staff day, out). $method: screen, badge or manual.
     *
     * @return array{record: Record, state: string, message: string}
     */
    public function checkIn(Event $event, ?User $user, ?Guest $guest, string $method, ?User $by = null, array $opts = []): array
    {
        if ($event->status === 'cancelled') $this->fail('This check-in was cancelled.');
        if ($method !== 'manual') {
            if ($event->status === 'closed') $this->fail('Check-in for this has closed.');
            if (now()->lt($event->opensAt())) $this->fail('Check-in opens at ' . $event->opensAt()->format('g:i A') . '.');
            if (now()->gt($event->closesAt())) $this->fail('Check-in for this has closed.');
        }
        if ($guest) {
            if ($guest->event_id !== $event->id) $this->fail('This ticket is for a different event.');
            if ($guest->cancelled_at) $this->fail('This ticket was cancelled.');
            $user ??= $guest->user;
        } elseif ($user) {
            if ($reason = $this->refusal($event, $user)) $this->fail($reason);
        } else {
            $this->fail('Nobody to check in.');
        }

        // Near the venue? Only for the door QR (a scanner at the door is already there).
        $lat = $opts['lat'] ?? null;
        $lng = $opts['lng'] ?? null;
        $distance = null;
        if ($method === 'screen' && $event->require_location && $event->hasLocation()) {
            if ($lat === null || $lng === null) $this->fail('Allow location so we can check you are at ' . ($event->location ?: 'the venue') . '.', 'location');
            $distance = self::distance($event->lat, $event->lng, (float) $lat, (float) $lng);
            if ($distance > $event->radius_m + 50) $this->fail('You seem to be about ' . number_format($distance) . ' m away. Check in when you get there.', 'location');
        }

        return DB::transaction(function () use ($event, $user, $guest, $method, $by, $opts, $lat, $lng, $distance) {
            $q = Record::where('event_id', $event->id)->lockForUpdate();
            $existing = $guest ? (clone $q)->where('guest_id', $guest->id)->first() : (clone $q)->where('user_id', $user->id)->first();
            if (!$existing && $guest && $user) $existing = (clone $q)->where('user_id', $user->id)->first();

            if ($existing) {
                // A second scan on a staff day clocks you out.
                if ($event->kind === 'staff' && $method !== 'manual' && !$existing->checked_out_at && $existing->checked_in_at->lt(now()->subMinute())) {
                    $existing->update(['checked_out_at' => now()]);

                    return ['record' => $existing, 'state' => 'out', 'message' => 'Clocked out at ' . now()->format('g:i A') . '. See you tomorrow.'];
                }
                if ($method === 'manual' && isset($opts['status']) && $opts['status'] !== $existing->status) {
                    $existing->update(['status' => $opts['status'], 'scanned_by' => $by?->id]);
                    $this->syncClass($event, $existing);
                }

                return ['record' => $existing, 'state' => 'already', 'message' => 'Already checked in at ' . $existing->checked_in_at->format('g:i A') . '.'];
            }

            if ($event->capacity && $event->kind === 'event' && $event->records()->count() >= $event->capacity) $this->fail('The venue is full (' . $event->capacity . ' people).');
            $status = $opts['status'] ?? (now()->gt($event->lateAt()) ? 'late' : 'present');
            $record = Record::create([
                'event_id' => $event->id, 'user_id' => $user?->id, 'guest_id' => $guest?->id, 'status' => $status, 'method' => $method,
                'checked_in_at' => now(), 'scanned_by' => $by?->id, 'lat' => $lat, 'lng' => $lng, 'distance_m' => $distance, 'note' => $opts['note'] ?? null,
            ]);
            $this->syncClass($event, $record);
            $who = $user?->name ?? $guest?->name;
            $msg = $event->kind === 'staff'
                ? ($status === 'late' ? 'Clocked in at ' . now()->format('g:i A') . ' (late).' : 'Clocked in at ' . now()->format('g:i A') . '. Have a good day!')
                : ($status === 'late' ? 'Checked in (late).' : 'Checked in. Welcome' . ($who ? ', ' . Str::before($who, ' ') : '') . '!');

            return ['record' => $record, 'state' => 'in', 'message' => $msg];
        });
    }

    /** Class check-ins go on the academy register. */
    protected function syncClass(Event $event, Record $record): void
    {
        if ($event->kind !== 'class' || !$event->subject || !$record->user_id) return;
        $enrollment = $this->enrollmentFor($event, $record->user);
        if (!$enrollment) return;
        Attendance::updateOrCreate(['class_session_id' => $event->subject_id, 'enrollment_id' => $enrollment->id],
            ['status' => $record->status, 'marked_by' => $record->scanned_by ?? $record->user_id, 'note' => 'QR check-in']);
        $session = $event->subject;
        if ($session->status === 'scheduled') $session->update(['status' => 'held', 'register_taken_at' => $session->register_taken_at ?? now()]);
        $this->attendance->recompute($enrollment);
    }

    public function undo(Record $record): void
    {
        $event = $record->event;
        if ($event->kind === 'class' && $record->user_id && ($en = $this->enrollmentFor($event, $record->user))) {
            Attendance::where('class_session_id', $event->subject_id)->where('enrollment_id', $en->id)->delete();
            $this->attendance->recompute($en);
        }
        $record->delete();
    }

    /** Close the door. For a class, everyone on the register who didn't come is marked absent. */
    public function close(Event $event, bool $markAbsent = true): int
    {
        $absent = 0;
        if ($event->kind === 'class' && $event->subject && $markAbsent) {
            $session = $event->subject;
            foreach ($this->attendance->roster($session) as $en) {
                $exists = Attendance::where('class_session_id', $session->id)->where('enrollment_id', $en->id)->exists();
                if (!$exists && $en->status === 'active') {
                    Attendance::create(['class_session_id' => $session->id, 'enrollment_id' => $en->id, 'status' => 'absent', 'marked_by' => $event->created_by, 'note' => 'Did not check in']);
                    $absent++;
                }
            }
            $session->update(['status' => 'held', 'register_taken_at' => $session->register_taken_at ?? now()]);
            $this->attendance->recomputeCohort($session->cohort);
        }
        $event->update(['status' => 'closed']);

        return $absent;
    }

    // ── Who is expected, and how it's going ─────────────────────────

    /** People expected (users), or null when anyone may come. */
    public function expected(Event $event): ?Collection
    {
        return match ($event->kind) {
            'class' => $event->subject ? $this->attendance->roster($event->subject)->map(fn ($e) => $e->student->user)->filter()->values() : collect(),
            'meeting' => $event->subject ? User::whereIn('id', $event->subject->attendees()->where('response', '!=', 'declined')->pluck('user_id')->push($event->subject->organizer_id))
                ->orWhereIn('id', $event->people()->pluck('users.id'))->orderBy('name')->get() : collect(),
            'staff' => User::where('is_disabled', false)->where('user_type', User::TYPE_STAFF)
                ->whereNotIn('id', Employee::where('status', '!=', 'active')->pluck('user_id'))->orderBy('name')->get(),
            default => match ($event->audience) {
                'invited' => $event->people()->orderBy('name')->get(),
                'staff' => User::where('is_disabled', false)->where('user_type', User::TYPE_STAFF)->orderBy('name')->get(),
                'students' => User::where('is_disabled', false)->where('user_type', User::TYPE_STUDENT)->orderBy('name')->get(),
                default => null,
            },
        };
    }

    public function stats(Event $event): array
    {
        $records = $event->records();
        $expected = $this->expected($event);
        $guests = $event->guests()->whereNull('cancelled_at')->count();

        return [
            'in' => (clone $records)->count(), 'late' => (clone $records)->where('status', 'late')->count(),
            'out' => (clone $records)->whereNotNull('checked_out_at')->count(),
            'expected' => $expected ? $expected->count() + ($event->audience === 'registered' || $event->kind === 'event' ? $guests : 0) : ($guests ?: null),
            'registered' => $guests, 'capacity' => $event->capacity,
        ];
    }

    // ── Registration (conferences and other events) ─────────────────

    public function register(Event $event, array $data, ?User $user = null): Guest
    {
        if (!$event->public_registration && !$user) $this->fail('Registration is closed.', 'email');
        if ($event->status !== 'open' || ($event->registration_closes_at && now()->gt($event->registration_closes_at)) || now()->gt($event->closesAt())) {
            $this->fail('Registration is closed.', 'email');
        }
        $email = isset($data['email']) ? Str::lower(trim($data['email'])) : null;
        if ($email && ($g = $event->guests()->where('email', $email)->whereNull('cancelled_at')->first())) {
            $this->sendTicket($g);

            return $g;   // already registered: send the ticket again
        }
        if ($event->capacity && $event->guests()->whereNull('cancelled_at')->count() >= $event->capacity) $this->fail('Sorry, this event is full.', 'email');

        $guest = $event->guests()->create([
            // Link the portal account only when people register themselves, not when signing someone else up.
            'user_id' => $user && (!$email || Str::lower($user->email) === $email) ? $user->id : null, 'name' => trim($data['name']), 'email' => $email, 'phone' => $data['phone'] ?? null,
            'organisation' => $data['organisation'] ?? null, 'code' => $this->newCode(),
        ]);
        $this->sendTicket($guest);

        return $guest;
    }

    protected function newCode(): string
    {
        do {
            $c = strtoupper(Str::random(10));
            $c = strtr($c, ['O' => 'X', '0' => '7', 'I' => 'K', '1' => '9', 'L' => 'M']);
        } while (Guest::where('code', $c)->exists());

        return $c;
    }

    public function sendTicket(Guest $guest): void
    {
        if (!$guest->email) return;
        try {
            Mail::to($guest->email, $guest->name)->send(new CheckinTicketMail($guest));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    // ── Staff reports ───────────────────────────────────────────────

    /**
     * One row per staff member for a month: days in, days late, average arrival, hours, and
     * each day's times (for the grid and the CSV).
     */
    public function staffMonth(Carbon $month, ?Place $place = null): array
    {
        $from = $month->copy()->startOfMonth();
        $to = $month->copy()->endOfMonth();
        $events = Event::where('kind', 'staff')->whereDate('day', '>=', $from)->whereDate('day', '<=', $to)
            ->when($place, fn ($q) => $q->where('place_id', $place->id))->pluck('day', 'id');
        $records = Record::with('user:id,name')->whereIn('event_id', $events->keys())->whereNotNull('user_id')->get();

        $workdays = 0;
        $places = $place ? collect([$place]) : Place::where('is_active', true)->get();
        for ($d = $from->copy(); $d->lte($to) && $d->lte(today()); $d->addDay()) {
            if ($places->contains(fn ($p) => $p->isWorkday($d))) $workdays++;
        }

        $rows = [];
        foreach ($records->groupBy('user_id') as $uid => $recs) {
            $days = [];
            $minutes = 0;
            foreach ($recs as $r) {
                $day = Carbon::parse($events[$r->event_id])->toDateString();
                $days[$day] = ['in' => $r->checked_in_at->format('H:i'), 'out' => $r->checked_out_at?->format('H:i'), 'late' => $r->status === 'late'];
                if ($r->checked_out_at) $minutes += $r->checked_in_at->diffInMinutes($r->checked_out_at);
            }
            $avg = $recs->avg(fn ($r) => $r->checked_in_at->hour * 60 + $r->checked_in_at->minute);
            $rows[] = ['user' => $recs->first()->user, 'present' => count($days), 'late' => $recs->where('status', 'late')->count(),
                'avg_in' => $avg !== null ? sprintf('%02d:%02d', intdiv((int) $avg, 60), (int) $avg % 60) : null,
                'hours' => round($minutes / 60, 1), 'days' => $days];
        }
        usort($rows, fn ($a, $b) => strcmp($a['user']?->name ?? '', $b['user']?->name ?? ''));

        return ['rows' => $rows, 'workdays' => $workdays, 'from' => $from, 'to' => $to];
    }
}
