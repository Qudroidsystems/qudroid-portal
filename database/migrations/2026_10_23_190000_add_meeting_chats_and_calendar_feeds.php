<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Meetings meet the workspace: each meeting can have its own chat (and so
 * calls), and each person gets a private calendar feed URL to subscribe to
 * from Google, Outlook or Apple Calendar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('connect_conversations', function (Blueprint $t) {
            $t->foreignId('meeting_id')->nullable()->after('project_id')->constrained('meetings')->cascadeOnDelete();
        });

        Schema::create('calendar_feeds', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $t->string('token', 64)->unique();
            $t->timestamp('last_used_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_feeds');
        Schema::table('connect_conversations', function (Blueprint $t) {
            $t->dropConstrainedForeignId('meeting_id');
        });
    }
};
