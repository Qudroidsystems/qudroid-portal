<?php

namespace App\Console\Commands;

use App\Services\Workflows\Engine;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class RunWorkflows extends Command
{
    protected $signature = 'workflows:tick';

    protected $description = 'Resume waiting workflow runs, check for overdue tasks and invoices, and start scheduled workflows';

    public function handle(): int
    {
        if (!Schema::hasTable('workflows')) return self::SUCCESS;
        $r = Engine::tick();
        if (array_sum($r)) $this->info("Resumed {$r['resumed']}, picked up {$r['leftover']}, overdue {$r['scanned']}, scheduled {$r['scheduled']}.");

        return self::SUCCESS;
    }
}
