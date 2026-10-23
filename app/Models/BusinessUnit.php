<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/** A part of the organisation (Qudroid Systems, Coding Academy, ViteSchool) that money, deals, projects, staff and courses belong to. */
class BusinessUnit extends Model
{
    protected $fillable = ['name', 'code', 'color', 'description', 'is_active', 'position'];

    protected $casts = ['is_active' => 'boolean', 'position' => 'integer'];

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function scopeOrdered(Builder $q): Builder
    {
        return $q->orderBy('position')->orderBy('name');
    }

    /** Units to pick from in forms (active ones, plus the one already chosen). */
    public static function options(?int $current = null): Collection
    {
        return static::ordered()->where(fn ($q) => $q->where('is_active', true)->when($current, fn ($w) => $w->orWhere('id', $current)))->get();
    }

    /** The unit new records go to when none is chosen. */
    public static function defaultId(): ?int
    {
        return static::idFor(config('qudroid.units.default', 'QS'));
    }

    /** The unit for academy courses and student fees. */
    public static function academyId(): ?int
    {
        return static::idFor(config('qudroid.units.academy', 'QCA'));
    }

    protected static function idFor(string $code): ?int
    {
        try {
            return static::where('code', $code)->value('id') ?? static::active()->ordered()->value('id');
        } catch (\Throwable $e) {
            return null;   // before migrate
        }
    }
}
