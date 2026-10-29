<?php

namespace App\Http\Controllers\Website\Admin;

use App\Models\Website\Media;
use App\Services\Website\Blocks;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

/** Picture fields on website forms: upload a new file, pick from the library, or remove. */
trait StoresSiteImages
{
    protected function imageRules(): array
    {
        return [
            'image' => ['nullable', 'string', 'max:500'],
            'image_file' => ['nullable', 'image', 'max:5120'],
            'remove_image' => ['nullable', 'boolean'],
        ];
    }

    /** The image value to save, or the current one when nothing changed. */
    protected function imageFrom(Request $request, ?string $current): ?string
    {
        if ($request->hasFile('image_file')) {
            return $this->storeMedia($request->file('image_file'), $request)->path;
        }
        if ($request->boolean('remove_image')) return null;

        return Blocks::link($request->input('image', $current)) ?: null;
    }

    protected function storeMedia(UploadedFile $file, Request $request): Media
    {
        $path = $file->store('website/' . now()->format('Y/m'), 'public');
        $size = @getimagesize($file->getRealPath()) ?: [null, null];

        return Media::create([
            'path' => $path,
            'name' => mb_substr($file->getClientOriginalName(), 0, 190),
            'mime' => $file->getMimeType(),
            'size' => $file->getSize(),
            'width' => $size[0], 'height' => $size[1],
            'uploaded_by' => $request->user()?->id,
        ]);
    }

    /** "a-b-c" from a typed slug or the title. */
    protected function slugFrom(?string $slug, string $title): string
    {
        return \Illuminate\Support\Str::slug($slug ?: $title) ?: 'item';
    }

    protected function uniqueSlug(string $modelClass, string $slug, ?int $ignore = null): string
    {
        $base = mb_substr($slug, 0, 80);
        $slug = $base;
        for ($i = 2; $modelClass::where('slug', $slug)->when($ignore, fn ($q) => $q->where('id', '!=', $ignore))->exists(); $i++) {
            $slug = $base . '-' . $i;
        }

        return $slug;
    }
}
