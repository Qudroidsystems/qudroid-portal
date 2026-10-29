<?php

namespace App\Models\Website;

use Illuminate\Database\Eloquent\Model;

/** Something we make or sell, shown on the website. */
class Product extends Model
{
    use HasSiteImage;

    protected $table = 'site_products';

    protected $fillable = ['slug', 'name', 'tagline', 'category', 'summary', 'body', 'features', 'icon', 'color', 'image',
        'link_url', 'link_label', 'price_label', 'featured', 'status', 'position'];

    protected $casts = ['features' => 'array', 'featured' => 'boolean', 'position' => 'integer'];
}
