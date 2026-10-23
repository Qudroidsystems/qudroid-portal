<?php

namespace App\Console\Commands;

use App\Models\Mail\MailAccount;
use App\Services\Mail\MailService;
use App\Services\Mail\MailSync;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class SyncMail extends Command
{
    protected $signature = 'mail:sync {--account= : Only this mailbox (id or address)}';

    protected $description = 'Fetch new Qmail mail, bring snoozed mail back and send scheduled messages';

    public function handle(MailSync $sync, MailService $mail): int
    {
        if (!Schema::hasTable('mail_accounts')) return self::SUCCESS;
        $one = $this->option('account');
        $accounts = MailAccount::query()
            ->when($one, fn ($q) => $q->where(fn ($w) => $w->whereKey((int) $one)->orWhere('email', strtolower((string) $one))))
            // A mailbox that keeps failing is tried every 15 minutes, not every minute.
            ->when(!$one, fn ($q) => $q->where(fn ($w) => $w->where('status', '!=', 'error')->orWhereNull('last_synced_at')->orWhere('last_synced_at', '<=', now()->subMinutes(15))))
            ->whereHas('users')->orderBy('last_synced_at')->get();

        $new = $errors = 0;
        $started = microtime(true);
        foreach ($accounts as $a) {
            // Leave the rest for the next minute rather than overlapping.
            if (!$one && microtime(true) - $started > 50) break;
            $r = $sync->sync($a);
            $new += $r['new'];
            if (!$r['ok']) {
                $errors++;
                $this->warn($a->email . ': ' . ($r['error'] ?? 'failed'));
            }
        }
        $woke = $mail->wakeSnoozed();
        $s = $mail->sendScheduled();
        if ($new || $errors || $woke || $s['sent'] || $s['failed'] || $one) {
            $this->info("Mailboxes {$accounts->count()}, new {$new}, problems {$errors}, unsnoozed {$woke}, scheduled sent {$s['sent']}, failed {$s['failed']}.");
        }

        return self::SUCCESS;
    }
}
