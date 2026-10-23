<?php

namespace App\Services\Messaging;

use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;

/** One shape for a notification, used by the web notification centre and the mobile API. */
class NotificationPresenter
{
    /** Colours for each group's app icon (iOS system colours). */
    public const COLORS = [
        'messages' => '#34c759', 'mail' => '#007aff', 'calls' => '#34c759', 'meetings' => '#ff9500', 'work' => '#5856d6',
        'files' => '#30b0c7', 'money' => '#34c759', 'people' => '#ff2d55', 'academy' => '#af52de', 'clients' => '#ff9500',
        'stock' => '#8e8e93', 'system' => '#8e8e93',
    ];

    /** Filters on the notification centre. */
    public const FILTERS = ['all' => 'All', 'unread' => 'Unread', 'mentions' => 'Mentions', 'approvals' => 'Approvals'];

    public static function item(DatabaseNotification $n): array
    {
        $d = (array) $n->data;
        $type = $d['type'] ?? 'system';
        $group = $d['group'] ?? (PortalNotifier::groupOf($type) ?? 'system');

        return [
            'id' => $n->id,
            'title' => $d['title'] ?? 'Notification',
            'body' => $d['body'] ?? '',
            'type' => $type,
            'group' => $group,
            'group_label' => PortalNotifier::GROUPS[$group][0] ?? 'Qudroid',
            'icon' => $d['icon'] ?? (PortalNotifier::ICONS[$type] ?? 'ri-notification-3-line'),
            'color' => self::COLORS[$group] ?? self::COLORS['system'],
            'category' => $d['category'] ?? PortalNotifier::categoryOf($type, $d['key'] ?? null),
            'level' => $d['level'] ?? PortalNotifier::levelOf($type),
            'thread' => $d['thread'] ?? PortalNotifier::threadOf($type, $d['key'] ?? null, $d['url'] ?? null),
            'actions' => empty($d['done']) ? array_values((array) ($d['actions'] ?? [])) : [],
            'done' => $d['done'] ?? null,
            'url' => $d['url'] ?? null,
            'open_url' => route('notifications.open', $n->id),
            'read' => (bool) $n->read_at,
            'created_at' => $n->created_at?->toIso8601String(),
            'time' => self::when($n),
        ];
    }

    /** "now", "5m", "2h", "Yesterday", "Mon", "12 Oct": short, like iOS. */
    public static function when(DatabaseNotification $n): string
    {
        $t = $n->created_at;
        if (!$t) return '';
        $mins = (int) $t->diffInMinutes(now());
        if ($mins < 1) return 'now';
        if ($mins < 60) return $mins . 'm ago';
        if ($t->isToday()) return (int) $t->diffInHours(now()) . 'h ago';
        if ($t->isYesterday()) return 'Yesterday';
        if ($t->gt(now()->subDays(6))) return $t->format('l');

        return $t->format($t->year === now()->year ? 'j M' : 'j M Y');
    }

    /** The person's notifications for a filter: all, unread, mentions, approvals, or a group key. */
    public static function query(User $user, ?string $filter)
    {
        $q = $user->notifications();
        if ($filter === 'unread') return $user->unreadNotifications();
        if ($filter === 'mentions') return $q->where('data', 'like', '%"category":"mention"%');
        if ($filter === 'approvals') return $q->where(fn ($w) => $w->where('data', 'like', '%"type":"approval"%')->orWhere('data', 'like', '%"type":"leave"%')
            ->orWhere('data', 'like', '%"target":{"kind":"expense"%'));
        if ($filter && isset(PortalNotifier::GROUPS[$filter])) {
            $types = PortalNotifier::GROUPS[$filter][2];

            return $q->where(fn ($w) => collect($types)->each(fn ($t) => $w->orWhere('data', 'like', '%"type":"' . $t . '"%')));
        }

        return $q;
    }

    /** Items stacked by thread: the newest of each thread, with how many more are under it. */
    public static function stacks(Collection $items): array
    {
        return $items->groupBy('thread')->map(function ($group) {
            $top = $group->first();
            $top['stack'] = $group->count() - 1;
            $top['stack_unread'] = $group->where('read', false)->count();
            $top['stack_ids'] = $group->pluck('id')->values()->all();
            $top['more'] = $group->slice(1, 5)->map(fn ($i) => array_intersect_key($i, array_flip(['id', 'title', 'body', 'time', 'read', 'open_url'])))->values()->all();

            return $top;
        })->values()->all();
    }

    /** Settings shown on the notifications page and in the app. */
    public static function settings(User $user): array
    {
        return [
            'groups' => collect(PortalNotifier::GROUPS)->map(fn ($g, $k) => ['key' => $k, 'label' => $g[0], 'icon' => $g[1], 'color' => self::COLORS[$k] ?? '#8e8e93',
                'on' => !in_array($k, (array) $user->muted_notifications, true)])->values()->all(),
            'quiet_from' => $user->notify_quiet_from ? substr((string) $user->notify_quiet_from, 0, 5) : null,
            'quiet_to' => $user->notify_quiet_to ? substr((string) $user->notify_quiet_to, 0, 5) : null,
            'in_quiet_hours' => PortalNotifier::inQuietHours($user),
            'digest' => (bool) $user->notify_digest,
        ];
    }
}
