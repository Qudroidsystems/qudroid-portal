<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Academy (Qudroid Coding Academy): programmes, courses and their fees,
 * cohorts (intakes), student records and enrollments.
 *
 * An enrollment keeps a copy of the fee it was made at, so later price
 * changes never alter what a student was charged. Course access is a
 * separate flag on the enrollment: Finance grants it on confirmed payment,
 * or an authorised person grants it manually with a reason.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academy_programmes', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('slug', 160)->unique();
            $table->text('description')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('academy_courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('programme_id')->nullable()->constrained('academy_programmes')->nullOnDelete();
            $table->string('code', 30)->unique();
            $table->string('title', 200);
            $table->string('slug', 220)->unique();
            $table->string('summary', 500)->nullable();
            $table->text('description')->nullable();
            $table->string('level', 20)->default('beginner');          // beginner | intermediate | advanced
            $table->string('delivery_mode', 20)->default('hybrid');    // physical | online | hybrid
            $table->unsignedSmallInteger('duration_weeks')->nullable();
            $table->decimal('fee', 12, 2)->default(0);
            $table->char('currency', 3)->default('NGN');
            $table->boolean('installments_allowed')->default(false);
            $table->decimal('min_deposit', 12, 2)->nullable();
            $table->string('status', 20)->default('draft')->index();   // draft | published | archived
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('academy_course_prerequisites', function (Blueprint $table) {
            $table->foreignId('course_id')->constrained('academy_courses')->cascadeOnDelete();
            $table->foreignId('prerequisite_id')->constrained('academy_courses')->cascadeOnDelete();
            $table->primary(['course_id', 'prerequisite_id']);
        });

        Schema::create('academy_cohorts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('academy_courses')->cascadeOnDelete();
            $table->string('code', 40)->unique();
            $table->string('name', 150);
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->date('registration_opens_on')->nullable();
            $table->date('registration_closes_on')->nullable();
            $table->unsignedSmallInteger('capacity')->nullable();
            $table->string('delivery_mode', 20)->nullable();           // null = the course's mode
            $table->decimal('fee_override', 12, 2)->nullable();        // null = the course's fee
            $table->foreignId('instructor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('schedule', 255)->nullable();               // e.g. "Tue & Thu, 4–7pm"
            $table->string('status', 20)->default('planned')->index(); // planned | open | running | completed | cancelled
            $table->timestamps();
        });

        Schema::create('academy_students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('student_no', 30)->unique();
            $table->string('status', 20)->default('applicant')->index(); // applicant | admitted | active | suspended | completed | withdrawn
            $table->string('education_level', 80)->nullable();
            $table->string('institution', 150)->nullable();
            $table->string('occupation', 120)->nullable();
            $table->string('emergency_contact_name', 150)->nullable();
            $table->string('emergency_contact_phone', 30)->nullable();
            $table->string('guardian_name', 150)->nullable();
            $table->string('guardian_phone', 30)->nullable();
            $table->string('referral_source', 120)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('admitted_at')->nullable();
            $table->foreignId('admitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('academy_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('academy_students')->cascadeOnDelete();
            $table->foreignId('cohort_id')->constrained('academy_cohorts')->restrictOnDelete();
            $table->foreignId('course_id')->constrained('academy_courses')->restrictOnDelete();
            $table->string('status', 20)->default('pending')->index(); // pending | active | completed | withdrawn | cancelled
            $table->decimal('fee_amount', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->char('currency', 3)->default('NGN');
            $table->boolean('access_granted')->default(false);
            $table->timestamp('access_granted_at')->nullable();
            $table->foreignId('access_granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('access_reason', 255)->nullable();
            $table->boolean('prerequisites_waived')->default(false);
            $table->timestamp('enrolled_at')->nullable();
            $table->foreignId('enrolled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('ended_at')->nullable();                 // withdrawn or cancelled
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['student_id', 'cohort_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academy_enrollments');
        Schema::dropIfExists('academy_students');
        Schema::dropIfExists('academy_cohorts');
        Schema::dropIfExists('academy_course_prerequisites');
        Schema::dropIfExists('academy_courses');
        Schema::dropIfExists('academy_programmes');
    }
};
