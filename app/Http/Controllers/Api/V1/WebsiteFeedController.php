<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\SchoolInformation;
use App\Services\Website\WebsiteContent as W;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Data for the school's separate public website (the "school website" project at
 * www.<school>, one install per school). Protected by a shared key
 * (X-Website-Key = WEBSITE_API_KEY), see App\Http\Middleware\VerifyWebsiteKey.
 *
 *   GET /api/v1/website/feed   school details, numbers, classes, subjects, public events
 *   GET /api/v1/website/staff  staff the website can import into "Our people"
 */
class WebsiteFeedController extends ApiController
{
    public function feed()
    {
        $s = SchoolInformation::getActiveSchool() ?? SchoolInformation::first();

        $count = function (string $table, ?callable $q = null): int {
            try { return Schema::hasTable($table) ? (int) ($q ? $q(DB::table($table)) : DB::table($table))->count() : 0; } catch (\Throwable $e) { return 0; }
        };
        $students = $count('studentRegistration', fn ($q) => $q->where(fn ($w) => $w->whereNull('statusId')->orWhere('statusId', 1)));
        if (!$students) $students = $count('studentRegistration');

        $a = W::academics();
        $levels = collect($a['levels'])->map(fn ($classes, $name) => ['name' => (string) $name, 'classes' => collect($classes)->values()->all()])->values()->all();

        $events = [];
        try {
            if (Schema::hasTable('calendar_events') && Schema::hasColumn('calendar_events', 'is_public')) {
                $events = DB::table('calendar_events')->where('is_public', 1)->where('is_active', 1)
                    ->whereDate('end_date', '>=', request()->query('from', now()->subMonths(2)->toDateString()))
                    ->whereDate('start_date', '<=', request()->query('to', now()->addMonths(12)->toDateString()))
                    ->orderBy('start_date')->limit(300)->get()
                    ->map(fn ($e) => [
                        'title' => $e->title, 'start_date' => $e->start_date, 'end_date' => $e->end_date,
                        'start_time' => $e->start_time ? substr((string) $e->start_time, 0, 5) : null, 'all_day' => (bool) $e->all_day,
                        'location' => $e->location, 'description' => strip_tags((string) $e->description), 'color' => $e->color,
                    ])->all();
            }
        } catch (\Throwable $e) {}

        return $this->ok([
            'school' => [
                'name'     => $s->school_name ?? config('app.name'),
                'motto'    => $s->school_motto ?? null,
                'address'  => $s->school_address ?? null,
                'phones'   => W::phones($s->school_phones ?? []),
                'email'    => $s->school_email ?? null,
                'website'  => $s->school_website ?? null,
                'logo_url' => $this->url($s->school_logo ?? null) ?: $this->url($s->app_logo ?? null),
                'next_term_begins' => optional($s?->date_next_term_begins)->toDateString(),
                'score_model' => config('services.mobile.score_model', 'dynamic'),
            ],
            'stats' => [
                'students' => $students,
                'staff'    => $count('staffbioinfo', fn ($q) => $q->whereNull('deleted_at')),
                'classes'  => $count('schoolclass'),
                'subjects' => $count('subject'),
            ],
            'academics' => ['levels' => $levels, 'subjects' => collect($a['subjects'])->values()->all()],
            'events'    => $events,
        ]);
    }

    public function staff()
    {
        $rows = Schema::hasTable('staffbioinfo')
            ? DB::table('staffbioinfo as b')->join('users as u', 'u.id', '=', 'b.userid')
                ->leftJoin('staffpicture as p', 'p.staffId', '=', 'u.id')
                ->whereNull('b.deleted_at')->orderBy('u.name')
                ->get(['u.id', 'u.name', 'b.job_title', 'b.position', 'p.picture'])
            : collect();

        return $this->ok($rows->map(fn ($r) => [
            'id' => (int) $r->id, 'name' => $r->name, 'role' => $r->job_title ?: null, 'department' => $r->position ?: null,
            'photo_url' => $r->picture ? $this->url('staff_avatars/' . basename($r->picture)) : null,
        ])->values());
    }

    protected function url(?string $path): ?string
    {
        if (!$path) return null;
        if (filter_var($path, FILTER_VALIDATE_URL)) return $path;
        try { if (Storage::disk('public')->exists($path)) return asset('storage/' . $path); } catch (\Throwable $e) {}
        return is_file(public_path($path)) ? asset($path) : null;
    }
}
