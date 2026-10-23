<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\StatusController as WebStatus;
use App\Services\Presence\Presence;
use Illuminate\Http\Request;

/** Status and do-not-disturb for the app: same rules as the portal. */
class StatusController extends ApiController
{
    public function show(Request $request, Presence $presence, WebStatus $web)
    {
        return $this->ok($web->payload($request->user(), $presence));
    }

    public function update(Request $request, Presence $presence, WebStatus $web)
    {
        return $this->ok($web->save($request, $presence), 'Status updated.');
    }
}
