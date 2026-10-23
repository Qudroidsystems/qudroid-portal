<?php

namespace App\Http\Controllers\Api\V1\Checkin;

use App\Http\Controllers\Api\V1\Concerns\RespondsWithApi;
use App\Http\Controllers\Controller;
use App\Models\Checkin\Event;
use App\Models\Checkin\Record;
use App\Services\Checkin\CheckinService;
use App\Services\Checkin\Qr;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CheckinApiController extends Controller
{
    use RespondsWithApi;

    public function __construct(protected CheckinService $checkin) {}

    protected function eventJson(Event $e): array
    {
        return [
            'id' => $e->id, 'kind' => $e->kind, 'kind_label' => $e->kindLabel(), 'title' => $e->title, 'location' => $e->location ?: $e->place?->name,
            'starts_at' => $e->starts_at?->toIso8601String(), 'ends_at' => $e->ends_at?->toIso8601String(), 'when' => $e->whenLabel(),
            'status' => $e->status, 'open_now' => $e->isOpenNow(), 'require_location' => (bool) $e->require_location,
        ];
    }

    protected function recordJson(Record $r): array
    {
        return [
            'id' => $r->id, 'event' => $r->relationLoaded('event') && $r->event ? $this->eventJson($r->event) : null, 'name' => $r->personName(),
            'status' => $r->status, 'method' => $r->method, 'checked_in_at' => $r->checked_in_at?->toIso8601String(),
            'checked_out_at' => $r->checked_out_at?->toIso8601String(),
        ];
    }

    /** My badge QR (as text the app renders, plus an SVG), today's check-ins I can do, and the events I run. */
    public function me(Request $request)
    {
        $me = $request->user();
        $today = Event::with('place')->where('status', '!=', 'cancelled')->where('starts_at', '<=', now()->addHours(12))
            ->where(fn ($q) => $q->where('ends_at', '>=', now())->orWhere(fn ($w) => $w->whereNull('ends_at')->where('starts_at', '>=', now()->subHours(12))))
            ->orderBy('starts_at')->limit(60)->get();

        return $this->ok([
            'badge' => ['payload' => CheckinService::badgePayload($me), 'svg' => Qr::svg(CheckinService::badgePayload($me), 320)],
            'today' => $today->filter(fn ($e) => $this->checkin->refusal($e, $me) === null)->map(fn ($e) => $this->eventJson($e))->values(),
            'running' => $today->filter(fn ($e) => $this->checkin->canRun($me, $e))->map(fn ($e) => $this->eventJson($e))->values(),
        ]);
    }

    public function history(Request $request)
    {
        return $this->paged(Record::with('event.place')->where('user_id', $request->user()->id)->latest('checked_in_at')->paginate(30),
            fn ($r) => $this->recordJson($r));
    }

    /** Self check-in from the door QR. The app sends the scanned URL (…/c/{event}/{token}) and, when it can, the phone's location. */
    public function door(Request $request)
    {
        $d = $request->validate(['url' => ['required', 'string', 'max:500'], 'lat' => ['nullable', 'numeric', 'between:-90,90'], 'lng' => ['nullable', 'numeric', 'between:-180,180']]);
        if (!preg_match('~/c/(\d+)/([a-f0-9]{12})(?:[/?#]|$)~', $d['url'], $m)) return $this->fail('That isn\'t a Qudroid check-in code.', 422, 'not_checkin');
        $event = Event::find((int) $m[1]);
        if (!$event || !$this->checkin->validScreenToken($event, $m[2])) return $this->fail('That code has expired. Scan the QR on the screen again.', 410, 'expired');
        try {
            $r = $this->checkin->checkIn($event, $request->user(), null, 'screen', null, ['lat' => $d['lat'] ?? null, 'lng' => $d['lng'] ?? null]);
        } catch (ValidationException $e) {
            $errors = $e->errors();

            return $this->fail(collect($errors)->flatten()->first(), 422, array_key_exists('location', $errors) ? 'location' : 'refused');
        }

        return $this->ok(['state' => $r['state'], 'record' => $this->recordJson($r['record']->load('event.place')), 'event' => $this->eventJson($event)], $r['message']);
    }

    /** Organisers: scan a staff/student badge or a guest ticket. */
    public function scan(Request $request, Event $event)
    {
        abort_unless($this->checkin->canRun($request->user(), $event), 403, 'You can\'t run check-in for this.');
        $d = $request->validate(['code' => ['required', 'string', 'max:200']]);
        $who = $this->checkin->resolve($d['code'], $event);
        if (!$who) return $this->fail('Not a Qudroid badge or a ticket for this event.', 422, 'unknown_code');
        try {
            $r = $this->checkin->checkIn($event, $who['user'] ?? null, $who['guest'] ?? null, 'badge', $request->user());
        } catch (ValidationException $e) {
            return $this->fail(collect($e->errors())->flatten()->first(), 422, 'refused', ['name' => ($who['user'] ?? null)?->name ?? ($who['guest'] ?? null)?->name]);
        }
        $rec = $r['record'];

        return $this->ok(['state' => $r['state'], 'record' => $this->recordJson($rec), 'photo' => $rec->user?->avatar_url,
            'org' => $rec->guest?->organisation, 'stats' => $this->checkin->stats($event)], $r['message']);
    }

    public function event(Request $request, Event $event)
    {
        abort_unless($this->checkin->canRun($request->user(), $event), 403, 'You can\'t run check-in for this.');
        $recent = $event->records()->with(['user', 'guest'])->latest('checked_in_at')->limit(20)->get();

        return $this->ok($this->eventJson($event) + ['stats' => $this->checkin->stats($event), 'recent' => $recent->map(fn ($r) => $this->recordJson($r))]);
    }
}
