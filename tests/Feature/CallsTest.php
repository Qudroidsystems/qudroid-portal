<?php

use App\Models\Connect\Call;
use App\Models\Connect\CallPerson;
use App\Models\Connect\Message;
use App\Models\User;
use App\Services\Connect\ConnectService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function callUser(string $name): User
{
    $u = User::factory()->create(['user_type' => 'staff', 'name' => $name]);
    $u->assignRole('Staff');
    $u->forceFill(['must_change_password' => false])->save();

    return $u;
}

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->ada = callUser('Ada Obi');
    $this->ben = callUser('Ben Eze');
    $this->cy = callUser('Cy Uche');
    $this->connect = app(ConnectService::class);
    $this->direct = $this->connect->direct($this->ada, $this->ben);
});

test('a call rings the other person, passes set-up messages between the two, and ends when both leave', function () {
    $this->actingAs($this->ada)->post(route('connect.calls.start', $this->direct), ['kind' => 'video'])->assertRedirect();
    $call = Call::firstOrFail();
    expect(Message::where('is_system', true)->latest('id')->value('body'))->toBe('Ada Obi started a video call')
        ->and($this->ben->notifications()->first()->data['title'])->toBe('Video call from Ada Obi');

    // starting again joins the same call
    $this->actingAs($this->ada)->postJson(route('connect.calls.start', $this->direct))->assertJsonPath('id', $call->id);

    // it rings for Ben, not for Ada or an outsider
    $this->actingAs($this->ben)->getJson(route('connect.calls.ringing'))->assertJsonPath('calls.0.id', $call->id)->assertJsonPath('calls.0.from', 'Ada Obi');
    $this->actingAs($this->ada)->getJson(route('connect.calls.ringing'))->assertJsonCount(0, 'calls');
    $this->actingAs($this->cy)->get(route('connect.calls.show', $call))->assertForbidden();
    $this->actingAs($this->cy)->postJson(route('connect.calls.join', $call))->assertUnprocessable();

    $this->actingAs($this->ada)->get(route('connect.calls.show', $call))->assertOk()->assertSee('Ready to join?');
    $this->actingAs($this->ada)->postJson(route('connect.calls.join', $call))->assertOk()->assertJsonPath('others', []);
    $this->actingAs($this->ben)->postJson(route('connect.calls.join', $call))->assertOk()->assertJsonPath('others', [$this->ada->id]);
    $this->actingAs($this->ben)->getJson(route('connect.calls.ringing'))->assertJsonCount(0, 'calls');

    // the newcomer offers, the other answers
    $this->actingAs($this->ben)->postJson(route('connect.calls.signal', $call), ['to' => $this->ada->id, 'type' => 'offer', 'payload' => '{"type":"offer","sdp":"v=0"}'])->assertOk();
    $poll = $this->actingAs($this->ada)->getJson(route('connect.calls.poll', $call) . '?after=0&mic=1&cam=0')->assertOk()
        ->assertJsonPath('signals.0.type', 'offer')->assertJsonPath('signals.0.from', $this->ben->id)->assertJsonCount(2, 'people');
    $after = $poll->json('signals.0.id');
    $this->actingAs($this->ada)->getJson(route('connect.calls.poll', $call) . '?after=' . $after)->assertJsonCount(0, 'signals');
    $this->actingAs($this->ada)->postJson(route('connect.calls.signal', $call), ['to' => $this->ben->id, 'type' => 'answer', 'payload' => '{}'])->assertOk();
    $this->actingAs($this->ben)->getJson(route('connect.calls.poll', $call) . '?after=0&mic=0')->assertJsonPath('signals.0.type', 'answer')
        ->assertJsonPath('people.1.mic', false);

    // signals only go to people in the call
    $this->actingAs($this->ada)->postJson(route('connect.calls.signal', $call), ['to' => $this->cy->id, 'type' => 'offer'])->assertUnprocessable();

    // Ben hangs up: the call ends for Ada too, and her next check-in says so
    $this->actingAs($this->ben)->postJson(route('connect.calls.leave', $call))->assertOk();
    $this->actingAs($this->ada)->getJson(route('connect.calls.poll', $call))->assertJsonPath('ended', true);
    $this->actingAs($this->ada)->postJson(route('connect.calls.leave', $call))->assertOk();
    expect($call->refresh()->ended_at)->not->toBeNull()
        ->and(Message::where('is_system', true)->latest('id')->value('body'))->toStartWith('Call ended')
        ->and($call->peak_people)->toBe(2);
    $this->actingAs($this->ada)->postJson(route('connect.calls.join', $call))->assertUnprocessable();
    $this->actingAs($this->ada)->get(route('connect.calls.index'))->assertOk()->assertSee('Ben Eze');
});

