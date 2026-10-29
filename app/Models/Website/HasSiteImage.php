<?php

namespace App\Models\Website;

use Illuminate\Database\Eloquent\Builder;

/** Shared bits for website content: published scope, ordering and image addresses. */
trait HasSiteImage
{
    public function scopePublished(Builder $q): Builder
    {
        return $q->where('status', 'published');
    }

    public function scopeOrdered(Builder $q): Builder
    {
        return $q->orderBy('position')->orderBy('id');
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    /** The image's web address: an uploaded file (public disk) or a full URL. */
    public function imageUrl(): ?string
    {
        return static::url($this->image);
    }

    public static function url(?string $path): ?string
    {
        if (!$path) return null;
        if (preg_match('#^(https?:)?//#', $path) || str_starts_with($path, '/')) return $path;

        return asset('storage/' . ltrim($path, '/'));
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
