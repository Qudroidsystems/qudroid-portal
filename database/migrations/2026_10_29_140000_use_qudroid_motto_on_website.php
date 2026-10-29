<?php

use App\Services\Website\Site;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Websites still showing the first default tagline get the Qudroid motto instead. Edited text is left alone. */
return new class extends Migration
{
    protected const OLD = 'Software, cloud and training for ambitious teams.';

    public function up(): void
    {
        $site = app(Site::class);
        if ($site->config()['tagline'] === self::OLD) $site->save(['tagline' => Site::MOTTO]);

        if (!Schema::hasTable('site_pages')) return;
        $home = DB::table('site_pages')->where('slug', 'home')->first();
        if (!$home) return;
        $blocks = json_decode($home->blocks ?? '[]', true) ?: [];
        if (($blocks[0]['type'] ?? null) === 'hero' && ($blocks[0]['subtitle'] ?? null) === self::OLD) {
            $blocks[0]['subtitle'] = Site::MOTTO;
            DB::table('site_pages')->where('id', $home->id)->update(['blocks' => json_encode($blocks)]);
        }
    }

    public function down(): void {}
};
