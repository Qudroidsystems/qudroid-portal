<?php

namespace App\Models\Concerns;

use App\Models\BusinessUnit;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A record that belongs to a business unit. New records without one get
 * defaultBusinessUnitId(), so every record is counted somewhere.
 */
trait HasBusinessUnit
{
    public static function bootHasBusinessUnit(): void
    {
        static::creating(function ($model) {
            if (empty($model->business_unit_id)) $model->business_unit_id = $model->defaultBusinessUnitId();
        });
    }

    public function initializeHasBusinessUnit(): void
    {
        if (!in_array('business_unit_id', $this->fillable, true) && $this->fillable) $this->fillable[] = 'business_unit_id';
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(BusinessUnit::class, 'business_unit_id');
    }

    protected function defaultBusinessUnitId(): ?int
    {
        return BusinessUnit::defaultId();
    }
}
