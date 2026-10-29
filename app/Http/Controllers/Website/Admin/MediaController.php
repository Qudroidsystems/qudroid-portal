<?php

namespace App\Http\Controllers\Website\Admin;

use App\Http\Controllers\Controller;
use App\Models\Website\Media;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/** Website → Pictures: images uploaded for the website (public disk, website/ folder). */
class MediaController extends Controller
{
    use StoresSiteImages;

    public function index()
    {
        return view('modules.website.media', ['media' => Media::latest('id')->paginate(48)]);
    }

    /** For the picture picker in the editors. */
    public function list()
    {
        return response()->json(['data' => Media::latest('id')->limit(200)->get()->map(fn (Media $m) => $this->row($m))]);
    }

    public function store(Request $request)
    {
        $request->validate(['files' => ['required', 'array', 'max:20'], 'files.*' => ['image', 'max:5120']], [], ['files.*' => 'picture']);
        $made = collect($request->file('files'))->map(fn ($f) => $this->storeMedia($f, $request));

        return $request->expectsJson()
            ? response()->json(['data' => $made->map(fn (Media $m) => $this->row($m))], 201)
            : back()->with('success', $made->count() . ' ' . str('picture')->plural($made->count()) . ' uploaded.');
    }

    public function update(Request $request, Media $media)
    {
        $media->update($request->validate(['alt' => ['nullable', 'string', 'max:200']]));

        return back()->with('success', 'Saved.');
    }

    public function destroy(Media $media)
    {
        Storage::disk('public')->delete($media->path);
        $media->delete();

        return back()->with('success', 'Picture deleted. Anywhere it was used now shows no picture.');
    }

    protected function row(Media $m): array
    {
        return ['id' => $m->id, 'path' => $m->path, 'url' => $m->url(), 'name' => $m->name, 'width' => $m->width, 'height' => $m->height];
    }
}
