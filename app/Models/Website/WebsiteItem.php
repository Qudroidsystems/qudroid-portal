<?php

namespace App\Models\Website;

use Illuminate\Database\Eloquent\Model;

/** Public website content (see App\Services\Website\WebsiteContent). */
class WebsiteItem extends Model
{
    protected $table = 'website_items';

    protected $fillable = ['kind','title','body','subtitle','image','image2','image3','link','icon','sort','published'];

    protected $casts = ['published' => 'boolean'];

    public const KINDS = [
        'testimonial' => ['Testimonials', 'What parents, students and alumni say.'],
        'faq'         => ['FAQs', 'Questions shown on the home and admissions pages.'],
        'value'       => ['Core values', 'The values shown on the home page.'],
        'partner'     => ['Partners & accreditations', 'Logos shown in the partners strip.'],
        'highlight'   => ['Why choose us', 'Short highlights on the home page (icon, title, text).'],
        'app_screen'  => ['App screens', 'Screenshots of the mobile app shown in phone frames on the home page and the Mobile app page — one feature per item, up to three screenshots each.'],
    ];

    /** Screenshots of an app_screen item, main first. */
    public function screens(): array
    {
        return array_values(array_filter([$this->image, $this->image2, $this->image3]));
    }
}
