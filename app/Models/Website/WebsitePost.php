<?php

namespace App\Models\Website;

use Illuminate\Database\Eloquent\Model;

/** Public website content (see App\Services\Website\WebsiteContent). */
class WebsitePost extends Model
{
    protected $table = 'website_posts';

    protected $fillable = ['type','title','slug','category','excerpt','body','cover','event_date','event_time','location','featured','published','published_at','created_by'];

    protected $casts = ['published' => 'boolean', 'featured' => 'boolean', 'event_date' => 'date', 'published_at' => 'datetime'];

    public function scopeLive($q)
    {
        return $q->where('published', true)->where(fn ($w) => $w->whereNull('published_at')->orWhere('published_at', '<=', now()));
    }
}
