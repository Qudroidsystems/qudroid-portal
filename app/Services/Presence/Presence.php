<?php

namespace App\Services\Presence;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Who is available. Someone's state is what they chose (away, busy, do not
 * disturb) or, on automatic, what the portal sees: on a call, in a meeting,
 * online (seen in the last two minutes), away (seen in the last 15) or offline.
 * A status (emoji and text) is shown alongside and can clear itself.
 */
class Presence
{
    public const CHOICES = [
        'auto' => 'Automatic',
        'away' => 'Away',
        'busy' => 'Busy',
        'dnd'  => 'Do not disturb',
    ];

    public const STATES = [
        'online'  => ['label' => 'Online', 'color' => '#22c55e'],
        'call'    => ['label' => 'On a call', 'color' => '#ef4444'],
        'meeting' => ['label' => 'In a meeting', 'color' => '#ef4444'],
        'busy'    => ['label' => 'Busy', 'color' => '#ef4444'],
        'dnd'     => ['label' => 'Do not disturb', 'color' => '#b42318'],
        'away'    => ['label' => 'Away', 'color' => '#f59e0b'],
        'offline' => ['label' => 'Offline', 'color' => '#94a3b8'],
    ];

    public const PRESETS = [
        ['📅', 'In a meeting'], ['🚗', 'Commuting'], ['🤒', 'Out sick'], ['🌴', 'On leave'], ['🏠', 'Working remotely'], ['🎯', 'Focusing'],
    ];

    /** Busy-ness found from meetings and calls, for a set of people (one query each). */
    protected array $meeting = [];
    protected array $call = [];
    protected bool $loaded = false;

    public static function make(): static
    {
        return app(static::class);
    }

    /** Load meeting and call state for these people in two queries. Call before showing many. */
    public function prime(iterable $userIds): static
    {
        $ids = collect($userIds)->filter()->unique()->values()->all();
        if (!$ids) return $this;
        $now = now();
        try {
            if (Schema::hasTable('meetings')) {
                $inMeeting = DB::table('meetings as m')->where('m.status', 'scheduled')->where('m.starts_at', '<=', $now)->where('m.ends_at', '>', $now)
                    ->leftJoin('meeting_attendees as a', fn ($j) => $j->on('a.meeting_id', '=', 'm.id')->whereIn('a.user_id', $ids)->where('a.response', '!=', 'declined'))
                    ->get(['m.organizer_id', 'a.user_id']);
                foreach ($inMeeting as $r) {
                    if ($r->user_id) $this->meeting[(int) $r->user_id] = true;
                    if (in_array((int) $r->organizer_id, $ids, true)) $this->meeting[(int) $r->organizer_id] = true;
                }
            }
            if (Schema::hasTable('connect_call_people')) {
                DB::table('connect_call_people as p')->join('connect_calls as c', 'c.id', '=', 'p.call_id')
                    ->whereNull('c.ended_at')->whereIn('p.user_id', $ids)->whereNotNull('p.joined_at')->whereNull('p.left_at')
                    ->where('p.seen_at', '>=', $now->copy()->subSeconds(60))
                    ->pluck('p.user_id')->each(fn ($id) => $this->call[(int) $id] = true);
            }
        } catch (\Throwable $e) {
            // presence is a nicety; never break a page over it
        }
        foreach ($ids as $id) { $this->meeting[$id] ??= false; $this->call[$id] ??= false; }

        return $this;
    }

    public static function isDnd(?User $u): bool
    {
        return $u && ($u->presence ?? 'auto') === 'dnd' && (!$u->presence_until || $u->presence_until->isFuture());
    }

    /** These people minus anyone on do not disturb right now. */
    public static function withoutDnd(array $userIds): array
    {
        if (!$userIds) return [];
        try {
            $dnd = User::whereIn('id', $userIds)->where('presence', 'dnd')
                ->where(fn ($q) => $q->whereNull('presence_until')->orWhere('presence_until', '>', now()))->pluck('id')->all();
        } catch (\Throwable $e) {
            return array_values($userIds);   // before migrate
        }

        return array_values(array_diff($userIds, $dnd));
    }

