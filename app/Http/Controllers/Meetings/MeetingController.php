<?php

namespace App\Http\Controllers\Meetings;

use App\Http\Controllers\Controller;
use App\Models\Crm\Company;
use App\Models\Meetings\Attendee;
use App\Models\Meetings\Meeting;
use App\Models\Meetings\Room;
use App\Models\Projects\Project;
use App\Services\Meetings\MeetingService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class MeetingController extends Controller
{
    public function __construct(protected MeetingService $meetings) {}

    public function index(Request $request)
    {
        $me = $request->user();
        $all = $request->input('show') === 'all' && $me->can('Manage meetings');
        $base = fn () => Meeting::with('room', 'organizer', 'attendees')->when(!$all, fn ($q) => $q->for($me));

        return view('modules.meetings.index', [
            'upcoming' => $base()->active()->where('ends_at', '>=', now())->orderBy('starts_at')->limit(50)->get(),
            'past' => $base()->where('ends_at', '<', now())->orderByDesc('starts_at')->limit(15)->get(),
            'toAnswer' => Attendee::with('meeting.organizer')->where('user_id', $me->id)->where('response', 'pending')
                ->whereHas('meeting', fn ($q) => $q->active()->where('starts_at', '>', now()))->get()->sortBy('meeting.starts_at'),
            'all' => $all,
        ]);
    }

    public function calendar(Request $request)
    {
        $month = rescue(fn () => Carbon::createFromFormat('Y-m', (string) $request->input('month')), today(), false)->startOfMonth();
        $from = $month->copy()->startOfWeek(Carbon::MONDAY);
        $to = $month->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);
        $items = $this->meetings->calendar($request->user(), $from, $to->copy()->endOfDay(), $request->boolean('everyone') && $request->user()->can('Manage meetings'));

        $days = [];
        for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
            $key = $d->toDateString();
            $days[$key] = $items->filter(fn ($i) => $i['start']->toDateString() <= $key && $i['end']->toDateString() >= $key)->values();
        }

        return view('modules.meetings.calendar', ['month' => $month, 'days' => $days, 'feedUrl' => $this->meetings->feedUrl($request->user())]);
    }

    public function create(Request $request)
    {
        $start = rescue(fn () => Carbon::parse($request->input('date', today()->toDateString()) . ' ' . $request->input('time', '10:00')), now()->addHour()->startOfHour(), false);
        if ($start->isPast()) $start = now()->addHour()->startOfHour();
        $meeting = new Meeting(['starts_at' => $start, 'ends_at' => $start->copy()->addHour(), 'remind_minutes' => 15,
            'project_id' => $request->integer('project') ?: null, 'company_id' => $request->integer('company') ?: null]);

        return view('modules.meetings.form', $this->formData($request, $meeting, array_filter([$request->integer('with')])));
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules());
        $meeting = $this->meetings->schedule($data, $data['attendees'] ?? [], $request->user());

        return redirect()->route('meetings.show', $meeting)->with('success', 'Meeting scheduled and invitations sent.');
    }

    /** Who is busy at the chosen time (used by the form while choosing people). */
    public function check(Request $request)
    {
        $d = $request->validate(['starts_at' => ['required', 'date'], 'ends_at' => ['required', 'date'], 'attendees' => ['array'], 'meeting_id' => ['nullable', 'integer']]);
        $busy = $this->meetings->busy(array_map('intval', $d['attendees'] ?? []), Carbon::parse($d['starts_at']), Carbon::parse($d['ends_at']), $d['meeting_id'] ?? null);
        $names = $this->meetings->people()->whereIn('id', array_keys($busy))->pluck('name', 'id');

        return response()->json(['busy' => collect($busy)->map(fn ($why, $id) => ['name' => $names[$id] ?? '', 'why' => $why])->values()]);
    }

    public function show(Request $request, Meeting $meeting)
    {
        $meeting->load('room', 'organizer', 'project', 'company', 'attendees.user');
        abort_unless($this->meetings->canView($meeting, $request->user()), 403);

        return view('modules.meetings.show', [
            'meeting' => $meeting,
            'mine' => $meeting->attendees->firstWhere('user_id', $request->user()->id),
            'canManage' => $this->meetings->canManage($meeting, $request->user()),
            'busy' => $meeting->isCancelled() || $meeting->isOver() ? [] : $this->meetings->busy($meeting->attendees->pluck('user_id')->all(), $meeting->starts_at, $meeting->ends_at, $meeting->id),
            'liveCall' => ($chat = \App\Models\Connect\Conversation::where('meeting_id', $meeting->id)->first()) && class_exists(\App\Services\Connect\CallService::class)
                ? app(\App\Services\Connect\CallService::class)->live($chat) : null,
        ]);
    }

    public function edit(Request $request, Meeting $meeting)
    {
        abort_unless($this->meetings->canManage($meeting, $request->user()) && !$meeting->isCancelled(), 403);

        return view('modules.meetings.form', $this->formData($request, $meeting, $meeting->attendees()->pluck('user_id')->all()));
    }

    public function update(Request $request, Meeting $meeting)
    {
        abort_unless($this->meetings->canManage($meeting, $request->user()), 403);
        $data = $request->validate($this->rules());
        $this->meetings->update($meeting, $data, $data['attendees'] ?? [], $request->user());

        return redirect()->route('meetings.show', $meeting)->with('success', 'Meeting saved. Anyone affected has been told.');
    }

    public function cancel(Request $request, Meeting $meeting)
    {
        abort_unless($this->meetings->canManage($meeting, $request->user()), 403);
        $this->meetings->cancel($meeting, $request->user(), $request->input('reason'));

        return back()->with('success', 'Meeting cancelled. Everyone invited has been told.');
    }

    public function respond(Request $request, Meeting $meeting)
    {
        $d = $request->validate(['response' => ['required', Rule::in(['accepted', 'tentative', 'declined'])], 'note' => ['nullable', 'string', 'max:255']]);
        $this->meetings->respond($meeting, $request->user(), $d['response'], $d['note'] ?? null);

        return back()->with('success', 'Answer saved.');
    }

    public function minutes(Request $request, Meeting $meeting)
    {
        abort_unless($this->meetings->canManage($meeting, $request->user()), 403);
        $d = $request->validate(['minutes' => ['nullable', 'string', 'max:20000']]);
        $this->meetings->saveMinutes($meeting, $request->user(), $d['minutes'] ?? null);

        return back()->with('success', 'Minutes saved.');
    }

    public function ics(Request $request, Meeting $meeting)
    {
        abort_unless($this->meetings->canView($meeting->load('attendees', 'organizer', 'room'), $request->user()), 403);

        return response($this->meetings->ics($meeting), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="meeting-' . $meeting->id . '.ics"',
        ]);
    }

    public function rooms()
    {
        return view('modules.meetings.rooms', ['rooms' => Room::orderBy('name')->get()]);
    }

    public function storeRoom(Request $request)
    {
        Room::create($request->validate(['name' => ['required', 'string', 'max:80', 'unique:meeting_rooms,name'], 'capacity' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'description' => ['nullable', 'string', 'max:255']]) + ['is_active' => true]);

        return back()->with('success', 'Room added.');
    }

    public function updateRoom(Request $request, Room $room)
    {
        $room->update($request->validate(['name' => ['required', 'string', 'max:80', Rule::unique('meeting_rooms', 'name')->ignore($room)],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:1000'], 'description' => ['nullable', 'string', 'max:255']]) + ['is_active' => $request->boolean('is_active')]);

        return back()->with('success', 'Room saved.');
    }

    protected function formData(Request $request, Meeting $meeting, array $invited): array
    {
        $me = $request->user();

        return [
            'meeting' => $meeting,
            'invited' => array_map('intval', $invited),
            'people' => $this->meetings->people()->whereKeyNot($meeting->organizer_id ?? $me->id)->get(['id', 'name', 'job_title']),
            'rooms' => Room::where('is_active', true)->orderBy('name')->get(),
            'projects' => $me->can('View projects') || $me->can('Manage projects') ? Project::visibleTo($me)->open()->orderBy('name')->get(['id', 'code', 'name']) : collect(),
            'companies' => $me->can('View clients') ? Company::orderBy('name')->get(['id', 'name']) : collect(),
        ];
    }

    protected function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'agenda' => ['nullable', 'string', 'max:10000'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date'],
            'room_id' => ['nullable', 'exists:meeting_rooms,id'],
            'location' => ['nullable', 'string', 'max:255'],
            'online_url' => ['nullable', 'url:http,https', 'max:500'],
            'project_id' => ['nullable', 'exists:projects,id'],
            'company_id' => ['nullable', 'exists:crm_companies,id'],
            'guests' => ['nullable', 'string', 'max:3000'],
            'remind_minutes' => ['nullable', 'integer', Rule::in([0, 5, 10, 15, 30, 60, 1440])],
            'attendees' => ['array', 'max:100'],
            'attendees.*' => ['integer'],
        ];
    }

    public function chat(Request $request, Meeting $meeting)
    {
        return redirect()->route('connect.show', $this->meetings->chat($meeting, $request->user()));
    }

    public function call(Request $request, Meeting $meeting)
    {
        return redirect()->route('connect.calls.show', $this->meetings->call($meeting, $request->user()));
    }

    public function resetFeed(Request $request)
    {
        $this->meetings->feedUrl($request->user(), reset: true);

        return back()->with('success', 'Your calendar link changed. Calendars using the old one stop updating.');
    }

    /** A person's private calendar feed (no sign-in: the long random token is the key). */
    public function feed(string $token)
    {
        $ics = $this->meetings->feed($token);
        abort_unless($ics, 404);

        return response($ics, 200, ['Content-Type' => 'text/calendar; charset=utf-8', 'Content-Disposition' => 'inline; filename="meetings.ics"', 'Cache-Control' => 'private, max-age=600']);
    }
}
