<?php

namespace App\Http\Controllers\Api\V1\Meetings;

use App\Http\Controllers\Api\V1\ApiController;
use App\Models\Meetings\Meeting;
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
            ];
        }

        return $out;
    }
}
