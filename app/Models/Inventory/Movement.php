<?php

namespace App\Models\Inventory;

use App\Models\Projects\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Movement extends Model
{
    public const TYPES = ['receive' => 'Received', 'issue' => 'Issued', 'adjust' => 'Adjusted', 'transfer' => 'Transferred'];

    protected $table = 'inv_movements';

    protected $fillable = ['item_id', 'location_id', 'type', 'quantity', 'unit_cost', 'balance', 'purchase_order_id', 'issued_to', 'project_id',
        'reference', 'note', 'created_by'];

    protected $casts = ['quantity' => 'float', 'balance' => 'float', 'unit_cost' => 'decimal:2'];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_to');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? ucfirst($this->type);
    }
}
