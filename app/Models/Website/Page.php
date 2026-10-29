<?php

namespace App\Models\Website;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A website page made of blocks (see App\Services\Website\Blocks). "home" is the front page. */
class Page extends Model
{
    use HasSiteImage;

    public const STATUSES = ['draft' => 'Draft', 'published' => 'Published'];

    /** Addresses the website itself uses, so a page can't take them. */
    public const RESERVED = ['home', 'products', 'work', 'courses', 'contact', 'forms', 'pay', 'website', 'sitemap',
        'academy', 'activity-log', 'adduser', 'admin', 'api', 'apply', 'attendance', 'billing', 'broadcasting', 'c', 'calendar',
        'certificates', 'checkin', 'command-center', 'connect', 'crm', 'dashboard', 'drive', 'e', 'finance', 'hr', 'inventory',
        'login', 'logout', 'register', 'mail', 'me', 'meetings', 'notifications', 'online-staff', 'password', 'permissions', 'profile',
        'projects', 'refresh-csrf', 'roles', 's', 'sanctum', 'search', 'storage', 'up', 'user', 'users', 'verify', 'webhook',
        'workflows', 'workspace', 'theme', 'css', 'js', 'fonts', 'images', 'vendor'];

    protected $table = 'site_pages';

    protected $fillable = ['slug', 'title', 'description', 'blocks', 'status', 'in_nav', 'in_footer', 'position', 'updated_by'];

    protected $casts = ['blocks' => 'array', 'in_nav' => 'boolean', 'in_footer' => 'boolean', 'position' => 'integer'];

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function url(): string
    {
        return $this->slug === 'home' ? route('site.home') : url('/' . $this->slug);
    }
}
