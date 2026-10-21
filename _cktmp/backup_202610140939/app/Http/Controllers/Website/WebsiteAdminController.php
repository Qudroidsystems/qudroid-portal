<?php

namespace App\Http\Controllers\Website;

use App\Http\Controllers\Controller;
use App\Models\Website\WebsiteAlbum;
use App\Models\Website\WebsiteItem;
use App\Models\Website\WebsiteMessage;
use App\Models\Website\WebsitePage;
use App\Models\Website\WebsitePerson;
use App\Models\Website\WebsitePhoto;
use App\Models\Website\WebsitePost;
use App\Models\Website\WebsiteSlide;
use App\Services\Website\WebsiteContent as W;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Website admin (portal › Website): settings, home slider, pages, news &
 * events, gallery albums, people, testimonials / FAQs / values / partners,
 * messages from the site, and the mobile-app download.
 *
 * Simple content types share one generic list + form (see TYPES).
 */
class WebsiteAdminController extends Controller
{
    /**
     * type => [model, title, fields]; field = [name, label, kind, options]
     * kinds: text, textarea, html, image, select, date, check, number, icon
     */
    protected function types(): array
    {
        $pageMenus = ['about' => 'About', 'admissions' => 'Admissions', 'academics' => 'Academics', 'life' => 'School life', 'none' => 'Not in the menu'];
        $item = fn (string $kind, array $fields) => [WebsiteItem::class, WebsiteItem::KINDS[$kind][0], $fields, ['kind' => $kind], WebsiteItem::KINDS[$kind][1]];

        return [
            'slides' => [WebsiteSlide::class, 'Home slider', [
                ['image', 'Background photo (wide, at least 1600px)', 'image', ['max' => 2400]],
                ['title', 'Heading', 'text', ['required' => true]],
                ['subtitle', 'Text under the heading', 'textarea'],
                ['button_text', 'Button text', 'text'],
                ['button_link', 'Button link', 'text', ['hint' => 'e.g. /admissions or https://…']],
                ['sort', 'Order', 'number'],
                ['published', 'Show on the website', 'check'],
            ], [], 'The big rotating pictures at the top of the home page.'],
            'pages' => [WebsitePage::class, 'Pages', [
                ['title', 'Title', 'text', ['required' => true]],
                ['menu', 'Show under menu', 'select', ['options' => $pageMenus]],
                ['summary', 'Short summary', 'textarea', ['hint' => 'Shown under the title and in page lists.']],
                ['banner', 'Banner photo (optional)', 'image', ['max' => 2200]],
                ['body', 'Content', 'html'],
                ['sort', 'Order', 'number'],
                ['published', 'Published', 'check'],
            ], [], 'About us, policies, curriculum, uniform… anything with its own page.'],
            'posts' => [WebsitePost::class, 'News & events', [
                ['type', 'Type', 'select', ['options' => ['news' => 'News', 'event' => 'Event', 'story' => 'Student story']]],
                ['title', 'Title', 'text', ['required' => true]],
                ['category', 'Category tag', 'text', ['hint' => 'e.g. Sports, Academics, Alumni']],
                ['cover', 'Cover photo', 'image', ['max' => 1800]],
                ['excerpt', 'Short summary', 'textarea'],
                ['body', 'Full story', 'html'],
                ['event_date', 'Event date (events)', 'date'],
                ['event_time', 'Event time (events)', 'text', ['hint' => 'e.g. 10:00am – 2:00pm']],
                ['location', 'Venue (events)', 'text'],
                ['published_at', 'Publish date', 'date'],
                ['featured', 'Feature on the home page', 'check'],
                ['published', 'Published', 'check'],
            ], [], 'Announcements, achievements, upcoming events and student stories.'],
            'people' => [WebsitePerson::class, 'Our people', [
                ['name', 'Name', 'text', ['required' => true]],
                ['role', 'Role / title', 'text'],
                ['group', 'Group', 'select', ['options' => ['leadership' => 'School leadership', 'board' => 'Board & governance', 'staff' => 'Teachers & staff']]],
                ['photo', 'Photo', 'image', ['max' => 900]],
                ['bio', 'Short bio', 'textarea'],
                ['sort', 'Order', 'number'],
                ['published', 'Show on the website', 'check'],
            ], [], 'Leaders and staff shown on the "Our people" page.'],
            'testimonials' => $item('testimonial', [
                ['title', 'Name', 'text', ['required' => true]], ['subtitle', 'Who they are', 'text', ['hint' => 'e.g. Parent of a JSS 2 student']],
                ['body', 'What they said', 'textarea', ['required' => true]], ['image', 'Photo (optional)', 'image', ['max' => 500]],
                ['sort', 'Order', 'number'], ['published', 'Show', 'check'],
            ]),
            'faqs' => $item('faq', [
                ['title', 'Question', 'text', ['required' => true]], ['body', 'Answer', 'textarea', ['required' => true]],
                ['sort', 'Order', 'number'], ['published', 'Show', 'check'],
            ]),
            'values' => $item('value', [
                ['title', 'Value', 'text', ['required' => true]], ['body', 'Short description', 'textarea'],
                ['icon', 'Icon', 'icon'], ['sort', 'Order', 'number'], ['published', 'Show', 'check'],
            ]),
            'highlights' => $item('highlight', [
                ['title', 'Highlight', 'text', ['required' => true]], ['body', 'Short text', 'textarea'],
                ['icon', 'Icon', 'icon'], ['sort', 'Order', 'number'], ['published', 'Show', 'check'],
            ]),
            'partners' => $item('partner', [
                ['title', 'Name', 'text', ['required' => true]], ['image', 'Logo', 'image', ['max' => 600, 'required' => true]],
                ['link', 'Website (optional)', 'text'], ['sort', 'Order', 'number'], ['published', 'Show', 'check'],
            ]),
        ];
    }

