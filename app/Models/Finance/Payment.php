<?php

namespace App\Models\Finance;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Money received. Confirmed payments are never edited or deleted, only reversed. */
class Payment extends Model
{
    public const METHODS = [
        'paystack'      => 'Paystack (online)',
        'opay'          => 'OPay (online)',
        'bank_transfer' => 'Bank transfer',
        'cash'          => 'Cash',
        'pos'           => 'POS / card terminal',
        'cheque'        => 'Cheque',
    ];

    /** Methods staff may record by hand (online payments are confirmed by the gateway). */
    public const MANUAL_METHODS = ['bank_transfer', 'cash', 'pos', 'cheque'];

    public const STATUSES = [
        'pending'   => 'Pending',
        'confirmed' => 'Confirmed',
        'failed'    => 'Failed',
        'reversed'  => 'Reversed',
    ];

    protected $table = 'finance_payments';

    protected $fillable = [
        'number', 'user_id', 'invoice_id', 'amount', 'currency', 'method', 'reference', 'status', 'paid_at',
        'gateway_response', 'notes', 'recorded_by', 'confirmed_by', 'confirmed_at', 'reversed_at',
        'reversed_by', 'reversal_reason',
    ];

    protected $casts = [
        'amount'           => 'decimal:2',
        'paid_at'          => 'datetime',
        'confirmed_at'     => 'datetime',
        'reversed_at'      => 'datetime',
        'gateway_response' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function reverser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }

    public function getMethodLabelAttribute(): string
    {
        return self::METHODS[$this->method] ?? ucfirst((string) $this->method);
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }
}
