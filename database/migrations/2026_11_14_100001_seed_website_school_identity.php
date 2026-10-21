<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Stores the school's identity (name, motto, address, e-mail, phones, logo) and
 * headline numbers in the website's own settings, copied from the portal's
 * School information. The public website uses the portal's live data when it can
 * read it and falls back to these, so the site never shows an empty header/footer.
 * Only fills settings that are missing or empty; never overwrites what the admin typed.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('website_settings')) return;
        $row = Schema::hasTable('school_information') ? DB::table('school_information')->orderByDesc('is_active')->first() : null;
        $phones = $row && !empty($row->school_phones) ? implode(', ', \App\Services\Website\WebsiteContent::phones($row->school_phones)) : null;
        $count = function (string $table, ?callable $q = null) {
            try { return Schema::hasTable($table) ? (int) ($q ? $q(DB::table($table)) : DB::table($table))->count() : 0; } catch (\Throwable $e) { return 0; }
        };
        $src = [
            'school_name' => $row->school_name ?? null, 'school_motto' => $row->school_motto ?? null,
            'school_address' => $row->school_address ?? null, 'school_email' => $row->school_email ?? null,
            'school_phones' => $phones, 'school_logo' => $row->school_logo ?? null,
            'stats_students' => $count('studentRegistration') ?: null, 'stats_staff' => $count('staffbioinfo', fn ($q) => $q->whereNull('deleted_at')) ?: null,
            'stats_classes' => $count('schoolclass') ?: null, 'stats_subjects' => $count('subject') ?: null,
        ];
        $now = now();
        // Earlier runs may have saved the phones as raw JSON; tidy them.
        $old = DB::table('website_settings')->where('key', 'school_phones')->value('value');
        if ($old && str_starts_with(trim($old), '[')) DB::table('website_settings')->where('key', 'school_phones')->update(['value' => implode(', ', \App\Services\Website\WebsiteContent::phones($old))]);
        $have = DB::table('website_settings')->pluck('value', 'key')->all();
        foreach ($src as $k => $v) {
            if ($v === null || trim((string) $v) === '') continue;
            if (!array_key_exists($k, $have)) DB::table('website_settings')->insert(['key' => $k, 'value' => (string) $v, 'created_at' => $now, 'updated_at' => $now]);
            elseif (trim((string) $have[$k]) === '') DB::table('website_settings')->where('key', $k)->update(['value' => (string) $v, 'updated_at' => $now]);
        }
        try { Cache::forget('website.settings.v1'); Cache::forget('website.stats'); } catch (\Throwable $e) {}
    }

    public function down(): void {}
};
