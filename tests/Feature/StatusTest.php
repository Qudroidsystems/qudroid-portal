<?php

use App\Models\Connect\Call;
use App\Models\User;
use App\Services\Connect\ConnectService;
use App\Services\Messaging\PortalNotifier;
use App\Services\Presence\Presence;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function statusUser(string $name): User
{
    $u = User::factory()->create(['user_type' => 'staff', 'name' => $name]);
    $u->assignRole('Staff');
    $u->forceFill(['must_change_password' => false, 'last_seen_at' => now()])->save();

    return $u;
}

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->ada = statusUser('Ada Obi');
    $this->ben = statusUser('Ben Eze');
});

test('people set do not disturb and a status that clear themselves', function () {
    $this->actingAs($this->ada)->postJson(route('status.update'), [
        'presence' => 'dnd', 'presence_for' => '60', 'status_emoji' => '🌴', 'status_text' => 'On leave', 'status_for' => 'week',
    ])->assertOk()->assertJson(['state' => 'dnd', 'dnd' => true, 'emoji' => '🌴', 'text' => 'On leave']);

    $ada = $this->ada->fresh();
    expect(Presence::isDnd($ada))->toBeTrue()
        ->and(app(Presence::class)->line($ada))->toStartWith('Do not disturb until')->toContain('🌴 On leave');

    // others see it in a direct chat
    $dm = app(ConnectService::class)->direct($this->ben, $this->ada);
    $this->actingAs($this->ben)->get(route('connect.show', $dm))->assertOk()->assertSee('Do not disturb until')->assertSee('On leave');

    // after the hour, back to automatic; the status lasts the week
    $this->travel(61)->minutes();
    $ada->forceFill(['last_seen_at' => now()])->save();
    expect(app(Presence::class)->of($ada->fresh())['state'])->toBe('online')->and(Presence::isDnd($ada->fresh()))->toBeFalse()
        ->and(Presence::status($ada->fresh())['text'])->toBe('On leave');

    // clearing
    $this->actingAs($this->ada)->postJson(route('status.update'), ['clear_status' => 1])->assertOk()->assertJson(['text' => null]);
    $this->actingAs($this->ada)->postJson(route('status.update'), ['presence' => 'nope'])->assertStatus(422);
});

test('automatic presence shows calls, away and offline; the app gets the same', function () {
    $p = fn () => app()->make(Presence::class);
    expect($p()->of($this->ben)['state'])->toBe('online');
    $this->ben->forceFill(['last_seen_at' => now()->subMinutes(10)])->save();
    expect((new Presence)->of($this->ben->fresh())['state'])->toBe('away');
    $this->ben->forceFill(['last_seen_at' => now()->subHours(3)])->save();
    expect((new Presence)->of($this->ben->fresh())['state'])->toBe('offline')->and((new Presence)->line($this->ben->fresh()))->toStartWith('Last seen');

    // in a meeting (organiser or invited)
    $this->ben->forceFill(['last_seen_at' => now()])->save();
    \App\Models\Meetings\Meeting::create(['title' => 'Standup', 'starts_at' => now()->subMinutes(5), 'ends_at' => now()->addMinutes(25), 'organizer_id' => $this->ben->id]);
    expect((new Presence)->of($this->ben->fresh())['state'])->toBe('meeting');

    // on a call
    $dm = app(ConnectService::class)->direct($this->ada, $this->ben);
    $call = Call::create(['conversation_id' => $dm->id, 'started_by' => $this->ada->id, 'kind' => 'audio', 'started_at' => now()]);
    $call->people()->create(['user_id' => $this->ada->id, 'joined_at' => now(), 'seen_at' => now()]);
    expect((new Presence)->of($this->ada)['state'])->toBe('call');

    Sanctum::actingAs($this->ben);
    $this->postJson('/api/v1/me/status', ['presence' => 'busy'])->assertOk()->assertJsonPath('data.state', 'busy');
    $this->getJson('/api/v1/me/status')->assertOk()->assertJsonPath('data.presence', 'busy');
});

test('do not disturb keeps the notification but skips the phone push', function () {
    $this->ada->forceFill(['presence' => 'dnd'])->save();
    PortalNotifier::toUsers([$this->ada->id, $this->ben->id], 'Hello', 'There', '/x', 'system');
    expect($this->ada->notifications()->count())->toBe(1)->and($this->ben->notifications()->count())->toBe(1)
        ->and(Presence::withoutDnd([$this->ada->id, $this->ben->id]))->toBe([$this->ben->id]);
    $this->ada->forceFill(['presence_until' => now()->subMinute()])->save();
    expect(Presence::withoutDnd([$this->ada->id, $this->ben->id]))->toBe([$this->ada->id, $this->ben->id]);
});
