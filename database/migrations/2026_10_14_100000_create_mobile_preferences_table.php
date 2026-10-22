<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Per-user settings chosen in the mobile app (push on/off, reminders). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mobile_preferences', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->boolean('push_enabled')->default(true);
            $table->boolean('reminders_enabled')->default(true);   // tasks, meetings, classes
            $table->unsignedSmallInteger('reminder_minutes')->nullable(); // null = portal default
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_preferences');
    }
};
