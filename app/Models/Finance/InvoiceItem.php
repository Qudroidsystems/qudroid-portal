<?php

namespace App\Models\Finance;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    public $timestamps = false;

    protected $table = 'finance_invoice_items';

    protected $fillable = ['invoice_id', 'enrollment_id', 'description', 'quantity', 'unit_price', 'discount', 'amount', 'position'];

    protected $casts = [
        'quantity'   => 'decimal:2',
        'unit_price' => 'decimal:2',
        'discount'   => 'decimal:2',
        'amount'     => 'decimal:2',
    ];

    /** The course enrollment this line charges for, if any. */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Academy\Enrollment::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
