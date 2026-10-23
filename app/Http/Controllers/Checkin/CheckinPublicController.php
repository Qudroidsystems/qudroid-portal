<?php

namespace App\Http\Controllers\Checkin;

use App\Http\Controllers\Controller;
use App\Models\Checkin\Event;
use App\Models\Checkin\Guest;
use App\Services\Checkin\CheckinService;
use App\Services\Checkin\Qr;
use Illuminate\Http\Request;

/** Pages that work without signing in: the door QR's link, event sign-up and tickets. */
class CheckinPublicController extends Controller
{
    public function __construct(protected CheckinService $checkin) {}

    /**
     * Where the door QR points. The code is checked now and remembered for ten minutes,
     * so signing in first doesn't make it expire.
     */
    public function door(Request $request, Event $event, string $token)
    {
        if (!$event->allow_screen || !$this->checkin->validScreenToken($event, $token)) {
            return response()->view('modules.checkin.expired', ['event' => $event], 410);
        }
        $request->session()->put('checkin_pass', ['event' => $event->id, 'until' => now()->getTimestamp() + 600]);

        return redirect()->route('checkin.go', $event);
    }

    protected function bySlug(string $slug): Event
    {
        $event = ctype_digit($slug) ? Event::find((int) $slug) : Event::where('slug', $slug)->first();
        abort_unless($event && $event->kind === 'event' && $event->status !== 'cancelled', 404);

        return $event;
    }

    public function register(string $slug)
    {
        $event = $this->bySlug($slug);
        abort_unless($event->public_registration, 404);
        $taken = $event->guests()->whereNull('cancelled_at')->count();

        return view('modules.checkin.register', ['event' => $event, 'full' => $event->capacity && $taken >= $event->capacity,
            'closed' => $event->status !== 'open' || ($event->registration_closes_at && now()->gt($event->registration_closes_at)) || now()->gt($event->closesAt())]);
    }

    public function store(Request $request, string $slug)
    {
        $event = $this->bySlug($slug);
        abort_unless($event->public_registration, 404);
        $d = $request->validate(['name' => ['required', 'string', 'max:150'], 'email' => ['required', 'email', 'max:191'], 'phone' => ['nullable', 'string', 'max:40'],
            'organisation' => ['nullable', 'string', 'max:150'], 'website' => ['prohibited']]);   // website: a trap for bots
        $guest = $this->checkin->register($event, $d, $request->user());

        return redirect()->route('checkin.public.ticket', [$event->slug, $guest->code])->with('success', 'You\'re registered. We\'ve e-mailed your ticket too.');
    }

    public function ticket(string $slug, string $code)
    {
        $event = $this->bySlug($slug);
        $guest = Guest::where('event_id', $event->id)->where('code', strtoupper($code))->firstOrFail();

        return view('modules.checkin.ticket', ['event' => $event, 'guest' => $guest, 'qr' => Qr::svg(CheckinService::guestPayload($guest), 260)]);
    }
}
