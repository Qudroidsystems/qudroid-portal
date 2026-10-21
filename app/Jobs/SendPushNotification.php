<?php

namespace App\Jobs;

use App\Services\Push\PushService;
use Illuminate\Foundation\Bus\Dispatchable;

/** Sends one push to a set of users' phones. Dispatched after the response. */
class SendPushNotification
{
    use Dispatchable;

    public function __construct(
        public array $userIds,
        public string $title,
        public string $body,
        public array $data = [],
    ) {}

    public function handle(): void
    {
        PushService::sendNow($this->userIds, $this->title, $this->body, $this->data);
    }
}
