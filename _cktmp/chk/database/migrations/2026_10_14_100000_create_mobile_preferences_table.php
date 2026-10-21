<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Per-user settings chosen in the mobile app (lesson reminders, push on/off). */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mobile_preferences')) return;

        Schema::create('mobile_preferences', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->boolean('push_enabled')->default(true);
            $table->boolean('lesson_reminders')->default(true);
            $table->unsignedSmallInteger('reminder_minutes')->nullable(); // null = school default
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_preferences');
    }
};
