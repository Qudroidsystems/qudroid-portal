<?php

namespace App\Services\Meetings;

use App\Models\Hr\Holiday;
use App\Models\Hr\LeaveRequest;
use App\Models\Meetings\Attendee;
use App\Models\Meetings\Meeting;
use App\Models\Meetings\Room;
use App\Models\Projects\Task;
use App\Models\User;
use App\Services\Messaging\PortalNotifier;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Meetings: anyone with View meetings schedules their own meetings and
 * invites colleagues; the organiser (or Manage meetings) changes or
 * cancels them. Invitees answer going / maybe / not going. Rooms can't be
 * double-booked. Everyone still coming gets a reminder before it starts.
 */
class MeetingService
{
    /** Staff who can be invited. */
    public function people()
    {
        return User::whereIn('id', PortalNotifier::userIdsWithPermission('View meetings'))->where('is_disabled', false)->orderBy('name');
    }

    public function canView(Meeting $m, User $u): bool
    {
        return $m->organizer_id === $u->id || $u->can('Manage meetings') || $m->attendees->contains('user_id', $u->id);
    }

    public function canManage(Meeting $m, User $u): bool
    {
        return $m->organizer_id === $u->id || $u->can('Manage meetings');
    }

    public function schedule(array $data, array $userIds, User $by): Meeting
    {
        [$fields, $start, $end] = $this->fields($data);
        $this->roomFree($fields['room_id'] ?? null, $start, $end);
        $this->check($start->lt(now()->subMinutes(5)), 'starts_at', 'That time has passed.');

        $meeting = DB::transaction(function () use ($fields, $userIds, $by) {
            $meeting = Meeting::create($fields + ['organizer_id' => $by->id, 'status' => 'scheduled']);
            $meeting->attendees()->create(['user_id' => $by->id, 'response' => 'accepted']);
            foreach ($this->invitable($userIds, $by) as $id) $meeting->attendees()->create(['user_id' => $id]);

            return $meeting;
        });

        $this->tell($meeting, $meeting->attendees()->where('user_id', '!=', $by->id)->pluck('user_id')->all(),
            'Meeting invite: ' . $meeting->title, $by->name . ' invited you. ' . $meeting->whenLabel() . $this->where($meeting) . '.');

        return $meeting;
    }

    public function update(Meeting $meeting, array $data, array $userIds, User $by): Meeting
    {
        $this->check($meeting->isCancelled(), 'title', 'This meeting was cancelled.');
        [$fields, $start, $end] = $this->fields($data);
        $this->roomFree($fields['room_id'] ?? null, $start, $end, $meeting->id);

        $moved = !$meeting->starts_at->eq($start) || !$meeting->ends_at->eq($end)
            || (int) $meeting->room_id !== (int) ($fields['room_id'] ?? 0) || (string) $meeting->location !== (string) ($fields['location'] ?? '');

        [$added, $removed] = DB::transaction(function () use ($meeting, $fields, $userIds, $by, $moved, $start) {
            $meeting->update($fields + ($moved && !$meeting->starts_at->eq($start) ? ['reminded_at' => null] : []));
            $want = collect($this->invitable($userIds, $meeting->organizer))->push($meeting->organizer_id)->unique();
            $have = $meeting->attendees()->pluck('user_id');
            $added = $want->diff($have)->values()->all();
            $removed = $have->diff($want)->values()->all();
            $meeting->attendees()->whereIn('user_id', $removed)->delete();
            foreach ($added as $id) $meeting->attendees()->create(['user_id' => $id]);
            if ($moved) {
                $meeting->attendees()->where('user_id', '!=', $meeting->organizer_id)->whereNotIn('user_id', $added)->update(['response' => 'pending']);
            }

            return [$added, $removed];
        });

        $others = fn (array $ids) => array_values(array_diff($ids, [$by->id]));
        $this->tell($meeting, $others($added), 'Meeting invite: ' . $meeting->title, $by->name . ' invited you. ' . $meeting->whenLabel() . $this->where($meeting) . '.');
        $this->tell($meeting, $others($removed), 'Removed from meeting: ' . $meeting->title, 'You are no longer needed at this meeting.');
        if ($moved) {
            $stay = $meeting->attendees()->whereNotIn('user_id', $added)->pluck('user_id')->all();
            $this->tell($meeting, $others($stay), 'Meeting changed: ' . $meeting->title, 'Now ' . $meeting->whenLabel() . $this->where($meeting) . '. Please answer again.');
        }

        return $meeting;
    }