    public function __construct()
    {
        $this->middleware('permission:Manage website');
    }

    // ── overview ────────────────────────────────────────────────────────

    public function index()
    {
        $count = fn ($t, $w = null) => Schema::hasTable($t) ? ($w ? $w(DB::table($t))->count() : DB::table($t)->count()) : 0;
        return view('website.admin.index', [
            'pagetitle' => 'Website',
            'counts' => [
                'slides' => $count('website_slides'), 'pages' => $count('website_pages'), 'posts' => $count('website_posts'),
                'albums' => $count('website_albums'), 'photos' => $count('website_photos'), 'people' => $count('website_people'),
                'testimonials' => $count('website_items', fn ($q) => $q->where('kind', 'testimonial')),
                'faqs' => $count('website_items', fn ($q) => $q->where('kind', 'faq')),
                'values' => $count('website_items', fn ($q) => $q->where('kind', 'value')),
                'highlights' => $count('website_items', fn ($q) => $q->where('kind', 'highlight')),
                'partners' => $count('website_items', fn ($q) => $q->where('kind', 'partner')),
                'unread' => $count('website_messages', fn ($q) => $q->whereNull('read_at')->where('type', '!=', 'newsletter')),
                'admissions' => $count('website_messages', fn ($q) => $q->where('type', 'admission')),
                'subscribers' => $count('website_messages', fn ($q) => $q->where('type', 'newsletter')),
            ],
            'recent' => WebsiteMessage::where('type', '!=', 'newsletter')->latest()->limit(6)->get(),
            'enabled' => W::enabled(),
        ]);
    }

    // ── settings ────────────────────────────────────────────────────────

    public function settings()
    {
        return view('website.admin.settings', ['pagetitle' => 'Website settings', 's' => W::settings(), 'school' => W::school()]);
    }

