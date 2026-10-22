<?php

namespace App\Console\Commands;

use App\Services\Meetings\MeetingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class SendMeetingReminders extends Command
{
    protected $signature = 'meetings:remind';

    protected $description = 'Remind attendees of meetings that start soon';

    public function handle(MeetingService $meetings): int
    {
        if (!Schema::hasTable('meetings')) return self::SUCCESS;

        $this->info('Reminded ' . $meetings->remindDue() . ' meetings.');
        return self::SUCCESS;
    }
}
