<?php

use App\Services\Website\Starter;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Adds Homilet, ViteSchools, Univite, Fashion House and Logistics to websites that already have products. Existing products are not touched. */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('site_products') || !DB::table('site_products')->exists()) return;   // new sites get them with the starter content
        $position = (int) DB::table('site_products')->max('position');
        foreach (Starter::industryProducts() as $p) {
            if (DB::table('site_products')->where('slug', $p['slug'])->exists()) continue;
            DB::table('site_products')->insert(array_merge($p, [
                'features' => json_encode($p['features']), 'status' => 'published', 'position' => ++$position,
                'created_at' => now(), 'updated_at' => now(),
            ]));
        }
    }

    public function down(): void {}
};