    public function cancel(Meeting $meeting, User $by, ?string $reason = null): Meeting
    {
        $this->check($meeting->isCancelled(), 'status', 'This meeting is already cancelled.');
        $this->check($meeting->isOver(), 'status', 'This meeting has already happened.');
        $meeting->update(['status' => 'cancelled', 'cancel_reason' => $reason ? mb_substr(trim($reason), 0, 255) : null]);
        $this->tell($meeting, $meeting->attendees()->where('user_id', '!=', $by->id)->pluck('user_id')->all(),
            'Meeting cancelled: ' . $meeting->title, $meeting->whenLabel() . ($reason ? '. ' . trim($reason) : '') . '.');

        return $meeting;
    }

    public function respond(Meeting $meeting, User $user, string $response, ?string $note = null): Attendee
    {
        $this->check(!isset(Attendee::RESPONSES[$response]) || $response === 'pending', 'response', 'Choose going, maybe or not going.');
        $this->check($meeting->isCancelled(), 'response', 'This meeting was cancelled.');
        $att = $meeting->attendees()->where('user_id', $user->id)->first();
        $this->check(!$att, 'response', 'You are not invited to this meeting.');
        $att->update(['response' => $response, 'note' => $note ? mb_substr(trim($note), 0, 255) : null]);

        if ($meeting->organizer_id !== $user->id && in_array($response, ['declined', 'tentative'], true)) {
            $this->tell($meeting, [$meeting->organizer_id], $user->name . ($response === 'declined' ? ' can\'t come' : ' might come') . ': ' . $meeting->title,
                $note ? trim($note) : $meeting->whenLabel());
        }

        return $att;
    }

    public function saveMinutes(Meeting $meeting, User $by, ?string $minutes): Meeting
    {
        $first = trim((string) $meeting->minutes) === '' && trim((string) $minutes) !== '';
        $meeting->update(['minutes' => trim((string) $minutes) ?: null]);
        if ($first) {
            $this->tell($meeting, $meeting->attendees()->where('user_id', '!=', $by->id)->where('response', '!=', 'declined')->pluck('user_id')->all(),
                'Minutes: ' . $meeting->title, $by->name . ' posted the notes from this meeting.');
        }

        return $meeting;
    }

    /**
     * Invitees who already have something on at that time (another meeting or approved leave).
     *
     * @return array<int, string> user id => what clashes
     */
    public function busy(array $userIds, Carbon $start, Carbon $end, ?int $exceptMeeting = null): array
    {
        $out = [];
        Attendee::with('meeting')->whereIn('user_id', $userIds)->where('response', '!=', 'declined')
            ->whereHas('meeting', fn ($q) => $q->active()->between($start, $end)->when($exceptMeeting, fn ($w) => $w->whereKeyNot($exceptMeeting)))
            ->get()->each(function ($a) use (&$out) { $out[$a->user_id] ??= 'in "' . $a->meeting->title . '" ' . $a->meeting->starts_at->format('H:i') . '–' . $a->meeting->ends_at->format('H:i'); });

        if (Schema::hasTable('hr_leave_requests')) {
            LeaveRequest::with('employee', 'type')->where('status', 'approved')
                ->whereDate('starts_on', '<=', $end)->whereDate('ends_on', '>=', $start)
                ->whereHas('employee', fn ($q) => $q->whereIn('user_id', $userIds))->get()
                ->each(function ($l) use (&$out) { $out[$l->employee->user_id] ??= 'on ' . strtolower($l->type->name); });
        }

        return $out;
    }

