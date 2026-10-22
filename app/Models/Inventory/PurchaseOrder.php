<?php

namespace App\Models\Inventory;

use App\Models\Finance\Expense;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrder extends Model
{
    public const STATUSES = ['draft' => 'Draft', 'ordered' => 'Ordered', 'partial' => 'Part received', 'received' => 'Received', 'cancelled' => 'Cancelled'];

    protected $table = 'inv_purchase_orders';

    protected $fillable = ['number', 'supplier_id', 'status', 'order_date', 'expected_on', 'location_id', 'total', 'currency', 'notes',
        'created_by', 'expense_id', 'cancelled_at'];

    protected $casts = ['order_date' => 'date', 'expected_on' => 'date', 'total' => 'decimal:2', 'cancelled_at' => 'datetime'];

    public function getRouteKeyName(): string
    {
        return 'number';
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseOrderLine::class)->orderBy('position');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    public function isLate(): bool
    {
        return in_array($this->status, ['ordered', 'partial'], true) && $this->expected_on && $this->expected_on->lt(today());
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst($this->status);
    }
}
