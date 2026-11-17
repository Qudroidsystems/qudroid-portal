<?php

use App\Services\Connect\CallService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

test('without TURN only STUN is offered, and the check command says so', function () {
    config(['qudroid.calls.turn_url' => null, 'qudroid.calls.metered_domain' => null, 'qudroid.calls.cloudflare_turn_key_id' => null]);
    expect(app(CallService::class)->turnServers())->toBe([]);
    $this->artisan('calls:turn')->expectsOutputToContain('No TURN relay is set up')->assertFailed();
});

test('a fixed TURN login is passed on', function () {
    config(['qudroid.calls.turn_url' => 'turn:t.example.com:3478, turns:t.example.com:443?transport=tcp', 'qudroid.calls.turn_username' => 'u', 'qudroid.calls.turn_credential' => 'p']);
    expect(app(CallService::class)->turnServers())->toBe([['urls' => ['turn:t.example.com:3478', 'turns:t.example.com:443?transport=tcp'], 'username' => 'u', 'credential' => 'p']]);
});

test('a coturn shared secret makes short-lived logins', function () {
    config(['qudroid.calls.turn_url' => 'turn:t.example.com:3478', 'qudroid.calls.turn_secret' => 's3cret']);
    [$s] = app(CallService::class)->turnServers();
    [$expires] = explode(':', $s['username']);
    expect((int) $expires)->toBeGreaterThan(now()->timestamp)
        ->and($s['credential'])->toBe(base64_encode(hash_hmac('sha1', $s['username'], 's3cret', true)));
});

test('Metered logins are fetched once and kept', function () {
    Cache::flush();
    config(['qudroid.calls.metered_domain' => 'qudroid.metered.live', 'qudroid.calls.metered_api_key' => 'k']);
    Http::fake(['qudroid.metered.live/*' => Http::response([
        ['urls' => 'stun:stun.relay.metered.ca:80'],
        ['urls' => 'turn:global.relay.metered.ca:80', 'username' => 'a', 'credential' => 'b'],
        ['urls' => 'turns:global.relay.metered.ca:443?transport=tcp', 'username' => 'a', 'credential' => 'b'],
    ])]);
    $calls = app(CallService::class);
    expect($calls->turnServers())->toHaveCount(2);
    $calls->turnServers();
    Http::assertSentCount(1);
    expect(collect($calls->iceServers())->pluck('urls')->flatten()->filter(fn ($u) => str_starts_with($u, 'turn'))->count())->toBe(2);
});

test('Cloudflare logins are fetched with the API token', function () {
    Cache::flush();
    config(['qudroid.calls.cloudflare_turn_key_id' => 'kid', 'qudroid.calls.cloudflare_turn_token' => 'tok']);
    Http::fake(['rtc.live.cloudflare.com/*' => Http::response(['iceServers' => [
        ['urls' => ['stun:stun.cloudflare.com:3478']],
        ['urls' => ['turn:turn.cloudflare.com:3478?transport=udp', 'turns:turn.cloudflare.com:443?transport=tcp'], 'username' => 'x', 'credential' => 'y'],
    ]])]);
    expect(app(CallService::class)->turnServers())->toBe([['urls' => ['turn:turn.cloudflare.com:3478?transport=udp', 'turns:turn.cloudflare.com:443?transport=tcp'], 'username' => 'x', 'credential' => 'y']]);
    Http::assertSent(fn ($r) => $r->hasHeader('Authorization', 'Bearer tok') && str_contains($r->url(), '/keys/kid/'));
});
