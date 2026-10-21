<?php

namespace App\Models\Website;

use Illuminate\Database\Eloquent\Model;

/** Public website content (see App\Services\Website\WebsiteContent). */
class WebsitePerson extends Model
{
    protected $table = 'website_people';

    protected $fillable = ['user_id','name','role','group','bio','photo','sort','published'];

    protected $casts = ['published' => 'boolean'];
}
