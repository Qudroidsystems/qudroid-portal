<?php

namespace App\Http\Controllers\Website\Admin;

use App\Http\Controllers\Controller;
use App\Models\Website\Product;
use App\Services\Website\Blocks;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Website → Products: what we make or sell. */
class ProductController extends Controller
{
    use StoresSiteImages;

    public function index()
    {
        return view('modules.website.products.index', ['products' => Product::ordered()->get()]);
    }

    public function create()
    {
        return view('modules.website.products.form', ['p' => new Product(['status' => 'draft', 'color' => '#0071e3', 'icon' => 'ri-apps-2-line', 'features' => []])]);
    }

    public function store(Request $request)
    {
        $p = Product::create($this->validated($request));

        return redirect()->route('website.products.edit', $p)->with('success', 'Product added.');
    }

    public function edit(Product $product)
    {
        return view('modules.website.products.form', ['p' => $product]);
    }

    public function update(Request $request, Product $product)
    {
        $product->update($this->validated($request, $product));

        return back()->with('success', 'Product saved.');
    }

    public function destroy(Product $product)
    {
        $product->delete();

        return redirect()->route('website.products.index')->with('success', 'Product removed.');
    }

    protected function validated(Request $request, ?Product $p = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:80'],
            'tagline' => ['nullable', 'string', 'max:200'],
            'category' => ['nullable', 'string', 'max:60'],
            'summary' => ['nullable', 'string', 'max:500'],
            'body' => ['nullable', 'string', 'max:20000'],
            'icon' => ['nullable', 'string', 'max:60', 'regex:/^ri-[a-z0-9-]+$/'],
            'color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'link_url' => ['nullable', 'string', 'max:500'],
            'link_label' => ['nullable', 'string', 'max:40'],
            'price_label' => ['nullable', 'string', 'max:80'],
            'featured' => ['nullable', 'boolean'],
            'status' => ['required', Rule::in(['draft', 'published'])],
            'position' => ['nullable', 'integer', 'min:0', 'max:999'],
            'features' => ['nullable', 'array', 'max:24'],
            'features.*.icon' => ['nullable', 'string', 'max:60'],
            'features.*.title' => ['nullable', 'string', 'max:120'],
            'features.*.text' => ['nullable', 'string', 'max:500'],
        ] + $this->imageRules(), [], ['icon' => 'icon name']);

        $data['slug'] = $this->uniqueSlug(Product::class, $this->slugFrom($data['slug'] ?? null, $data['name']), $p?->id);
        $data['features'] = collect($data['features'] ?? [])
            ->map(fn ($f) => ['icon' => preg_match('/^ri-[a-z0-9-]+$/', $f['icon'] ?? '') ? $f['icon'] : '', 'title' => trim($f['title'] ?? ''), 'text' => trim($f['text'] ?? '')])
            ->filter(fn ($f) => $f['title'] !== '')->values()->all();
        $data['link_url'] = Blocks::link($data['link_url'] ?? '') ?: null;
        $data['featured'] = $request->boolean('featured');
        $data['position'] = (int) ($data['position'] ?? 0);
        $data['image'] = $this->imageFrom($request, $p?->image);
        unset($data['image_file'], $data['remove_image']);

        return $data;
    }
}
