<?php

namespace App\Http\Controllers\Drive;

use App\Http\Controllers\Controller;
use App\Models\Drive\File;
use App\Models\Drive\Folder;
use App\Services\Drive\DriveService;
use Illuminate\Http\Request;

/**
 * "Anyone with the link": no sign-in, view and download only. A folder link
 * opens that folder and everything inside it, and nothing above it.
 */
class PublicLinkController extends Controller
{
    public function __construct(protected DriveService $drive) {}

    public function show(string $token)
    {
        [$link, $item] = $this->resolve($token);

        return $item instanceof File
            ? view('modules.drive.public', ['link' => $link, 'root' => $item, 'file' => $item, 'folder' => null])
            : $this->folderPage($link, $item, $item);
    }

    public function folder(string $token, Folder $folder)
    {
        [$link, $root] = $this->resolve($token);
        abort_unless($this->drive->linkCoversFolder($root, $folder), 404);

        return $this->folderPage($link, $root, $folder);
    }

    public function file(Request $request, string $token, File $file)
    {
        [$link, $root] = $this->resolve($token);
        abort_unless($this->drive->linkCovers($root, $file), 404);
        $inline = $request->boolean('inline');
        if (!$inline) $link->increment('downloads');

        return $this->drive->stream($file, $inline);
    }

    public function zip(Request $request, string $token)
    {
        [$link, $root] = $this->resolve($token);
        $folder = $request->filled('folder') ? Folder::findOrFail($request->integer('folder')) : $root;
        abort_unless($folder instanceof Folder && ($folder->is($root) || $this->drive->linkCoversFolder($root, $folder)), 404);
        $link->increment('downloads');

        return response()->download($this->drive->zip($folder), $folder->name . '.zip')->deleteFileAfterSend();
    }

    protected function folderPage($link, Folder $root, Folder $folder)
    {
        $trail = collect($folder->trail());
        $trail = $trail->slice($trail->search(fn ($f) => $f->id === $root->id))->values();

        return view('modules.drive.public', [
            'link' => $link, 'root' => $root, 'folder' => $folder, 'file' => null, 'trail' => $trail,
            'folders' => Folder::live()->where('parent_id', $folder->id)->orderBy('name')->get(),
            'files' => File::live()->where('folder_id', $folder->id)->orderBy('name')->get(),
        ]);
    }

    protected function resolve(string $token): array
    {
        $found = $this->drive->resolveLink($token);
        abort_unless($found, 404, 'This link has expired or was turned off.');

        return $found;
    }
}
