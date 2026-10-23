<?php

use App\Events\Realtime\Poke;
use App\Models\User;
use App\Services\Connect\CallService;
use App\Services\Connect\ConnectService;
use App\Services\Realtime\Realtime;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function rtUser(string $name, string $role = 'Staff'): User
{
    $u = User::factory()->create(['user_type' => 'staff', 'name' => $name]);
    $u->assignRole($role);
    $u->forceFill(['must_change_password' => false])->save();

    return $u;
}

function rtOn(): void
{
    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb.key' => 'test-key',
        'broadcasting.connections.reverb.secret' => 'test-secret',
        'broadcasting.connections.reverb.app_id' => '1',
        'broadcasting.connections.reverb.options.host' => 'ws.example.test',
        'broadcasting.connections.reverb.options.port' => 443,
        'broadcasting.connections.reverb.options.scheme' => 'https',
    ]);
}

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->ada = rtUser('Ada');
    $this->ben = rtUser('Ben');
    $this->cy = rtUser('Cy');
    $this->svc = app(ConnectService::class);
    Realtime::flush();
});

test('with instant updates off, nothing is queued and pages load no socket code', function () {
    $c = $this->svc->direct($this->ada, $this->ben);
    $this->svc->send($c, $this->ada, 'hello');

    expect(Realtime::enabled())->toBeFalse()->and(Realtime::pending())->toBe([]);
    $this->actingAs($this->ada)->get(route('connect.index'))->assertOk()->assertDontSee('pusher.min.js');
});

test('a message pokes the conversation and the other person, and nothing private goes over the socket', function () {
    rtOn();
    Event::fake([Poke::class]);
    $c = $this->svc->direct($this->ada, $this->ben);
    Realtime::flush();

    $m = $this->svc->send($c, $this->ada, 'the secret plan');
    $pending = collect(Realtime::pending());
    $byEvent = $pending->mapWithKeys(fn ($p) => [$p['event'] => array_keys($p['channels'])]);

    expect($byEvent['message'])->toBe(['connect.c.' . $c->id])
        ->and($byEvent['inbox'])->toContain('user.' . $this->ben->id)
        ->and(json_encode($pending))->not->toContain('secret plan');

    Realtime::flush();
    Event::assertDispatched(Poke::class, fn (Poke $e) => $e->broadcastAs() === 'message' && $e->data['id'] === $m->id);
    expect(Realtime::pending())->toBe([]);
});

test('typing pokes at most once every few seconds', function () {
    rtOn();
    Event::fake([Poke::class]);
    $c = $this->svc->direct($this->ada, $this->ben);
    Realtime::flush();

    $this->svc->typing($c, $this->ada);
    $this->svc->typing($c, $this->ada);
    Realtime::flush();
    Event::assertDispatchedTimes(Poke::class, 1);
});

test('starting a call rings the others on their own channels', function () {
    rtOn();
    $c = $this->svc->direct($this->ada, $this->ben);
    Realtime::flush();

    app(CallService::class)->start($c, $this->ada, 'audio');
    $call = collect(Realtime::pending())->firstWhere('event', 'call');
    expect(array_keys($call['channels']))->toBe(['user.' . $this->ben->id]);
    Realtime::flush();
});

test('channel rules let people listen only to their own channel and chats they can see', function () {
    $c = $this->svc->direct($this->ada, $this->ben);
    $channels = Broadcast::driver()->getChannels();
    $rule = fn (string $pattern) => $channels[$pattern];

    expect($rule('user.{id}')($this->ada, $this->ada->id))->toBeTrue()
        ->and($rule('user.{id}')($this->ada, $this->ben->id))->toBeFalse()
        ->and($rule('connect.c.{id}')($this->ben, $c->id))->toBeTrue()
        ->and($rule('connect.c.{id}')($this->cy, $c->id))->toBeFalse()
        ->and($rule('connect.c.{id}')($this->ada, 999999))->toBeFalse();

    $call = app(CallService::class)->start($c, $this->ada, 'audio');
    expect($rule('call.{id}')($this->ben, $call->id))->toBeTrue()
        ->and($rule('call.{id}')($this->cy, $call->id))->toBeFalse();
});

test('the app asks the API whether instant updates are on and how to connect', function () {
    Sanctum::actingAs($this->ada);
    $this->getJson('/api/v1/realtime')->assertOk()->assertJsonPath('data.enabled', false);
    $this->postJson('/api/v1/broadcasting/auth', ['socket_id' => '1.1', 'channel_name' => 'private-user.' . $this->ada->id])->assertNotFound();

    rtOn();
    $this->getJson('/api/v1/realtime')->assertOk()
        ->assertJsonPath('data.enabled', true)
        ->assertJsonPath('data.key', 'test-key')
        ->assertJsonPath('data.host', 'ws.example.test')
        ->assertJsonPath('data.tls', true)
        ->assertJsonPath('data.channels.0', 'user.' . $this->ada->id)
        ->assertJsonMissingPath('data.secret');
    Realtime::flush();
});

test('signed-in pages load the socket client when instant updates are on', function () {
    rtOn();
    $this->actingAs($this->ada)->get(route('connect.index'))->assertOk()->assertSee('pusher.min.js')->assertSee('QRealtime')->assertDontSee('test-secret');
    Realtime::flush();
});
