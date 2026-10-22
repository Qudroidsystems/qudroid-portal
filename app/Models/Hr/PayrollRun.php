<?php

namespace App\Models\Hr;

use App\Models\Finance\Account;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollRun extends Model
{
    public const STATUSES = ['draft' => 'Draft', 'approved' => 'Approved, to pay', 'paid' => 'Paid'];

    protected $table = 'hr_payroll_runs';

    protected $fillable = ['period', 'status', 'gross', 'deductions', 'net', 'employer_pension', 'prepared_by', 'approved_by', 'approved_at',
        'paid_by', 'paid_at', 'paid_from_account_id', 'notes'];

    protected $casts = ['period' => 'date', 'approved_at' => 'datetime', 'paid_at' => 'datetime',
        'gross' => 'decimal:2', 'deductions' => 'decimal:2', 'net' => 'decimal:2', 'employer_pension' => 'decimal:2'];

    public function getRouteKeyName(): string
    {
        return 'id';
    }

    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class);
    }

    public function preparer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function paidFrom(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'paid_from_account_id');
    }

    /** "October 2026" */
    public function getLabelAttribute(): string
    {
        return $this->period->format('F Y');
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }
}
