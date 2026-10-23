<?php

namespace App\Models\Hr;

use App\Models\User;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employee extends Model
{
    use \App\Models\Concerns\HasBusinessUnit;
    public const TYPES = ['full_time' => 'Full time', 'part_time' => 'Part time', 'contract' => 'Contract', 'intern' => 'Intern'];
    public const STATUSES = ['active' => 'Active', 'suspended' => 'Suspended', 'exited' => 'Left'];

    protected $table = 'hr_employees';

    protected $fillable = ['user_id', 'employee_no', 'department_id', 'job_title', 'employment_type', 'status', 'start_date', 'exit_date', 'exit_reason',
        'manager_id', 'next_of_kin_name', 'next_of_kin_phone', 'basic_salary', 'housing_allowance', 'transport_allowance', 'other_allowance',
        'annual_rent', 'pension_enrolled', 'nhf_enrolled', 'bank_name', 'account_number', 'account_name', 'pension_pfa', 'pension_pin',
        'tax_id', 'nhf_number', 'annual_leave_days', 'notes'];

    protected $casts = [
        'start_date' => 'date', 'exit_date' => 'date', 'basic_salary' => 'decimal:2', 'housing_allowance' => 'decimal:2',
        'transport_allowance' => 'decimal:2', 'other_allowance' => 'decimal:2', 'annual_rent' => 'decimal:2',
        'pension_enrolled' => 'boolean', 'nhf_enrolled' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('status', 'active');
    }

    /** Monthly gross before bonuses and deductions, in minor units. */
    public function monthlyGrossMinor(): int
    {
        return Money::minor($this->basic_salary) + Money::minor($this->housing_allowance) + Money::minor($this->transport_allowance) + Money::minor($this->other_allowance);
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->employment_type] ?? ucfirst((string) $this->employment_type);
    }
}
