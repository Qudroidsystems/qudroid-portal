<?php

namespace App\Events\Realtime;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/**
 * A small "something changed" signal over the websocket. Pages and the app
 * react by fetching what changed through the normal endpoints, so nothing
 * private travels in the event and polling stays the fallback.
 */
class Poke implements ShouldBroadcastNow
{
    /** @param  string[]  $channels  private channel names, e.g. "connect.c.12", "user.5" */
    public function __construct(public array $channels, public string $event, public array $data = []) {}

    public function broadcastOn(): array
    {
        return array_map(fn ($c) => new PrivateChannel($c), $this->channels);
    }

    public function broadcastAs(): string
    {
        return $this->event;
    }

    public function broadcastWith(): array
    {
        return $this->data;
    }
}
