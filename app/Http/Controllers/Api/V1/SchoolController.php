<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\SchoolInformation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/** Public school details for the app's splash and sign-in screens. */
class SchoolController extends ApiController
{
    public function show()
    {
        $s = SchoolInformation::getActiveSchool() ?? SchoolInformation::first();

        $url = function (?string $path) {
            if (!$path) return null;
            if (filter_var($path, FILTER_VALIDATE_URL)) return $path;
            try {
                if (Storage::disk('public')->exists($path)) return asset('storage/' . $path);
            } catch (\Throwable $e) {}
            return asset($path);
        };

        return $this->ok([
            'name'      => $s->school_name ?? config('app.name'),
            'motto'     => $s->school_motto ?? null,
            'address'   => $s->school_address ?? null,
            'phones'    => array_values(array_filter((array) ($s->school_phones ?? []))),
            'email'     => $s->school_email ?? null,
            'website'   => $s->school_website ?? null,
            'logo_url'  => $url($s->app_logo ?? null) ?? $url($s->school_logo ?? null),
            'next_term_begins' => optional($s?->date_next_term_begins)->toDateString(),
            'current'   => $this->currentPeriod(),
            'sessions'  => DB::table('schoolsession')->orderByDesc('id')->get(['id', 'session', 'status']),
            'terms'     => DB::table('schoolterm')->orderBy('id')->get(['id', 'term']),
            'portal_url'  => rtrim(url('/'), '/'),
            'api_version' => 'v1',
            // 'dynamic' = CSS Kabba-type portal (Vite Schools app); 'fixed' = TopClass-type portal (TopClass Schools app).
            'score_model' => config('services.mobile.score_model', 'dynamic'),
            'gpa' => config('services.mobile.score_model', 'dynamic') !== 'fixed',
        ]);
    }
}
