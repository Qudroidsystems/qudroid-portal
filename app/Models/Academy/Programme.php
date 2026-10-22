<?php

namespace App\Models\Academy;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/** A group of related courses, e.g. "Web Development" or "Data Science". */
class Programme extends Model
{
    protected $table = 'academy_programmes';

    protected $fillable = ['name', 'slug', 'description', 'position', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    protected static function booted(): void
    {
        static::saving(function (self $p) {
            if (empty($p->slug)) $p->slug = Str::slug($p->name);
        });
    }

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class, 'programme_id')->orderBy('title');
    }
}
