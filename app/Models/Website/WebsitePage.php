<?php

namespace App\Models\Website;

use Illuminate\Database\Eloquent\Model;

/** Public website content (see App\Services\Website\WebsiteContent). */
class WebsitePage extends Model
{
    protected $table = 'website_pages';

    protected $fillable = ['title','slug','menu','summary','body','banner','sort','published'];

    protected $casts = ['published' => 'boolean'];
}