    public function saveSettings(Request $request)
    {
        $text = ['hero_title', 'hero_subtitle', 'hero_button_text', 'hero_button_link', 'intro_title', 'values_title', 'footer_about',
                 'social_facebook', 'social_instagram', 'social_x', 'social_youtube', 'social_tiktok', 'whatsapp', 'office_hours',
                 'meta_description', 'founded_year', 'primary_color', 'secondary_color', 'accent_color'];
        $rules = array_fill_keys($text, 'nullable|string|max:500');
        $rules = array_merge($rules, [
            'primary_color' => 'nullable|regex:/^#[0-9a-fA-F]{6}$/', 'secondary_color' => 'nullable|regex:/^#[0-9a-fA-F]{6}$/',
            'accent_color' => 'nullable|regex:/^#[0-9a-fA-F]{6}$/', 'founded_year' => 'nullable|integer|min:1800|max:' . date('Y'),
            'intro_body' => 'nullable|string|max:20000', 'admission_text' => 'nullable|string|max:20000', 'map_embed' => 'nullable|string|max:3000',
            'hero_image' => 'nullable|image|max:8192', 'intro_image' => 'nullable|image|max:8192',
        ]);
        $data = $request->validate($rules);

        $values = [];
        foreach ($text as $k) $values[$k] = trim((string) ($data[$k] ?? ''));
        foreach (['social_facebook', 'social_instagram', 'social_x', 'social_youtube', 'social_tiktok'] as $k) {
            if ($values[$k] !== '' && !Str::startsWith($values[$k], ['http://', 'https://'])) $values[$k] = 'https://' . $values[$k];
        }
        $values['intro_body'] = W::clean($data['intro_body'] ?? '') ?? '';
        $values['admission_text'] = W::clean($data['admission_text'] ?? '') ?? '';
        $map = trim((string) ($data['map_embed'] ?? ''));
        $values['map_embed'] = preg_match('#<iframe[^>]+src="https://(www\.)?google\.com/maps/embed[^"]*"[^>]*></iframe>#i', $map, $m) ? $m[0] : '';
        foreach (['enabled', 'admission_open', 'show_stats'] as $k) $values[$k] = $request->boolean($k) ? '1' : '0';

        foreach (['hero_image' => 2400, 'intro_image' => 1600] as $k => $max) {
            if ($request->hasFile($k)) {
                W::deleteFile(W::get($k));
                $values[$k] = W::storeImage($request->file($k), 'settings', $max)['path'];
            } elseif ($request->boolean('remove_' . $k)) {
                W::deleteFile(W::get($k));
                $values[$k] = '';
            }
        }

        W::put($values);
        return back()->with('success', 'Website settings saved.');
    }

    // ── generic content (slides, pages, posts, people, items) ───────────

    protected function type(string $type): array
    {
        $t = $this->types()[$type] ?? abort(404);
        return ['model' => $t[0], 'title' => $t[1], 'fields' => $t[2], 'scope' => $t[3] ?? [], 'about' => $t[4] ?? '', 'key' => $type];
    }

    public function list(Request $request, string $type)
    {
        $t = $this->type($type);
        $q = $t['model']::query()->where($t['scope']);
        if ($request->filled('q')) {
            $first = $t['fields'][0][2] === 'image' ? $t['fields'][1][0] : $t['fields'][0][0];
            $q->where($first, 'like', '%' . $request->get('q') . '%');
        }
        if ($type === 'posts' && $request->filled('kind')) $q->where('type', $request->get('kind'));
        $hasSort = collect($t['fields'])->contains(fn ($f) => $f[0] === 'sort');
        $rows = ($hasSort ? $q->orderBy('sort') : $q->orderByDesc('published_at'))->orderByDesc('id')->paginate(25)->withQueryString();
        return view('website.admin.list', ['pagetitle' => $t['title'], 't' => $t, 'rows' => $rows]);
    }

    public function create(string $type)
    {
        $t = $this->type($type);
        $row = new $t['model']();
        $row->published = true;
        if ($type === 'posts') { $row->type = request('kind', 'news'); $row->published_at = now(); }
        return view('website.admin.form', ['pagetitle' => 'New — ' . $t['title'], 't' => $t, 'row' => $row]);
    }

    public function edit(string $type, int $id)
    {
        $t = $this->type($type);
        $row = $t['model']::where($t['scope'])->findOrFail($id);
        return view('website.admin.form', ['pagetitle' => 'Edit — ' . $t['title'], 't' => $t, 'row' => $row]);
    }

    public function store(Request $request, string $type)
    {
        $t = $this->type($type);
        $row = new $t['model']($t['scope']);
        $this->fill($request, $t, $row);
        return redirect()->route('website.admin.list', $type)->with('success', 'Saved.');
    }

