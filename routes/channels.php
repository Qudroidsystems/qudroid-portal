<?php

use App\Models\Connect\Call;
use App\Models\Connect\Conversation;
use App\Services\Connect\CallService;
use App\Services\Connect\ConnectService;
use Illuminate\Support\Facades\Broadcast;

/*
| Private websocket channels (see App\Services\Realtime\Realtime). Events on
| them only say that something changed; the data itself is fetched through
| the normal, access-checked endpoints.
*/

Broadcast::channel('user.{id}', fn ($user, $id) => (int) $user->id === (int) $id);

Broadcast::channel('connect.c.{id}', function ($user, $id) {
    $c = Conversation::find($id);

    return $c && app(ConnectService::class)->canView($c, $user);
});

Broadcast::channel('call.{id}', function ($user, $id) {
    $call = Call::find($id);

    return $call && app(CallService::class)->canJoin($call, $user);
});
