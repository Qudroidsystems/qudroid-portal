<?php

namespace App\Models\Website;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** An image uploaded for the website (public disk, website/ folder). */
class Media extends Model
{
    protected $table = 'site_media';

    protected $fillable = ['path', 'name', 'mime', 'size', 'width', 'height', 'alt', 'uploaded_by'];

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function url(): string
    {
        return asset('storage/' . $this->path);
    }
}
