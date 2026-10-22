<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Online applications: someone applies for a cohort from the public Apply
 * page (or from My academy), and academy staff admit or decline them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academy_applications', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->unique();                        // APP-2026-0001
            $table->foreignId('student_id')->constrained('academy_students')->cascadeOnDelete();
            $table->foreignId('cohort_id')->constrained('academy_cohorts')->restrictOnDelete();
            $table->foreignId('course_id')->constrained('academy_courses')->restrictOnDelete();
            $table->string('status', 20)->default('submitted')->index();     // submitted | admitted | declined | withdrawn
            $table->text('motivation')->nullable();                           // why they want to join
            $table->string('experience_level', 30)->nullable();               // none | some | experienced
            $table->boolean('has_laptop')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('decision_note', 500)->nullable();
            $table->foreignId('enrollment_id')->nullable()->constrained('academy_enrollments')->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academy_applications');
    }
};
