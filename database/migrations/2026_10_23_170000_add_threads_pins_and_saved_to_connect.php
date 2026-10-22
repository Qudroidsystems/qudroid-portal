<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Connect, part 2: threaded replies, @mentions, pinned messages and each
 * person's saved messages.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('connect_messages', function (Blueprint $t) {
            // A reply in a thread points at the thread's first message; those replies stay out of the main timeline.
            $t->foreignId('thread_id')->nullable()->after('reply_to_id')->constrained('connect_messages')->cascadeOnDelete();
            $t->unsignedInteger('reply_count')->default(0)->after('thread_id');
            $t->timestamp('last_reply_at')->nullable()->after('reply_count');
            $t->json('mentions')->nullable()->after('body');             // user ids, or ["all"]
            $t->timestamp('pinned_at')->nullable();
            $t->foreignId('pinned_by')->nullable()->constrained('users')->nullOnDelete();
            $t->index(['conversation_id', 'thread_id', 'id']);
        });

        Schema::create('connect_saved', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('message_id')->constrained('connect_messages')->cascadeOnDelete();
            $t->timestamp('created_at')->nullable();
            $t->unique(['user_id', 'message_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('connect_saved');
        Schema::table('connect_messages', function (Blueprint $t) {
            $t->dropIndex(['conversation_id', 'thread_id', 'id']);
            $t->dropConstrainedForeignId('pinned_by');
            $t->dropConstrainedForeignId('thread_id');
            $t->dropColumn(['reply_count', 'last_reply_at', 'mentions', 'pinned_at']);
        });
    }
};
