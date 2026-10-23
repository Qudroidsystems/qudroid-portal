<?php

namespace App\Http\Controllers\Api\V1\Meetings;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Controllers\Meetings\MeetingController;
use App\Models\Connect\Conversation;
use App\Models\Meetings\Meeting;
use App\Models\Meetings\Room;
use App\Services\Meetings\MeetingService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/** Meetings and the personal calendar for the mobile app. */
class MeetingApiController extends ApiController
{
    public function __construct(protected MeetingService $meetings) {}

    /** Upcoming meetings (`?past=1` for earlier ones). */
    public function index(Request $request)
    {
        $me = $request->user();
        $page = Meeting::with('room', 'organizer', 'attendees')->for($me)
            ->when($request->boolean('past'), fn ($q) => $q->where('ends_at', '<', now())->orderByDesc('starts_at'),
                fn ($q) => $q->active()->where('ends_at', '>=', now())->orderBy('starts_at'))
            ->paginate(30);

        return $this->paged($page, fn ($m) => $this->meeting($m, $me));
    }

    /** `?from=2026-11-01&to=2026-11-30` (at most 62 days). */
    public function calendar(Request $request)
    {
        $d = $request->validate(['from' => ['required', 'date'], 'to' => ['required', 'date', 'after_or_equal:from']]);
        $from = Carbon::parse($d['from'])->startOfDay();
        $to = Carbon::parse($d['to'])->endOfDay();
        if ($from->diffInDays($to) > 62) return $this->fail('Ask for at most 62 days at a time.');

        return $this->ok($this->meetings->calendar($request->user(), $from, $to)->map(fn ($i) => [
            'type' => $i['type'], 'title' => $i['title'], 'start' => $i['start']->toIso8601String(), 'end' => $i['end']->toIso8601String(),
            'all_day' => $i['all_day'], 'url' => $i['url'], 'meta' => $i['meta'],
        ])->values());
    }

    public function show(Request $request, Meeting $meeting)
    {
        $meeting->load('room', 'organizer', 'attendees.user', 'project');
        if (!$this->meetings->canView($meeting, $request->user())) return $this->fail('Not found.', 404);

        return $this->ok($this->meeting($meeting, $request->user(), true));
    }

    /** Schedule a meeting: the web form's fields, with `attendees[]` user ids (`GET meetings/people`). */
    public function store(Request $request)
    {
        $data = $request->validate(MeetingController::rules());
        $meeting = $this->meetings->schedule($data, $data['attendees'] ?? [], $request->user());

        return $this->ok($this->meeting($meeting->fresh(['room', 'organizer', 'attendees.user', 'project']), $request->user(), true), 'Meeting scheduled and invitations sent.', 201);
    }

    public function update(Request $request, Meeting $meeting)
    {
        if (!$this->meetings->canManage($meeting, $request->user())) return $this->fail('Only the organiser can change this meeting.', 403, 'forbidden');
        $data = $request->validate(MeetingController::rules());
        $this->meetings->update($meeting, $data, $data['attendees'] ?? [], $request->user());

        return $this->ok($this->meeting($meeting->fresh(['room', 'organizer', 'attendees.user', 'project']), $request->user(), true), 'Meeting saved.');
    }

    /** Cancels the meeting (it stays in the list, marked cancelled); `reason` is optional. */
    public function destroy(Request $request, Meeting $meeting)
    {
        if (!$this->meetings->canManage($meeting, $request->user())) return $this->fail('Only the organiser can cancel this meeting.', 403, 'forbidden');
        $this->meetings->cancel($meeting, $request->user(), $request->input('reason'));

        return $this->ok(null, 'Meeting cancelled. Everyone invited has been told.');
    }

    /** People who can be invited, and the rooms. */
    public function people(Request $request)
    {
        return $this->ok([
            'people' => $this->meetings->people()->whereKeyNot($request->user()->id)->get(['id', 'name', 'job_title'])->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'job_title' => $u->job_title, 'avatar' => $u->avatar_url])->values(),
            'rooms' => Room::where('is_active', true)->orderBy('name')->get(['id', 'name', 'capacity']),
        ]);
    }

    public function respond(Request $request, Meeting $meeting)
    {
        $d = $request->validate(['response' => ['required', Rule::in(['accepted', 'tentative', 'declined'])], 'note' => ['nullable', 'string', 'max:255']]);
        $this->meetings->respond($meeting, $request->user(), $d['response'], $d['note'] ?? null);

        return $this->ok($this->meeting($meeting->fresh(['room', 'organizer', 'attendees']), $request->user()), 'Answer saved.');
    }

    protected function meeting(Meeting $m, $me, bool $full = false): array
    {
        $out = [
            'id' => $m->id, 'title' => $m->title, 'starts_at' => $m->starts_at->toIso8601String(), 'ends_at' => $m->ends_at->toIso8601String(),
            'where' => $m->whereLabel(), 'online_url' => $m->online_url, 'status' => $m->status,
            'organizer' => ['id' => $m->organizer_id, 'name' => $m->organizer?->name],
            'my_response' => $m->attendees->firstWhere('user_id', $me->id)?->response,
            'going' => $m->attendees->where('response', 'accepted')->count(), 'invited' => $m->attendees->count(),
        ];
        if ($full) {
            $out += [
                'agenda' => $m->agenda, 'minutes' => $m->minutes, 'cancel_reason' => $m->cancel_reason,
                'project' => $m->project ? ['code' => $m->project->code, 'name' => $m->project->name] : null,
                'attendees' => $m->attendees->map(fn ($a) => ['id' => $a->user_id, 'name' => $a->user?->name, 'avatar' => $a->user?->avatar_url, 'response' => $a->response])->values(),
                'guests' => $m->guests ?? [],
                'can_manage' => $this->meetings->canManage($m, $me),
                'join' => $this->joinInfo($m, $me),
            ];
        }

        return $out;
    }

    /** How to join in the portal: the meeting chat and its live call, if any. */
    protected function joinInfo(Meeting $m, $me): array
    {
        $chat = Conversation::where('meeting_id', $m->id)->first();
        $live = $chat ? app(\App\Services\Connect\CallService::class)->live($chat) : null;

        return [
            'conversation_id' => $chat?->id,
            'call_id' => $live?->id,
            // Start or join with POST calls {meeting_id}; it creates the chat when needed.
            'can_call' => !$m->isCancelled() && $me->can('Use connect'),
        ];
    }
}
