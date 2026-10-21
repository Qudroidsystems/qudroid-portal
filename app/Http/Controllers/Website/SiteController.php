<?php

namespace App\Http\Controllers\Website;

use App\Http\Controllers\Controller;
use App\Models\Website\WebsiteAlbum;
use App\Models\Website\WebsiteItem;
use App\Models\Website\WebsiteMessage;
use App\Models\Website\WebsitePage;
use App\Models\Website\WebsitePerson;
use App\Models\Website\WebsitePost;
use App\Models\Website\WebsiteSlide;
use App\Services\Website\WebsiteContent as W;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * The school's public website. Everything shown is read from the database:
 * content the admin manages under "Website" in the portal, plus live school
 * data (name, logo, contacts, numbers, classes, subjects, public events).
 */
class SiteController extends Controller
{
    public function home()
    {
        // "/" is the portal login unless this request came in on the website's own
        // domain; /website previews the site before the domain is set up.
        if (!W::enabled()) return redirect('/login');
        if (!request()->routeIs('site.preview') && !W::isWebsiteHost()) return redirect('/login');

        return view('website.home', [
            'slides'       => WebsiteSlide::where('published', true)->orderBy('sort')->orderBy('id')->get(),
            'values'       => WebsiteItem::where('kind', 'value')->where('published', true)->orderBy('sort')->get(),
            'highlights'   => WebsiteItem::where('kind', 'highlight')->where('published', true)->orderBy('sort')->get(),
            'testimonials' => WebsiteItem::where('kind', 'testimonial')->where('published', true)->orderBy('sort')->get(),
            'faqs'         => WebsiteItem::where('kind', 'faq')->where('published', true)->orderBy('sort')->limit(6)->get(),
            'partners'     => WebsiteItem::where('kind', 'partner')->where('published', true)->orderBy('sort')->get(),
            'news'         => WebsitePost::live()->whereIn('type', ['news', 'story'])->orderByDesc('featured')->orderByDesc('published_at')->limit(6)->get(),
            'events'       => W::upcomingEvents(4),
            'albums'       => WebsiteAlbum::where('published', true)->with('photos')->orderBy('sort')->orderByDesc('taken_on')->limit(8)->get(),
            'stats'        => W::get('show_stats', '1') === '1' ? W::stats() : [],
        ]);
    }

    public function page(string $slug)
    {
        $page = WebsitePage::where('slug', $slug)->where('published', true)->firstOrFail();
        $siblings = $page->menu ? WebsitePage::where('menu', $page->menu)->where('published', true)->orderBy('sort')->get(['title', 'slug']) : collect();
        return view('website.page', compact('page', 'siblings'));
    }

    public function news(Request $request)
    {
        $type = in_array($request->get('type'), ['news', 'event', 'story'], true) ? $request->get('type') : null;
        $q = WebsitePost::live()
            ->when($type, fn ($x) => $x->where('type', $type))
            ->when($request->filled('category'), fn ($x) => $x->where('category', $request->get('category')))
            ->when($request->filled('q'), fn ($x) => $x->where(fn ($w) => $w->where('title', 'like', '%' . $request->get('q') . '%')->orWhere('excerpt', 'like', '%' . $request->get('q') . '%')));
        return view('website.news', [
            'posts' => $q->orderByDesc('published_at')->orderByDesc('id')->paginate(9)->withQueryString(),
            'categories' => WebsitePost::live()->whereNotNull('category')->distinct()->orderBy('category')->pluck('category'),
            'type' => $type,
        ]);
    }

    public function post(string $slug)
    {
        $post = WebsitePost::live()->where('slug', $slug)->firstOrFail();
        $more = WebsitePost::live()->where('id', '!=', $post->id)->where('type', $post->type)->orderByDesc('published_at')->limit(3)->get();
        return view('website.post', compact('post', 'more'));
    }

    public function gallery()
    {
        return view('website.gallery', [
            'albums' => WebsiteAlbum::where('published', true)->whereHas('photos')->withCount('photos')->with('photos')
                ->orderBy('sort')->orderByDesc('taken_on')->paginate(12),
        ]);
    }

    public function album(string $slug)
    {
        $album = WebsiteAlbum::where('slug', $slug)->where('published', true)->with('photos')->firstOrFail();
        return view('website.album', compact('album'));
    }

    public function people()
    {
        $people = WebsitePerson::where('published', true)->orderBy('sort')->orderBy('name')->get()->groupBy('group');
        return view('website.people', compact('people'));
    }

    public function academics()
    {
        return view('website.academics', [
            'academics' => W::academics(),
            'pages' => WebsitePage::where('menu', 'academics')->where('published', true)->orderBy('sort')->get(),
        ]);
    }

    public function admissions()
    {
        return view('website.admissions', [
            'pages' => WebsitePage::where('menu', 'admissions')->where('published', true)->orderBy('sort')->get(),
            'faqs' => WebsiteItem::where('kind', 'faq')->where('published', true)->orderBy('sort')->get(),
            'classes' => W::academics()['levels'],
        ]);
    }

