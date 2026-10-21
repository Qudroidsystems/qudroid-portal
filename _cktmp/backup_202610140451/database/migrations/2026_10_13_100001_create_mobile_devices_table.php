<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phones signed in to the mobile app. Holds the push (FCM) token for each
 * device so notifications can be sent to the app later.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mobile_devices')) return;

        Schema::create('mobile_devices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('token_id')->nullable()->index(); // personal_access_tokens.id
            $table->string('push_token', 512)->nullable();
            $table->string('platform', 20)->nullable();      // android | ios
            $table->string('device_name', 120)->nullable();
            $table->string('app_version', 30)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique('push_token');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_devices');
    }
};
