<?php

namespace App\Console\Commands;

use App\Services\Connect\CallService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * php artisan calls:turn — show the STUN/TURN servers calls will use, so you can
 * check the TURN set-up after changing .env (run php artisan config:clear first).
 */
class CheckTurn extends Command
{
    protected $signature = 'calls:turn {--fresh : Fetch new logins from the provider instead of using the kept ones}';

    protected $description = 'Show the STUN and TURN servers that calls use';

    public function handle(CallService $calls): int
    {
        if ($this->option('fresh')) {
            Cache::forget('calls.turn.metered');
            Cache::forget('calls.turn.cloudflare');
        }
        $servers = $calls->iceServers();
        $turn = $calls->turnServers();

        foreach ($servers as $s) {
            foreach ((array) $s['urls'] as $url) {
                $this->line(str_pad(str_starts_with($url, 'stun') ? 'STUN' : 'TURN', 6) . $url . (isset($s['username']) ? '   (login ' . substr((string) $s['username'], 0, 24) . '…)' : ''));
            }
        }
        if (!$turn) {
            $this->warn('No TURN relay is set up. Calls between phones on mobile data will often connect without sound or picture.');
            $this->line('Set QUDROID_METERED_DOMAIN + QUDROID_METERED_API_KEY, or QUDROID_CLOUDFLARE_TURN_KEY_ID + QUDROID_CLOUDFLARE_TURN_TOKEN,');
            $this->line('or QUDROID_TURN_URL with QUDROID_TURN_USERNAME/QUDROID_TURN_CREDENTIAL (or QUDROID_TURN_SECRET for coturn). Then: php artisan config:clear');

            return self::FAILURE;
        }
        $this->info(count($turn) . ' TURN ' . (count($turn) === 1 ? 'entry' : 'entries') . ' ready. Calls will relay through it when a direct link is not possible.');

        return self::SUCCESS;
    }
}
