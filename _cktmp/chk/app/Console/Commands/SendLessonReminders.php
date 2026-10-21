<?php

namespace App\Console\Commands;

use App\Services\Push\PushService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pushes "your lesson starts in N minutes" to teachers' phones.
 *
 * Runs every minute from the scheduler. Uses the published, active timetable
 * of the current session; a substitute assigned for today gets the reminder
 * instead of the usual teacher. Skips weekends' empty days and full-day
 * school holidays. Each teacher can turn reminders off or change the lead
 * time in the app (mobile_preferences); the school default is
 * TIMETABLE_REMINDER_MINUTES (10).
 */
class SendLessonReminders extends Command
{
    protected $signature = 'timetable:lesson-reminders {--dry-run : List what would be sent}';
    protected $description = 'Push upcoming-lesson reminders to teachers on the mobile app';

    public function handle(): int
    {
        if (!Schema::hasTable('timetable_slots') || !Schema::hasTable('mobile_devices')) return self::SUCCESS;
        if (!PushService::enabled() && !$this->option('dry-run')) return self::SUCCESS;

        $now = Carbon::now();
        if ($this->isHoliday($now)) return self::SUCCESS;

        $sessionId = DB::table('schoolsession')->where('status', 'Current')->value('id');
        $default = max(1, (int) config('services.timetable_reminders.minutes', 10));
        $hasPublished = Schema::hasColumn('timetable_settings', 'is_published');

        $slots = DB::table('timetable_slots as ts')
            ->join('timetable_settings as st', 'st.id', '=', 'ts.setting_id')
            ->join('timetable_periods as p', 'p.id', '=', 'ts.period_id')
            ->leftJoin('subject as sj', 'sj.id', '=', 'ts.subject_id')
            ->leftJoin('schoolclass as c', 'c.id', '=', 'st.schoolclass_id')
            ->leftJoin('schoolarm as a', 'a.id', '=', 'c.arm')
            ->when(Schema::hasTable('rooms'), fn ($q) => $q->leftJoin('rooms as r', 'r.id', '=', 'ts.room_id'))
            ->where('ts.day', $now->format('l'))
            ->where('st.is_active', 1)
            ->when($hasPublished, fn ($q) => $q->where('st.is_published', 1))
            ->when($sessionId, fn ($q) => $q->where('st.session_id', $sessionId))
            ->where(fn ($q) => $q->whereNull('ts.is_free')->orWhere('ts.is_free', 0))
            ->whereNotNull('ts.teacher_id')
            ->where('p.is_break', 0)
            ->get(array_merge([
                'ts.id', 'ts.teacher_id', 'p.start_time', 'p.name as period', 'sj.subject',
                DB::raw("TRIM(CONCAT(COALESCE(c.schoolclass,''), ' ', COALESCE(a.arm,''))) as class_name"),
            ], Schema::hasTable('rooms') ? ['r.room_name as room'] : [DB::raw('NULL as room')]));

        if ($slots->isEmpty()) return self::SUCCESS;

        // Today's substitutes take over the reminder.
        $subs = Schema::hasTable('substitute_assignments')
            ? DB::table('substitute_assignments')->whereDate('assignment_date', $now->toDateString())
                ->whereNotIn('status', ['rejected', 'cancelled', 'declined'])
                ->pluck('substitute_teacher_id', 'slot_id')
            : collect();

        $prefs = Schema::hasTable('mobile_preferences')
            ? DB::table('mobile_preferences')->get()->keyBy('user_id')
            : collect();

        $sent = 0;
        foreach ($slots as $s) {
            if (!$s->start_time) continue;
            $teacherId = (int) ($subs[$s->id] ?? $s->teacher_id);
            $pref = $prefs[$teacherId] ?? null;
            if ($pref && (!$pref->lesson_reminders || !$pref->push_enabled)) continue;

            $lead = (int) ($pref?->reminder_minutes ?? $default) ?: $default;
            $start = Carbon::parse($now->toDateString() . ' ' . $s->start_time);
            $remindAt = $start->copy()->subMinutes($lead);

            // Window of two minutes so a late scheduler run does not miss it.
            if ($now->lt($remindAt) || $now->gte($start) || $now->diffInSeconds($remindAt, true) > 120) continue;

            $key = 'lesson-remind:' . $s->id . ':' . $teacherId . ':' . $now->toDateString();
            if (Cache::has($key)) continue;

            $mins = max(1, (int) ceil($now->diffInSeconds($start, true) / 60));
            $title = ($s->subject ?: 'Lesson') . ' — ' . ($s->class_name ?: 'class') . " in {$mins} min";
            $body = 'Starts at ' . $start->format('g:i A') . ($s->room ? ' in ' . $s->room : '') . ' (' . ($s->period ?: 'period') . ').'
                . (isset($subs[$s->id]) ? ' You are covering this class today.' : '');

            if ($this->option('dry-run')) {
                $this->line("Would remind user {$teacherId}: {$title}");
            } else {
                PushService::sendNow([$teacherId], $title, $body, [
                    'type' => 'lesson', 'slot_id' => (string) $s->id, 'starts_at' => $start->toIso8601String(),
                ]);
            }
            Cache::put($key, 1, now()->endOfDay());
            $sent++;
        }

        if ($sent) $this->info("Lesson reminders: {$sent}");
        return self::SUCCESS;
    }

    protected function isHoliday(Carbon $day): bool
    {
        $d = $day->toDateString();
        try {
            if (Schema::hasTable('holidays')) {
                $q = DB::table('holidays')->where(function ($w) use ($d) {
                    if (Schema::hasColumn('holidays', 'date')) $w->orWhereDate('date', $d);
                    if (Schema::hasColumn('holidays', 'start_date')) {
                        $w->orWhere(fn ($r) => $r->whereDate('start_date', '<=', $d)->whereDate(DB::raw('COALESCE(end_date, start_date)'), '>=', $d));
                    }
                });
                if (Schema::hasColumn('holidays', 'is_full_day')) $q->where(fn ($w) => $w->whereNull('is_full_day')->orWhere('is_full_day', 1));
                if ($q->exists()) return true;
            }
            if (Schema::hasTable('attendance_holidays')) {
                return DB::table('attendance_holidays')
                    ->where(fn ($w) => $w->where(fn ($x) => $x->whereNull('holiday_end_date')->whereDate('holiday_date', $d))
                        ->orWhere(fn ($x) => $x->whereDate('holiday_date', '<=', $d)->whereDate('holiday_end_date', '>=', $d)))
                    ->exists();
            }
        } catch (\Throwable $e) {
            return false;
        }
        return false;
    }
}
