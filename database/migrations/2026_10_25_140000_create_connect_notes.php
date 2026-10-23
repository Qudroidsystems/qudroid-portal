<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Connect, part 6: shared notes in a chat (like a channel's canvas), with their history. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('connect_notes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('conversation_id')->constrained('connect_conversations')->cascadeOnDelete();
            $t->string('title', 150);
            $t->longText('body')->nullable();                 // Markdown
            $t->unsignedInteger('version')->default(1);
            $t->boolean('pinned')->default(false);
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['conversation_id', 'pinned', 'updated_at']);
        });

        Schema::create('connect_note_revisions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('note_id')->constrained('connect_notes')->cascadeOnDelete();
            $t->unsignedInteger('version');
            $t->string('title', 150);
            $t->longText('body')->nullable();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamp('created_at')->nullable();
            $t->index(['note_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('connect_note_revisions');
        Schema::dropIfExists('connect_notes');
    }
};
