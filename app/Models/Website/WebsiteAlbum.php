<?php

namespace App\Models\Website;

use Illuminate\Database\Eloquent\Model;

/** Public website content (see App\Services\Website\WebsiteContent). */
class WebsiteAlbum extends Model
{
    protected $table = 'website_albums';

    protected $fillable = ['title','slug','description','taken_on','cover_photo_id','sort','published'];

    protected $casts = ['published' => 'boolean', 'taken_on' => 'date'];

    public function photos()
    {
        return $this->hasMany(WebsitePhoto::class, 'album_id')->orderBy('sort')->orderBy('id');
    }

    public function cover()
    {
        return $this->belongsTo(WebsitePhoto::class, 'cover_photo_id');
    }

    /** The cover photo, or the album's first photo. */
    public function coverPhoto(): ?WebsitePhoto
    {
        return $this->cover ?? $this->photos->first();
    }
}
