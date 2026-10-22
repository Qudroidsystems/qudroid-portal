<?php

namespace App\Models\Inventory;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Asset extends Model
{
    public const STATUSES = ['in_store' => 'In store', 'assigned' => 'Assigned', 'repair' => 'Being repaired', 'retired' => 'Retired', 'lost' => 'Lost'];
    public const CONDITIONS = ['new' => 'New', 'good' => 'Good', 'fair' => 'Fair', 'poor' => 'Poor'];

    protected $table = 'inv_assets';

    protected $fillable = ['tag', 'name', 'category_id', 'make_model', 'serial_number', 'supplier_id', 'purchase_date', 'purchase_cost',
        'warranty_until', 'status', 'condition', 'assigned_to', 'assigned_at', 'location_id', 'notes'];

    protected $casts = ['purchase_date' => 'date', 'warranty_until' => 'date', 'assigned_at' => 'datetime', 'purchase_cost' => 'decimal:2'];

    public function getRouteKeyName(): string
    {
        return 'tag';
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function holder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(AssetEvent::class)->latest('id');
    }

    public function warrantyEndingSoon(): bool
    {
        return $this->warranty_until && $this->warranty_until->between(today(), today()->addDays(60));
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst($this->status);
    }
}
