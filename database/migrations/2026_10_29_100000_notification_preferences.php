<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Quiet hours (no banners, sounds or phone pushes except urgent ones) and the daily summary e-mail.
        Schema::table('users', function (Blueprint $t) {
            if (!Schema::hasColumn('users', 'notify_quiet_from')) $t->time('notify_quiet_from')->nullable();
            if (!Schema::hasColumn('users', 'notify_quiet_to')) $t->time('notify_quiet_to')->nullable();
            if (!Schema::hasColumn('users', 'notify_digest')) $t->boolean('notify_digest')->default(false);
            if (!Schema::hasColumn('users', 'notify_digest_at')) $t->timestamp('notify_digest_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->dropColumn(['notify_quiet_from', 'notify_quiet_to', 'notify_digest', 'notify_digest_at']);
        });
    }
};
