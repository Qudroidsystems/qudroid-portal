<?php

namespace App\Services\Messaging;

use App\Models\User;
use App\Notifications\PortalNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Writes in-portal notifications (the bell). Bulk-inserts straight into the
 * notifications table so a company-wide announcement stays fast. A `key` makes a
 * notification idempotent per user (e.g. "task:12:assigned").
 */
class PortalNotifier
{
    public const ICONS = [
        'system'      => 'ri-notification-3-line',
        'announcement'=> 'ri-broadcast-line',
        'approval'    => 'ri-shield-check-line',
        'task'        => 'ri-task-line',
        'project'     => 'ri-folder-chart-line',
        'message'     => 'ri-chat-3-line',
        'meeting'     => 'ri-video-chat-line',
        'drive'       => 'ri-folder-cloud-line',
        'course'      => 'ri-book-open-line',
        'payment'     => 'ri-money-dollar-circle-line',
        'invoice'     => 'ri-bill-line',
        'inventory'   => 'ri-archive-line',
        'leave'       => 'ri-calendar-event-line',
        'payroll'     => 'ri-wallet-3-line',
        'certificate' => 'ri-award-line',
        'client'      => 'ri-briefcase-4-line',
        'support'     => 'ri-customer-service-2-line',
    ];

    public static function available(): bool
    {
        static $ok = null;
        return $ok ??= Schema::hasTable('notifications');
    }

    /** @param int[] $userIds */
    public static function toUsers(array $userIds, string $title, string $body, ?string $url = null, string $type = 'system', ?string $key = null): int
    {
        if (!self::available()) return 0;
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds))));
        if (!$userIds) return 0;

        try {
            if ($key) {
                $already = DB::table('notifications')
                    ->where('notifiable_type', User::class)
                    ->whereIn('notifiable_id', $userIds)
                    ->where('data', 'like', '%"key":' . json_encode($key) . '%')
                    ->pluck('notifiable_id')->map(fn ($v) => (int) $v)->all();
                $userIds = array_values(array_diff($userIds, $already));
            }

            $data = json_encode([
                'title' => mb_substr($title, 0, 150),
                'body'  => mb_substr($body, 0, 1000),
                'url'   => $url,
                'type'  => $type,
                'icon'  => self::ICONS[$type] ?? self::ICONS['system'],
                'key'   => $key,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            $now = now();
            foreach (array_chunk($userIds, 500) as $chunk) {
                DB::table('notifications')->insert(array_map(fn ($uid) => [
                    'id' => (string) Str::uuid(), 'type' => PortalNotification::class,
                    'notifiable_type' => User::class, 'notifiable_id' => $uid,
                    'data' => $data, 'read_at' => null, 'created_at' => $now, 'updated_at' => $now,
                ], $chunk));
            }

            // The same message as a push to the mobile app (sent after the response).
            \App\Services\Push\PushService::toUsers($userIds, $title, $body, ['type' => $type, 'url' => (string) $url, 'key' => (string) $key]);

            return count($userIds);
        } catch (\Throwable $e) {
            Log::warning('Portal notification failed', ['error' => $e->getMessage()]);
            return 0;
        }
    }

    /** Every active account of one type (staff, student, client, partner). */
    public static function userIdsOfType(string $type): array
    {
        return User::where('user_type', $type)->where('is_disabled', false)->pluck('id')->all();
    }

    public static function staffIds(): array
    {
        return self::userIdsOfType(User::TYPE_STAFF);
    }

    /** Users who hold a permission (directly or through a role). */
    public static function userIdsWithPermission(string $permission): array
    {
        try {
            return User::permission($permission)->where('is_disabled', false)->pluck('id')->all();
        } catch (\Throwable $e) {
            return [];
        }
    }
}
