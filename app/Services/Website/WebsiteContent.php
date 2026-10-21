<?php

namespace App\Services\Website;

use App\Models\Website\WebsitePage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Everything the public website needs, in one place:
 *  - the website settings the admin edits (cached),
 *  - live school data from the portal (name, logo, contacts, counts, classes,
 *    subjects, public calendar events),
 *  - image handling for uploads (resized, with thumbnails) and HTML cleaning
 *    for text written in the admin editor.
 */
class WebsiteContent
{
    protected const CACHE = 'website.settings.v1';

    // ── settings ────────────────────────────────────────────────────────

    public static function settings(): array
    {
        if (!Schema::hasTable('website_settings')) return [];
        return Cache::rememberForever(self::CACHE, fn () => DB::table('website_settings')->pluck('value', 'key')->all());
    }

    public static function get(string $key, $default = null)
    {
        $v = self::settings()[$key] ?? null;
        return ($v === null || $v === '') ? $default : $v;
    }

    public static function put(array $values): void
    {
        $now = now();
        foreach ($values as $k => $v) {
            DB::table('website_settings')->updateOrInsert(['key' => $k], ['value' => is_array($v) ? json_encode($v) : $v, 'updated_at' => $now, 'created_at' => $now]);
        }
        self::flush();
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE);
        Cache::forget('website.stats');
    }

    public static function enabled(): bool
    {
        return Schema::hasTable('website_settings') && self::get('enabled', '1') === '1';
    }

    // ── domains ─────────────────────────────────────────────────────────
    //
    // The website lives on the school's main domain (e.g. www.csskabba.ng)
    // and the portal on its own (e.g. portal.csskabba.ng). Both domains point
    // at this same Laravel app; the request's host decides which one answers.
    // With no website domain set, "/" opens the portal login and the website
    // can be previewed at /website-preview.

    /** Normalise "https://www.School.ng/path" → "www.school.ng". */
    public static function hostOf(?string $value): string
    {
        $v = strtolower(trim((string) $value));
        if ($v === '') return '';
        if (!str_contains($v, '://')) $v = 'http://' . $v;
        return (string) (parse_url($v, PHP_URL_HOST) ?? '');
    }

    /** The website's domain without "www." (empty = not set). */
    public static function websiteDomain(): string
    {
        return preg_replace('/^www\./', '', self::hostOf(self::get('website_domain')));
    }

    /** True when this request came in on the website's domain (with or without www). */
    public static function isWebsiteHost(?string $host = null): bool
    {
        $domain = self::websiteDomain();
        if ($domain === '') return false;
        $host = preg_replace('/^www\./', '', strtolower($host ?? request()->getHost()));
        return $host === $domain;
    }

    /** Base URL of the portal, e.g. https://portal.csskabba.ng (falls back to APP_URL). */
    public static function portalUrl(string $path = ''): string
    {
        $base = trim((string) self::get('portal_url', ''));
        if ($base === '') {
            // On the portal itself, or before domains are set: this app's own address.
            $base = self::isWebsiteHost() ? rtrim((string) config('app.url'), '/') : rtrim(request()->getSchemeAndHttpHost(), '/');
        }
        if (!str_contains($base, '://')) $base = 'https://' . $base;
        return rtrim($base, '/') . ($path === '' ? '' : '/' . ltrim($path, '/'));
    }

    /** The website's home page: its own domain once set, otherwise the /website-preview page. */
    public static function homeUrl(): string
    {
        return self::websiteDomain() === '' ? url('/website-preview') : self::websiteUrl('/');
    }

    /** The live website's own admin (www.<school>/admin), or null until the website domain is set. */
    public static function websiteAdminUrl(): ?string
    {
        return self::websiteDomain() === '' ? null : self::websiteUrl('/admin');
    }

    /** Full URL on the website's domain (keeps "www." if the admin typed it). */
    public static function websiteUrl(string $path = ''): string
    {
        $host = self::hostOf(self::get('website_domain'));
        if ($host === '') return $path === '' || $path === '/' ? url('/website-preview') : url($path);
        $scheme = request()->isSecure() || str_starts_with((string) config('app.url'), 'https') ? 'https' : 'http';
        return $scheme . '://' . $host . ($path === '' ? '' : '/' . ltrim($path, '/'));
    }

    // ── school (from the portal) ────────────────────────────────────────

    public static function school(): object
    {
        static $school;
        if ($school) return $school;
        // The portal's School information (model), active record first.
        $row = null;
        try { $row = \App\Models\SchoolInformation::getActiveSchool() ?? \App\Models\SchoolInformation::first(); } catch (\Throwable $e) {}
        // Portal data first; the website's own saved copy when the portal's isn't available.
        $pick = fn ($v, string $key) => (is_string($v) ? trim($v) !== '' : !empty($v)) ? $v : self::get($key);
        // Logo uploaded under Website › Settings wins; otherwise the portal's, then the saved copy.
        $logo = self::get('website_logo') ?: $pick($row->school_logo ?? null, 'school_logo');
        $phones = self::phones($pick($row->school_phones ?? null, 'school_phones'));
        return $school = (object) [
            'name'    => $pick($row->school_name ?? null, 'school_name') ?: config('app.name'),
            'motto'   => $pick($row->school_motto ?? null, 'school_motto'),
            'address' => $pick($row->school_address ?? null, 'school_address'),
            'email'   => $pick($row->school_email ?? null, 'school_email'),
            'phones'  => $phones,
            'website' => $row->school_website ?? null,
            'logo'    => $logo ? self::fileUrl($logo) : null,
            'next_term_begins' => $row->date_next_term_begins ?? null,
        ];
    }

    /** Top-bar links: "Label | /path" per line (admin setting), with the standard four as default. @return array<int,array{0:string,1:string}> */
    public static function topbarLinks(): array
    {
        $raw = (string) self::get('topbar_links', "Apply | /admissions#apply\nVisit us | /contact#visit\nNews & updates | /news\nGallery | /gallery");
        $out = [];
        foreach (preg_split('/\R/', $raw) as $line) {
            [$label, $url] = array_pad(array_map('trim', explode('|', $line, 2)), 2, '');
            if ($label === '' || $url === '') continue;
            $out[] = [$label, str_starts_with($url, '/') ? self::websiteUrl($url) : $url];
        }
        return $out;
    }

    /** Phone numbers from a JSON array (how School information stores them), an array, or "a, b / c". */
    public static function phones($value): array
    {
        if (is_string($value)) {
            $t = trim($value);
            $decoded = str_starts_with($t, '[') ? json_decode($t, true) : null;
            $value = is_array($decoded) ? $decoded : preg_split('/[,;\/\n]+/', $t);
        }
        return collect((array) $value)->map(fn ($p) => trim((string) $p, " \t\n\r\0\x0B\"'[]"))->filter()->values()->all();
    }

    /** A stored file path (relative to the public disk, or a full URL) as a URL. */
    public static function fileUrl(?string $path): ?string
    {
        if (!$path) return null;
        if (Str::startsWith($path, ['http://', 'https://', '//'])) return $path;
        if (Str::startsWith($path, '/')) return url($path);
        if (is_file(public_path($path))) return asset($path);
        $path = ltrim($path, '/');
        // Normal case: public/storage is the `php artisan storage:link` symlink.
        if (is_link(public_path('storage')) || is_file(public_path('storage/' . $path))) return asset('storage/' . $path);
        // Hosts where public/storage is a plain folder: stream website files through Laravel.
        if (Str::startsWith($path, 'website/') && Storage::disk('public')->exists($path)) return route('site.media', ['path' => $path]);
        return asset('storage/' . $path);
    }

    /** Live numbers for the counters strip. */
    public static function stats(): array
    {
        return Cache::remember('website.stats', now()->addHours(6), function () {
            $students = 0; $staff = 0; $classes = 0; $subjects = 0;
            try {
                $students = Schema::hasTable('studentRegistration')
                    ? (int) DB::table('studentRegistration')->where(fn ($q) => $q->whereNull('statusId')->orWhere('statusId', 1))->count() : 0;
                if (!$students && Schema::hasTable('studentRegistration')) $students = (int) DB::table('studentRegistration')->count();
                $staff = Schema::hasTable('staffbioinfo') ? (int) DB::table('staffbioinfo')->whereNull('deleted_at')->count() : 0;
                $classes = Schema::hasTable('schoolclass') ? (int) DB::table('schoolclass')->count() : 0;
                $subjects = Schema::hasTable('subject') ? (int) DB::table('subject')->count() : 0;
            } catch (\Throwable $e) {}
            // Saved numbers when the portal's tables can't be read here.
            $students = $students ?: (int) self::get('stats_students', 0);
            $staff = $staff ?: (int) self::get('stats_staff', 0);
            $classes = $classes ?: (int) self::get('stats_classes', 0);
            $subjects = $subjects ?: (int) self::get('stats_subjects', 0);
            $founded = (int) self::get('founded_year', 0);
            return array_values(array_filter([
                $students ? ['value' => $students, 'label' => 'Students', 'suffix' => '+'] : null,
                $staff ? ['value' => $staff, 'label' => 'Teachers & staff', 'suffix' => ''] : null,
                $classes ? ['value' => $classes, 'label' => 'Classes', 'suffix' => ''] : null,
                $subjects ? ['value' => $subjects, 'label' => 'Subjects taught', 'suffix' => ''] : null,
                $founded > 1800 ? ['value' => now()->year - $founded, 'label' => 'Years of excellence', 'suffix' => '+'] : null,
            ]));
        });
    }

    /** Class levels and subjects for the Academics page. */
    public static function academics(): array
    {
        $levels = collect();
        $subjects = collect();
        try {
            if (Schema::hasTable('schoolclass')) {
                $levels = DB::table('schoolclass as c')->leftJoin('classcategories as cc', 'cc.id', '=', 'c.classcategoryid')
                    ->select('c.schoolclass', 'cc.category', 'cc.is_senior')->distinct()->orderBy('c.schoolclass')->get()
                    ->groupBy(fn ($r) => $r->category ?: ($r->is_senior ? 'Senior school' : 'Junior school'))
                    ->map(fn ($g) => $g->pluck('schoolclass')->unique()->values());
            }
            if (Schema::hasTable('subject')) $subjects = DB::table('subject')->orderBy('subject')->pluck('subject')->unique()->values();
        } catch (\Throwable $e) {}
        return ['levels' => $levels, 'subjects' => $subjects];
    }

    /** Upcoming public events: website events plus public portal calendar events. */
    public static function upcomingEvents(int $limit = 6)
    {
        $items = collect();
        if (Schema::hasTable('website_posts')) {
            $items = $items->merge(DB::table('website_posts')->where('type', 'event')->where('published', true)
                ->whereDate('event_date', '>=', today())->orderBy('event_date')->limit($limit)->get()
                ->map(fn ($e) => (object) ['title' => $e->title, 'date' => $e->event_date, 'time' => $e->event_time, 'location' => $e->location,
                                           'url' => route('site.post', $e->slug), 'excerpt' => $e->excerpt]));
        }
        if (Schema::hasTable('calendar_events') && Schema::hasColumn('calendar_events', 'is_public')) {
            $items = $items->merge(DB::table('calendar_events')->where('is_public', 1)->where('is_active', 1)
                ->whereDate('start_date', '>=', today())->orderBy('start_date')->limit($limit)->get()
                ->map(fn ($e) => (object) ['title' => $e->title, 'date' => $e->start_date, 'time' => $e->all_day ? null : substr((string) $e->start_time, 0, 5),
                                           'location' => $e->location, 'url' => route('site.calendar'), 'excerpt' => Str::limit(strip_tags((string) $e->description), 140)]));
        }
        return $items->sortBy('date')->take($limit)->values();
    }

    /**
     * The main navigation, from the admin's "Main menu" setting: one item per line, "Label | target".
     * target = a page of this website (/news), a full https:// address, or a built-in dropdown:
     * @about, @admissions, @academics, @life (these list the pages the admin put under that menu).
     * @return array<int,array{label:string,url:string,children:array<int,array{0:string,1:string}>,active:bool}>
     */
    public static function mainMenu(): array
    {
        $default = "Home | /\nAbout | @about\nAdmissions | @admissions\nAcademics | @academics\nSchool life | @life\nMobile app | /app\nContact | /contact";
        $pages = self::menu();
        $group = fn (string $k) => array_map(fn ($p) => [$p['title'], route('site.page', $p['slug'])], $pages[$k] ?? []);
        $builtin = [
            'about' => fn () => ['url' => isset($pages['about'][0]) ? route('site.page', $pages['about'][0]['slug']) : route('site.people'),
                'children' => array_merge($group('about'), [['Our people', route('site.people')]])],
            'admissions' => fn () => ['url' => route('site.admissions'),
                'children' => array_merge([['Admissions overview', route('site.admissions')]], $group('admissions'), [['Apply online', route('site.admissions') . '#apply']])],
            'academics' => fn () => ['url' => route('site.academics'),
                'children' => array_merge([['Classes & subjects', route('site.academics')]], $group('academics'), [['School calendar', route('site.calendar')]])],
            'life' => fn () => ['url' => route('site.news'),
                'children' => array_merge([['News & updates', route('site.news')], ['Events', route('site.news', ['type' => 'event'])], ['Gallery', route('site.gallery')]], $group('life'))],
        ];
        $cur = '/' . ltrim(request()->path(), '/');
        $out = [];
        foreach (preg_split('/\R/', (string) self::get('main_menu', $default)) as $line) {
            [$label, $target] = array_pad(array_map('trim', explode('|', $line, 2)), 2, '');
            if ($label === '' || $target === '') continue;
            $children = [];
            if (str_starts_with($target, '@')) {
                $b = $builtin[substr($target, 1)] ?? null;
                if (!$b) continue;
                ['url' => $url, 'children' => $children] = $b();
            } elseif ($target === '/') {
                $url = self::homeUrl();
            } else {
                $url = str_starts_with($target, '/') ? self::menuPathUrl($target) : $target;
            }
            $path = '/' . ltrim((string) parse_url($url, PHP_URL_PATH), '/');
            $active = $target === '/' ? $cur === '/' || $cur === '/website-preview'
                : (!str_starts_with($target, '@') && str_starts_with($target, '/') && $path !== '/' && str_starts_with($cur, rtrim($path, '/')));
            $out[] = ['label' => $label, 'url' => $url, 'children' => $children, 'active' => $active];
        }
        return $out;
    }

    protected static function menuPathUrl(string $path): string
    {
        return self::websiteUrl($path);
    }

    /** Pages grouped by menu (about, admissions, academics, life). */
    public static function menu(): array
    {
        if (!Schema::hasTable('website_pages')) return [];
        return Cache::remember('website.menu', 300, fn () => WebsitePage::where('published', true)->whereNotNull('menu')->where('menu', '!=', 'none')
            ->orderBy('sort')->orderBy('title')->get(['title', 'slug', 'menu'])->groupBy('menu')->map(fn ($g) => $g->map(fn ($p) => ['title' => $p->title, 'slug' => $p->slug])->all())->all());
    }

    // ── uploads ─────────────────────────────────────────────────────────

    /**
     * Saves an uploaded image on the public disk, scaled down to $max px on
     * the long side (and a $thumb px thumbnail when asked).
     * @return array{path:string, thumb:?string, width:int, height:int}
     */
    public static function storeImage(UploadedFile $file, string $dir, int $max = 1920, ?int $thumb = null): array
    {
        $dir = 'website/' . trim($dir, '/');
        $name = date('Ymd') . '-' . Str::random(10);
        $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'jpg');

        $info = @getimagesize($file->getRealPath());
        if (!$info || !function_exists('imagecreatetruecolor') || in_array($ext, ['svg', 'gif'], true)) {
            $path = $file->storeAs($dir, $name . '.' . $ext, 'public');
            return ['path' => $path, 'thumb' => null, 'width' => (int) ($info[0] ?? 0), 'height' => (int) ($info[1] ?? 0)];
        }

        [$w, $h, $type] = $info;
        $src = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($file->getRealPath()),
            IMAGETYPE_PNG  => @imagecreatefrompng($file->getRealPath()),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($file->getRealPath()) : null,
            default        => null,
        };
        if (!$src) {
            $path = $file->storeAs($dir, $name . '.' . $ext, 'public');
            return ['path' => $path, 'thumb' => null, 'width' => $w, 'height' => $h];
        }
        if ($type === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
            $o = @exif_read_data($file->getRealPath())['Orientation'] ?? 1;
            if (in_array($o, [3, 6, 8], true)) {
                $src = imagerotate($src, [3 => 180, 6 => -90, 8 => 90][$o], 0);
                [$w, $h] = [imagesx($src), imagesy($src)];
            }
        }
        $keepAlpha = $type === IMAGETYPE_PNG;
        $save = function ($im, string $file) use ($keepAlpha) {
            Storage::disk('public')->makeDirectory(dirname($file));
            $full = Storage::disk('public')->path($file);
            $keepAlpha ? imagepng($im, $full, 6) : imagejpeg($im, $full, 82);
        };
        $resize = function (int $limit) use ($src, $w, $h, $keepAlpha) {
            $scale = min(1, $limit / max($w, $h));
            $nw = max(1, (int) round($w * $scale)); $nh = max(1, (int) round($h * $scale));
            $dst = imagecreatetruecolor($nw, $nh);
            if ($keepAlpha) { imagealphablending($dst, false); imagesavealpha($dst, true); }
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
            return [$dst, $nw, $nh];
        };

        $ext = $keepAlpha ? 'png' : 'jpg';
        [$big, $bw, $bh] = $resize($max);
        $path = "{$dir}/{$name}.{$ext}";
        $save($big, $path);
        imagedestroy($big);

        $thumbPath = null;
        if ($thumb) {
            [$small] = $resize($thumb);
            $thumbPath = "{$dir}/thumbs/{$name}.{$ext}";
            $save($small, $thumbPath);
            imagedestroy($small);
        }
        imagedestroy($src);

        return ['path' => $path, 'thumb' => $thumbPath, 'width' => $bw, 'height' => $bh];
    }

    public static function deleteFile(?string $path): void
    {
        if ($path && !Str::startsWith($path, ['http', '/'])) Storage::disk('public')->delete($path);
    }

    /** Text from the admin editor, limited to safe formatting (and YouTube embeds). */
    public static function clean(?string $html): ?string
    {
        if ($html === null || trim($html) === '') return null;
        if (!class_exists(\HTMLPurifier::class)) return strip_tags($html, '<p><br><b><strong><i><em><u><h2><h3><h4><ul><ol><li><a><img><blockquote><table><thead><tbody><tr><th><td><hr><span>');

        $config = \HTMLPurifier_Config::createDefault();
        $config->set('Cache.SerializerPath', storage_path('framework/cache'));
        $config->set('HTML.Allowed', 'p,br,b,strong,i,em,u,s,h2,h3,h4,ul,ol,li,a[href|target|title],img[src|alt|width|height],blockquote,table,thead,tbody,tr,th,td,hr,span[style],div[class],iframe[src|width|height]');
        $config->set('CSS.AllowedProperties', 'color,background-color,text-align,font-weight,font-style,text-decoration');
        $config->set('HTML.SafeIframe', true);
        $config->set('URI.SafeIframeRegexp', '%^(https?:)?//(www\.youtube(?:-nocookie)?\.com/embed/|player\.vimeo\.com/video/)%');
        $config->set('Attr.AllowedFrameTargets', ['_blank']);
        return (new \HTMLPurifier($config))->purify($html);
    }

    public static function uniqueSlug(string $table, string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title) ?: Str::random(6);
        $slug = $base;
        $i = 2;
        while (DB::table($table)->where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }
}
