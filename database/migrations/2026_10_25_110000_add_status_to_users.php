<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Status and do-not-disturb: what someone chose to show, and until when. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('presence', 12)->default('auto');          // auto, away, busy, dnd
            $t->timestamp('presence_until')->nullable();          // back to auto after this
            $t->string('status_emoji', 16)->nullable();
            $t->string('status_text', 100)->nullable();
            $t->timestamp('status_until')->nullable();            // the status clears itself after this
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->dropColumn(['presence', 'presence_until', 'status_emoji', 'status_text', 'status_until']);
        });
    }
};
