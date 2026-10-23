<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Calls and meetings: recordings (uploaded in pieces while the call runs),
 * live transcripts (each person's browser transcribes their own voice) and
 * a recap note.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('connect_call_recordings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('call_id')->constrained('connect_calls')->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('status', 10)->default('recording');      // recording, ready, failed
            $t->string('path', 255)->nullable();
            $t->string('mime', 80)->default('video/webm');
            $t->unsignedBigInteger('size')->default(0);
            $t->unsignedInteger('chunks')->default(0);
            $t->unsignedInteger('duration_seconds')->nullable();
            $t->timestamp('started_at');
            $t->timestamp('ended_at')->nullable();
            $t->timestamps();
            $t->index(['call_id', 'status']);
        });

        Schema::create('connect_call_transcript', function (Blueprint $t) {
            $t->id();
            $t->foreignId('call_id')->constrained('connect_calls')->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->text('text');
            $t->timestamp('spoken_at');
            $t->index(['call_id', 'id']);
        });

        Schema::table('connect_calls', function (Blueprint $t) {
            $t->foreignId('transcribing_by')->nullable()->after('peak_people')->constrained('users')->nullOnDelete();
            $t->foreignId('note_id')->nullable()->after('transcribing_by')->constrained('connect_notes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('connect_calls', function (Blueprint $t) {
            $t->dropConstrainedForeignId('note_id');
            $t->dropConstrainedForeignId('transcribing_by');
        });
        Schema::dropIfExists('connect_call_transcript');
        Schema::dropIfExists('connect_call_recordings');
    }
};
