<?php

namespace App\Http\Controllers\Checkin;

use App\Http\Controllers\Controller;
use App\Models\Academy\ClassSession;
use App\Models\Checkin\Event;
use App\Models\Checkin\Guest;
use App\Models\Checkin\Place;
use App\Models\Checkin\Record;
use App\Models\Meetings\Meeting;
use App\Models\User;
use App\Services\Checkin\CheckinService;
use App\Services\Checkin\Qr;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CheckinController extends Controller
{
    public function __construct(protected CheckinService $checkin) {}

    protected function run(Request $request, Event $event): Event
    {
        abort_unless($this->checkin->canRun($request->user(), $event), 403, 'You can\'t run check-in for this.');

        return $event;
    }

    /** My QR, what's on today, and my recent check-ins. Organisers also see the events they run. */
    public function index(Request $request)
    {
        $me = $request->user();
        $today = Event::where('status', '!=', 'cancelled')->where('starts_at', '<=', now()->addHours(12))
            ->where(fn ($q) => $q->where('ends_at', '>=', now())->orWhere(fn ($w) => $w->whereNull('ends_at')->where('starts_at', '>=', now()->subHours(12))))
            ->orderBy('starts_at')->limit(60)->get()
            ->filter(fn ($e) => $this->checkin->canRun($me, $e) || $this->checkin->refusal($e, $me) === null)->values();
        $running = $me->can('Run check-in')
            ? Event::where('kind', 'event')->where(fn ($q) => $q->where('created_by', $me->id)->orWhereHas('people', fn ($p) => $p->where('users.id', $me->id)->where('checkin_event_user.role', 'host')))
                ->latest('starts_at')->limit(30)->get()
            : collect();
        if ($this->checkin->isAdmin($me)) $running = Event::where('kind', 'event')->latest('starts_at')->limit(30)->get();
        $mine = Record::with('event.place')->where('user_id', $me->id)->latest('checked_in_at')->limit(15)->get();

        return view('modules.checkin.index', [
            'today' => $today, 'running' => $running, 'mine' => $mine,
            'badge' => Qr::svg(CheckinService::badgePayload($me), 220),
            'places' => Place::where('is_active', true)->orderBy('name')->get(),
            'canPlaces' => $me->can('Manage check-in places') || $me->can('Manage employees'),
        ]);
    }

    /** Full-screen personal QR, to hold up to a scanner. */
    public function badge(Request $request)
    {
        return view('modules.checkin.badge', ['qr' => Qr::svg(CheckinService::badgePayload($request->user()), 320), 'user' => $request->user()]);
    }

    // ── Events ──────────────────────────────────────────────────────

    public function create()
    {
        return view('modules.checkin.form', ['event' => new Event(['audience' => 'registered', 'opens_minutes' => 60, 'late_minutes' => 15, 'radius_m' => 200,
            'allow_screen' => true, 'allow_badge' => true, 'starts_at' => now()->addDay()->setTime(9, 0), 'ends_at' => now()->addDay()->setTime(17, 0)])]);
    }

    protected function rules(?Event $event = null): array
    {
        return [
            'title' => ['required', 'string', 'max:200'], 'description' => ['nullable', 'string', 'max:5000'],
            'location' => ['nullable', 'string', 'max:255'], 'lat' => ['nullable', 'numeric', 'between:-90,90', 'required_with:lng'], 'lng' => ['nullable', 'numeric', 'between:-180,180', 'required_with:lat'],
            'radius_m' => ['nullable', 'integer', 'between:20,5000'], 'require_location' => ['nullable', 'boolean'],
            'starts_at' => ['required', 'date'], 'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'opens_minutes' => ['nullable', 'integer', 'between:0,1440'], 'late_minutes' => ['nullable', 'integer', 'between:0,600'],
            'audience' => ['required', Rule::in(array_keys(Event::AUDIENCES))],
            'allow_screen' => ['nullable', 'boolean'], 'allow_badge' => ['nullable', 'boolean'], 'public_registration' => ['nullable', 'boolean'],
            'slug' => ['nullable', 'string', 'max:80', 'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/', Rule::unique('checkin_events', 'slug')->ignore($event?->id)],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:1000000'], 'registration_closes_at' => ['nullable', 'date'],
        ];
    }

    protected function fill(Event $event, array $d, Request $request): Event
    {
        $event->fill([
            'title' => $d['title'], 'description' => $d['description'] ?? null, 'location' => $d['location'] ?? null,
            'lat' => $d['lat'] ?? null, 'lng' => $d['lng'] ?? null, 'radius_m' => $d['radius_m'] ?? 200, 'require_location' => $request->boolean('require_location') && isset($d['lat']),
            'starts_at' => $d['starts_at'], 'ends_at' => $d['ends_at'] ?? null, 'opens_minutes' => $d['opens_minutes'] ?? 60, 'late_minutes' => $d['late_minutes'] ?? 15,
            'audience' => $d['audience'], 'allow_screen' => $request->boolean('allow_screen'), 'allow_badge' => $request->boolean('allow_badge'),
            'public_registration' => $request->boolean('public_registration'), 'capacity' => $d['capacity'] ?? null, 'registration_closes_at' => $d['registration_closes_at'] ?? null,
        ]);
        if (!$event->allow_screen && !$event->allow_badge) throw ValidationException::withMessages(['allow_screen' => 'Turn on at least one way to check in.']);
        if ($event->public_registration) $event->slug = $d['slug'] ?? $event->slug ?? $this->slugFor($event->title);
        elseif (array_key_exists('slug', $d)) $event->slug = $d['slug'] ?: $event->slug;

        return $event;
    }

    protected function slugFor(string $title): string
    {
        $base = Str::slug(Str::limit($title, 50, '')) ?: 'event';
        $slug = $base;
        for ($i = 2; Event::where('slug', $slug)->exists(); $i++) $slug = $base . '-' . $i;

        return $slug;
    }

    public function store(Request $request)
    {
        $d = $request->validate($this->rules());
        $event = $this->fill(new Event(['kind' => 'event', 'secret' => Str::random(40), 'status' => 'open', 'created_by' => $request->user()->id]), $d, $request);
        $event->save();

        return redirect()->route('checkin.events.show', $event)->with('success', 'Event created. Open the door screen or the scanner when people arrive.');
    }

    public function edit(Request $request, Event $event)
    {
        $this->run($request, $event);
        abort_unless($event->kind === 'event', 404);

        return view('modules.checkin.form', ['event' => $event]);
    }

    public function update(Request $request, Event $event)
    {
        $this->run($request, $event);
        if ($event->kind !== 'event') {
            // Classes, meetings and staff days take their times from elsewhere; only the door rules change here.
            $d = $request->validate(['late_minutes' => ['required', 'integer', 'between:0,600'], 'opens_minutes' => ['required', 'integer', 'between:0,1440'],
                'require_location' => ['nullable', 'boolean'], 'lat' => ['nullable', 'numeric', 'between:-90,90'], 'lng' => ['nullable', 'numeric', 'between:-180,180'], 'radius_m' => ['nullable', 'integer', 'between:20,5000']]);
            $event->update(['late_minutes' => $d['late_minutes'], 'opens_minutes' => $d['opens_minutes'], 'lat' => $d['lat'] ?? $event->lat, 'lng' => $d['lng'] ?? $event->lng,
                'radius_m' => $d['radius_m'] ?? $event->radius_m, 'require_location' => $request->boolean('require_location') && ($d['lat'] ?? $event->lat) !== null]);

            return back()->with('success', 'Check-in settings saved.');
        }
        $this->fill($event, $request->validate($this->rules($event)), $request)->save();

        return redirect()->route('checkin.events.show', $event)->with('success', 'Event saved.');
    }

    public function show(Request $request, Event $event)
    {
        $this->run($request, $event);
        $records = $event->records()->with(['user:id,name,email,avatar', 'guest', 'scanner:id,name'])->orderByDesc('checked_in_at')->get();
        $expected = $this->checkin->expected($event);
        $inIds = $records->pluck('user_id')->filter()->all();
        $missing = $expected ? $expected->reject(fn ($u) => in_array($u->id, $inIds, true))->values() : collect();
        $guests = $event->guests()->with('record')->orderBy('name')->get();

        return view('modules.checkin.show', [
            'event' => $event, 'records' => $records, 'missing' => $missing, 'guests' => $guests, 'stats' => $this->checkin->stats($event),
            'people' => $event->kind === 'event' ? $event->people()->orderBy('name')->get() : collect(),
            'users' => $event->kind === 'event' ? User::where('is_disabled', false)->orderBy('name')->get(['id', 'name', 'email']) : collect(),
        ]);
    }

    public function close(Request $request, Event $event)
    {
        $this->run($request, $event);
        $n = $this->checkin->close($event, $request->boolean('mark_absent', true));

        return back()->with('success', 'Check-in closed.' . ($n ? " {$n} " . Str::plural('student', $n) . ' who did not come were marked absent.' : ''));
    }

    public function reopen(Request $request, Event $event)
    {
        $this->run($request, $event);
        $event->update(['status' => 'open']);

        return back()->with('success', 'Check-in is open again.');
    }

    public function destroy(Request $request, Event $event)
    {
        $this->run($request, $event);
        abort_unless($event->kind === 'event', 422, 'Only events can be deleted. Close the others instead.');
        if ($event->records()->exists() || $event->guests()->exists()) {
            $event->update(['status' => 'cancelled']);

            return redirect()->route('checkin.index')->with('success', 'Event cancelled. Its records are kept.');
        }
        $event->delete();

        return redirect()->route('checkin.index')->with('success', 'Event deleted.');
    }

    // ── The door ────────────────────────────────────────────────────

    /** The rotating QR for a TV or laptop at the entrance. */
    public function screen(Request $request, Event $event)
    {
        $this->run($request, $event);
        abort_unless($event->allow_screen, 404, 'The door QR is off for this event.');

        return view('modules.checkin.screen', ['event' => $event]);
    }

    public function screenData(Request $request, Event $event)
    {
        $this->run($request, $event);
        $token = $this->checkin->screenToken($event);
        $url = route('checkin.door', [$event, $token]);
        $recent = $event->records()->with(['user:id,name,avatar', 'guest:id,name'])->latest('updated_at')->limit(6)->get()
            ->map(fn ($r) => ['name' => $r->personName(), 'status' => $r->status, 'out' => (bool) $r->checked_out_at, 'at' => ($r->checked_out_at ?? $r->checked_in_at)->format('g:i A'),
                'photo' => $r->user?->avatar_url]);

        return response()->json(['qr' => Qr::svg($url, 520), 'url' => $url, 'seconds' => $this->checkin->secondsLeft(), 'open' => $event->isOpenNow(),
            'opens_at' => $event->opensAt()->format('g:i A'), 'status' => $event->status, 'stats' => $this->checkin->stats($event), 'recent' => $recent])
            ->header('Cache-Control', 'no-store');
    }

    /** Camera scanner for badges and tickets. */
    public function scanner(Request $request, Event $event)
    {
        $this->run($request, $event);
        abort_unless($event->allow_badge, 404, 'Badge scanning is off for this event.');

        return view('modules.checkin.scanner', ['event' => $event, 'stats' => $this->checkin->stats($event)]);
    }

    public function scan(Request $request, Event $event)
    {
        $this->run($request, $event);
        $data = $request->validate(['code' => ['required', 'string', 'max:200']]);
        $who = $this->checkin->resolve($data['code'], $event);
        if (!$who) return response()->json(['ok' => false, 'message' => 'Not a Qudroid badge or a ticket for this event.'], 422);
        try {
            $r = $this->checkin->checkIn($event, $who['user'] ?? null, $who['guest'] ?? null, 'badge', $request->user());
        } catch (ValidationException $e) {
            $name = ($who['user'] ?? null)?->name ?? ($who['guest'] ?? null)?->name;

            return response()->json(['ok' => false, 'name' => $name, 'message' => collect($e->errors())->flatten()->first()], 422);
        }
        $rec = $r['record'];

        return response()->json(['ok' => true, 'state' => $r['state'], 'message' => $r['message'], 'name' => $rec->personName(), 'status' => $rec->status,
            'photo' => $rec->user?->avatar_url, 'org' => $rec->guest?->organisation, 'stats' => $this->checkin->stats($event)]);
    }

    /** Mark someone in by hand (no phone, flat battery…), or change present/late. */
    public function mark(Request $request, Event $event)
    {
        $this->run($request, $event);
        $d = $request->validate(['user_id' => ['nullable', 'integer', 'exists:users,id'], 'guest_id' => ['nullable', 'integer'], 'status' => ['required', Rule::in(['present', 'late'])]]);
        $guest = !empty($d['guest_id']) ? Guest::where('event_id', $event->id)->findOrFail($d['guest_id']) : null;
        $user = !$guest && !empty($d['user_id']) ? User::find($d['user_id']) : null;
        $r = $this->checkin->checkIn($event, $user, $guest, 'manual', $request->user(), ['status' => $d['status']]);

        return $request->expectsJson() ? response()->json(['ok' => true, 'message' => $r['message']]) : back()->with('success', $r['record']->personName() . ' marked ' . $d['status'] . '.');
    }

    public function undo(Request $request, Record $record)
    {
        $this->run($request, $record->event);
        $name = $record->personName();
        $this->checkin->undo($record);

        return back()->with('success', $name . '\'s check-in removed.');
    }

    public function export(Request $request, Event $event)
    {
        $this->run($request, $event);
        $records = $event->records()->with(['user:id,name,email', 'guest', 'scanner:id,name'])->orderBy('checked_in_at')->get();
        $missing = ($this->checkin->expected($event) ?? collect())->reject(fn ($u) => $records->pluck('user_id')->contains($u->id));
        $name = Str::slug($event->title) . '-attendance.csv';

        return response()->streamDownload(function () use ($records, $missing, $event) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Name', 'E-mail', 'Organisation', 'Status', 'Checked in', 'Checked out', 'How', 'Scanned by']);
            foreach ($records as $r) {
                fputcsv($out, [$r->personName(), $r->user?->email ?? $r->guest?->email, $r->guest?->organisation, ucfirst($r->status),
                    $r->checked_in_at->format('Y-m-d H:i'), $r->checked_out_at?->format('Y-m-d H:i'), Record::METHODS[$r->method] ?? $r->method, $r->scanner?->name]);
            }
            foreach ($missing as $u) fputcsv($out, [$u->name, $u->email, null, 'Absent', null, null, null, null]);
            foreach ($event->guests()->whereNull('cancelled_at')->whereDoesntHave('record')->get() as $g) fputcsv($out, [$g->name, $g->email, $g->organisation, 'Registered, did not come', null, null, null, null]);
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv']);
    }

    // ── People and guests on standalone events ──────────────────────

    public function addPeople(Request $request, Event $event)
    {
        $this->run($request, $event);
        $d = $request->validate(['users' => ['required', 'array', 'max:2000'], 'users.*' => ['integer', 'exists:users,id'], 'role' => ['nullable', Rule::in(['guest', 'host'])]]);
        foreach (array_unique($d['users']) as $id) $event->people()->syncWithoutDetaching([$id => ['role' => $d['role'] ?? 'guest']]);

        return back()->with('success', count(array_unique($d['users'])) . ' added.');
    }

    public function removePerson(Request $request, Event $event, User $user)
    {
        $this->run($request, $event);
        $event->people()->detach($user->id);

        return back()->with('success', $user->name . ' removed.');
    }

    public function addGuest(Request $request, Event $event)
    {
        $this->run($request, $event);
        $d = $request->validate(['name' => ['required', 'string', 'max:150'], 'email' => ['nullable', 'email', 'max:191'], 'phone' => ['nullable', 'string', 'max:40'],
            'organisation' => ['nullable', 'string', 'max:150']]);
        $g = $this->checkin->register($event, $d, $request->user());

        return back()->with('success', $g->name . ' registered (ticket ' . $g->code . ')' . ($g->email ? ' and the ticket was e-mailed.' : '.'));
    }

    public function cancelGuest(Request $request, Event $event, Guest $guest)
    {
        $this->run($request, $event);
        abort_unless($guest->event_id === $event->id, 404);
        $guest->update(['cancelled_at' => now()]);

        return back()->with('success', $guest->name . '\'s ticket cancelled.');
    }

    public function resendTicket(Request $request, Event $event, Guest $guest)
    {
        $this->run($request, $event);
        abort_unless($guest->event_id === $event->id && $guest->email, 404);
        $this->checkin->sendTicket($guest);

        return back()->with('success', 'Ticket sent to ' . $guest->email . '.');
    }

    // ── Classes and meetings ────────────────────────────────────────

    public function forSession(Request $request, ClassSession $session)
    {
        $event = $this->checkin->forClassSession($session, $request->user());
        $this->run($request, $event);

        return redirect()->route('checkin.events.show', $event);
    }

    public function forMeeting(Request $request, Meeting $meeting)
    {
        abort_unless($meeting->organizer_id === $request->user()->id || $request->user()->can('Manage meetings'), 403);
        $event = $this->checkin->forMeeting($meeting, $request->user());

        return redirect()->route('checkin.events.show', $event);
    }

    // ── Self check-in after scanning the door QR ────────────────────

    /** The door QR's link lands here (via the public route that checked the code). */
    public function go(Request $request, Event $event)
    {
        $pass = $request->session()->get('checkin_pass');
        $valid = $pass && (int) $pass['event'] === $event->id && $pass['until'] > now()->getTimestamp();
        if (!$valid) return view('modules.checkin.go', ['event' => $event, 'result' => null, 'error' => 'That code has expired. Scan the QR on the screen again.']);

        // When the venue checks location, the page asks the phone first and posts back.
        if ($event->require_location && $event->hasLocation() && !$request->isMethod('post')) {
            return view('modules.checkin.go', ['event' => $event, 'result' => null, 'error' => null, 'askLocation' => true]);
        }
        try {
            $r = $this->checkin->checkIn($event, $request->user(), null, 'screen', null, ['lat' => $request->input('lat'), 'lng' => $request->input('lng')]);
            $request->session()->forget('checkin_pass');
        } catch (ValidationException $e) {
            return view('modules.checkin.go', ['event' => $event, 'result' => null, 'error' => collect($e->errors())->flatten()->first(),
                'askLocation' => array_key_exists('location', $e->errors()) && $event->require_location]);
        }

        return view('modules.checkin.go', ['event' => $event, 'result' => $r, 'error' => null]);
    }
}
