<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Voice and video calls in Connect. Browsers connect to each other directly
 * (WebRTC); the portal only passes the set-up messages between them
 * (connect_call_signals) and keeps who is in which call.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('connect_calls', function (Blueprint $t) {
            $t->id();
            $t->foreignId('conversation_id')->constrained('connect_conversations')->cascadeOnDelete();
            $t->foreignId('started_by')->nullable()->constrained('users')->nullOnDelete();
            $t->string('kind', 5)->default('video');            // audio, video
            $t->timestamp('started_at');
            $t->timestamp('ended_at')->nullable();
            $t->unsignedInteger('peak_people')->default(1);
            $t->timestamps();
            $t->index(['conversation_id', 'ended_at']);
        });

        Schema::create('connect_call_people', function (Blueprint $t) {
            $t->id();
            $t->foreignId('call_id')->constrained('connect_calls')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->timestamp('joined_at')->nullable();
            $t->timestamp('left_at')->nullable();
            $t->timestamp('declined_at')->nullable();
            $t->timestamp('seen_at')->nullable();                // last check-in from the call page
            $t->json('state')->nullable();                       // {"mic":true,"cam":false,"screen":false}
            $t->timestamps();
            $t->unique(['call_id', 'user_id']);
        });

        Schema::create('connect_call_signals', function (Blueprint $t) {
            $t->id();
            $t->foreignId('call_id')->constrained('connect_calls')->cascadeOnDelete();
            $t->foreignId('from_user_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('to_user_id')->constrained('users')->cascadeOnDelete();
            $t->string('type', 10);                              // offer, answer, ice, bye
            $t->longText('payload')->nullable();
            $t->timestamp('created_at')->nullable();
            $t->index(['call_id', 'to_user_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('connect_call_signals');
        Schema::dropIfExists('connect_call_people');
        Schema::dropIfExists('connect_calls');
    }
};
