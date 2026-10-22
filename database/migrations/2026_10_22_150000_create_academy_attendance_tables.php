<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Class attendance: the sessions a cohort meets for, and each student's
 * mark in the register. Every enrollment keeps its attendance rate.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academy_class_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cohort_id')->constrained('academy_cohorts')->cascadeOnDelete();
            $table->date('date');
            $table->time('starts_at')->nullable();
            $table->time('ends_at')->nullable();
            $table->string('topic', 200)->nullable();
            $table->string('status', 20)->default('scheduled');   // scheduled | held | cancelled
            $table->string('cancel_reason', 300)->nullable();
            $table->timestamp('register_taken_at')->nullable();
            $table->foreignId('register_taken_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['cohort_id', 'date']);
        });

        Schema::create('academy_attendance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_session_id')->constrained('academy_class_sessions')->cascadeOnDelete();
            $table->foreignId('enrollment_id')->constrained('academy_enrollments')->cascadeOnDelete();
            $table->string('status', 20);                        // present | late | absent | excused
            $table->string('note', 300)->nullable();
            $table->foreignId('marked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['class_session_id', 'enrollment_id']);
        });

        Schema::table('academy_enrollments', function (Blueprint $table) {
            $table->decimal('attendance_percent', 5, 2)->nullable()->after('grade_percent');
        });
    }

    public function down(): void
    {
        Schema::table('academy_enrollments', fn (Blueprint $t) => $t->dropColumn('attendance_percent'));
        Schema::dropIfExists('academy_attendance');
        Schema::dropIfExists('academy_class_sessions');
    }
};
