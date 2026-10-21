<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\Push\PushService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Settings the user chooses in the app: push notifications and lesson reminders. */
class PreferenceController extends ApiController
{
    public function show(Request $request)
    {
        return $this->ok($this->present($request->user()->id));
    }

    public function update(Request $request)
    {
        $d = $request->validate([
            'push_enabled'     => 'sometimes|boolean',
            'lesson_reminders' => 'sometimes|boolean',
            'reminder_minutes' => 'sometimes|nullable|integer|min:1|max:60',
        ]);

        $uid = $request->user()->id;
        $row = DB::table('mobile_preferences')->where('user_id', $uid)->first();
        $values = array_intersect_key($d, array_flip(['push_enabled', 'lesson_reminders', 'reminder_minutes'])) + ['updated_at' => now()];

        if ($row) {
            DB::table('mobile_preferences')->where('user_id', $uid)->update($values);
        } else {
            DB::table('mobile_preferences')->insert($values + ['user_id' => $uid, 'created_at' => now()]);
        }

        return $this->ok($this->present($uid), 'Settings saved.');
    }

    protected function present(int $uid): array
    {
        $row = DB::table('mobile_preferences')->where('user_id', $uid)->first();
        $default = (int) config('services.timetable_reminders.minutes', 10);
        return [
            'push_enabled'      => $row ? (bool) $row->push_enabled : true,
            'lesson_reminders'  => $row ? (bool) $row->lesson_reminders : true,
            'reminder_minutes'  => $row && $row->reminder_minutes ? (int) $row->reminder_minutes : $default,
            'default_minutes'   => $default,
            'push_available'    => PushService::enabled(),
        ];
    }
}
