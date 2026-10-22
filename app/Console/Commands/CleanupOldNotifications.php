<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CleanupOldNotifications extends Command
{
    protected $signature = 'notifications:prune {--days=180 : remove read notifications older than this}';

    protected $description = 'Delete read notifications older than the given number of days';

    public function handle(): int
    {
        if (!Schema::hasTable('notifications')) return self::SUCCESS;

        $n = DB::table('notifications')
            ->whereNotNull('read_at')
            ->where('created_at', '<', now()->subDays((int) $this->option('days')))
            ->delete();

        $this->info("Removed {$n} old notifications.");
        return self::SUCCESS;
    }
}
