<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Admission requests with several courses (the website's course cart).
 *
 *  - An application now lists the courses chosen (academy_application_courses).
 *    Its own course_id / cohort_id stay as the first choice, so older screens
 *    and the phone app keep working.
 *  - Applicants no longer get a student number; the admission number is given
 *    when they are admitted.
 *  - One invoice can cover several courses: each line names its enrollment.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academy_application_courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained('academy_applications')->cascadeOnDelete();
            $table->foreignId('course_id')->constrained('academy_courses')->restrictOnDelete();
            $table->foreignId('cohort_id')->constrained('academy_cohorts')->restrictOnDelete();
            $table->foreignId('enrollment_id')->nullable()->constrained('academy_enrollments')->nullOnDelete();
            $table->string('status', 20)->default('pending');   // pending | admitted | declined
            $table->string('problem', 255)->nullable();         // why the enrollment could not be made
            $table->timestamps();
            $table->unique(['application_id', 'course_id']);
        });

        Schema::table('academy_applications', function (Blueprint $table) {
            $table->foreignId('invoice_id')->nullable()->after('enrollment_id')->constrained('finance_invoices')->nullOnDelete();
        });

        Schema::table('academy_students', function (Blueprint $table) {
            $table->string('student_no', 30)->nullable()->change();
        });

        Schema::table('finance_invoice_items', function (Blueprint $table) {
            $table->foreignId('enrollment_id')->nullable()->after('invoice_id')->constrained('academy_enrollments')->nullOnDelete();
        });

        // Every earlier application had one course.
        $now = now();
        DB::table('academy_applications')->orderBy('id')->chunkById(200, function ($rows) use ($now) {
            DB::table('academy_application_courses')->insert($rows->map(fn ($a) => [
                'application_id' => $a->id,
                'course_id'      => $a->course_id,
                'cohort_id'      => $a->cohort_id,
                'enrollment_id'  => $a->enrollment_id,
                'status'         => match ($a->status) { 'admitted' => 'admitted', 'declined' => 'declined', default => 'pending' },
                'created_at'     => $a->created_at ?? $now,
                'updated_at'     => $now,
            ])->all());
        });

        // Course-fee invoices already name their enrollment; let their lines say so too.
        DB::table('finance_invoices')->whereNotNull('enrollment_id')->orderBy('id')->chunkById(200, function ($rows) {
            foreach ($rows as $invoice) {
                DB::table('finance_invoice_items')->where('invoice_id', $invoice->id)->update(['enrollment_id' => $invoice->enrollment_id]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('finance_invoice_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('enrollment_id');
        });
        Schema::table('academy_applications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('invoice_id');
        });
        Schema::dropIfExists('academy_application_courses');
    }
};
