<?php

namespace App\Http\Controllers\Website;

use App\Http\Controllers\Controller;
use App\Models\Website\Form;
use App\Models\Website\Page;
use App\Models\Website\Product;
use App\Models\Website\Work;
use App\Services\Website\Content;
use App\Services\Website\Site;
use App\Services\Website\Starter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** The public website: front page, pages, products, our work, contact and forms. */
class SiteController extends Controller
{
    public function __construct(protected Site $site) {}

    public function home()
    {
        if (!$this->site->enabled()) {
            return Auth::check() ? redirect()->route('dashboard') : redirect()->route('login');
        }
        $this->firstVisit();

        return view('site.page', ['page' => null, 'blocks' => $this->site->homeBlocks()]);
    }

    /** Any other address: a published page with that slug, or not found. */
    public function page(Request $request)
    {
        $slug = trim($request->path(), '/');
        abort_unless($this->site->enabled() && preg_match('/^[a-z0-9-]{1,120}$/', $slug) && $slug !== 'home', 404);
        $page = Page::where('slug', $slug)->first();
        abort_unless($page && ($page->isPublished() || $this->canPreview()), 404);

        return view('site.page', ['page' => $page, 'blocks' => (array) $page->blocks]);
    }

    public function products()
    {
        $this->firstVisit();

        return view('site.products', ['products' => Content::products()]);
    }

    public function product(Product $product)
    {
        abort_unless($product->isPublished() || $this->canPreview(), 404);

        return view('site.product', ['p' => $product, 'others' => Content::products()->where('id', '!=', $product->id)->take(3)]);
    }

    public function works()
    {
        return view('site.works', ['works' => Content::works()]);
    }

    public function work(Work $work)
    {
        abort_unless($work->isPublished() || $this->canPreview(), 404);

        return view('site.work', ['w' => $work, 'others' => Content::works()->where('id', '!=', $work->id)->take(2)]);
    }

    public function contact()
    {
        return view('site.contact', ['form' => Form::contact(), 'cfg' => $this->site->config()]);
    }

    public function form(Form $form)
    {
        return view('site.form', ['form' => $form]);
    }

    public function submit(Request $request, Form $form)
    {
        abort_unless($form->isOpen(), 404);
        // Honeypot: bots fill the hidden field; pretend it worked.
        if (filled($request->input('website'))) {
            return back()->with('form_sent', $form->slug);
        }

        $attributes = collect((array) $form->fields)->mapWithKeys(fn ($f) => ['f.' . $f['key'] => mb_strtolower($f['label'])])->all();
        $data = $request->validate($this->site->rules($form), ['f.*.regex' => 'Enter a phone number using digits, spaces, + and brackets.'], $attributes);

        $this->site->submit($form, $data['f'] ?? [], $request->ip(), $request->input('_page'));

        return back()->with('form_sent', $form->slug);
    }

    /** Fill an empty website with the starter content, once. */
    protected function firstVisit(): void
    {
        if (!Page::query()->exists() && !Product::query()->exists() && !Work::query()->exists()) {
            Starter::install($this->site->config());
        }
    }

    /** Website editors may open drafts to check them. */
    protected function canPreview(): bool
    {
        return (bool) Auth::user()?->can('Manage website');
    }
}
