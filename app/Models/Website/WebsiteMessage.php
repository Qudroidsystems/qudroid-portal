<?php

namespace App\Models\Website;

use Illuminate\Database\Eloquent\Model;

/** Public website content (see App\Services\Website\WebsiteContent). */
class WebsiteMessage extends Model
{
    protected $table = 'website_messages';

    protected $fillable = ['type','name','email','phone','subject','message','data','ip','read_at'];

    protected $casts = ['data' => 'array', 'read_at' => 'datetime'];
}
