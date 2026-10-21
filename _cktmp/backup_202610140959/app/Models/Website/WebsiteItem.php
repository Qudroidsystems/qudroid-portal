<?php

namespace App\Models\Website;

use Illuminate\Database\Eloquent\Model;

/** Public website content (see App\Services\Website\WebsiteContent). */
class WebsiteItem extends Model
{
    protected $table = 'website_items';

    protected $fillable = ['kind','title','body','subtitle','image','link','icon','sort','published'];

    protected $casts = ['published' => 'boolean'];

    public const KINDS = [
        'testimonial' => ['Testimonials', 'What parents, students and alumni say.'],
        'faq'         => ['FAQs', 'Questions shown on the home and admissions pages.'],
        'value'       => ['Core values', 'The values shown on the home page.'],
        'partner'     => ['Partners & accreditations', 'Logos shown in the partners strip.'],
        'highlight'   => ['Why choose us', 'Short highlights on the home page (icon, title, text).'],
    ];
}
