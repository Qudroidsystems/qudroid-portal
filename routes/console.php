<?php

use Illuminate\Support\Facades\Schedule;

/*
| Scheduler — needs ONE cron job running every minute:
|   * * * * * cd /path/to/qudroid-portal && php artisan schedule:run >> /dev/null 2>&1
|
| Modules add their own scheduled commands here as they are built.
*/

// Queued jobs (push notifications, e-mails) on hosts without a permanent queue worker.
// Remove this line once a Redis queue worker (php artisan queue:work) runs under Supervisor.
Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3')->everyMinute()->withoutOverlapping(5);

// Keep a year of activity history.
Schedule::command('activity:prune --days=365')->dailyAt('02:30');

// Read notifications older than 6 months are removed.
Schedule::command('notifications:prune --days=180')->dailyAt('02:45');
