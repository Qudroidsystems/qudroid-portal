<?php

namespace App\Models\Finance;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** How much of a payment went to an invoice. */
class PaymentAllocation extends Model
{
    protected $table = 'finance_payment_allocations';

    protected $fillable = ['payment_id', 'invoice_id', 'amount'];

    protected $casts = ['amount' => 'decimal:2'];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
