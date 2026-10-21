<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Api\V1\ApiController;
use App\Services\Api\TimetableGrid;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** The staff home screen: today's lessons, quick counts, and the weekly timetable. */
class HomeController extends ApiController
{
    public function home(Request $request)
    {
        $user = $request->user();
        $sessionId = $this->currentSessionId();
        $termId = $this->currentTermId();

        $week = Schema::hasTable('timetable_slots') ? TimetableGrid::forTeacher($user->id, $sessionId) : [];
        $today = collect($week)->firstWhere('day', Carbon::now()->format('l'))['lessons'] ?? [];

        $subjects = DB::table('subjectteacher')->where('staffid', $user->id)
            ->when($sessionId, fn ($q) => $q->where('sessionid', $sessionId))
            ->when($termId, fn ($q) => $q->where('termid', $termId))->count();

        $classes = DB::table('classteacher')->where('staffid', $user->id)
            ->when($sessionId, fn ($q) => $q->where('sessionid', $sessionId))
            ->when($termId, fn ($q) => $q->where('termid', $termId))->count();

        $staff = DB::table('staffbioinfo')->where('userid', $user->id)->first();
        $pendingLeave = $staff ? DB::table('leave_requests')->where('staff_id', $staff->id)
            ->whereIn('status', ['pending_hod', 'pending_principal'])->count() : 0;

        // The next lesson today (or the one in progress).
        $now = Carbon::now()->format('H:i');
        $next = null;
        foreach ($today as $l) {
            if (($l['end_time'] ?? '') > $now) {
                $start = Carbon::parse(Carbon::today()->toDateString() . ' ' . $l['start_time']);
                $next = $l + [
                    'in_progress'       => ($l['start_time'] ?? '') <= $now,
                    'starts_in_minutes' => max(0, (int) ceil(Carbon::now()->diffInSeconds($start, false) / 60)),
                ];
                break;
            }
        }

        return $this->ok([
            'current'         => $this->currentPeriod(),
            'today'           => Carbon::now()->format('l'),
            'today_lessons'   => $today,
            'next_lesson'     => $next,
            'subject_count'   => $subjects,
            'class_count'     => $classes,
            'pending_leave'   => $pendingLeave,
        ]);
    }

    public function timetable(Request $request)
    {
        $sessionId = (int) ($request->get('session_id') ?: $this->currentSessionId());
        return $this->ok(Schema::hasTable('timetable_slots') ? TimetableGrid::forTeacher($request->user()->id, $sessionId) : []);
    }
}
