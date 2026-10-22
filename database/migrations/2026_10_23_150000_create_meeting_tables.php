<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Meetings & Calendar: meeting rooms, meetings with invited staff (and
 * outside guests by name), RSVPs, reminders and minutes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meeting_rooms', function (Blueprint $t) {
            $t->id();
            $t->string('name', 80)->unique();
            $t->unsignedSmallInteger('capacity')->nullable();
            $t->string('description', 255)->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('meetings', function (Blueprint $t) {
            $t->id();
            $t->string('title', 150);
            $t->text('agenda')->nullable();
            $t->dateTime('starts_at');
            $t->dateTime('ends_at');
            $t->foreignId('room_id')->nullable()->constrained('meeting_rooms')->nullOnDelete();
            $t->string('location', 255)->nullable();                   // when not in a room
            $t->string('online_url', 500)->nullable();
            $t->foreignId('organizer_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $t->foreignId('company_id')->nullable()->constrained('crm_companies')->nullOnDelete();
            $t->json('guests')->nullable();                            // outside people: [{name, email}]
            $t->string('status', 10)->default('scheduled');           // scheduled, cancelled
            $t->string('cancel_reason', 255)->nullable();
            $t->unsignedSmallInteger('remind_minutes')->default(15);
            $t->timestamp('reminded_at')->nullable();
            $t->text('minutes')->nullable();                           // notes after the meeting
            $t->timestamps();
            $t->index(['starts_at', 'status']);
        });

        Schema::create('meeting_attendees', function (Blueprint $t) {
            $t->id();
            $t->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('response', 10)->default('pending');            // pending, accepted, tentative, declined
            $t->string('note', 255)->nullable();
            $t->timestamps();
            $t->unique(['meeting_id', 'user_id']);
            $t->index(['user_id', 'response']);
        });

        $now = now();
        DB::table('meeting_rooms')->insertOrIgnore([['name' => 'Board room', 'capacity' => 12, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]]);
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_attendees');
        Schema::dropIfExists('meetings');
        Schema::dropIfExists('meeting_rooms');
    }
};
