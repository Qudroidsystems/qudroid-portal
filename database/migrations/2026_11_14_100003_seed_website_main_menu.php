<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Stores the standard main menu as an editable website setting (only if not set yet). */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('website_settings') || DB::table('website_settings')->where('key', 'main_menu')->exists()) return;
        DB::table('website_settings')->insert(['key' => 'main_menu',
            'value' => "Home | /\nAbout | @about\nAdmissions | @admissions\nAcademics | @academics\nSchool life | @life\nMobile app | /app\nContact | /contact",
            'created_at' => now(), 'updated_at' => now()]);
        try { Cache::forget('website.settings.v1'); } catch (\Throwable $e) {}
    }

    public function down(): void {}
};
