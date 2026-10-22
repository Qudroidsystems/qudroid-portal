<?php

use App\Models\Hr\Holiday;
use App\Models\Meetings\Attendee;
use App\Models\Meetings\Meeting;
use App\Models\Meetings\Room;
use App\Models\User;
use App\Services\Meetings\MeetingService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

function mtUser(string $role): User
{
    $u = User::factory()->create(['user_type' => 'staff']);
    $u->assignRole($role);
    $u->forceFill(['must_change_password' => false])->save();

    return $u;
}

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->pm = mtUser('Project Manager');     // Manage meetings
    $this->ada = mtUser('Staff');
    $this->bola = mtUser('Staff');
    $this->room = Room::firstOrFail();
    $this->at = fn (string $time) => Carbon::parse('tomorrow ' . $time)->format('Y-m-d\TH:i');
});

test('staff schedule a meeting, invitees answer, and the room cannot be double-booked', function () {
    $this->actingAs($this->ada)->post(route('meetings.store'), [
        'title' => 'Design review', 'starts_at' => ($this->at)('10:00'), 'ends_at' => ($this->at)('11:00'), 'room_id' => $this->room->id,
        'attendees' => [$this->bola->id, $this->pm->id], 'guests' => "Ngozi Ade <ngozi@acme.ng>\nTunde", 'remind_minutes' => 15,
    ])->assertRedirect();
    $m = Meeting::firstOrFail();
    expect($m->organizer_id)->toBe($this->ada->id)->and($m->attendees()->count())->toBe(3)
        ->and($m->guests)->toBe([['name' => 'Ngozi Ade', 'email' => 'ngozi@acme.ng'], ['name' => 'Tunde', 'email' => null]])
        ->and($this->bola->notifications()->count())->toBe(1);

    // room clash
    $this->actingAs($this->bola)->post(route('meetings.store'), [
        'title' => 'Clash', 'starts_at' => ($this->at)('10:30'), 'ends_at' => ($this->at)('11:30'), 'room_id' => $this->room->id,
    ])->assertSessionHasErrors('room_id');

    // busy check sees Bola in the design review
    $this->actingAs($this->pm)->postJson(route('meetings.check'), ['starts_at' => ($this->at)('10:30'), 'ends_at' => ($this->at)('11:30'), 'attendees' => [$this->bola->id]])
        ->assertOk()->assertJsonPath('busy.0.name', $this->bola->name);

    $this->actingAs($this->bola)->post(route('meetings.respond', $m), ['response' => 'declined', 'note' => 'On leave'])->assertSessionHasNoErrors();
    expect(Attendee::where('user_id', $this->bola->id)->value('response'))->toBe('declined')
        ->and($this->ada->notifications()->count())->toBe(1);

    // someone not invited can't see it or edit it; the organiser and Manage meetings can
    $outsider = mtUser('Staff');
    $this->actingAs($outsider)->get(route('meetings.show', $m))->assertForbidden();
    $this->actingAs($this->bola)->get(route('meetings.edit', $m))->assertForbidden();
    $this->actingAs($this->bola)->get(route('meetings.show', $m))->assertOk()->assertSee('Ngozi Ade')->assertSee('Not going');
    $this->actingAs($this->pm)->get(route('meetings.edit', $m))->assertOk();

    $ics = $this->actingAs($this->bola)->get(route('meetings.ics', $m));
    $ics->assertOk();
    expect($ics->headers->get('content-type'))->toContain('text/calendar')->and($ics->getContent())->toContain('SUMMARY:Design review')->toContain('BEGIN:VEVENT');
});

test('moving a meeting resets answers and tells people; cancelling tells everyone', function () {
    $m = app(MeetingService::class)->schedule(['title' => 'Sprint planning', 'starts_at' => ($this->at)('09:00'), 'ends_at' => ($this->at)('10:00')],
        [$this->ada->id], $this->pm);
    app(MeetingService::class)->respond($m, $this->ada, 'accepted');

    $this->actingAs($this->pm)->put(route('meetings.update', $m), [
        'title' => 'Sprint planning', 'starts_at' => ($this->at)('14:00'), 'ends_at' => ($this->at)('15:00'), 'attendees' => [$this->ada->id, $this->bola->id],
    ])->assertSessionHasNoErrors();
    expect(Attendee::where('meeting_id', $m->id)->where('user_id', $this->ada->id)->value('response'))->toBe('pending')
        ->and($this->ada->notifications()->get()->pluck('data.title')->all())->toContain('Meeting changed: Sprint planning')
        ->and($this->bola->notifications()->first()->data['title'] ?? '')->toContain('Meeting invite');

    $this->actingAs($this->ada)->post(route('meetings.cancel', $m))->assertForbidden();
    $this->actingAs($this->pm)->post(route('meetings.cancel', $m), ['reason' => 'Client moved the demo'])->assertSessionHasNoErrors();
    expect($m->fresh()->status)->toBe('cancelled')->and($this->bola->notifications()->count())->toBe(2);
    $this->actingAs($this->ada)->post(route('meetings.respond', $m), ['response' => 'accepted'])->assertSessionHasErrors('response');
});

