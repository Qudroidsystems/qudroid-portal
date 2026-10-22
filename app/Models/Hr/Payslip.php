<?php

namespace App\Models\Hr;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payslip extends Model
{
    protected $table = 'hr_payslips';

    protected $fillable = ['payroll_run_id', 'employee_id', 'basic', 'housing', 'transport', 'other_allowance', 'bonus', 'unpaid_days', 'unpaid_deduction',
        'gross', 'pension', 'nhf', 'paye', 'other_deductions', 'other_deductions_note', 'net', 'employer_pension', 'workings', 'bank'];

    protected $casts = [
        'basic' => 'decimal:2', 'housing' => 'decimal:2', 'transport' => 'decimal:2', 'other_allowance' => 'decimal:2', 'bonus' => 'decimal:2',
        'unpaid_days' => 'float', 'unpaid_deduction' => 'decimal:2', 'gross' => 'decimal:2', 'pension' => 'decimal:2', 'nhf' => 'decimal:2',
        'paye' => 'decimal:2', 'other_deductions' => 'decimal:2', 'net' => 'decimal:2', 'employer_pension' => 'decimal:2',
        'workings' => 'array', 'bank' => 'array',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** Pension + NHF + PAYE + other deductions. */
    public function totalDeductions(): string
    {
        return \App\Support\Money::major(\App\Support\Money::minor($this->pension) + \App\Support\Money::minor($this->nhf)
            + \App\Support\Money::minor($this->paye) + \App\Support\Money::minor($this->other_deductions));
    }
}