test('declining a direct call ends it; people who go quiet drop out and an empty call ends', function () {
    $call = app(\App\Services\Connect\CallService::class)->start($this->direct, $this->ada, 'audio');
    $this->actingAs($this->ada)->postJson(route('connect.calls.join', $call));
    $this->actingAs($this->ben)->postJson(route('connect.calls.decline', $call))->assertOk();
    expect($call->refresh()->ended_at)->not->toBeNull()
        ->and(Message::where('is_system', true)->latest('id')->value('body'))->toBe('Ben Eze declined the call');

    $group = $this->connect->group($this->ada, 'Team', [$this->ben->id, $this->cy->id]);
    $call = app(\App\Services\Connect\CallService::class)->start($group, $this->ada, 'video');
    $this->actingAs($this->ada)->postJson(route('connect.calls.join', $call));
    $this->actingAs($this->ben)->postJson(route('connect.calls.join', $call));
    // in a group, one person declining doesn't end it
    $this->actingAs($this->cy)->postJson(route('connect.calls.decline', $call));
    expect($call->refresh()->ended_at)->toBeNull();
    $this->actingAs($this->cy)->get(route('connect.show', $group))->assertOk()->assertSee('"huddle":false', false);   // the live-call bar (drawn by the page)

    // Ben's tab closed without leaving: after 30 seconds he drops out, Ada is on her own and the call ends
    CallPerson::where('user_id', $this->ben->id)->update(['seen_at' => now()->subMinute()]);
    $this->actingAs($this->ada)->getJson(route('connect.calls.poll', $call))->assertJsonPath('ended', true);
    $this->actingAs($this->cy)->get(route('connect.show', $group))->assertOk()->assertDontSee('call in progress');
    expect($call->refresh()->ended_at)->not->toBeNull();

    // an empty call nobody is checking in to ends too
    $call = app(\App\Services\Connect\CallService::class)->start($group, $this->ada, 'video');
    $this->actingAs($this->ada)->postJson(route('connect.calls.join', $call));
    CallPerson::where('user_id', $this->ada->id)->update(['seen_at' => now()->subMinute()]);
    $this->travel(2)->minutes();
    $this->actingAs($this->cy)->get(route('connect.show', $group))->assertOk()->assertDontSee('call in progress');
    expect($call->refresh()->ended_at)->not->toBeNull();
});

test('a call nobody answers is reported as missed', function () {
    $call = app(\App\Services\Connect\CallService::class)->start($this->direct, $this->ada, 'video');
    $this->travel(2)->minutes();
    $this->actingAs($this->ben)->getJson(route('connect.calls.ringing'))->assertJsonCount(0, 'calls');
    expect($call->refresh()->ended_at)->not->toBeNull();

    $this->actingAs($this->ben)->get(route('connect.calls.index'))->assertOk()->assertSee('Missed');
});

test('a huddle is a voice room in a group that rings nobody and shows who is in it', function () {
    $group = $this->connect->group($this->ada, 'Design', [$this->ben->id, $this->cy->id]);
    $this->ben->notifications()->delete();
    $res = $this->actingAs($this->ada)->postJson(route('connect.huddle', $group))->assertOk()->assertJsonPath('huddle', true)->json();
    $call = \App\Models\Connect\Call::find($res['id']);
    expect($call->kind)->toBe('audio')->and($this->ben->notifications()->count())->toBe(0);
    $this->actingAs($this->ben)->getJson(route('connect.calls.ringing'))->assertJsonCount(0, 'calls');

    // a second press joins the same one; the chat's poll shows who is in it
    $this->actingAs($this->cy)->postJson(route('connect.huddle', $group))->assertJsonPath('id', $call->id);
    $this->actingAs($this->ada)->postJson(route('connect.calls.join', $call))->assertOk();
    $this->actingAs($this->ben)->getJson(route('connect.poll', $group))->assertJsonPath('call.huddle', true)->assertJsonPath('call.people.0.name', 'Ada Obi');
    $this->actingAs($this->ben)->get(route('connect.calls.show', [$call, 'embed' => 1]))->assertOk()->assertSee('class="embed"', false);
    $this->actingAs($this->ben)->get(route('connect.index'))->assertSee('Huddle on now');

    // everyone leaves: it ends with a note in the chat
    $this->actingAs($this->ada)->postJson(route('connect.calls.leave', $call));
    $this->travel(2)->minutes();
    $this->actingAs($this->ben)->getJson(route('connect.poll', $group))->assertJsonPath('call', null);
    expect(\App\Models\Connect\Message::where('conversation_id', $group->id)->where('is_system', true)->latest('id')->value('body'))->toStartWith('Huddle ended');
    $this->actingAs($this->out ?? $this->cy)->postJson(route('connect.huddle', $this->connect->direct($this->ben, $this->cy)))->assertOk();
});

