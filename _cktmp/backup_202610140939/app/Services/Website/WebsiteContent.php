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

    // ── school (from the portal) ────────────────────────────────────────

    public static function school(): object
    {
        static $school;
        if ($school) return $school;
        $row = Schema::hasTable('school_information') ? DB::table('school_information')->orderByDesc('is_active')->first() : null;
        $logo = $row->school_logo ?? null;
        $phones = collect(preg_split('/[,;\/]+/', (string) ($row->school_phones ?? '')))->map(fn ($p) => trim($p))->filter()->values()->all();
        return $school = (object) [
            'name'    => $row->school_name ?? config('app.name'),
            'motto'   => $row->school_motto ?? null,
            'address' => $row->school_address ?? null,
            'email'   => $row->school_email ?? null,
            'phones'  => $phones,
            'website' => $row->school_website ?? null,
            'logo'    => $logo ? self::fileUrl($logo) : null,
            'next_term_begins' => $row->date_next_term_begins ?? null,
        ];
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
