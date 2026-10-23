<?php

use App\Models\Connect\Call;
use App\Models\Meetings\Meeting;
use App\Models\User;
use App\Services\Connect\ConnectService;
use App\Services\Push\FcmClient;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function apiCallUser(string $name, string $role = 'Staff'): User
{
    $u = User::factory()->create(['user_type' => 'staff', 'name' => $name]);
    $u->assignRole($role);
    $u->forceFill(['must_change_password' => false])->save();

    return $u;
}

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->ada = apiCallUser('Ada Obi');
    $this->ben = apiCallUser('Ben Eze');
    $this->cy = apiCallUser('Cy Uche');
    $this->chat = app(ConnectService::class)->direct($this->ada, $this->ben);
});

test('the app starts a call, the other phone sees it ringing, and both swap set-up messages', function () {
    Sanctum::actingAs($this->ada);
    $this->getJson('/api/v1/calls/ice-servers')->assertOk()->assertJsonStructure(['data' => ['ice_servers', 'max_people']]);
    $id = $this->postJson('/api/v1/calls', ['conversation_id' => $this->chat->id, 'video' => false])->assertCreated()
        ->assertJsonPath('data.kind', 'audio')->assertJsonPath('data.from.name', 'Ada Obi')->json('data.id');
    $this->postJson("/api/v1/calls/{$id}/join")->assertOk()->assertJsonPath('data.others', []);

    Sanctum::actingAs($this->ben);
    $this->getJson('/api/v1/calls/ringing')->assertOk()->assertJsonPath('data.0.id', $id);
    $this->postJson("/api/v1/calls/{$id}/join")->assertOk()->assertJsonPath('data.others', [$this->ada->id]);
    $this->postJson("/api/v1/calls/{$id}/signal", ['to_user_id' => $this->ada->id, 'type' => 'offer', 'payload' => ['sdp' => 'x', 'type' => 'offer']])->assertOk();

    Sanctum::actingAs($this->ada);
    $r = $this->getJson("/api/v1/calls/{$id}/signals?since=0&mic=1&cam=0")->assertOk();
    expect($r->json('data.signals.0.type'))->toBe('offer')
        ->and(json_decode($r->json('data.signals.0.payload'), true)['sdp'])->toBe('x')
        ->and($r->json('data.signals.0.from_user_id'))->toBe($this->ben->id)
        ->and(collect($r->json('data.people'))->pluck('id')->sort()->values()->all())->toBe(collect([$this->ada->id, $this->ben->id])->sort()->values()->all())
        ->and($r->json('data.recording'))->toBeNull();

    Sanctum::actingAs($this->cy);
    $this->getJson("/api/v1/calls/{$id}")->assertNotFound();
    $this->postJson('/api/v1/calls', ['conversation_id' => $this->chat->id])->assertNotFound();

    Sanctum::actingAs($this->ben);
    $this->postJson("/api/v1/calls/{$id}/leave")->assertOk();
    Sanctum::actingAs($this->ada);
    $this->postJson("/api/v1/calls/{$id}/leave")->assertOk();
    $this->getJson("/api/v1/calls/{$id}")->assertJsonPath('data.live', false);
});

test('incoming calls go to phones as data-only, high-priority pushes', function () {
    $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    openssl_pkey_export($key, $pem);
    $file = tempnam(sys_get_temp_dir(), 'fcm');
    file_put_contents($file, json_encode(['project_id' => 'qh-test', 'client_email' => 'x@qh-test.iam.gserviceaccount.com', 'private_key' => $pem, 'token_uri' => 'https://oauth2.googleapis.com/token']));
    config(['services.fcm.credentials' => $file]);
    Http::fake(['oauth2.googleapis.com/*' => Http::response(['access_token' => 't']), 'fcm.googleapis.com/*' => Http::response(['name' => 'ok'])]);

    $fcm = new FcmClient();
    expect($fcm->send('tok', 'Video call from Ada Obi', 'Incoming call', ['type' => 'call', 'call_id' => 7, 'video' => '1']))->toBe('ok');
    expect($fcm->send('tok', 'Hello', 'Body', ['type' => 'message']))->toBe('ok');
    $sent = collect(Http::recorded())->map(fn ($p) => $p[0])->filter(fn ($r) => str_contains($r->url(), 'fcm.googleapis.com'))->values();
    $call = $sent[0]->data()['message'];
    $msg = $sent[1]->data()['message'];
    expect($call)->not->toHaveKey('notification')
        ->and($call['data'])->toMatchArray(['type' => 'call', 'call_id' => '7', 'video' => '1', 'title' => 'Video call from Ada Obi'])
        ->and($call['android'])->toBe(['priority' => 'high', 'ttl' => '60s'])
        ->and($msg)->toHaveKey('notification');
    @unlink($file);
});

test('meetings can be created, changed and cancelled from the app, with join details', function () {
    Sanctum::actingAs($this->ada);
    $this->getJson('/api/v1/meetings/people')->assertOk()->assertJsonStructure(['data' => ['people', 'rooms']]);
    $start = now()->addDay()->setTime(10, 0);
    $id = $this->postJson('/api/v1/meetings', ['title' => 'Kick-off', 'starts_at' => $start->toDateTimeString(), 'ends_at' => $start->copy()->addHour()->toDateTimeString(),
        'attendees' => [$this->ben->id]])->assertCreated()->assertJsonPath('data.title', 'Kick-off')->assertJsonPath('data.can_manage', true)->json('data.id');
    $this->putJson("/api/v1/meetings/{$id}", ['title' => 'Kick-off call', 'starts_at' => $start->toDateTimeString(), 'ends_at' => $start->copy()->addMinutes(30)->toDateTimeString(),
        'attendees' => [$this->ben->id]])->assertOk()->assertJsonPath('data.title', 'Kick-off call');

    // start the meeting call from the app; the meeting shows how to join
    $call = $this->postJson('/api/v1/calls', ['meeting_id' => $id])->assertCreated()->assertJsonPath('data.meeting_id', $id)->json('data.id');
    Sanctum::actingAs($this->ben);
    $this->getJson("/api/v1/meetings/{$id}")->assertOk()->assertJsonPath('data.join.call_id', $call)->assertJsonPath('data.can_manage', false);
    $this->putJson("/api/v1/meetings/{$id}", ['title' => 'Mine'])->assertForbidden();
    $this->deleteJson("/api/v1/meetings/{$id}")->assertForbidden();

    Sanctum::actingAs($this->ada);
    $this->deleteJson("/api/v1/meetings/{$id}", ['reason' => 'Moved'])->assertOk();
    expect(Meeting::find($id)->status)->toBe('cancelled');
});