    public function update(Request $request, string $type, int $id)
    {
        $t = $this->type($type);
        $row = $t['model']::where($t['scope'])->findOrFail($id);
        $this->fill($request, $t, $row);
        return redirect()->route('website.admin.list', $type)->with('success', 'Saved.');
    }

    public function destroy(string $type, int $id)
    {
        $t = $this->type($type);
        $row = $t['model']::where($t['scope'])->findOrFail($id);
        foreach ($t['fields'] as [$name, , $kind]) if ($kind === 'image') W::deleteFile($row->{$name});
        $row->delete();
        W::flush();
        Cache::forget('website.menu');
        return back()->with('success', 'Deleted.');
    }

    protected function fill(Request $request, array $t, $row): void
    {
        $rules = [];
        foreach ($t['fields'] as $f) {
            [$name, $label, $kind] = $f;
            $opt = $f[3] ?? [];
            $req = !empty($opt['required']) ? 'required' : 'nullable';
            $rules[$name] = match ($kind) {
                'image'  => ($req === 'required' && !$row->{$name} ? 'required' : 'nullable') . '|image|max:8192',
                'html'   => 'nullable|string|max:200000',
                'date'   => 'nullable|date',
                'number' => 'nullable|integer|min:0|max:65000',
                'check'  => 'nullable',
                'select' => $req . '|in:' . implode(',', array_keys($opt['options'] ?? [])),
                'textarea' => $req . '|string|max:5000',
                default  => $req . '|string|max:255',
            };
        }
        $data = $request->validate($rules);

        foreach ($t['fields'] as $f) {
            [$name, , $kind] = $f;
            $opt = $f[3] ?? [];
            if ($kind === 'image') {
                if ($request->hasFile($name)) {
                    W::deleteFile($row->{$name});
                    $row->{$name} = W::storeImage($request->file($name), $t['key'], $opt['max'] ?? 1600)['path'];
                } elseif ($request->boolean('remove_' . $name)) {
                    W::deleteFile($row->{$name});
                    $row->{$name} = null;
                }
            } elseif ($kind === 'check') {
                $row->{$name} = $request->boolean($name);
            } elseif ($kind === 'html') {
                $row->{$name} = W::clean($data[$name] ?? null);
            } elseif ($kind === 'number') {
                $row->{$name} = (int) ($data[$name] ?? 0);
            } else {
                $row->{$name} = ($data[$name] ?? null) === '' ? null : ($data[$name] ?? null);
            }
        }

        $table = $row->getTable();
        if (Schema::hasColumn($table, 'slug')) {
            $source = $row->title ?? $row->name ?? Str::random(6);
            if (!$row->slug || $row->isDirty('title')) $row->slug = W::uniqueSlug($table, $source, $row->id);
        }
        if ($row instanceof WebsitePost) {
            $row->published_at = $row->published_at ?: now();
            $row->created_by = $row->created_by ?: auth()->id();
        }
        $row->save();
        W::flush();
        Cache::forget('website.menu');
    }

    // ── people from staff ───────────────────────────────────────────────

    public function importStaff(Request $request)
    {
        $data = $request->validate(['user_ids' => 'required|array', 'user_ids.*' => 'integer', 'group' => 'required|in:leadership,staff,board']);
        $added = 0;
        foreach ($data['user_ids'] as $uid) {
            if (WebsitePerson::where('user_id', $uid)->exists()) continue;
            $u = DB::table('users')->where('id', $uid)->first(['id', 'name']);
            if (!$u) continue;
            $bio = Schema::hasTable('staffbioinfo') ? DB::table('staffbioinfo')->where('userid', $uid)->first(['title', 'job_title', 'position']) : null;
            $pic = Schema::hasTable('staffpicture') ? DB::table('staffpicture')->where('staffId', $uid)->orderByDesc('id')->value('picture') : null;
            WebsitePerson::create([
                'user_id' => $uid, 'name' => trim(($bio->title ?? '') . ' ' . $u->name), 'role' => $bio->job_title ?? $bio->position ?? null,
                'group' => $data['group'], 'photo' => $pic ? 'staff_avatars/' . basename($pic) : null, 'published' => true,
                'sort' => (int) WebsitePerson::max('sort') + 1,
            ]);
            $added++;
        }
        return back()->with('success', "{$added} staff member(s) added. Edit them to add a short bio.");
    }

