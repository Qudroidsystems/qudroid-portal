<?php

namespace App\Models\Inventory;

use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Item extends Model
{
    protected $table = 'inv_items';

    protected $fillable = ['sku', 'name', 'category_id', 'unit', 'reorder_level', 'avg_cost', 'supplier_id', 'description', 'is_active'];

    protected $casts = ['reorder_level' => 'float', 'avg_cost' => 'decimal:2', 'is_active' => 'boolean'];

    public function getRouteKeyName(): string
    {
        return 'sku';
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function stock(): HasMany
    {
        return $this->hasMany(Stock::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(Movement::class);
    }

    /** Units on hand across all locations (uses a loaded `on_hand` sum when present). */
    public function onHand(): float
    {
        return (float) ($this->attributes['on_hand'] ?? $this->stock()->sum('quantity'));
    }

    public function isLow(): bool
    {
        return $this->reorder_level > 0 && $this->onHand() <= $this->reorder_level;
    }

    /** Stock value at average cost, in minor units. */
    public function valueMinor(): int
    {
        return (int) round($this->onHand() * Money::minor($this->avg_cost));
    }

    /** "12" or "2.5" */
    public static function qty($n): string
    {
        return rtrim(rtrim(number_format((float) $n, 2), '0'), '.');
    }
}
