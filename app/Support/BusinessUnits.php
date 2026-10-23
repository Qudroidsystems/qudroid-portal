<?php

namespace App\Support;

use App\Models\BusinessUnit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Saves the "Business unit" field of a form onto the record it created or edited. */
class BusinessUnits
{
    public static function apply(Request $request, Model $model): void
    {
        if (!$request->has('business_unit_id')) return;

        $id = $request->validate([
            'business_unit_id' => ['nullable', 'integer', Rule::exists('business_units', 'id')->where(fn ($q) => $q->where('is_active', true)->orWhere('id', $model->business_unit_id ?? 0))],
        ])['business_unit_id'] ?? null;

        if ($id && (int) $id !== (int) $model->business_unit_id) $model->forceFill(['business_unit_id' => (int) $id])->save();
    }

    /** Validation rule for the field, for controllers that validate everything up front. */
    public static function rule(): array
    {
        return ['nullable', 'integer', Rule::exists('business_units', 'id')];
    }

    public static function enabled(): bool
    {
        try {
            return BusinessUnit::active()->count() > 1;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
