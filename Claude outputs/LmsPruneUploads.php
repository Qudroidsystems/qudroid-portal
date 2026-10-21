<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Delete abandoned chunked-upload temp files (storage/app/lms-tmp/*.part) that
 * were never finalised. Runs daily.
 */
class LmsPruneUploads extends Command
{
    protected $signature = 'lms:prune-uploads {--hours=24 : Delete part-files older than this}';
    protected $description = 'Remove stale LMS chunked-upload temp files';

    public function handle(): int
    {
        $dir = storage_path('app/lms-tmp');
        if (!is_dir($dir)) { $this->info('Nothing to prune.'); return self::SUCCESS; }

        $cutoff = now()->subHours(max(1, (int) $this->option('hours')))->getTimestamp();
        $removed = 0;
        foreach (glob($dir . '/*.part') ?: [] as $f) {
            if (@filemtime($f) < $cutoff && @unlink($f)) $removed++;
        }
        $this->info("Pruned {$removed} stale upload part(s).");
        return self::SUCCESS;
    }
}
