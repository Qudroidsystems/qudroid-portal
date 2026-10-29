<?php

namespace App\Http\Controllers\Website\Admin;

use App\Http\Controllers\Controller;
use App\Models\Website\Work;
use App\Services\Website\Blocks;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Website → Our work: client projects shown as case studies. */
class WorkController extends Controller
{
    use StoresSiteImages;

    public function index()
    {
        return view('modules.website.works.index', ['works' => Work::ordered()->get()]);
    }

    public function create()
    {
        return view('modules.website.works.form', ['w' => new Work(['status' => 'draft', 'color' => '#1d1d1f', 'year' => now()->year, 'tags' => [], 'results' => []])]);
    }

    public function store(Request $request)
    {
        $w = Work::create($this->validated($request));

        return redirect()->route('website.works.edit', $w)->with('success', 'Work added.');
    }

    public function edit(Work $work)
    {
        return view('modules.website.works.form', ['w' => $work]);
    }

    public function update(Request $request, Work $work)
    {
        $work->update($this->validated($request, $work));

        return back()->with('success', 'Work saved.');
    }

    public function destroy(Work $work)
    {
        $work->delete();

        return redirect()->route('website.works.index')->with('success', 'Work removed.');
    }

    protected function validated(Request $request, ?Work $w = null): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'slug' => ['nullable', 'string', 'max:80'],
            'client' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'string', 'max:60'],
            'year' => ['nullable', 'integer', 'min:1990', 'max:2100'],
            'summary' => ['nullable', 'string', 'max:500'],
            'body' => ['nullable', 'string', 'max:20000'],
            'tags' => ['nullable', 'string', 'max:300'],
            'color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'link_url' => ['nullable', 'string', 'max:500'],
            'featured' => ['nullable', 'boolean'],
            'status' => ['required', Rule::in(['draft', 'published'])],
            'position' => ['nullable', 'integer', 'min:0', 'max:999'],
            'results' => ['nullable', 'array', 'max:8'],
            'results.*.value' => ['nullable', 'string', 'max:30'],
            'results.*.label' => ['nullable', 'string', 'max:80'],
        ] + $this->imageRules());

        $data['slug'] = $this->uniqueSlug(Work::class, $this->slugFrom($data['slug'] ?? null, $data['title']), $w?->id);
        $data['tags'] = collect(explode(',', (string) ($data['tags'] ?? '')))->map(fn ($t) => trim($t))->filter()->unique()->take(12)->values()->all();
        $data['results'] = collect($data['results'] ?? [])
            ->map(fn ($r) => ['value' => trim($r['value'] ?? ''), 'label' => trim($r['label'] ?? '')])
            ->filter(fn ($r) => $r['value'] !== '')->values()->all();
        $data['link_url'] = Blocks::link($data['link_url'] ?? '') ?: null;
        $data['featured'] = $request->boolean('featured');
        $data['position'] = (int) ($data['position'] ?? 0);
        $data['image'] = $this->imageFrom($request, $w?->image);
        unset($data['image_file'], $data['remove_image']);

        return $data;
    }
}
