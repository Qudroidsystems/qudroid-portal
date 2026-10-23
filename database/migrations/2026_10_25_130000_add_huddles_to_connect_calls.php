<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Huddles: drop-in voice rooms in a chat. They don't ring anyone. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('connect_calls', function (Blueprint $t) {
            $t->boolean('is_huddle')->default(false)->after('kind');
        });
    }

    public function down(): void
    {
        Schema::table('connect_calls', function (Blueprint $t) {
            $t->dropColumn('is_huddle');
        });
    }
};
