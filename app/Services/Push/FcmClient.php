<?php

namespace App\Services\Push;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Firebase Cloud Messaging (HTTP v1) without extra packages.
 *
 * Needs the service-account JSON of the Qlouds Workspace Firebase project
 * (Firebase console → Project settings → Service accounts → Generate new
 * private key). Save it as storage/app/fcm-service-account.json, or point
 * FCM_CREDENTIALS in .env at it.
 */
class FcmClient
{
    protected ?array $creds = null;

    public function __construct()
    {
        $path = config('services.fcm.credentials') ?: storage_path('app/fcm-service-account.json');
        if (is_string($path) && is_file($path)) {
            $json = json_decode((string) file_get_contents($path), true);
            if (is_array($json) && !empty($json['private_key']) && !empty($json['client_email'])) {
                $this->creds = $json;
            }
        }
    }

    public function configured(): bool
    {
        return $this->creds !== null && $this->projectId() !== null;
    }

    public function projectId(): ?string
    {
        return config('services.fcm.project_id') ?: ($this->creds['project_id'] ?? null);
    }

    /**
     * Send one message to one device token.
     * @return string ok | invalid_token | error
     */
    public function send(string $token, string $title, string $body, array $data = []): string
    {
        if (!$this->configured()) return 'error';

        // FCM data values must be strings.
        $strings = array_map(fn ($v) => is_scalar($v) || $v === null ? (string) $v : json_encode($v), $data);
        if (in_array($data['type'] ?? '', ['call', 'call_end'], true)) {
            // Incoming calls: a data-only, high-priority message, so the Android app can show its own
            // ringing screen even when closed; it expires after a minute (a missed call is not news).
            // iOS gets a time-sensitive alert (ringing a closed iPhone app needs a VoIP push).
            $message = [
                'token' => $token,
                'data' => $strings + ['title' => $title, 'body' => $body],
                'android' => ['priority' => 'high', 'ttl' => $data['type'] === 'call' ? '60s' : '30s'],
                'apns' => $data['type'] === 'call'
                    ? ['headers' => ['apns-priority' => '10', 'apns-expiration' => (string) (time() + 60)],
                        'payload' => ['aps' => ['alert' => ['title' => $title, 'body' => $body], 'sound' => 'default', 'interruption-level' => 'time-sensitive']]]
                    : ['headers' => ['apns-priority' => '5', 'apns-push-type' => 'background'], 'payload' => ['aps' => ['content-available' => 1]]],
            ];
        } else {
            // Android: one channel per kind so people can tune them in system settings; a tag so a
            // thread replaces its earlier banner. iOS: thread-id stacks, badge is the unread total.
            $level = $data['level'] ?? 'normal';
            $category = $data['category'] ?? '';
            $thread = (string) ($data['thread_id'] ?? '');
            $aps = ['sound' => 'default', 'interruption-level' => ['urgent' => 'time-sensitive', 'passive' => 'passive'][$level] ?? 'active'];
            if ($level === 'passive') unset($aps['sound']);
            if ($thread !== '') $aps['thread-id'] = $thread;
            if (isset($data['badge']) && is_numeric($data['badge'])) $aps['badge'] = (int) $data['badge'];
            if ($category !== '') $aps['category'] = $category;
            $android = array_filter([
                'channel_id' => self::channelFor($category ?: ($data['type'] ?? '')),
                'sound' => $level === 'passive' ? null : 'default',
                'tag' => $thread !== '' ? mb_substr($thread, 0, 64) : null,
                'notification_count' => isset($data['badge']) && is_numeric($data['badge']) ? (int) $data['badge'] : null,
            ], fn ($v) => $v !== null);
            $message = [
                'token' => $token,
                'notification' => ['title' => $title, 'body' => $body],
                'data' => $strings,
                'android' => ['priority' => $level === 'passive' ? 'normal' : 'high', 'notification' => $android],
                'apns' => ['headers' => ['apns-priority' => $level === 'passive' ? '5' : '10'], 'payload' => ['aps' => $aps]],
            ];
        }

        try {
            $res = Http::withToken($this->accessToken())
                ->timeout(10)
                ->post('https://fcm.googleapis.com/v1/projects/' . $this->projectId() . '/messages:send', ['message' => $message]);
        } catch (\Throwable $e) {
            Log::warning('FCM send failed', ['error' => $e->getMessage()]);
            return 'error';
        }

        if ($res->successful()) return 'ok';

        $status = $res->json('error.status');
        $detail = collect($res->json('error.details') ?? [])->pluck('errorCode')->filter()->first();
        if ($res->status() === 404 || $status === 'NOT_FOUND' || $detail === 'UNREGISTERED'
            || ($status === 'INVALID_ARGUMENT' && str_contains((string) $res->json('error.message'), 'registration token'))) {
            return 'invalid_token';
        }
        if ($res->status() === 401) Cache::forget($this->tokenCacheKey());

        Log::warning('FCM rejected message', ['status' => $res->status(), 'body' => mb_substr($res->body(), 0, 500)]);
        return 'error';
    }

    /** OAuth2 access token from the service account (cached ~55 minutes). */
    /**
     * Android notification channels the app creates: messages, meetings, approvals, tasks, general (and
     * incoming_calls, which the app uses itself for ringing calls). Call notices such as missed calls go to
     * messages so they still make a sound.
     */
    public static function channelFor(string $category): string
    {
        return match ($category) {
            'message', 'mention', 'call' => 'messages',
            'meeting' => 'meetings',
            'approval', 'leave' => 'approvals',
            'task' => 'tasks',
            default => 'general',
        };
    }

    protected function accessToken(): string
    {
        return Cache::remember($this->tokenCacheKey(), now()->addMinutes(55), function () {
            $now = time();
            $header = $this->b64(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
            $claims = $this->b64(json_encode([
                'iss'   => $this->creds['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud'   => $this->creds['token_uri'] ?? 'https://oauth2.googleapis.com/token',
                'iat'   => $now,
                'exp'   => $now + 3600,
            ]));
            $signature = '';
            openssl_sign("$header.$claims", $signature, $this->creds['private_key'], 'sha256WithRSAEncryption');
            $jwt = "$header.$claims." . $this->b64($signature);

            $res = Http::asForm()->timeout(10)->post($this->creds['token_uri'] ?? 'https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion'  => $jwt,
            ]);
            if (!$res->successful() || !$res->json('access_token')) {
                throw new \RuntimeException('FCM auth failed: ' . mb_substr($res->body(), 0, 300));
            }
            return $res->json('access_token');
        });
    }

    protected function tokenCacheKey(): string
    {
        return 'fcm:access_token:' . md5(($this->creds['client_email'] ?? '') . ($this->projectId() ?? ''));
    }

    protected function b64(string $s): string
    {
        return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
    }
}
