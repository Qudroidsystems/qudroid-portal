<?php

namespace App\Services\Realtime;

use App\Events\Realtime\Poke;
use Illuminate\Support\Facades\Log;

/**
 * Instant updates through Laravel Reverb (websockets).
 *
 * Off unless BROADCAST_CONNECTION=reverb and the REVERB_* keys are set; then
 * every page still polls, just less often. Pokes are sent after the response
 * so a slow or stopped Reverb server never delays a click.
 *
 * Channels (all private):
 *   user.{id}        inbox, notification, call (ringing), signal (call set-up)
 *   connect.c.{id}   message, changed, typing in one conversation
 *   call.{id}        people (someone joined, left or changed mic/camera)
 */
class Realtime
{
    protected static array $queue = [];
    protected static bool $hooked = false;

    public static function enabled(): bool
    {
        return config('broadcasting.default') === 'reverb' && (string) config('broadcasting.connections.reverb.key') !== '';
    }

    /** What the browser needs to connect (null when off). */
    public static function clientConfig(): ?array
    {
        if (!self::enabled()) return null;
        $o = config('broadcasting.connections.reverb.options', []);
        $scheme = $o['scheme'] ?? 'https';

        return [
            'key' => config('broadcasting.connections.reverb.key'),
            'host' => $o['host'] ?? request()->getHost(),
            'port' => (int) ($o['port'] ?? ($scheme === 'https' ? 443 : 80)),
            'tls' => $scheme === 'https',
        ];
    }

    /** @param  string|string[]  $channels */
    public static function poke(string|array $channels, string $event, array $data = []): void
    {
        if (!self::enabled()) return;
        $key = $event . '|' . json_encode($data);
        self::$queue[$key] ??= ['event' => $event, 'data' => $data, 'channels' => []];
        foreach ((array) $channels as $c) self::$queue[$key]['channels'][$c] = true;
        if (!self::$hooked) {
            self::$hooked = true;
            app()->terminating(fn () => self::flush());
        }
    }

    public static function toUsers(array $userIds, string $event, array $data = []): void
    {
        self::poke(array_map(fn ($id) => 'user.' . (int) $id, array_unique($userIds)), $event, $data);
    }

    public static function flush(): void
    {
        $queue = self::$queue;
        self::$queue = [];
        self::$hooked = false;
        foreach ($queue as $item) {
            // Reverb (like Pusher) takes at most 100 channels per event.
            foreach (array_chunk(array_keys($item['channels']), 100) as $chunk) {
                try {
                    broadcast(new Poke($chunk, $item['event'], $item['data']));
                } catch (\Throwable $e) {
                    Log::warning('Realtime poke failed', ['event' => $item['event'], 'error' => $e->getMessage()]);
                }
            }
        }
    }

    /** For tests. */
    public static function pending(): array
    {
        return self::$queue;
    }
}