test('when one person hangs up a direct or group call ends for everyone; a call with three carries on', function () {
    $calls = app(\App\Services\Connect\CallService::class);
    $group = $this->connect->group($this->ada, 'Team', [$this->ben->id, $this->cy->id]);
    $call = $calls->start($group, $this->ada, 'video');
    foreach ([$this->ada, $this->ben, $this->cy] as $u) $this->actingAs($u)->postJson(route('connect.calls.join', $call))->assertOk();

    // three in: one leaving leaves two talking
    $this->actingAs($this->cy)->postJson(route('connect.calls.leave', $call))->assertOk();
    expect($call->refresh()->ended_at)->toBeNull();
    // two in: the other hangs up and it is over for Ada, with a "Call ended" note in the chat
    $this->actingAs($this->ben)->postJson(route('connect.calls.leave', $call))->assertOk();
    expect($call->refresh()->ended_at)->not->toBeNull()
        ->and(Message::where('conversation_id', $group->id)->where('is_system', true)->latest('id')->value('body'))->toStartWith('Call ended');
    $this->actingAs($this->ada)->getJson(route('connect.calls.poll', $call))->assertJsonPath('ended', true);

    // the caller waiting alone for an answer is not cut off
    $call = $calls->start($this->direct, $this->ada, 'audio');
    $this->actingAs($this->ada)->postJson(route('connect.calls.join', $call));
    $this->actingAs($this->ada)->getJson(route('connect.calls.poll', $call))->assertJsonPath('ended', false);
    // and the caller hanging up ends it for the person who answered (the phone app gets the same answer)
    $this->actingAs($this->ben)->postJson(route('connect.calls.join', $call));
    $this->actingAs($this->ada)->postJson(route('connect.calls.leave', $call));
    \Laravel\Sanctum\Sanctum::actingAs($this->ben);
    $this->getJson('/api/v1/calls/' . $call->id . '/signals')->assertJsonPath('data.ended', true);
});

test('a huddle stays open when only one person is left in it', function () {
    $group = $this->connect->group($this->ada, 'Team', [$this->ben->id]);
    $call = app(\App\Services\Connect\CallService::class)->huddle($group, $this->ada);
    $this->actingAs($this->ada)->postJson(route('connect.calls.join', $call));
    $this->actingAs($this->ben)->postJson(route('connect.calls.join', $call));
    $this->actingAs($this->ben)->postJson(route('connect.calls.leave', $call));
    expect($call->refresh()->ended_at)->toBeNull();
});

test('checking in every second during a call does not use up the limits on ringing or starting a call', function () {
    $call = app(\App\Services\Connect\CallService::class)->start($this->direct, $this->ada, 'video');
    $this->actingAs($this->ada)->postJson(route('connect.calls.join', $call));
    $this->actingAs($this->ben)->postJson(route('connect.calls.join', $call));
    // a minute and a half of check-ins and set-up messages
    for ($i = 0; $i < 90; $i++) $this->actingAs($this->ada)->getJson(route('connect.calls.poll', $call))->assertOk();
    for ($i = 0; $i < 30; $i++) $this->actingAs($this->ada)->postJson(route('connect.calls.signal', $call), ['to' => $this->ben->id, 'type' => 'ice', 'payload' => '[]'])->assertOk();
    $this->actingAs($this->ada)->getJson(route('connect.calls.ringing'))->assertOk();
    $this->actingAs($this->ada)->postJson(route('connect.calls.start', $this->direct))->assertOk();
    $this->actingAs($this->ada)->getJson(route('connect.poll'))->assertOk();
});

test('several TURN relay addresses can be listed', function () {
    config(['qudroid.calls.turn_url' => 'turn:relay.example.com:3478, turns:relay.example.com:443?transport=tcp', 'qudroid.calls.turn_username' => 'u', 'qudroid.calls.turn_credential' => 'p']);
    $servers = app(\App\Services\Connect\CallService::class)->iceServers();
    expect(end($servers))->toBe(['urls' => ['turn:relay.example.com:3478', 'turns:relay.example.com:443?transport=tcp'], 'username' => 'u', 'credential' => 'p']);
});

