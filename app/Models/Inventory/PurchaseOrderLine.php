<?php

namespace App\Models\Inventory;

use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderLine extends Model
{
    protected $table = 'inv_purchase_order_lines';

    protected $fillable = ['purchase_order_id', 'item_id', 'description', 'quantity', 'unit_cost', 'received', 'position'];

    protected $casts = ['quantity' => 'float', 'received' => 'float', 'unit_cost' => 'decimal:2'];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function outstanding(): float
    {
        return max(0, round($this->quantity - $this->received, 2));
    }

    public function amountMinor(): int
    {
        return (int) round($this->quantity * Money::minor($this->unit_cost));
    }
}
