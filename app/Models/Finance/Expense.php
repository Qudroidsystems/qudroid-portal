<?php

namespace App\Models\Finance;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Money the company spends: submitted, approved (or rejected), then paid. */
class Expense extends Model
{
    public const STATUSES = ['submitted' => 'Awaiting approval', 'approved' => 'Approved, to pay', 'rejected' => 'Rejected', 'paid' => 'Paid'];

    protected $table = 'finance_expenses';

    protected $fillable = [
        'number', 'date', 'payee', 'description', 'account_id', 'amount', 'currency', 'status', 'attachment_path', 'attachment_name',
        'submitted_by', 'approved_by', 'approved_at', 'rejection_reason', 'paid_from_account_id', 'paid_by', 'paid_at', 'payment_reference',
    ];

    protected $casts = ['date' => 'date', 'amount' => 'decimal:2', 'approved_at' => 'datetime', 'paid_at' => 'datetime'];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function paidFrom(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'paid_from_account_id');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst($this->status);
    }
}