    /** Send reminders for meetings starting within their reminder window. Returns how many meetings. */
    public function remindDue(): int
    {
        $n = 0;
        Meeting::active()->whereNull('reminded_at')->where('remind_minutes', '>', 0)
            ->where('starts_at', '>', now())->where('starts_at', '<=', now()->addDay())->get()
            ->filter(fn ($m) => $m->starts_at->lte(now()->addMinutes($m->remind_minutes)))
            ->each(function (Meeting $m) use (&$n) {
                $m->update(['reminded_at' => now()]);
                $this->tell($m, $m->attendees()->where('response', '!=', 'declined')->pluck('user_id')->all(),
                    'Starting soon: ' . $m->title, 'At ' . $m->starts_at->format('H:i') . $this->where($m) . '.');
                $n++;
            });

        return $n;
    }

    /**
     * Everything on this person's calendar between two dates, as plain arrays.
     *
     * @return Collection<int, array{type:string, title:string, start:Carbon, end:Carbon, all_day:bool, url:?string, tone:string, meta:?string}>
     */
    public function calendar(User $user, Carbon $from, Carbon $to, bool $everyone = false): Collection
    {
        $items = collect();

        Meeting::with('room', 'attendees')->active()->between($from, $to)->when(!$everyone, fn ($q) => $q->for($user))->get()
            ->each(function (Meeting $m) use ($items, $user) {
                $mine = $m->attendees->firstWhere('user_id', $user->id);
                $items->push(['type' => 'meeting', 'title' => $m->title, 'start' => $m->starts_at, 'end' => $m->ends_at, 'all_day' => false,
                    'url' => route('meetings.show', $m), 'tone' => $mine?->response === 'declined' ? 'secondary' : ($mine?->response === 'pending' ? 'warning' : 'primary'),
                    'meta' => $m->whereLabel()]);
            });

        if (Schema::hasTable('hr_holidays')) {
            Holiday::whereDate('date', '>=', $from)->whereDate('date', '<=', $to)->get()->each(fn ($h) => $items->push([
                'type' => 'holiday', 'title' => $h->name, 'start' => $h->date->copy()->startOfDay(), 'end' => $h->date->copy()->endOfDay(),
                'all_day' => true, 'url' => null, 'tone' => 'danger', 'meta' => 'Public holiday']));

            $seeAll = $user->can('View employees');
            LeaveRequest::with('employee.user', 'type')->where('status', 'approved')
                ->whereDate('starts_on', '<=', $to)->whereDate('ends_on', '>=', $from)
                ->whereHas('employee', fn ($q) => $seeAll ? $q : $q->where('user_id', $user->id)->orWhere('manager_id', $user->id))->get()
                ->each(fn ($l) => $items->push([
                    'type' => 'leave', 'title' => ($l->employee->user_id === $user->id ? 'You' : $l->employee->user->name) . ': ' . $l->type->name,
                    'start' => $l->starts_on->copy()->startOfDay(), 'end' => $l->ends_on->copy()->endOfDay(), 'all_day' => true,
                    'url' => route('hr.leave.show', $l), 'tone' => 'success', 'meta' => null]));
        }

        if (Schema::hasTable('project_tasks')) {
            Task::with('project')->where('assignee_id', $user->id)->where('status', '!=', 'done')
                ->whereDate('due_on', '>=', $from)->whereDate('due_on', '<=', $to)->get()
                ->each(fn ($t) => $items->push([
                    'type' => 'task', 'title' => $t->key_label . ' ' . $t->title, 'start' => $t->due_on->copy()->startOfDay(), 'end' => $t->due_on->copy()->endOfDay(),
                    'all_day' => true, 'url' => route('projects.tasks.show', [$t->project, $t->number]), 'tone' => 'info', 'meta' => 'Task due']));
        }

        return $items->sortBy(fn ($i) => [$i['all_day'] ? 0 : 1, $i['start']->timestamp])->values();
    }

