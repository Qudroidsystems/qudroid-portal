<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Connect, part 5: messages scheduled to send later, and personal reminders. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('connect_scheduled', function (Blueprint $t) {
            $t->id();
            $t->foreignId('conversation_id')->constrained('connect_conversations')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->text('body');
            $t->unsignedBigInteger('reply_to_id')->nullable();
            $t->unsignedBigInteger('thread_id')->nullable();
            $t->timestamp('send_at');
            $t->timestamp('sent_at')->nullable();
            $t->foreignId('message_id')->nullable()->constrained('connect_messages')->nullOnDelete();
            $t->string('failed_reason', 255)->nullable();
            $t->timestamps();
            $t->index(['sent_at', 'send_at']);
        });

        Schema::create('connect_reminders', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('message_id')->nullable()->constrained('connect_messages')->cascadeOnDelete();
            $t->string('note', 255)->nullable();
            $t->timestamp('remind_at');
            $t->timestamp('reminded_at')->nullable();
            $t->timestamp('done_at')->nullable();
            $t->timestamps();
            $t->index(['reminded_at', 'remind_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('connect_reminders');
        Schema::dropIfExists('connect_scheduled');
    }
};
