<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Qudroid Connect: direct messages, private groups and open channels.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('connect_conversations', function (Blueprint $t) {
            $t->id();
            $t->string('type', 10);                          // direct, group, channel
            $t->string('name', 100)->nullable();             // groups and channels
            $t->string('description', 255)->nullable();
            $t->string('direct_key', 50)->nullable()->unique(); // "12:57" for a direct chat, so there is only one
            $t->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->unsignedBigInteger('last_message_id')->nullable();
            $t->timestamp('last_message_at')->nullable();
            $t->timestamp('archived_at')->nullable();
            $t->timestamps();
            $t->index(['type', 'last_message_at']);
        });

        Schema::create('connect_participants', function (Blueprint $t) {
            $t->id();
            $t->foreignId('conversation_id')->constrained('connect_conversations')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('role', 10)->default('member');      // admin, member
            $t->unsignedBigInteger('last_read_message_id')->default(0);
            $t->boolean('muted')->default(false);
            $t->timestamp('left_at')->nullable();
            $t->timestamps();
            $t->unique(['conversation_id', 'user_id']);
            $t->index(['user_id', 'left_at']);
        });

        Schema::create('connect_messages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('conversation_id')->constrained('connect_conversations')->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->text('body')->nullable();
            $t->foreignId('reply_to_id')->nullable()->constrained('connect_messages')->nullOnDelete();
            $t->string('attachment_path')->nullable();
            $t->string('attachment_name')->nullable();
            $t->string('attachment_mime', 100)->nullable();
            $t->unsignedBigInteger('attachment_size')->nullable();
            $t->boolean('is_system')->default(false);       // "Ada joined the channel"
            $t->timestamp('edited_at')->nullable();
            $t->timestamp('deleted_at')->nullable();        // shown as "message deleted"
            $t->timestamps();
            $t->index(['conversation_id', 'id']);
        });

        Schema::create('connect_reactions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('message_id')->constrained('connect_messages')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('emoji', 16);
            $t->timestamps();
            $t->unique(['message_id', 'user_id', 'emoji']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('connect_reactions');
        Schema::dropIfExists('connect_messages');
        Schema::dropIfExists('connect_participants');
        Schema::dropIfExists('connect_conversations');
    }
};
