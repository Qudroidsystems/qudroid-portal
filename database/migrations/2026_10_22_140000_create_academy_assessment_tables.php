<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Assessments and certificates.
 *
 * A course has quizzes (marked automatically, essays by a teacher) and
 * assignments (text or a file, graded by a teacher). Each has a weight; the
 * weighted result is the enrollment's grade. A completed enrollment whose
 * grade reaches the course's pass grade gets a certificate with a serial
 * number and a QR code that anyone can check at /verify/{token}.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academy_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('academy_courses')->cascadeOnDelete();
            $table->string('type', 20);                                     // quiz | assignment
            $table->string('title', 200);
            $table->text('instructions')->nullable();                       // Markdown
            $table->decimal('weight', 5, 2)->default(0);                    // share of the final grade
            $table->unsignedTinyInteger('pass_mark')->default(50);          // percent
            $table->decimal('max_score', 6, 2)->default(100);               // assignments; quizzes use their points
            $table->unsignedSmallInteger('max_attempts')->default(1);       // quizzes; 0 = unlimited
            $table->unsignedSmallInteger('time_limit_minutes')->nullable(); // quizzes
            $table->boolean('shuffle')->default(false);
            $table->boolean('show_answers')->default(true);                 // after submitting a quiz
            $table->timestamp('due_at')->nullable();
            $table->boolean('is_published')->default(false);
            $table->unsignedInteger('position')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('academy_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id')->constrained('academy_assessments')->cascadeOnDelete();
            $table->string('type', 20);                // single | multiple | boolean | short_answer | essay
            $table->text('question');
            $table->json('options')->nullable();       // ["A", "B", ...]
            $table->json('correct')->nullable();       // option indexes
            $table->json('accepted_answers')->nullable();
            $table->text('explanation')->nullable();
            $table->decimal('points', 6, 2)->default(1);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('academy_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id')->constrained('academy_assessments')->cascadeOnDelete();
            $table->foreignId('enrollment_id')->constrained('academy_enrollments')->cascadeOnDelete();
            $table->unsignedSmallInteger('attempt_no');
            $table->json('answers')->nullable();       // question id => answer
            $table->json('marks')->nullable();         // question id => points awarded
            $table->decimal('score', 8, 2)->nullable();
            $table->decimal('max_score', 8, 2)->nullable();
            $table->decimal('percent', 5, 2)->nullable();
            $table->boolean('needs_review')->default(false);   // has essays to mark
            $table->boolean('timed_out')->default(false);
            $table->timestamp('started_at');
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('graded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('graded_at')->nullable();
            $table->timestamps();
            $table->unique(['assessment_id', 'enrollment_id', 'attempt_no']);
        });

        Schema::create('academy_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id')->constrained('academy_assessments')->cascadeOnDelete();
            $table->foreignId('enrollment_id')->constrained('academy_enrollments')->cascadeOnDelete();
            $table->text('body')->nullable();
            $table->string('file_path')->nullable();   // private local disk
            $table->string('file_name')->nullable();
            $table->string('status', 20)->default('submitted');  // submitted | returned | graded
            $table->decimal('score', 6, 2)->nullable();
            $table->decimal('percent', 5, 2)->nullable();
            $table->text('feedback')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('graded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('graded_at')->nullable();
            $table->timestamps();
            $table->unique(['assessment_id', 'enrollment_id']);
        });

        Schema::table('academy_courses', function (Blueprint $table) {
            $table->unsignedTinyInteger('pass_grade')->default(50)->after('min_deposit');
            $table->boolean('certificate_enabled')->default(true)->after('pass_grade');
        });

        Schema::table('academy_enrollments', function (Blueprint $table) {
            $table->decimal('grade_percent', 5, 2)->nullable()->after('progress_percent');
        });

        Schema::create('academy_certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_id')->constrained('academy_enrollments')->restrictOnDelete(); // a revoked one stays when it is replaced
            $table->foreignId('student_id')->constrained('academy_students')->restrictOnDelete();
            $table->foreignId('course_id')->constrained('academy_courses')->restrictOnDelete();
            $table->string('serial', 40)->unique();              // QUD-CERT-2026-0001
            $table->string('verify_token', 64)->unique();
            $table->string('status', 20)->default('issued');     // issued | revoked
            $table->json('snapshot');                            // name, course, cohort, dates, grade as issued
            $table->timestamp('issued_at');
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('revoke_reason', 300)->nullable();
            $table->unsignedInteger('verify_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academy_certificates');
        Schema::table('academy_enrollments', fn (Blueprint $t) => $t->dropColumn('grade_percent'));
        Schema::table('academy_courses', fn (Blueprint $t) => $t->dropColumn(['pass_grade', 'certificate_enabled']));
        Schema::dropIfExists('academy_submissions');
        Schema::dropIfExists('academy_attempts');
        Schema::dropIfExists('academy_questions');
        Schema::dropIfExists('academy_assessments');
    }
};
