<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * HR & Payroll: departments, employee records, leave, public holidays,
 * monthly payroll runs and payslips.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_departments', function (Blueprint $t) {
            $t->id();
            $t->string('name', 100)->unique();
            $t->foreignId('head_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('description', 255)->nullable();
            $t->timestamps();
        });

        Schema::create('hr_employees', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $t->string('employee_no', 20)->unique();
            $t->foreignId('department_id')->nullable()->constrained('hr_departments')->nullOnDelete();
            $t->string('job_title', 100)->nullable();
            $t->string('employment_type', 12)->default('full_time');   // full_time, part_time, contract, intern
            $t->string('status', 10)->default('active');              // active, suspended, exited
            $t->date('start_date')->nullable();
            $t->date('exit_date')->nullable();
            $t->string('exit_reason', 255)->nullable();
            $t->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('next_of_kin_name', 150)->nullable();
            $t->string('next_of_kin_phone', 40)->nullable();
            // Pay (monthly, in the payroll currency)
            $t->decimal('basic_salary', 14, 2)->default(0);
            $t->decimal('housing_allowance', 14, 2)->default(0);
            $t->decimal('transport_allowance', 14, 2)->default(0);
            $t->decimal('other_allowance', 14, 2)->default(0);
            $t->decimal('annual_rent', 14, 2)->nullable();             // for rent relief in PAYE
            $t->boolean('pension_enrolled')->default(true);
            $t->boolean('nhf_enrolled')->default(false);
            $t->string('bank_name', 100)->nullable();
            $t->string('account_number', 20)->nullable();
            $t->string('account_name', 150)->nullable();
            $t->string('pension_pfa', 100)->nullable();
            $t->string('pension_pin', 30)->nullable();
            $t->string('tax_id', 30)->nullable();
            $t->string('nhf_number', 30)->nullable();
            $t->unsignedSmallInteger('annual_leave_days')->default(20);
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->index(['status', 'department_id']);
        });

        Schema::create('hr_leave_types', function (Blueprint $t) {
            $t->id();
            $t->string('name', 60)->unique();
            $t->unsignedSmallInteger('days_per_year')->nullable();   // null = no limit
            $t->boolean('is_paid')->default(true);
            $t->boolean('is_annual')->default(false);                // uses the employee's own annual allowance
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('hr_holidays', function (Blueprint $t) {
            $t->id();
            $t->date('date')->unique();
            $t->string('name', 100);
            $t->timestamps();
        });

        Schema::create('hr_leave_requests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $t->foreignId('leave_type_id')->constrained('hr_leave_types');
            $t->date('starts_on');
            $t->date('ends_on');
            $t->decimal('days', 5, 1);                                // working days
            $t->string('reason', 500)->nullable();
            $t->string('status', 10)->default('pending');            // pending, approved, rejected, cancelled
            $t->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('decided_at')->nullable();
            $t->string('decision_note', 255)->nullable();
            $t->timestamps();
            $t->index(['employee_id', 'status', 'starts_on']);
        });

        Schema::create('hr_payroll_runs', function (Blueprint $t) {
            $t->id();
            $t->date('period')->unique();                             // first day of the month
            $t->string('status', 10)->default('draft');              // draft, approved, paid
            $t->decimal('gross', 16, 2)->default(0);
            $t->decimal('deductions', 16, 2)->default(0);
            $t->decimal('net', 16, 2)->default(0);
            $t->decimal('employer_pension', 16, 2)->default(0);
            $t->foreignId('prepared_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('approved_at')->nullable();
            $t->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('paid_at')->nullable();
            $t->foreignId('paid_from_account_id')->nullable()->constrained('finance_accounts')->nullOnDelete();
            $t->text('notes')->nullable();
            $t->timestamps();
        });

        Schema::create('hr_payslips', function (Blueprint $t) {
            $t->id();
            $t->foreignId('payroll_run_id')->constrained('hr_payroll_runs')->cascadeOnDelete();
            $t->foreignId('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $t->decimal('basic', 14, 2)->default(0);
            $t->decimal('housing', 14, 2)->default(0);
            $t->decimal('transport', 14, 2)->default(0);
            $t->decimal('other_allowance', 14, 2)->default(0);
            $t->decimal('bonus', 14, 2)->default(0);
            $t->decimal('unpaid_days', 5, 1)->default(0);
            $t->decimal('unpaid_deduction', 14, 2)->default(0);
            $t->decimal('gross', 14, 2)->default(0);
            $t->decimal('pension', 14, 2)->default(0);                // employee share
            $t->decimal('nhf', 14, 2)->default(0);
            $t->decimal('paye', 14, 2)->default(0);
            $t->decimal('other_deductions', 14, 2)->default(0);
            $t->string('other_deductions_note', 255)->nullable();
            $t->decimal('net', 14, 2)->default(0);
            $t->decimal('employer_pension', 14, 2)->default(0);
            $t->json('workings')->nullable();                         // how PAYE was worked out
            $t->json('bank')->nullable();                             // bank details at the time
            $t->timestamps();
            $t->unique(['payroll_run_id', 'employee_id']);
        });

        $now = now();
        DB::table('hr_leave_types')->insertOrIgnore([
            ['name' => 'Annual leave', 'days_per_year' => null, 'is_paid' => true, 'is_annual' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Sick leave', 'days_per_year' => 12, 'is_paid' => true, 'is_annual' => false, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Casual leave', 'days_per_year' => 5, 'is_paid' => true, 'is_annual' => false, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Maternity leave', 'days_per_year' => 60, 'is_paid' => true, 'is_annual' => false, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Paternity leave', 'days_per_year' => 10, 'is_paid' => true, 'is_annual' => false, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Compassionate leave', 'days_per_year' => 5, 'is_paid' => true, 'is_annual' => false, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Unpaid leave', 'days_per_year' => null, 'is_paid' => false, 'is_annual' => false, 'created_at' => $now, 'updated_at' => $now],
        ]);

        // Ledger accounts payroll posts to.
        DB::table('finance_accounts')->insertOrIgnore([
            ['code' => '2300', 'name' => 'Pension payable', 'type' => 'liability', 'is_system' => true, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => '2400', 'name' => 'NHF payable', 'type' => 'liability', 'is_system' => true, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => '2500', 'name' => 'Other payroll deductions', 'type' => 'liability', 'is_system' => true, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
        DB::table('finance_accounts')->whereIn('code', ['2200', '5000'])->update(['is_system' => true]);
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_payslips');
        Schema::dropIfExists('hr_payroll_runs');
        Schema::dropIfExists('hr_leave_requests');
        Schema::dropIfExists('hr_holidays');
        Schema::dropIfExists('hr_leave_types');
        Schema::dropIfExists('hr_employees');
        Schema::dropIfExists('hr_departments');
    }
};
