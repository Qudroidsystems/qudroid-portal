<?php

use App\Models\Connect\Call;
use App\Models\Connect\Conversation;
use App\Models\Meetings\Meeting;
use App\Models\User;
use App\Services\Meetings\MeetingService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function wsUser(string $name, string $role = 'Staff'): User
{
    $u = User::factory()->create(['user_type' => 'staff', 'name' => $name]);
    $u->assignRole($role);
    $u->forceFill(['must_change_password' => false])->save();

    return $u;
}

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->ada = wsUser('Ada Obi');
    $this->ada->givePermissionTo('Manage meetings');
    $this->ben = wsUser('Ben Eze');
    $this->cy = wsUser('Cy Uche');
    $start = now()->addDay()->setTime(10, 0);
    $this->meeting = app(MeetingService::class)->schedule(['title' => 'Sprint review', 'starts_at' => $start, 'ends_at' => $start->copy()->addHour()],
        [$this->ben->id], $this->ada);
});

test('a meeting gets its own chat that follows the invite list, and a call from the meeting page', function () {
    $this->actingAs($this->ben)->post(route('meetings.chat', $this->meeting))->assertRedirect();
    $chat = Conversation::where('meeting_id', $this->meeting->id)->firstOrFail();
    expect($chat->name)->toBe('Sprint review')
        ->and($chat->members()->whereNull('left_at')->pluck('user_id')->all())->toEqualCanonicalizing([$this->ada->id, $this->ben->id]);

    // opening it again reuses it; outsiders can't
    $this->actingAs($this->ada)->post(route('meetings.chat', $this->meeting))->assertRedirect(route('connect.show', $chat));
    $this->actingAs($this->cy)->post(route('meetings.chat', $this->meeting))->assertSessionHasErrors('meeting');
    expect(Conversation::where('meeting_id', $this->meeting->id)->count())->toBe(1);

    // inviting Cy and dropping Ben updates the chat
    app(MeetingService::class)->update($this->meeting, ['title' => 'Sprint review', 'starts_at' => $this->meeting->starts_at, 'ends_at' => $this->meeting->ends_at], [$this->cy->id], $this->ada);
    expect($chat->members()->whereNull('left_at')->pluck('user_id')->all())->toEqualCanonicalizing([$this->ada->id, $this->cy->id]);

    $this->actingAs($this->cy)->post(route('meetings.call', $this->meeting))->assertRedirect();
    $call = Call::firstOrFail();
    expect($call->conversation_id)->toBe($chat->id);
    $this->actingAs($this->ada)->get(route('meetings.show', $this->meeting))->assertOk()->assertSee('Join the call');
});

test('each person has a private calendar feed that apps can subscribe to', function () {
    $page = $this->actingAs($this->ben)->get(route('meetings.calendar'))->assertOk();
    $url = app(MeetingService::class)->feedUrl($this->ben);
    $page->assertSee($url);

    auth()->logout();
    $ics = $this->get($url)->assertOk()->assertHeader('Content-Type', 'text/calendar; charset=utf-8');
    expect($ics->getContent())->toContain('BEGIN:VCALENDAR')->toContain('SUMMARY:Sprint review');
    expect($this->get(app(MeetingService::class)->feedUrl($this->cy))->getContent())->not->toContain('Sprint review');

    // resetting the link stops the old one working
    $this->actingAs($this->ben)->post(route('meetings.feed.reset'))->assertRedirect();
    auth()->logout();
    $this->get($url)->assertNotFound();
    $this->get(app(MeetingService::class)->feedUrl($this->ben))->assertOk();

    // the single-meeting download still works
    $this->actingAs($this->ben)->get(route('meetings.ics', $this->meeting))->assertOk();
});

test('the workspace home shows chats, live calls, meetings and files', function () {
    $this->actingAs($this->ben)->post(route('meetings.chat', $this->meeting));
    $chat = Conversation::where('meeting_id', $this->meeting->id)->first();
    app(\App\Services\Connect\CallService::class)->start($chat, $this->ada, 'video');
    app(\App\Services\Connect\ConnectService::class)->send($chat, $this->ada, 'Agenda is in the doc');
    $this->cy->forceFill(['last_seen_at' => now()])->save();

    $this->actingAs($this->ben)->get(route('workspace.index'))->assertOk()
        ->assertSee('Needs a reply')->assertSee('Agenda is in the doc')
        ->assertSee('A call is going on')->assertSee('Sprint review')->assertSee('Cy');
    $this->actingAs($this->cy)->get(route('workspace.index'))->assertOk()->assertDontSee('Agenda is in the doc');
});
