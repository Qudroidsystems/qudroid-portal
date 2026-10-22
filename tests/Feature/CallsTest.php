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

    $this->actingAs($this->ben)->postJson(route('connect.calls.leave', $call))->assertOk();
    expect($call->refresh()->ended_at)->toBeNull();
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
    $this->actingAs($this->cy)->get(route('connect.show', $group))->assertOk()->assertSee('call in progress');

    // Ben's tab closed without leaving: after 30 seconds he drops out
    CallPerson::where('user_id', $this->ben->id)->update(['seen_at' => now()->subMinute()]);
    $this->actingAs($this->ada)->getJson(route('connect.calls.poll', $call))->assertJsonCount(1, 'people')->assertJsonPath('ended', false);

    // then Ada's goes quiet too, and the next check ends the call
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
