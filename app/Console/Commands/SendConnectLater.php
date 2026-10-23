<?php

namespace App\Console\Commands;

use App\Services\Connect\LaterService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class SendConnectLater extends Command
{
    protected $signature = 'connect:later';

    protected $description = 'Send scheduled chat messages and reminders that are due';

    public function handle(LaterService $later): int
    {
        if (!Schema::hasTable('connect_scheduled')) return self::SUCCESS;
        $r = $later->runDue();
        if (array_sum($r)) $this->info("Sent {$r['sent']}, failed {$r['failed']}, reminders {$r['reminded']}.");

        return self::SUCCESS;
    }
}
