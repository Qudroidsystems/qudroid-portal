<?php

namespace App\Services\Push;

use App\Jobs\SendPushNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Push notifications to the Qlouds Workspace mobile app.
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

        $ok = 0;
        $title = mb_substr($title, 0, 150);
        $body  = mb_substr($body, 0, 500);

        // Each phone gets its own unread total (the app badge) and, for bell items, the id of its
        // own copy of the notification, so buttons on the push can call the notification API.
        $badge = ($data['badge'] ?? null) === 'auto';
        $ids = (array) ($data['notification_ids'] ?? []);
        unset($data['notification_ids']);
        $devices = DB::table('mobile_devices')->whereIn('user_id', $userIds)->whereNotNull('push_token')->get(['id', 'user_id', 'push_token']);
        $unread = $badge && $devices->isNotEmpty() && Schema::hasTable('notifications')
            ? DB::table('notifications')->where('notifiable_type', \App\Models\User::class)->whereIn('notifiable_id', $devices->pluck('user_id')->unique()->all())
                ->whereNull('read_at')->groupBy('notifiable_id')->selectRaw('notifiable_id, count(*) as n')->pluck('n', 'notifiable_id')->all()
            : [];

        foreach ($devices as $d) {
            $mine = $data;
            if ($badge) $mine['badge'] = (int) ($unread[$d->user_id] ?? 0);
            if (isset($ids[$d->user_id])) $mine['notification_id'] = $ids[$d->user_id];
            $r = $fcm->send($d->push_token, $title, $body, $mine);
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
        return once(function () {
            try {
                return Schema::hasTable('mobile_devices') && config('services.fcm.enabled', true) !== false && app(FcmClient::class)->configured();
            } catch (\Throwable $e) {
                return false;
            }
        });
    }
}
