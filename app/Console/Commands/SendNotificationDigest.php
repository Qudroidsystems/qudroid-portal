<?php

namespace App\Console\Commands;

use App\Mail\NotificationDigestMail;
use App\Models\User;
use App\Services\Messaging\NotificationPresenter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/** Each morning: e-mail people who asked for it the notifications they haven't read since the last summary. */
class SendNotificationDigest extends Command
{
    protected $signature = 'notifications:digest';

    protected $description = 'E-mail the daily summary of unread notifications';

    public function handle(): int
    {
        $sent = 0;
        User::where('notify_digest', true)->where('is_disabled', false)->chunkById(200, function ($users) use (&$sent) {
            foreach ($users as $user) {
                $since = $user->notify_digest_at ?? now()->subDay();
                $q = $user->unreadNotifications()->where('created_at', '>', $since);
                $total = (clone $q)->count();
                if ($total > 0) {
                    $items = $q->limit(12)->get()->map(fn ($n) => NotificationPresenter::item($n))->all();
                    try {
                        Mail::to($user->email, $user->name)->send(new NotificationDigestMail($user, $items, $total));
                        $sent++;
                    } catch (\Throwable $e) {
                        report($e);
                        continue;
                    }
                }
                $user->forceFill(['notify_digest_at' => now()])->save();
            }
        });
        $this->info("Sent {$sent} summaries.");

        return self::SUCCESS;
    }
}