    /** The status someone set, unless it has run out. */
    public static function status(?User $u): ?array
    {
        if (!$u || (!$u->status_text && !$u->status_emoji)) return null;
        if ($u->status_until && $u->status_until->isPast()) return null;

        return ['emoji' => $u->status_emoji, 'text' => $u->status_text, 'until' => $u->status_until];
    }

    /**
     * @return array{state: string, label: string, color: string, detail: ?string, emoji: ?string, text: ?string, dnd: bool, online: bool}
     */
    public function of(?User $u): array
    {
        if (!$u) return ['state' => 'offline', 'label' => 'Offline', 'color' => self::STATES['offline']['color'], 'detail' => null, 'emoji' => null, 'text' => null, 'dnd' => false, 'online' => false];
        if (!array_key_exists($u->id, $this->call)) $this->prime([$u->id]);

        $seen = $u->last_seen_at;
        $online = $seen && $seen->gt(now()->subMinutes(2));
        $chosen = $u->presence ?? 'auto';
        if ($chosen !== 'auto' && $u->presence_until && $u->presence_until->isPast()) $chosen = 'auto';

        $state = match (true) {
            $chosen === 'dnd'   => 'dnd',
            $this->call[$u->id] ?? false => 'call',
            $chosen === 'busy'  => 'busy',
            $this->meeting[$u->id] ?? false => $online || $seen?->gt(now()->subHour()) ? 'meeting' : 'offline',
            $chosen === 'away'  => 'away',
            $online             => 'online',
            $seen && $seen->gt(now()->subMinutes(15)) => 'away',
            default             => 'offline',
        };
        $detail = null;
        if (in_array($state, ['dnd', 'busy', 'away'], true) && $chosen === $state && $u->presence_until) {
            $detail = 'until ' . self::when($u->presence_until);
        } elseif ($state === 'offline' && $seen) {
            $detail = 'last seen ' . $seen->diffForHumans();
        }
        $status = self::status($u);

        return [
            'state'  => $state,
            'label'  => self::STATES[$state]['label'],
            'color'  => self::STATES[$state]['color'],
            'detail' => $detail,
            'emoji'  => $status['emoji'] ?? null,
            'text'   => $status['text'] ?? null,
            'dnd'    => $state === 'dnd',
            'online' => $online,
        ];
    }

    /** One line, e.g. "Do not disturb until 3:00 pm · 🌴 On leave". */
    public function line(?User $u): string
    {
        $p = $this->of($u);
        $s = $p['state'] === 'offline' && $p['detail'] ? ucfirst($p['detail']) : $p['label'] . ($p['detail'] ? ' ' . $p['detail'] : '');
        if ($p['text'] || $p['emoji']) $s .= ' · ' . trim(($p['emoji'] ?? '') . ' ' . ($p['text'] ?? ''));

        return $s;
    }

    public static function when(Carbon $t): string
    {
        return $t->isToday() ? $t->format('g:i a') : ($t->isTomorrow() ? 'tomorrow ' . $t->format('g:i a') : $t->format('D j M, g:i a'));
    }

    /** Turn a choice like "60", "today", "tomorrow8", "week" or "" into an end time. */
    public static function until(?string $choice): ?Carbon
    {
        return match (true) {
            $choice === null || $choice === '' || $choice === 'never' => null,
            ctype_digit($choice) => now()->addMinutes(min((int) $choice, 60 * 24 * 14)),
            $choice === 'today' => now()->endOfDay(),
            $choice === 'tomorrow8' => now()->addDay()->setTime(8, 0),
            $choice === 'week' => now()->endOfWeek(),
            default => null,
        };
    }
}
