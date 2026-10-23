<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Connect, part 4: polls and GIFs. A poll or a GIF is a message with a kind
 * and its details in meta; votes are kept one row per person per option.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('connect_messages', function (Blueprint $t) {
            $t->string('kind', 20)->nullable()->after('body');   // null (text/file), poll, gif
            $t->json('meta')->nullable()->after('kind');
        });

        Schema::create('connect_poll_votes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('message_id')->constrained('connect_messages')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->unsignedSmallInteger('option');
            $t->timestamps();
            $t->unique(['message_id', 'user_id', 'option']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('connect_poll_votes');
        Schema::table('connect_messages', function (Blueprint $t) {
            $t->dropColumn(['kind', 'meta']);
        });
    }
};
