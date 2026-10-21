<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mobile-app showcase on the website: each "app screen" item is one feature
 * (title + description) with up to three phone screenshots (image, image2, image3).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('website_items')) return;
        Schema::table('website_items', function (Blueprint $t) {
            if (!Schema::hasColumn('website_items', 'image2')) $t->string('image2')->nullable()->after('image');
            if (!Schema::hasColumn('website_items', 'image3')) $t->string('image3')->nullable()->after('image2');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('website_items')) return;
        Schema::table('website_items', function (Blueprint $t) {
            foreach (['image2', 'image3'] as $c) if (Schema::hasColumn('website_items', $c)) $t->dropColumn($c);
        });
    }
};
