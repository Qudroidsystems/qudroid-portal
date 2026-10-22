<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Course content: sections and lessons, and each enrolled student's progress
 * through them. Progress belongs to the enrollment, so a student who retakes
 * a course on a new cohort starts fresh.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academy_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('academy_courses')->cascadeOnDelete();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('academy_lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('academy_courses')->cascadeOnDelete();
            $table->foreignId('section_id')->nullable()->constrained('academy_sections')->nullOnDelete();
            $table->string('title', 200);
            $table->string('type', 10)->default('text');      // text | video | file | link
            $table->longText('body')->nullable();               // Markdown
            $table->string('video_url', 500)->nullable();       // YouTube / Vimeo
            $table->string('external_url', 500)->nullable();
            $table->string('file_path')->nullable();            // on the private "local" disk
            $table->string('file_name')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->unsignedSmallInteger('duration_minutes')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_preview')->default(false);      // open to anyone who can see the course
            $table->boolean('is_published')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('academy_lesson_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_id')->constrained('academy_enrollments')->cascadeOnDelete();
            $table->foreignId('lesson_id')->constrained('academy_lessons')->cascadeOnDelete();
            $table->timestamp('first_viewed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['enrollment_id', 'lesson_id']);
        });

        Schema::table('academy_enrollments', function (Blueprint $table) {
            $table->unsignedTinyInteger('progress_percent')->default(0)->after('status');
            $table->timestamp('last_activity_at')->nullable()->after('progress_percent');
        });
    }

    public function down(): void
    {
        Schema::table('academy_enrollments', function (Blueprint $table) {
            $table->dropColumn(['progress_percent', 'last_activity_at']);
        });
        Schema::dropIfExists('academy_lesson_progress');
        Schema::dropIfExists('academy_lessons');
        Schema::dropIfExists('academy_sections');
    }
};
