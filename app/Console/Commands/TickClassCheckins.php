<?php

namespace App\Console\Commands;

use App\Services\Checkin\CheckinService;
use Illuminate\Console\Command;

/** Every minute: tell students when class check-in opens, and close it (marking absentees) when the class ends. */
class TickClassCheckins extends Command
{
    protected $signature = 'attendance:tick';

    protected $description = 'Open class check-in with a reminder to students, and close it when the class ends';

    public function handle(CheckinService $checkin): int
    {
        [$reminded, $closed] = $checkin->tickClasses();
        $this->info("Reminded {$reminded} classes, closed {$closed}.");

        return self::SUCCESS;
    }
}