    public function staffOptions()
    {
        $taken = WebsitePerson::whereNotNull('user_id')->pluck('user_id');
        $rows = Schema::hasTable('staffbioinfo')
            ? DB::table('staffbioinfo as b')->join('users as u', 'u.id', '=', 'b.userid')->whereNull('b.deleted_at')
                ->whereNotIn('u.id', $taken)->orderBy('u.name')->get(['u.id', 'u.name', 'b.job_title', 'b.position'])
            : collect();
        return response()->json($rows);
    }

    // ── gallery ─────────────────────────────────────────────────────────

    public function albums()
    {
        return view('website.admin.albums', [
            'pagetitle' => 'Gallery',
            'albums' => WebsiteAlbum::withCount('photos')->with('photos')->orderBy('sort')->orderByDesc('taken_on')->orderByDesc('id')->get(),
        ]);
    }

    public function saveAlbum(Request $request, ?int $id = null)
    {
        $data = $request->validate([
            'title' => 'required|string|max:150', 'description' => 'nullable|string|max:500',
            'taken_on' => 'nullable|date', 'sort' => 'nullable|integer|min:0', 'published' => 'nullable',
        ]);
        $album = $id ? WebsiteAlbum::findOrFail($id) : new WebsiteAlbum();
        $album->fill([
            'title' => $data['title'], 'description' => $data['description'] ?? null, 'taken_on' => $data['taken_on'] ?? null,
            'sort' => (int) ($data['sort'] ?? 0), 'published' => $request->boolean('published', !$id),
        ]);
        if (!$album->slug || $album->isDirty('title')) $album->slug = W::uniqueSlug('website_albums', $data['title'], $album->id);
        $album->save();
        return redirect()->route('website.admin.album', $album->id)->with('success', $id ? 'Album saved.' : 'Album created — now add photos.');
    }

    public function album(int $id)
    {
        return view('website.admin.album', ['pagetitle' => 'Gallery album', 'album' => WebsiteAlbum::with('photos')->findOrFail($id)]);
    }

    public function uploadPhotos(Request $request, int $id)
    {
        $album = WebsiteAlbum::findOrFail($id);
        $request->validate(['photos' => 'required|array|max:40', 'photos.*' => 'image|max:12288']);
        $sort = (int) $album->photos()->max('sort');
        $added = 0;
        foreach ($request->file('photos') as $file) {
            $img = W::storeImage($file, 'gallery/' . $album->id, 2000, 640);
            WebsitePhoto::create(['album_id' => $album->id, 'path' => $img['path'], 'thumb' => $img['thumb'],
                                  'width' => $img['width'], 'height' => $img['height'], 'sort' => ++$sort]);
            $added++;
        }
        if (!$album->cover_photo_id) $album->update(['cover_photo_id' => $album->photos()->value('id')]);
        if ($request->expectsJson()) return response()->json(['ok' => true, 'added' => $added]);
        return back()->with('success', "{$added} photo(s) uploaded.");
    }

    public function updatePhotos(Request $request, int $id)
    {
        $album = WebsiteAlbum::findOrFail($id);
        $data = $request->validate(['captions' => 'nullable|array', 'captions.*' => 'nullable|string|max:255',
                                    'order' => 'nullable|array', 'cover' => 'nullable|integer', 'delete' => 'nullable|array']);
        foreach ((array) ($data['captions'] ?? []) as $pid => $cap) {
            WebsitePhoto::where('album_id', $album->id)->where('id', $pid)->update(['caption' => $cap]);
        }
        foreach ((array) ($data['order'] ?? []) as $pid => $n) {
            WebsitePhoto::where('album_id', $album->id)->where('id', $pid)->update(['sort' => (int) $n]);
        }
        foreach ((array) ($data['delete'] ?? []) as $pid) {
            $p = WebsitePhoto::where('album_id', $album->id)->find($pid);
            if ($p) { W::deleteFile($p->path); W::deleteFile($p->thumb); $p->delete(); }
        }
        $cover = $data['cover'] ?? $album->cover_photo_id;
        if (!WebsitePhoto::where('album_id', $album->id)->where('id', $cover)->exists()) $cover = $album->photos()->value('id');
        $album->update(['cover_photo_id' => $cover]);
        return back()->with('success', 'Photos updated.');
    }

