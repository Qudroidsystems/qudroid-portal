<?php

namespace App\Http\Controllers\Website\Admin;

use App\Http\Controllers\Controller;
use App\Models\Website\Form;
use App\Models\Website\FormEntry;
use App\Models\Website\Media;
use App\Models\Website\Page;
use App\Models\Website\Product;
use App\Models\Website\Work;
use App\Services\Website\Content;
use App\Services\Website\Site;
use App\Services\Website\Starter;
use Illuminate\Http\Request;

/** Website → overview, settings, and installing the starter content. */
class WebsiteController extends Controller
{
    public function __construct(protected Site $site) {}

    public function index()
    {
        return view('modules.website.index', [
            'cfg' => $this->site->config(),
            'counts' => [
                'pages' => Page::count(), 'products' => Product::count(), 'works' => Work::count(),
                'forms' => Form::count(), 'media' => Media::count(), 'new' => FormEntry::where('status', 'new')->count(),
            ],
            'entries' => FormEntry::with('form')->latest('id')->limit(8)->get(),
            'pages' => Page::orderByRaw("slug = 'home' desc")->ordered()->limit(8)->get(),
            'courses' => Content::courses(),
        ]);
    }

    public function settings()
    {
        return view('modules.website.settings', ['cfg' => $this->site->config()]);
    }

    public function saveSettings(Request $request)
    {
        $socials = collect(Site::SOCIALS)->keys()->mapWithKeys(fn ($k) => ["socials.$k" => ['nullable', 'url:http,https', 'max:300']])->all();
        $data = $request->validate([
            'enabled' => ['nullable', 'boolean'],
            'name' => ['required', 'string', 'max:80'],
            'tagline' => ['nullable', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:300'],
            'announcement' => ['nullable', 'string', 'max:160'],
            'announcement_url' => ['nullable', 'string', 'max:300'],
            'email' => ['nullable', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:300'],
            'footer_note' => ['nullable', 'string', 'max:500'],
            'nav' => ['nullable', 'array'],
        ] + $socials);

        $values = [
            'enabled' => $request->boolean('enabled'),
            'nav' => collect(['products', 'work', 'academy', 'contact'])->mapWithKeys(fn ($k) => [$k => (bool) ($data['nav'][$k] ?? false)])->all(),
            'socials' => collect(Site::SOCIALS)->keys()->mapWithKeys(fn ($k) => [$k => (string) ($data['socials'][$k] ?? '')])->all(),
            'announcement_url' => \App\Services\Website\Blocks::link($data['announcement_url'] ?? ''),
        ];
        foreach (['name', 'tagline', 'description', 'announcement', 'email', 'phone', 'address', 'footer_note'] as $k) {
            $values[$k] = trim((string) ($data[$k] ?? ''));
        }
        $this->site->save($values, $request->user());

        return back()->with('success', 'Website settings saved.');
    }

    /** Add the starter home and about pages, products, sample works and contact form (only what is missing). */
    public function starter(Request $request)
    {
        $made = Starter::install($this->site->config(), $request->user());
        $total = array_sum($made);

        return back()->with('success', $total
            ? "Added {$made['pages']} pages, {$made['products']} products and {$made['works']} sample works (as drafts)."
            : 'The starter content is already there.');
    }
}
