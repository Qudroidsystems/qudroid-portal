<?php

namespace App\Providers;

use App\Services\Push\PushService;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Mobile app wiring that is not tied to a route: notifications stored with
 * Laravel's "database" channel are also pushed to the user's phones.
 * (Notifications written through PortalNotifier push from there.)
 */
class MobileApiServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(NotificationSent::class, function (NotificationSent $event) {
            if ($event->channel !== 'database' || !($event->notifiable instanceof \App\Models\User)) return;
            if ($event->notification instanceof \App\Notifications\PortalNotification) return;

            try {
                $data = method_exists($event->notification, 'toDatabase')
                    ? $event->notification->toDatabase($event->notifiable)
                    : $event->notification->toArray($event->notifiable);
                $title = $data['title'] ?? null;
                if (!$title) return;
                PushService::toUsers([$event->notifiable->id], (string) $title, (string) ($data['body'] ?? $data['message'] ?? ''), [
                    'type' => (string) ($data['type'] ?? 'system'),
                ]);
            } catch (\Throwable $e) {
                // Push is best-effort.
            }
        });
    }
}
