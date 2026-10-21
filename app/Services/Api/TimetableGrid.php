<?php

namespace App\Services\Api;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Timetables shaped for the app: periods (rows), days (columns) and slots. */
class TimetableGrid
{
    public const DEFAULT_DAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];

    /** A class timetable from one timetable_settings row. */
    public static function forSetting(object $setting): array
    {
        $days = json_decode($setting->active_days ?? '[]', true) ?: self::DEFAULT_DAYS;

        $periods = DB::table('timetable_periods')->where('setting_id', $setting->id)->orderBy('order')
            ->get(['id', 'order', 'name', 'type', 'start_time', 'end_time', 'is_break']);

        $slots = DB::table('timetable_slots as ts')
            ->leftJoin('subject as sj', 'sj.id', '=', 'ts.subject_id')
            ->leftJoin('users as u', 'u.id', '=', 'ts.teacher_id')
            ->when(Schema::hasTable('rooms'), fn ($q) => $q->leftJoin('rooms as r', 'r.id', '=', 'ts.room_id'))
            ->where('ts.setting_id', $setting->id)
            ->get(array_merge(
                ['ts.day', 'ts.period_id', 'ts.is_free', 'ts.is_double', 'sj.subject', 'sj.subject_code', 'u.name as teacher'],
                Schema::hasTable('rooms') ? ['r.room_name as room'] : [DB::raw('NULL as room')]
            ));

        return [
            'published' => true,
            'setting_id' => (int) $setting->id,
            'days'      => array_values($days),
            'periods'   => $periods->map(fn ($p) => [
                'id' => (int) $p->id, 'order' => (int) $p->order, 'name' => $p->name, 'type' => $p->type,
                'start_time' => $p->start_time ? substr($p->start_time, 0, 5) : null,
                'end_time'   => $p->end_time ? substr($p->end_time, 0, 5) : null,
                'is_break'   => (bool) $p->is_break,
            ])->values(),
            'slots' => $slots->map(fn ($s) => [
                'day' => $s->day, 'period_id' => (int) $s->period_id, 'is_free' => (bool) $s->is_free,
                'is_double' => (bool) $s->is_double, 'subject' => $s->subject, 'subject_code' => $s->subject_code,
                'teacher' => $s->teacher, 'room' => $s->room,
            ])->values(),
        ];
    }

    /** A teacher's week across every class they teach (published, current session). */
    public static function forTeacher(int $userId, ?int $sessionId): array
    {
        $hasPublished = Schema::hasColumn('timetable_settings', 'is_published');

        $rows = DB::table('timetable_slots as ts')
            ->join('timetable_settings as st', 'st.id', '=', 'ts.setting_id')
            ->join('timetable_periods as p', 'p.id', '=', 'ts.period_id')
            ->leftJoin('subject as sj', 'sj.id', '=', 'ts.subject_id')
            ->leftJoin('schoolclass as c', 'c.id', '=', 'st.schoolclass_id')
            ->leftJoin('schoolarm as a', 'a.id', '=', 'c.arm')
            ->when(Schema::hasTable('rooms'), fn ($q) => $q->leftJoin('rooms as r', 'r.id', '=', 'ts.room_id'))
            ->where('ts.teacher_id', $userId)
            ->where('st.is_active', 1)
            ->when($hasPublished, fn ($q) => $q->where('st.is_published', 1))
            ->when($sessionId, fn ($q) => $q->where('st.session_id', $sessionId))
            ->orderBy('p.start_time')
            ->get(array_merge([
                'ts.day', 'p.name as period', 'p.start_time', 'p.end_time', 'sj.subject', 'sj.subject_code',
                'st.schoolclass_id as class_id',
                DB::raw("TRIM(CONCAT(COALESCE(c.schoolclass,''), ' ', COALESCE(a.arm,''))) as class_name"),
            ], Schema::hasTable('rooms') ? ['r.room_name as room'] : [DB::raw('NULL as room')]));

        $order = array_flip(['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday']);

        return $rows->groupBy('day')
            ->sortBy(fn ($v, $day) => $order[$day] ?? 9)
            ->map(fn ($items, $day) => [
                'day'     => $day,
                'lessons' => $items->map(fn ($r) => [
                    'period' => $r->period,
                    'start_time' => $r->start_time ? substr($r->start_time, 0, 5) : null,
                    'end_time'   => $r->end_time ? substr($r->end_time, 0, 5) : null,
                    'subject' => $r->subject, 'subject_code' => $r->subject_code,
                    'class_id' => (int) $r->class_id, 'class_name' => $r->class_name, 'room' => $r->room,
                ])->values(),
            ])->values()->all();
    }
}
