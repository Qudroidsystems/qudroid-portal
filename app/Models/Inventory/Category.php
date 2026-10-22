<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $table = 'inv_categories';

    protected $fillable = ['name', 'kind'];

    public function scopeKind(Builder $q, string $kind): Builder
    {
        return $q->where('kind', $kind)->orderBy('name');
    }
}
