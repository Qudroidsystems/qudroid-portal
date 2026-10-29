<?php

use App\Services\Website\Starter;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Home pages still showing the first starter hero get the rotating "We engineer …"
 * headline, and a "How we work" steps block after "What we do". Edited pages are left alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('site_pages')) return;
        $home = DB::table('site_pages')->where('slug', 'home')->first();
        if (!$home) return;
        $blocks = json_decode($home->blocks ?? '[]', true) ?: [];
        $changed = false;

        if (($blocks[0]['type'] ?? null) === 'hero' && ($blocks[0]['title'] ?? null) === 'Software that moves your business forward.') {
            $blocks[0]['title'] = Starter::HERO_TITLE;
            $blocks[0]['rotating'] = Starter::HERO_WORDS;
            $changed = true;
        }
        if (!in_array('steps', array_column($blocks, 'type'), true)) {
            foreach ($blocks as $i => $b) {
                if (($b['type'] ?? null) === 'features' && ($b['items'] ?? null) == Starter::services()) {
                    array_splice($blocks, $i + 1, 0, [Starter::stepsBlock()]);
                    $changed = true;
                    break;
                }
            }
        }
        if ($changed) DB::table('site_pages')->where('id', $home->id)->update(['blocks' => json_encode($blocks)]);
    }

    public function down(): void {}
};
