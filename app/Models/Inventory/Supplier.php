<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    protected $table = 'inv_suppliers';

    protected $fillable = ['name', 'contact_name', 'email', 'phone', 'address', 'bank_details', 'notes', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }
}