test('reminders go out once, inside each meeting\'s window, to people still coming', function () {
    $svc = app(MeetingService::class);
    $soon = $svc->schedule(['title' => 'Stand-up', 'starts_at' => now()->addMinutes(10)->format('Y-m-d H:i'), 'ends_at' => now()->addMinutes(25)->format('Y-m-d H:i'), 'remind_minutes' => 15],
        [$this->ada->id, $this->bola->id], $this->pm);
    $svc->respond($soon, $this->bola, 'declined');
    $later = $svc->schedule(['title' => 'Later', 'starts_at' => now()->addHours(3)->format('Y-m-d H:i'), 'ends_at' => now()->addHours(4)->format('Y-m-d H:i'), 'remind_minutes' => 15],
        [$this->ada->id], $this->pm);

    $this->artisan('meetings:remind')->assertSuccessful();
    expect($soon->fresh()->reminded_at)->not->toBeNull()->and($later->fresh()->reminded_at)->toBeNull();
    $titles = fn (User $u) => $u->notifications()->get()->map(fn ($n) => $n->data['title'] ?? '')->all();
    expect($titles($this->ada))->toContain('Starting soon: Stand-up')->and($titles($this->bola))->not->toContain('Starting soon: Stand-up');
    expect($svc->remindDue())->toBe(0);
});

test('the calendar shows meetings, holidays, leave and task deadlines', function () {
    $day = Carbon::parse('first monday of next month');
    app(MeetingService::class)->schedule(['title' => 'Board meeting', 'starts_at' => $day->copy()->setTime(9, 0), 'ends_at' => $day->copy()->setTime(10, 0)], [$this->ada->id], $this->pm);
    Holiday::create(['date' => $day->copy()->addDay(), 'name' => 'Founders Day']);

    $r = $this->actingAs($this->ada)->get(route('meetings.calendar', ['month' => $day->format('Y-m')]));
    $r->assertOk()->assertSee('Board meeting')->assertSee('Founders Day')->assertSee($day->format('F Y'));
    $this->actingAs($this->bola)->get(route('meetings.calendar', ['month' => $day->format('Y-m')]))->assertOk()->assertDontSee('Board meeting');

    $items = app(MeetingService::class)->calendar($this->ada, $day->copy()->startOfMonth(), $day->copy()->endOfMonth());
    expect($items->pluck('type')->unique()->sort()->values()->all())->toBe(['holiday', 'meeting']);

    // mobile API
    $this->actingAs($this->ada, 'sanctum')->getJson('/api/v1/meetings/calendar?from=' . $day->copy()->startOfMonth()->toDateString() . '&to=' . $day->copy()->endOfMonth()->toDateString())
        ->assertOk()->assertJsonFragment(['title' => 'Board meeting']);
    $this->actingAs($this->ada, 'sanctum')->getJson('/api/v1/meetings')->assertOk()->assertJsonFragment(['title' => 'Board meeting', 'my_response' => 'pending']);
    $id = Meeting::firstOrFail()->id;
    $this->actingAs($this->ada, 'sanctum')->postJson("/api/v1/meetings/{$id}/respond", ['response' => 'accepted'])->assertOk()->assertJsonPath('data.my_response', 'accepted');
});

test('the meetings screens render', function () {
    $m = app(MeetingService::class)->schedule(['title' => 'Kick-off', 'starts_at' => ($this->at)('09:00'), 'ends_at' => ($this->at)('10:00'), 'online_url' => 'https://meet.example.com/abc'], [$this->ada->id], $this->pm);
    foreach ([route('meetings.index'), route('meetings.calendar'), route('meetings.create'), route('meetings.show', $m), route('meetings.edit', $m), route('meetings.rooms'), route('meetings.index', ['show' => 'all'])] as $url) {
        $this->actingAs($this->pm)->get($url)->assertOk();
    }
    $this->actingAs($this->ada)->get(route('meetings.index'))->assertOk()->assertSee('Waiting for your answer')->assertSee('Kick-off');
    $this->actingAs($this->ada)->get(route('meetings.rooms'))->assertForbidden();
});