test('the phone app checking in during a call does not use up its general API limit', function () {
    $call = app(\App\Services\Connect\CallService::class)->start($this->direct, $this->ada, 'video');
    \Laravel\Sanctum\Sanctum::actingAs($this->ada);
    $this->postJson('/api/v1/calls/' . $call->id . '/join')->assertOk();
    \Laravel\Sanctum\Sanctum::actingAs($this->ben);
    $this->postJson('/api/v1/calls/' . $call->id . '/join')->assertOk();
    \Laravel\Sanctum\Sanctum::actingAs($this->ada);
    // two minutes of once-a-second check-ins, plus set-up messages
    for ($i = 0; $i < 120; $i++) $this->getJson('/api/v1/calls/' . $call->id . '/signals')->assertOk();
    for ($i = 0; $i < 20; $i++) $this->postJson('/api/v1/calls/' . $call->id . '/signal', ['to_user_id' => $this->ben->id, 'type' => 'ice', 'payload' => []])->assertOk();
    $this->getJson('/api/v1/calls/ringing')->assertOk();
    $this->getJson('/api/v1/auth/me')->assertOk();
});

test('a page that reloads mid-call keeps its place for a few seconds; one that never comes back drops out', function () {
    $call = app(\App\Services\Connect\CallService::class)->start($this->direct, $this->ada, 'video');
    $this->actingAs($this->ada)->postJson(route('connect.calls.join', $call));
    $this->actingAs($this->ben)->postJson(route('connect.calls.join', $call));

    // Ben's page reloads: the call carries on and he rejoins the same call
    $this->actingAs($this->ben)->postJson(route('connect.calls.leave', $call), ['soft' => 1])->assertOk();
    $this->actingAs($this->ada)->getJson(route('connect.calls.poll', $call))->assertJsonPath('ended', false)->assertJsonCount(2, 'people');
    $this->actingAs($this->ben)->postJson(route('connect.calls.join', $call))->assertOk()->assertJsonPath('others', [$this->ada->id]);
    $this->travel(20)->seconds();
    $this->actingAs($this->ben)->getJson(route('connect.calls.poll', $call))->assertJsonPath('ended', false);
    $this->actingAs($this->ada)->getJson(route('connect.calls.poll', $call))->assertJsonPath('ended', false)->assertJsonCount(2, 'people');

    // Ben's tab closes for good: a little later the call ends for Ada
    $this->actingAs($this->ben)->postJson(route('connect.calls.leave', $call), ['soft' => 1])->assertOk();
    $this->travel(\App\Services\Connect\CallService::RELOAD_GRACE + 1)->seconds();
    $this->actingAs($this->ada)->getJson(route('connect.calls.poll', $call))->assertJsonPath('ended', true);
});

test('the caller is told when the person they are calling is on another call', function () {
    $calls = app(\App\Services\Connect\CallService::class);
    // Ben is talking to Cy
    $other = $calls->start($this->connect->direct($this->ben, $this->cy), $this->cy, 'audio');
    $this->actingAs($this->cy)->postJson(route('connect.calls.join', $other));
    $this->actingAs($this->ben)->postJson(route('connect.calls.join', $other));

    // Ada calls Ben and waits
    $call = $calls->start($this->direct, $this->ada, 'video');
    $this->actingAs($this->ada)->postJson(route('connect.calls.join', $call));
    $this->actingAs($this->ada)->getJson(route('connect.calls.poll', $call))->assertJsonPath('busy', ['Ben Eze']);

    // once Ben is free, or has joined, nothing is said
    $this->actingAs($this->ben)->postJson(route('connect.calls.leave', $other));
    $this->actingAs($this->ada)->getJson(route('connect.calls.poll', $call))->assertJsonPath('busy', []);
});

test('the phone app is told too when the person it is calling is on another call', function () {
    $calls = app(\App\Services\Connect\CallService::class);
    $other = $calls->start($this->connect->direct($this->ben, $this->cy), $this->cy, 'audio');
    $this->actingAs($this->cy)->postJson(route('connect.calls.join', $other));
    $this->actingAs($this->ben)->postJson(route('connect.calls.join', $other));
    $call = $calls->start($this->direct, $this->ada, 'video');
    \Laravel\Sanctum\Sanctum::actingAs($this->ada);
    $this->postJson('/api/v1/calls/' . $call->id . '/join')->assertOk();
    $this->getJson('/api/v1/calls/' . $call->id . '/signals')->assertOk()->assertJsonPath('data.busy', ['Ben Eze']);
});