    public function apply(Request $request)
    {
        $data = $request->validate([
            'child_name'    => 'required|string|max:150',
            'date_of_birth' => 'nullable|date|before:today',
            'gender'        => 'nullable|in:Male,Female',
            'class_sought'  => 'required|string|max:60',
            'current_school'=> 'nullable|string|max:150',
            'name'          => 'required|string|max:150',
            'phone'         => 'required|string|max:40',
            'email'         => 'nullable|email|max:150',
            'message'       => 'nullable|string|max:2000',
            'website'       => 'nullable|max:0', // honeypot
        ]);
        WebsiteMessage::create([
            'type' => 'admission', 'name' => $data['name'], 'email' => $data['email'] ?? null, 'phone' => $data['phone'],
            'subject' => 'Admission — ' . $data['child_name'] . ' (' . $data['class_sought'] . ')',
            'message' => $data['message'] ?? null, 'ip' => $request->ip(),
            'data' => array_intersect_key($data, array_flip(['child_name', 'date_of_birth', 'gender', 'class_sought', 'current_school'])),
        ]);
        $this->notifyAdmins('New admission application', $data['child_name'] . ' for ' . $data['class_sought'] . ' — ' . $data['name'] . ', ' . $data['phone']);
        return back()->with('site_success', 'Thank you! Your application has been received. The admissions office will contact you shortly.');
    }

    public function contact()
    {
        return view('website.contact');
    }

    public function sendContact(Request $request)
    {
        $data = $request->validate([
            'type'    => 'nullable|in:contact,visit',
            'name'    => 'required|string|max:150',
            'email'   => 'nullable|email|max:150',
            'phone'   => 'nullable|string|max:40',
            'subject' => 'nullable|string|max:200',
            'message' => 'required|string|max:3000',
            'visit_date' => 'nullable|date|after_or_equal:today',
            'website' => 'nullable|max:0',
        ]);
        WebsiteMessage::create([
            'type' => $data['type'] ?? 'contact', 'name' => $data['name'], 'email' => $data['email'] ?? null, 'phone' => $data['phone'] ?? null,
            'subject' => $data['subject'] ?? (($data['type'] ?? '') === 'visit' ? 'School visit request' : 'Website enquiry'),
            'message' => $data['message'], 'ip' => $request->ip(),
            'data' => !empty($data['visit_date']) ? ['visit_date' => $data['visit_date']] : null,
        ]);
        $this->notifyAdmins(($data['type'] ?? '') === 'visit' ? 'School visit request' : 'New website message', $data['name'] . ': ' . mb_substr($data['message'], 0, 120));
        return back()->with('site_success', 'Thank you for reaching out. We will get back to you soon.');
    }

    public function newsletter(Request $request)
    {
        $data = $request->validate(['email' => 'required|email|max:150', 'website' => 'nullable|max:0']);
        if (!WebsiteMessage::where('type', 'newsletter')->where('email', $data['email'])->exists()) {
            WebsiteMessage::create(['type' => 'newsletter', 'email' => $data['email'], 'subject' => 'Newsletter sign-up', 'ip' => $request->ip()]);
        }
        return back()->with('site_success', 'You are subscribed. Thank you!');
    }

    public function calendar(Request $request)
    {
        $month = $request->filled('month') ? \Carbon\Carbon::parse($request->get('month') . '-01') : now()->startOfMonth();
        $events = collect();
        if (Schema::hasTable('calendar_events') && Schema::hasColumn('calendar_events', 'is_public')) {
            $events = DB::table('calendar_events')->where('is_public', 1)->where('is_active', 1)
                ->where('start_date', '<=', $month->copy()->endOfMonth())
                ->where(fn ($q) => $q->where('end_date', '>=', $month)->orWhere(fn ($x) => $x->whereNull('end_date')->where('start_date', '>=', $month)))
                ->orderBy('start_date')->get();
        }
        $posts = WebsitePost::live()->where('type', 'event')->whereBetween('event_date', [$month, $month->copy()->endOfMonth()])->orderBy('event_date')->get();
        return view('website.calendar', compact('month', 'events', 'posts'));
    }

    public function app()
    {
        return view('website.app');
    }

    /** Download the Android app (APK uploaded by the admin). */
    public function apk()
    {
        $path = W::get('app_apk');
        abort_unless($path && Storage::disk('public')->exists($path), 404);
        $name = preg_replace('/[^A-Za-z0-9_-]+/', '-', W::get('app_name', 'school-app')) . '-' . W::get('app_version', '1') . '.apk';
        return Storage::disk('public')->download($path, $name, ['Content-Type' => 'application/vnd.android.package-archive']);
    }

    /** Website images when public/storage is not linked (see WebsiteContent::fileUrl). */
    public function media(string $path)
    {
        abort_unless(str_starts_with($path, 'website/') && !str_contains($path, '..') && Storage::disk('public')->exists($path), 404);
        return Storage::disk('public')->response($path, null, ['Cache-Control' => 'public, max-age=2592000']);
    }

    protected function notifyAdmins(string $title, string $body): void
    {
        try {
            if (!class_exists(\App\Services\Messaging\PortalNotifier::class)) return;
            $ids = DB::table('model_has_permissions as mp')->join('permissions as p', 'p.id', '=', 'mp.permission_id')
                ->where('p.name', 'Manage website')->pluck('mp.model_id')
                ->merge(DB::table('model_has_roles as mr')->join('role_has_permissions as rp', 'rp.role_id', '=', 'mr.role_id')
                    ->join('permissions as p', 'p.id', '=', 'rp.permission_id')->where('p.name', 'Manage website')->pluck('mr.model_id'))
                ->unique()->values()->all();
            if ($ids) \App\Services\Messaging\PortalNotifier::toUsers($ids, $title, $body, route('website.admin.messages'), 'system', 'web-msg:' . now()->format('YmdHis') . ':' . substr(md5($body), 0, 6));
        } catch (\Throwable $e) {
        }
    }
}
