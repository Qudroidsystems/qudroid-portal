<?php

namespace App\Models\Checkin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** An office or campus where staff clock in and out. */
class Place extends Model
{
    protected $table = 'checkin_places';

    protected $fillable = ['name', 'address', 'lat', 'lng', 'radius_m', 'work_starts', 'work_ends', 'grace_minutes', 'workdays', 'require_location', 'is_active'];

    protected $casts = ['workdays' => 'array', 'require_location' => 'boolean', 'is_active' => 'boolean', 'lat' => 'float', 'lng' => 'float'];

    public function events(): HasMany
    {
        return $this->hasMany(Event::class, 'place_id');
    }

    public function isWorkday(\DateTimeInterface $d): bool
    {
        return in_array((int) $d->format('N'), $this->workdays ?: [1, 2, 3, 4, 5], true);
    }

    public function hoursLabel(): string
    {
        return substr((string) $this->work_starts, 0, 5) . '–' . substr((string) $this->work_ends, 0, 5);
    }
}
