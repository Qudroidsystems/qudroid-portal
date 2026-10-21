<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Soft-delete for courses: a deleted course goes to Trash (recoverable) instead
 * of being wiped, so an accidental delete of a whole course can be undone.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('lms_courses') && !Schema::hasColumn('lms_courses', 'deleted_at')) {
            Schema::table('lms_courses', function (Blueprint $table) {
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('lms_courses') && Schema::hasColumn('lms_courses', 'deleted_at')) {
            Schema::table('lms_courses', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }
    }
};