    public function deleteAlbum(int $id)
    {
        $album = WebsiteAlbum::with('photos')->findOrFail($id);
        foreach ($album->photos as $p) { W::deleteFile($p->path); W::deleteFile($p->thumb); $p->delete(); }
        $album->delete();
        return redirect()->route('website.admin.albums')->with('success', 'Album deleted.');
    }

    // ── messages ────────────────────────────────────────────────────────

    public function messages(Request $request)
    {
        $type = $request->get('type');
        return view('website.admin.messages', [
            'pagetitle' => 'Website messages',
            'type' => $type,
            'rows' => WebsiteMessage::when($type, fn ($q) => $q->where('type', $type), fn ($q) => $q->where('type', '!=', 'newsletter'))
                ->latest()->paginate(30)->withQueryString(),
            'counts' => WebsiteMessage::selectRaw('type, COUNT(*) as n, SUM(read_at IS NULL) as unread')->groupBy('type')->get()->keyBy('type'),
        ]);
    }

    public function readMessage(int $id)
    {
        $m = WebsiteMessage::findOrFail($id);
        if (!$m->read_at) $m->update(['read_at' => now()]);
        return response()->json(['ok' => true]);
    }

    public function deleteMessage(int $id)
    {
        WebsiteMessage::findOrFail($id)->delete();
        return back()->with('success', 'Message deleted.');
    }

    public function exportMessages(Request $request)
    {
        $type = $request->get('type', 'admission');
        $rows = WebsiteMessage::where('type', $type)->latest()->get();
        $name = 'website-' . $type . '-' . date('Ymd') . '.csv';
        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Date', 'Type', 'Name', 'Phone', 'E-mail', 'Subject', 'Message', 'Details']);
            foreach ($rows as $r) {
                fputcsv($out, [$r->created_at?->format('Y-m-d H:i'), $r->type, $r->name, $r->phone, $r->email, $r->subject, $r->message,
                               collect($r->data ?? [])->map(fn ($v, $k) => "{$k}: {$v}")->implode('; ')]);
            }
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv']);
    }

    // ── mobile app download ─────────────────────────────────────────────

    public function app()
    {
        $s = W::settings();
        $apk = $s['app_apk'] ?? null;
        return view('website.admin.app', [
            'pagetitle' => 'Mobile app download', 's' => $s,
            'apk' => $apk && Storage::disk('public')->exists($apk) ? ['size' => round(Storage::disk('public')->size($apk) / 1048576, 1), 'at' => date('d M Y, H:i', Storage::disk('public')->lastModified($apk))] : null,
            'maxUpload' => ini_get('upload_max_filesize') . ' / post ' . ini_get('post_max_size'),
        ]);
    }

    public function saveApp(Request $request)
    {
        $data = $request->validate([
            'app_name' => 'nullable|string|max:120', 'app_intro' => 'nullable|string|max:400', 'app_version' => 'nullable|string|max:30',
            'app_play_url' => 'nullable|url|max:300', 'app_ios_url' => 'nullable|url|max:300',
            'apk' => 'nullable|file|max:204800', 'remove_apk' => 'nullable',
        ]);
        $values = collect($data)->only(['app_name', 'app_intro', 'app_version', 'app_play_url', 'app_ios_url'])->map(fn ($v) => (string) $v)->all();

        if ($request->hasFile('apk')) {
            $file = $request->file('apk');
            if (strtolower($file->getClientOriginalExtension()) !== 'apk') {
                return back()->withErrors(['apk' => 'Please choose an Android .apk file.']);
            }
            W::deleteFile(W::get('app_apk'));
            $values['app_apk'] = $file->storeAs('website/app', 'app-' . date('YmdHis') . '.apk', 'public');
        } elseif ($request->boolean('remove_apk')) {
            W::deleteFile(W::get('app_apk'));
            $values['app_apk'] = '';
        }
        W::put($values);
        return back()->with('success', 'Mobile app download saved.');
    }
}
