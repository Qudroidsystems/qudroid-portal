<?php

namespace App\Http\Controllers\Api\V1\Connect;

use App\Http\Controllers\Api\V1\ApiController;
use App\Models\Connect\Call;
use App\Models\Connect\Conversation;
use App\Models\Meetings\Meeting;
use App\Services\Connect\CallRecordingService;
use App\Services\Connect\CallService;
use App\Services\Connect\ConnectService;
use App\Services\Meetings\MeetingService;
use Illuminate\Http\Request;

/**
 * Voice and video calls for the mobile app. Same mesh as the web call page:
 * start or join, then call `signals` about once a second while in the call;
 * it keeps you in, returns set-up messages for you and who is in the call.
 * Incoming calls arrive as a data push {type: "call", call_id, from, video}
 * and a {type: "call_end"} push when the call ends.
 */
class CallApiController extends ApiController
{
    public function __construct(protected CallService $calls, protected ConnectService $connect) {}

    public function iceServers()
    {
        return $this->ok(['ice_servers' => $this->calls->iceServers(), 'max_people' => (int) config('qudroid.calls.max_people', 8)]);
    }

    /** Start (or join the running) call in a chat or a meeting: `{conversation_id | meeting_id, video}`. */
    public function start(Request $request)
    {
        $data = $request->validate(['conversation_id' => ['required_without:meeting_id', 'nullable', 'integer'], 'meeting_id' => ['nullable', 'integer'],
            'video' => ['nullable', 'boolean'], 'huddle' => ['nullable', 'boolean']]);
        $me = $request->user();
        if (!empty($data['meeting_id'])) {
            $meeting = Meeting::find($data['meeting_id']);
            if (!$meeting) return $this->fail('Meeting not found.', 404, 'not_found');
            $call = app(MeetingService::class)->call($meeting, $me);
        } else {
            $c = Conversation::find($data['conversation_id']);
            if (!$c || !$this->connect->canView($c, $me)) return $this->fail('Conversation not found.', 404, 'not_found');
            $call = !empty($data['huddle']) ? $this->calls->huddle($c, $me) : $this->calls->start($c, $me, ($data['video'] ?? true) ? 'video' : 'audio');
        }

        return $this->ok($this->call($call->fresh(), $request), 'Call started.', 201);
    }

    public function ringing(Request $request)
    {
        return $this->ok($this->calls->ringing($request->user())->map(fn ($c) => $this->call($c, $request))->values());
    }

    public function show(Request $request, Call $call)
    {
        if (!$this->calls->canJoin($call, $request->user())) return $this->fail('Call not found.', 404, 'not_found');
        $this->calls->sweep($call);

        return $this->ok($this->call($call->refresh(), $request));
    }

    public function join(Request $request, Call $call)
    {
        if (!$this->calls->canJoin($call, $request->user())) return $this->fail('Call not found.', 404, 'not_found');
        $here = $this->calls->join($call, $request->user());

        return $this->ok($this->call($call->refresh(), $request) + ['others' => $here->pluck('user_id')->values()], 'Joined.');
    }

    public function leave(Request $request, Call $call)
    {
        $this->calls->leave($call, $request->user());

        return $this->ok(null, 'Left.');
    }

    public function decline(Request $request, Call $call)
    {
        $this->calls->decline($call, $request->user());

        return $this->ok(null, 'Declined.');
    }

    public function signal(Request $request, Call $call)
    {
        $data = $request->validate(['to_user_id' => ['required', 'integer'], 'type' => ['required', 'in:offer,answer,ice,bye'], 'payload' => ['nullable']]);
        $payload = $data['payload'] ?? null;
        $this->calls->signal($call, $request->user(), (int) $data['to_user_id'], $data['type'], is_string($payload) || $payload === null ? $payload : json_encode($payload));

        return $this->ok(null);
    }

    /**
     * The check-in: `?since=` (last signal id you handled), `mic`, `cam`, `screen` (1/0), and
     * `captions_since` for the transcript. Returns signals for you, who is in, recording and captions.
     */
    public function signals(Request $request, Call $call)
    {
        if (!$this->calls->canJoin($call, $request->user())) return $this->fail('Call not found.', 404, 'not_found');
        $state = ['mic' => $request->boolean('mic', true), 'cam' => $request->boolean('cam'), 'screen' => $request->boolean('screen')];
        $out = $this->calls->checkIn($call, $request->user(), max(0, $request->integer('since')), $state);
        $recordings = app(CallRecordingService::class);
        if (!$out['ended']) $recordings->closeOrphans($call);
        $out += $recordings->state($call->refresh(), $request->user(), max(0, $request->integer('captions_since')));
        $out['signals'] = collect($out['signals'])->map(fn ($s) => ['id' => $s->id, 'from_user_id' => $s->from, 'type' => $s->type, 'payload' => $s->payload])->values();

        return $this->ok($out);
    }

    protected function call(Call $call, Request $request): array
    {
        $me = $request->user();
        $c = $call->conversation;

        return [
            'id' => $call->id, 'kind' => $call->kind, 'video' => $call->kind === 'video', 'huddle' => (bool) $call->is_huddle,
            'conversation_id' => $c->id, 'meeting_id' => $c->meeting_id, 'title' => $c->titleFor($me), 'group' => !$c->isDirect(),
            'from' => $call->starter ? ['id' => $call->starter->id, 'name' => $call->starter->name, 'avatar' => $call->starter->avatar_url] : null,
            'live' => $call->ended_at === null, 'started_at' => $call->started_at->toIso8601String(), 'ended_at' => $call->ended_at?->toIso8601String(),
            'duration' => $call->durationLabel(),
            'people' => $call->ended_at ? [] : $this->calls->present($call)->map(fn ($p) => ['id' => $p->user_id, 'name' => $p->user?->name, 'avatar' => $p->user?->avatar_url])->values(),
            'web_url' => route('connect.calls.show', $call),
        ];
    }
}
