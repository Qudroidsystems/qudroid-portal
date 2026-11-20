<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A class can appear in the website yearbook, with a class photo and a note. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('academy_cohorts', function (Blueprint $t) {
            $t->boolean('in_yearbook')->default(false)->after('auto_absent');
            $t->string('yearbook_photo')->nullable()->after('in_yearbook');
            $t->text('yearbook_note')->nullable()->after('yearbook_photo');
        });
    }

    public function down(): void
    {
        Schema::table('academy_cohorts', fn (Blueprint $t) => $t->dropColumn(['in_yearbook', 'yearbook_photo', 'yearbook_note']));
    }
};
