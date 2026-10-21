<?php

namespace App\Models\Website;

use Illuminate\Database\Eloquent\Model;

/** Public website content (see App\Services\Website\WebsiteContent). */
class WebsiteSlide extends Model
{
    protected $table = 'website_slides';

    protected $fillable = ['title','subtitle','image','button_text','button_link','sort','published'];

    protected $casts = ['published' => 'boolean'];
}
