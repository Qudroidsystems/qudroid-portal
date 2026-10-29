<?php

use App\Services\Website\Starter;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Home pages still showing the first "What we do" list get software, AI and blockchain engineering. Edited lists are left alone. */
return new class extends Migration
{
    protected const OLD_TITLES = ['Custom software', 'Mobile apps', 'Cloud and DevOps', 'Product design', 'Data and AI', 'Training'];

    public function up(): void
    {
        if (!Schema::hasTable('site_pages')) return;
        $home = DB::table('site_pages')->where('slug', 'home')->first();
        if (!$home) return;
        $blocks = json_decode($home->blocks ?? '[]', true) ?: [];
        $changed = false;
        foreach ($blocks as &$b) {
            if (($b['type'] ?? null) !== 'features' || array_column($b['items'] ?? [], 'title') !== self::OLD_TITLES) continue;
            $b['items'] = Starter::services();
            if (($b['intro'] ?? '') === 'One team for strategy, design, engineering, cloud and training.') $b['intro'] = Starter::SERVICES_INTRO;
            $changed = true;
        }
        if ($changed) DB::table('site_pages')->where('id', $home->id)->update(['blocks' => json_encode($blocks)]);
    }

    public function down(): void {}
};
