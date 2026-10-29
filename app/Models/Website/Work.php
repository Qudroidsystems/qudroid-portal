<?php

namespace App\Models\Website;

use Illuminate\Database\Eloquent\Model;

/** A piece of client work (case study) shown on the website. */
class Work extends Model
{
    use HasSiteImage;

    protected $table = 'site_works';

    protected $fillable = ['slug', 'title', 'client', 'category', 'year', 'summary', 'body', 'tags', 'results', 'image', 'color',
        'link_url', 'featured', 'status', 'position'];

    protected $casts = ['tags' => 'array', 'results' => 'array', 'featured' => 'boolean', 'year' => 'integer', 'position' => 'integer'];
}
