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
        'mail'        => 'ri-mail-line',
        'call'        => 'ri-phone-line',
        'reminder'    => 'ri-alarm-line',
        'meeting'     => 'ri-video-chat-line',
        'drive'       => 'ri-folder-3-line',
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

    /** Kinds of notification people can filter by and switch off. `system` and `announcement` can't be switched off. */
    public const GROUPS = [
        'messages' => ['Messages', 'ri-chat-3-line', ['message']],
        'mail'     => ['E-mail', 'ri-mail-line', ['mail']],
        'calls'    => ['Calls', 'ri-phone-line', ['call']],
        'meetings' => ['Meetings', 'ri-video-chat-line', ['meeting']],
        'work'     => ['Tasks & projects', 'ri-task-line', ['task', 'project']],
        'files'    => ['Files', 'ri-folder-3-line', ['drive']],
        'money'    => ['Money', 'ri-money-dollar-circle-line', ['payment', 'invoice', 'payroll']],
        'people'   => ['Leave & approvals', 'ri-shield-check-line', ['leave', 'approval']],
        'academy'  => ['Academy', 'ri-book-open-line', ['course', 'certificate']],
        'clients'  => ['Clients & support', 'ri-briefcase-4-line', ['client', 'crm', 'support']],
        'stock'    => ['Stock', 'ri-archive-line', ['inventory']],
    ];

    /**
     * How loudly each kind arrives. urgent: a banner and a sound on the web, a
     * time-sensitive push. normal: a banner and a push. passive: only the
     * notification centre (no banner, no sound, a quiet push).
     */
    public const URGENT = ['call', 'approval', 'leave', 'meeting'];
    public const PASSIVE = ['system', 'drive', 'inventory', 'certificate'];

    /** The mobile app's categories (Android channels, iOS categories). */
    public const CATEGORIES = [
        'message' => 'message', 'call' => 'call', 'meeting' => 'meeting', 'task' => 'task', 'project' => 'task',
        'approval' => 'approval', 'leave' => 'approval', 'payment' => 'finance', 'invoice' => 'finance', 'payroll' => 'finance',
        'course' => 'academy', 'certificate' => 'academy', 'mail' => 'mail',
    ];

    public static function levelOf(string $type): string
    {
        return in_array($type, self::URGENT, true) ? 'urgent' : (in_array($type, self::PASSIVE, true) ? 'passive' : 'normal');
    }

    public static function categoryOf(string $type, ?string $key = null): string
    {
        if ($type === 'message' && $key && str_starts_with($key, 'connect-mention:')) return 'mention';

        return self::CATEGORIES[$type] ?? 'system';
    }

    /** Is the person in their quiet hours right now? */
    public static function inQuietHours(User $user, ?\Illuminate\Support\Carbon $at = null): bool
    {
        $from = $user->notify_quiet_from ?? null;
        $to = $user->notify_quiet_to ?? null;
        if (!$from || !$to) return false;
        $now = ($at ?? now())->format('H:i');
        $from = substr((string) $from, 0, 5);
        $to = substr((string) $to, 0, 5);

        return $from <= $to ? ($now >= $from && $now < $to) : ($now >= $from || $now < $to);   // e.g. 22:00 to 07:00
    }

    public static function groupOf(string $type): ?string
    {
        foreach (self::GROUPS as $key => [, , $types]) if (in_array($type, $types, true)) return $key;

        return null;
    }

    public static function available(): bool
    {
        static $ok = null;
        return $ok ??= Schema::hasTable('notifications');
    }

    /** @param int[] $userIds */
    /**
     * $extra (all optional):
     *   actions  buttons shown on the notification: [['id' => 'approve', 'label' => 'Approve', 'style' => 'primary'|'destructive'|null, 'input' => 'Reason'|null]]
     *            run through NotificationActions with `target`
     *   target   what the actions act on: ['kind' => 'leave', 'id' => 12]
     *   thread   notifications with the same thread stack together (defaults to the key's prefix)
     *   level    urgent | normal | passive (defaults by type)
     */
    public static function toUsers(array $userIds, string $title, string $body, ?string $url = null, string $type = 'system', ?string $key = null, array $extra = []): int
    {
        if (!self::available()) return 0;
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds))));
        if (!$userIds) return 0;

        try {
            // people who switched this kind of notification off
            if ($group = self::groupOf($type)) {
                $muted = User::whereIn('id', $userIds)->whereNotNull('muted_notifications')->get(['id', 'muted_notifications'])
                    ->filter(fn ($u) => in_array($group, (array) $u->muted_notifications, true))->pluck('id')->all();
                $userIds = array_values(array_diff($userIds, $muted));
                if (!$userIds) return 0;
            }

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
                'group' => self::groupOf($type) ?? 'system',
                'category' => self::categoryOf($type, $key),
                'level' => $extra['level'] ?? self::levelOf($type),
                'thread' => $extra['thread'] ?? self::threadOf($type, $key, $url),
                'actions' => array_values($extra['actions'] ?? []) ?: null,
                'target' => $extra['target'] ?? null,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            $now = now();
            $ids = [];
            foreach ($userIds as $uid) $ids[$uid] = (string) Str::uuid();
            foreach (array_chunk($userIds, 500) as $chunk) {
                DB::table('notifications')->insert(array_map(fn ($uid) => [
                    'id' => $ids[$uid], 'type' => PortalNotification::class,
                    'notifiable_type' => User::class, 'notifiable_id' => $uid,
                    'data' => $data, 'read_at' => null, 'created_at' => $now, 'updated_at' => $now,
                ], $chunk));
            }

            // The same message as a push to the mobile app (sent after the response).
            // People on do not disturb still get the notification, but not the phone buzz.
            \App\Services\Realtime\Realtime::toUsers($userIds, 'notification', ['type' => $type]);

            // Calls send their own ringing push (CallService), so the bell entry is enough here.
            // Quiet hours hold back everything but urgent items; the notification still waits in the centre.
            $level = $extra['level'] ?? self::levelOf($type);
            $pushTo = $type === 'call' ? [] : \App\Services\Presence\Presence::withoutDnd($userIds);
            if ($pushTo && $level !== 'urgent') {
                $quiet = User::whereIn('id', $pushTo)->whereNotNull('notify_quiet_from')->get()->filter(fn ($u) => self::inQuietHours($u))->pluck('id')->all();
                $pushTo = array_values(array_diff($pushTo, $quiet));
            }
            if ($pushTo) {
                $d = json_decode($data, true);
                \App\Services\Push\PushService::toUsers($pushTo, $title, $body, [
                    'type' => $type, 'url' => (string) $url, 'key' => (string) $key, 'category' => $d['category'], 'group' => $d['group'],
                    'thread_id' => (string) $d['thread'], 'level' => $level,
                    'actions' => $d['actions'] ? json_encode(array_map(fn ($a) => ['id' => $a['id'], 'label' => $a['label'], 'input' => $a['input'] ?? null], $d['actions'])) : '',
                    'notification_key' => (string) $key,
                    'badge' => 'auto',
                    'notification_ids' => array_intersect_key($ids, array_flip($pushTo)),
                ]);
            }

            return count($userIds);
        } catch (\Throwable $e) {
            Log::warning('Portal notification failed', ['error' => $e->getMessage()]);
            return 0;
        }
    }

    /** Notifications from the same conversation, task or approval stack together. */
    public static function threadOf(string $type, ?string $key, ?string $url): string
    {
        if ($key) {
            // "connect:12:345" → "connect:12"; "task:12:assigned" → "task:12"
            $parts = explode(':', $key);

            return implode(':', array_slice($parts, 0, min(2, count($parts))));
        }

        return $type . ':' . substr(md5((string) $url), 0, 8);
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
