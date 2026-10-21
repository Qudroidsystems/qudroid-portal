<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Saves the phone's push (FCM) token so the school can notify the app. */
class DeviceController extends ApiController
{
    public function store(Request $request)
    {
        $d = $request->validate([
            'push_token'  => 'required|string|max:512',
            'platform'    => 'nullable|in:android,ios,web',
            'device_name' => 'nullable|string|max:120',
            'app_version' => 'nullable|string|max:30',
        ]);

        $user = $request->user();
        DB::table('mobile_devices')->updateOrInsert(
            ['push_token' => $d['push_token']],
            [
                'user_id'      => $user->id,
                'token_id'     => $user->currentAccessToken()?->id,
                'platform'     => $d['platform'] ?? null,
                'device_name'  => $d['device_name'] ?? null,
                'app_version'  => $d['app_version'] ?? null,
                'last_seen_at' => now(),
                'updated_at'   => now(),
                'created_at'   => now(),
            ]
        );

        return $this->ok(null, 'Device registered.');
    }

    public function destroy(Request $request)
    {
        $request->validate(['push_token' => 'required|string|max:512']);
        DB::table('mobile_devices')->where('user_id', $request->user()->id)->where('push_token', $request->push_token)->delete();

        return $this->ok(null, 'Device removed.');
    }
}