    /** An .ics file so the meeting can be added to a phone or Google calendar. */
    public function ics(Meeting $m): string
    {
        $esc = fn ($s) => str_replace(["\\", ';', ',', "\r\n", "\n"], ['\\\\', '\;', '\,', '\n', '\n'], (string) $s);
        $utc = fn (Carbon $d) => $d->copy()->utc()->format('Ymd\THis\Z');
        $lines = [
            'BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//Qudroid Systems//Portal//EN', 'METHOD:PUBLISH', 'BEGIN:VEVENT',
            'UID:meeting-' . $m->id . '@' . parse_url(config('app.url'), PHP_URL_HOST),
            'DTSTAMP:' . $utc(now()), 'DTSTART:' . $utc($m->starts_at), 'DTEND:' . $utc($m->ends_at),
            'SUMMARY:' . $esc($m->title),
            'DESCRIPTION:' . $esc(trim(($m->agenda ?? '') . ($m->online_url ? "\n" . $m->online_url : '') . "\n" . route('meetings.show', $m))),
            'LOCATION:' . $esc($m->whereLabel()),
            'ORGANIZER;CN=' . $esc($m->organizer->name) . ':mailto:' . $m->organizer->email,
            'STATUS:' . ($m->isCancelled() ? 'CANCELLED' : 'CONFIRMED'),
        ];
        if ($m->online_url) $lines[] = 'URL:' . $m->online_url;
        $lines = array_merge($lines, ['END:VEVENT', 'END:VCALENDAR']);

        return implode("\r\n", $lines) . "\r\n";
    }

    protected function fields(array $data): array
    {
        $start = Carbon::parse($data['starts_at']);
        $end = Carbon::parse($data['ends_at']);
        $this->check($end->lte($start), 'ends_at', 'The meeting must end after it starts.');
        $this->check($start->diffInHours($end) > 24, 'ends_at', 'A meeting can last at most a day.');
        if (!empty($data['room_id'])) {
            $this->check(!Room::where('is_active', true)->whereKey($data['room_id'])->exists(), 'room_id', 'That room is not available.');
        }
        $guests = collect(preg_split('/[\r\n]+/', (string) ($data['guests'] ?? '')))->map(fn ($g) => trim($g))->filter()->take(30)
            ->map(function ($g) {
                preg_match('/[^\s<>]+@[^\s<>]+/', $g, $mm);
                $name = trim(preg_replace('/[<>]|[^\s<>]+@[^\s<>]+/', '', $g), " ,-");

                return ['name' => mb_substr($name ?: ($mm[0] ?? $g), 0, 150), 'email' => $mm[0] ?? null];
            })->values()->all();

        return [[
            'title' => mb_substr(trim($data['title']), 0, 150), 'agenda' => $data['agenda'] ?? null, 'starts_at' => $start, 'ends_at' => $end,
            'room_id' => $data['room_id'] ?? null, 'location' => !empty($data['room_id']) ? null : ($data['location'] ?? null),
            'online_url' => $data['online_url'] ?? null, 'project_id' => $data['project_id'] ?? null, 'company_id' => $data['company_id'] ?? null,
            'guests' => $guests ?: null, 'remind_minutes' => (int) ($data['remind_minutes'] ?? 15),
        ], $start, $end];
    }

    protected function roomFree(?int $roomId, Carbon $start, Carbon $end, ?int $except = null): void
    {
        if (!$roomId) return;
        $clash = Meeting::active()->where('room_id', $roomId)->between($start, $end)->when($except, fn ($q) => $q->whereKeyNot($except))->first();
        $this->check((bool) $clash, 'room_id', 'The room is booked then for "' . $clash?->title . '" (' . $clash?->starts_at->format('H:i') . '–' . $clash?->ends_at->format('H:i') . ').');
    }

    protected function invitable(array $ids, User $organizer): array
    {
        $ids = array_values(array_unique(array_map('intval', array_filter($ids))));

        return $this->people()->whereIn('id', $ids)->whereKeyNot($organizer->id)->pluck('id')->all();
    }

    protected function where(Meeting $m): string
    {
        $w = $m->whereLabel();

        return $w ? ', ' . $w : '';
    }

    protected function tell(Meeting $m, array $userIds, string $title, string $body): void
    {
        if (!$userIds) return;
        PortalNotifier::toUsers($userIds, $title, $body, route('meetings.show', $m), 'meeting');
    }

    protected function check(bool $failed, string $field, string $message): void
    {
        if ($failed) throw ValidationException::withMessages([$field => $message]);
    }
}
