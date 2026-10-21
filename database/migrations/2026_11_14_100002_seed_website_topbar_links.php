<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Stores the standard top-bar links as an editable website setting (only if not set yet). */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('website_settings') || DB::table('website_settings')->where('key', 'topbar_links')->exists()) return;
        DB::table('website_settings')->insert(['key' => 'topbar_links', 'value' => "Apply | /admissions#apply\nVisit us | /contact#visit\nNews & updates | /news\nGallery | /gallery", 'created_at' => now(), 'updated_at' => now()]);
        try { Cache::forget('website.settings.v1'); } catch (\Throwable $e) {}
    }

    public function down(): void {}
};
