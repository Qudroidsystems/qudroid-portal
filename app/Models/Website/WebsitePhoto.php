<?php

namespace App\Models\Website;

use Illuminate\Database\Eloquent\Model;

/** Public website content (see App\Services\Website\WebsiteContent). */
class WebsitePhoto extends Model
{
    protected $table = 'website_photos';

    protected $fillable = ['album_id','path','thumb','caption','width','height','sort'];

    protected $casts = [];

    public function album()
    {
        return $this->belongsTo(WebsiteAlbum::class, 'album_id');
    }
}
