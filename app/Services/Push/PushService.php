<?php

namespace App\Services\Push;

use App\Jobs\SendPushNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Push notifications to the Vite Schools mobile app.
 *
 * Every in-portal notification (the bell) is also pushed to the user's phones
 * — see PortalNotifier::toUsers() and MobileApiServiceProvider. Sending happens
 * after the HTTP response, so pages never wait for Firebase.
 */
class PushService
{
    /** Queue a push for these users (sent after the response). */
    public static function toUsers(array $userIds, string $title, string $body, array $data = []): void
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds))));
        if (!$userIds || !self::enabled()) return;

        try {
            SendPushNotification::dispatchAfterResponse($userIds, $title, $body, $data);
        } catch (\Throwable $e) {
            Log::warning('Push dispatch failed', ['error' => $e->getMessage()]);
        }
    }

    /** Send now (used by the job and by console commands). Returns messages delivered. */
    public static function sendNow(array $userIds, string $title, string $body, array $data = []): int
    {
        if (!self::enabled()) return 0;
        $fcm = app(FcmClient::class);
        if (!$fcm->configured()) return 0;

        // Users who turned push off in the app.
        if (Schema::hasTable('mobile_preferences')) {
            $off = DB::table('mobile_preferences')->whereIn('user_id', $userIds)->where('push_enabled', false)->pluck('user_id')->all();
            $userIds = array_values(array_diff($userIds, array_map('intval', $off)));
            if (!$userIds) return 0;
        }

        $devices = DB::table('mobile_devices')->whereIn('user_id', $userIds)->whereNotNull('push_token')->get(['id', 'push_token']);
        $ok = 0;
        $title = mb_substr($title, 0, 150);
        $body  = mb_substr($body, 0, 500);

        foreach ($devices as $d) {
            $r = $fcm->send($d->push_token, $title, $body, $data);
            if ($r === 'ok') {
                $ok++;
            } elseif ($r === 'invalid_token') {
                DB::table('mobile_devices')->where('id', $d->id)->delete();
            }
        }
        return $ok;
    }

    public static function enabled(): bool
    {
        static $ok = null;
        if ($ok !== null) return $ok;
        try {
            $ok = Schema::hasTable('mobile_devices') && config('services.fcm.enabled', true) !== false && app(FcmClient::class)->configured();
        } catch (\Throwable $e) {
            $ok = false;
        }
        return $ok;
    }
}
