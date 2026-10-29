<?php

namespace App\Http\Controllers\Website\Admin;

use App\Http\Controllers\Controller;
use App\Models\Website\Page;
use App\Services\Website\Blocks;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Website → Pages: pages built from blocks. "home" is the front page. */
class PageController extends Controller
{
    public function index()
    {
        return view('modules.website.pages.index', [
            'pages' => Page::with('editor')->orderByRaw("slug = 'home' desc")->ordered()->get(),
        ]);
    }

    public function create()
    {
        return $this->form(new Page(['status' => 'draft', 'blocks' => [
            ['type' => 'hero', 'title' => 'A new page', 'subtitle' => '', 'theme' => 'light'],
        ]]));
    }

    public function store(Request $request)
    {
        $page = Page::create($this->validated($request) + ['updated_by' => $request->user()->id]);

        return redirect()->route('website.pages.edit', $page)->with('success', 'Page created.');
    }

    public function edit(Page $page)
    {
        return $this->form($page);
    }

    public function update(Request $request, Page $page)
    {
        $page->update($this->validated($request, $page) + ['updated_by' => $request->user()->id]);

        return back()->with('success', $page->isPublished() ? 'Saved. The page is live.' : 'Saved as a draft.');
    }

    public function destroy(Page $page)
    {
        abort_if($page->slug === 'home', 422, 'The home page cannot be deleted.');
        $page->delete();

        return redirect()->route('website.pages.index')->with('success', 'Page deleted.');
    }

    protected function form(Page $page)
    {
        return view('modules.website.pages.form', ['page' => $page, 'schema' => Blocks::forEditor()]);
    }

    protected function validated(Request $request, ?Page $page = null): array
    {
        $home = $page?->slug === 'home';
        $request->merge(['slug' => $home ? 'home' : \Illuminate\Support\Str::slug((string) ($request->input('slug') ?: $request->input('title')))]);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'slug' => $home ? ['required'] : ['required', 'string', 'max:80', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::notIn(Page::RESERVED), Rule::unique('site_pages', 'slug')->ignore($page?->id)],
            'description' => ['nullable', 'string', 'max:300'],
            'status' => ['required', Rule::in(array_keys(Page::STATUSES))],
            'in_nav' => ['nullable', 'boolean'],
            'in_footer' => ['nullable', 'boolean'],
            'position' => ['nullable', 'integer', 'min:0', 'max:999'],
            'blocks' => ['nullable', 'string', 'max:500000'],
        ], ['slug.not_in' => 'That address is used by the website or the portal. Pick another.'], ['slug' => 'address']);

        if ($home) $data['status'] = 'published';
        $data['in_nav'] = !$home && $request->boolean('in_nav');
        $data['in_footer'] = !$home && $request->boolean('in_footer');
        $data['position'] = (int) ($data['position'] ?? 0);
        $data['blocks'] = Blocks::clean($data['blocks'] ?? '[]');

        return $data;
    }
}
