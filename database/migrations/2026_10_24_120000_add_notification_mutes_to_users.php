<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Kinds of notification a person has switched off (see PortalNotifier::GROUPS).
        Schema::table('users', function (Blueprint $table) {
            $table->json('muted_notifications')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('muted_notifications');
        });
    }
};
