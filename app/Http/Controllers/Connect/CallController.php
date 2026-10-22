<?php

namespace App\Http\Controllers\Connect;

use App\Http\Controllers\Controller;
use App\Models\Connect\Call;
use App\Models\Connect\Conversation;
use App\Services\Connect\CallService;
use Illuminate\Http\Request;

/**
 * Voice and video calls. The call page opens in its own tab; it joins,
 * then checks in every second to swap set-up messages with the others.
 */
class CallController extends Controller
{
    public function __construct(protected CallService $calls) {}

    public function index(Request $request)
    {
        return view('modules.connect.calls', ['calls' => $this->calls->history($request->user()), 'me' => $request->user()]);
    }

    public function start(Request $request, Conversation $conversation)
    {
        $data = $request->validate(['kind' => ['nullable', 'in:audio,video']]);
        $call = $this->calls->start($conversation, $request->user(), $data['kind'] ?? 'video');

        return $request->expectsJson()
            ? response()->json(['id' => $call->id, 'url' => route('connect.calls.show', $call)])
            : redirect()->route('connect.calls.show', $call);
    }

    public function show(Request $request, Call $call)
    {
        $me = $request->user();
        abort_unless($this->calls->canJoin($call, $me), 403);
        $this->calls->sweep($call);

        return view('modules.connect.call', [
            'call' => $call->refresh()->load('conversation'), 'me' => $me, 'title' => $call->conversation->titleFor($me),
            'iceServers' => $this->calls->iceServers(),
        ]);
    }

    public function join(Request $request, Call $call)
    {
        $here = $this->calls->join($call, $request->user());

        return response()->json(['ok' => true, 'others' => $here->pluck('user_id')]);
    }

    public function poll(Request $request, Call $call)
    {
        abort_unless($this->calls->canJoin($call, $request->user()), 403);
        $state = ['mic' => $request->boolean('mic'), 'cam' => $request->boolean('cam'), 'screen' => $request->boolean('screen')];

        return response()->json($this->calls->checkIn($call, $request->user(), max(0, $request->integer('after')), $state));
    }

    public function signal(Request $request, Call $call)
    {
        $data = $request->validate(['to' => ['required', 'integer'], 'type' => ['required', 'string'], 'payload' => ['nullable', 'string']]);
        $this->calls->signal($call, $request->user(), $data['to'], $data['type'], $data['payload'] ?? null);

        return response()->json(['ok' => true]);
    }

    public function leave(Request $request, Call $call)
    {
        $this->calls->leave($call, $request->user());

        return $request->expectsJson() ? response()->json(['ok' => true]) : redirect()->route('connect.show', $call->conversation_id);
    }

    public function decline(Request $request, Call $call)
    {
        $this->calls->decline($call, $request->user());

        return $request->expectsJson() ? response()->json(['ok' => true]) : back();
    }

    /** Calls ringing for me (checked from every portal page). */
    public function ringing(Request $request)
    {
        return response()->json(['calls' => $this->calls->ringing($request->user())->map(fn ($c) => [
            'id' => $c->id, 'kind' => $c->kind, 'from' => $c->starter?->name, 'avatar' => $c->starter?->avatar_url,
            'where' => $c->conversation->isDirect() ? null : $c->conversation->titleFor($request->user()),
            'url' => route('connect.calls.show', $c), 'decline' => route('connect.calls.decline', $c),
        ])->values()]);
    }
}
