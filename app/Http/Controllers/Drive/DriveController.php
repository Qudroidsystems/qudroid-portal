<?php

namespace App\Http\Controllers\Drive;

use App\Http\Controllers\Controller;
use App\Models\Connect\Conversation;
use App\Models\Connect\Message;
use App\Models\Drive\File;
use App\Models\Drive\Folder;
use App\Models\Drive\Share;
use App\Models\Drive\Version;
use App\Models\Projects\Project;
use App\Models\User;
use App\Services\Connect\ConnectService;
use App\Services\Drive\DriveService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DriveController extends Controller
{
    public function __construct(protected DriveService $drive) {}

    // ── Browsing ────────────────────────────────────────────────────────

    public function index(Request $request)
    {
        [$folders, $files] = $this->drive->contents($request->user());

        return $this->browse($request, 'My Drive', 'ri-hard-drive-2-line', $folders, $files, ['canAdd' => true, 'section' => 'mine']);
    }

    public function folder(Request $request, Folder $folder)
    {
        $me = $request->user();
        $level = $this->drive->access($folder, $me);
        abort_unless($level, 403);
        abort_if($this->drive->inTrash($folder), 404);
        [$folders, $files] = $this->drive->contents($me, $folder);

        return $this->browse($request, $folder->name, 'ri-folder-3-line', $folders, $files, [
            'folder' => $folder, 'level' => $level, 'canAdd' => DriveService::RANK[$level] >= 2,
            'trail' => $this->trail($folder, $me), 'section' => $folder->project_id ? 'project' : ($folder->owner_id === $me->id ? 'mine' : 'shared'),
        ]);
    }

    public function project(Request $request, Project $project)
    {
        $me = $request->user();
        $level = $this->drive->projectLevel($project->id, $me);
        abort_unless($level, 403);
        [$folders, $files] = $this->drive->contents($me, null, $project);

        return $this->browse($request, $project->name, 'ri-folder-chart-line', $folders, $files, [
            'project' => $project, 'level' => $level, 'canAdd' => DriveService::RANK[$level] >= 2, 'section' => 'project',
            'subtitle' => 'Shared with everyone on the project.',
        ]);
    }

    public function shared(Request $request)
    {
        [$folders, $files] = $this->drive->sharedWithMe($request->user());

        return $this->browse($request, 'Shared with me', 'ri-share-line', $folders, $files, ['section' => 'shared', 'showShare' => true]);
    }

    public function recent(Request $request)
    {
        return $this->browse($request, 'Recent', 'ri-time-line', collect(), $this->drive->recent($request->user()), ['section' => 'recent', 'showWhere' => true]);
    }

    public function starred(Request $request)
    {
        [$folders, $files] = $this->drive->starred($request->user());

        return $this->browse($request, 'Starred', 'ri-star-line', $folders, $files, ['section' => 'starred', 'showWhere' => true]);
    }

    public function search(Request $request)
    {
        $term = trim((string) $request->input('q'));
        [$folders, $files] = mb_strlen($term) >= 2 ? $this->drive->search($request->user(), $term) : [collect(), collect()];

        return $this->browse($request, 'Search', 'ri-search-line', $folders, $files, ['section' => 'search', 'term' => $term, 'showWhere' => true,
            'subtitle' => mb_strlen($term) >= 2 ? 'Results for "' . $term . '"' : 'Type at least two letters.']);
    }

    public function trash(Request $request)
    {
        [$folders, $files] = $this->drive->trash($request->user());

        return $this->browse($request, 'Trash', 'ri-delete-bin-6-line', $folders, $files, ['section' => 'trash', 'inTrash' => true,
            'subtitle' => 'Restore things, or delete them for good to free up space.']);
    }

    public function chats(Request $request)
    {
        return view('modules.drive.chats', $this->side($request) + ['messages' => $this->drive->chatFiles($request->user()), 'section' => 'chats']);
    }

    public function file(Request $request, File $file)
    {
        $me = $request->user();
        $level = $this->drive->access($file, $me);
        abort_unless($level, 403);
        $trashed = $this->drive->inTrash($file);
        abort_if($trashed && DriveService::RANK[$level] < 2, 404);
        $file->load('owner', 'editor', 'versions.uploader', 'project');

        return view('modules.drive.file', $this->side($request) + [
            'file' => $file, 'level' => $level, 'trashed' => $trashed, 'trail' => $file->folder ? $this->trail($file->folder, $me) : [],
            'shares' => $this->drive->shares($file), 'link' => $this->drive->link($file),
            'starred' => isset($this->drive->starredKeys($me)['file:' . $file->id]), 'back' => $this->drive->parentUrl($file, $me),
            'conversations' => app(ConnectService::class)->inbox($me)->take(30),
            'text' => $file->previewKind() === 'text' && Storage::disk('local')->exists($file->path)
                ? mb_convert_encoding(substr((string) Storage::disk('local')->get($file->path), 0, 200000), 'UTF-8', 'UTF-8') : null,
            'section' => $file->project_id ? 'project' : ($file->owner_id === $me->id ? 'mine' : 'shared'),
        ]);
    }

    public function view(Request $request, File $file)
    {
        $this->authorizeItem($file, $request->user(), 'viewer');

        return $this->drive->stream($file, true);
    }

    public function download(Request $request, File $file)
    {
        $this->authorizeItem($file, $request->user(), 'viewer');
        if ($request->filled('version')) {
            $v = Version::where('file_id', $file->id)->findOrFail($request->integer('version'));

            return $this->drive->stream(new File(['name' => $this->versionName($file, $v->version), 'mime' => $v->mime]), false, $v->path);
        }

        return $this->drive->stream($file, false);
    }

    public function zip(Request $request, Folder $folder)
    {
        $this->authorizeItem($folder, $request->user(), 'viewer');
        $path = $this->drive->zip($folder);

        return response()->download($path, $folder->name . '.zip')->deleteFileAfterSend();
    }

    // ── Adding ──────────────────────────────────────────────────────────

    public function storeFolder(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:150'], 'parent_id' => ['nullable', 'integer'], 'project_id' => ['nullable', 'integer']]);
        [$parent, $project] = $this->target($data);
        $folder = $this->drive->createFolder($request->user(), $data['name'], $parent, $project);

        return redirect()->route('drive.folder', $folder)->with('success', 'Folder created.');
    }

    public function upload(Request $request)
    {
        $max = max(1, (int) config('qudroid.drive.max_upload_mb', 200)) * 1024;
        $data = $request->validate([
            'files' => ['required', 'array', 'max:50'], 'files.*' => ['file', 'max:' . $max],
            'parent_id' => ['nullable', 'integer'], 'project_id' => ['nullable', 'integer'],
        ], ['files.*.max' => 'Each file can be up to ' . File::humanSize($max * 1024) . '.']);
        [$parent, $project] = $this->target($data);

        $saved = [];
        foreach ($request->file('files') as $upload) {
            $saved[] = $this->drive->upload($request->user(), $upload, $parent, $project);
        }

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'files' => collect($saved)->map(fn ($f) => ['id' => $f->id, 'name' => $f->name, 'url' => route('drive.file', $f)])]);
        }

        return back()->with('success', count($saved) === 1 ? 'Uploaded ' . $saved[0]->name . '.' : 'Uploaded ' . count($saved) . ' files.');
    }

    public function newVersion(Request $request, File $file)
    {
        $max = max(1, (int) config('qudroid.drive.max_upload_mb', 200)) * 1024;
        $request->validate(['file' => ['required', 'file', 'max:' . $max]]);
        $this->drive->newVersion($request->user(), $file, $request->file('file'));

        return back()->with('success', 'New version uploaded. Earlier versions are kept below.');
    }

    public function restoreVersion(Request $request, File $file, Version $version)
    {
        $this->drive->restoreVersion($request->user(), $file, $version);

        return back()->with('success', 'Version ' . $version->version . ' is the current version again.');
    }

    public function describe(Request $request, File $file)
    {
        $data = $request->validate(['description' => ['nullable', 'string', 'max:500']]);
        $this->drive->describe($request->user(), $file, $data['description'] ?? null);

        return back()->with('success', 'Description saved.');
    }

    public function saveFromChat(Request $request, Message $message)
    {
        $file = $this->drive->saveFromChat($request->user(), $message);

        return back()->with('success', $file->name . ' saved to My Drive.');
    }

    // ── Changing ────────────────────────────────────────────────────────

    public function rename(Request $request, string $type, int $id)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:150']]);
        $this->drive->rename($request->user(), $this->item($type, $id), $data['name']);

        return back()->with('success', 'Renamed.');
    }

    public function moveForm(Request $request, string $type, int $id)
    {
        $me = $request->user();
        $item = $this->item($type, $id);
        $this->authorizeItem($item, $me, 'editor');
        $projects = $this->drive->projectsFor($me)->filter(fn ($p) => in_array($this->drive->projectLevel($p->id, $me), ['owner', 'editor'], true));

        return view('modules.drive.move', $this->side($request) + [
            'item' => $item, 'targets' => $this->drive->targets($me, $item), 'projects' => $projects, 'back' => $this->drive->parentUrl($item, $me),
        ]);
    }

    public function move(Request $request, string $type, int $id)
    {
        $data = $request->validate(['to' => ['required', 'string', 'regex:/^(mine|folder:\d+|project:\d+)$/']]);
        [$kind, $toId] = array_pad(explode(':', $data['to']), 2, null);
        $item = $this->item($type, $id);
        $this->drive->move($request->user(), $item,
            $kind === 'folder' ? Folder::findOrFail((int) $toId) : null,
            $kind === 'project' ? Project::findOrFail((int) $toId) : null);

        return redirect($this->drive->parentUrl($item->refresh(), $request->user()))->with('success', 'Moved ' . $item->name . '.');
    }

    public function trashItem(Request $request, string $type, int $id)
    {
        $me = $request->user();
        $item = $this->item($type, $id);
        $this->drive->trashItem($me, $item);
        $back = $this->drive->parentUrl($item, $me);

        return ($request->boolean('stay') ? back() : redirect($back))->with('success', $item->name . ' moved to the trash.');
    }

    public function restore(Request $request, string $type, int $id)
    {
        $item = $this->item($type, $id);
        $this->drive->restore($request->user(), $item);

        return back()->with('success', $item->name . ' restored.');
    }

    public function destroy(Request $request, string $type, int $id)
    {
        $item = $this->item($type, $id);
        $this->drive->destroy($request->user(), $item);

        return redirect()->route('drive.trash')->with('success', $item->name . ' deleted for good.');
    }

    public function emptyTrash(Request $request)
    {
        $n = $this->drive->emptyTrash($request->user());

        return back()->with('success', $n ? 'Deleted ' . $n . ' ' . str('item')->plural($n) . ' for good.' : 'Nothing you can delete is in the trash.');
    }

    public function star(Request $request, string $type, int $id)
    {
        $item = $this->item($type, $id);
        $this->authorizeItem($item, $request->user(), 'viewer');
        $on = $this->drive->toggleStar($request->user(), $item);

        return $request->expectsJson() ? response()->json(['starred' => $on]) : back();
    }

    // ── Sharing ─────────────────────────────────────────────────────────

    public function shareForm(Request $request, string $type, int $id)
    {
        $me = $request->user();
        $item = $this->item($type, $id);
        $level = $this->drive->access($item, $me);
        abort_unless($level, 403);
        $shares = $this->drive->shares($item);

        return view('modules.drive.share', $this->side($request) + [
            'item' => $item->load('owner'), 'level' => $level, 'shares' => $shares, 'link' => $this->drive->link($item),
            'people' => DriveService::RANK[$level] >= 2 ? $this->drive->people($me)->whereNotIn('id', $shares->pluck('user_id')->push($item->owner_id))->get(['id', 'name', 'email']) : collect(),
            'conversations' => app(ConnectService::class)->inbox($me)->take(30),
            'back' => $item instanceof File ? route('drive.file', $item) : route('drive.folder', $item),
        ]);
    }

    public function share(Request $request, string $type, int $id)
    {
        $data = $request->validate(['users' => ['required', 'array', 'min:1', 'max:50'], 'users.*' => ['integer'], 'role' => ['required', 'in:viewer,editor']]);
        $item = $this->item($type, $id);
        $people = User::whereIn('id', $data['users'])->get();
        $allowed = $this->drive->people($request->user())->pluck('id')->flip();
        foreach ($people as $person) {
            if (isset($allowed[$person->id])) $this->drive->share($request->user(), $item, $person, $data['role']);
        }

        return back()->with('success', 'Shared with ' . $people->pluck('name')->implode(', ') . '.');
    }

    public function unshare(Request $request, string $type, int $id, User $user)
    {
        $item = $this->item($type, $id);
        $this->drive->unshare($request->user(), $item, $user->id);

        return $user->id === $request->user()->id
            ? redirect()->route('drive.shared')->with('success', 'Removed ' . $item->name . ' from your shared items.')
            : back()->with('success', $user->name . ' no longer has access.');
    }

    public function createLink(Request $request, string $type, int $id)
    {
        $data = $request->validate(['days' => ['nullable', 'integer', 'in:1,7,30,90']]);
        $this->drive->createLink($request->user(), $this->item($type, $id), $data['days'] ?? null);

        return back()->with('success', 'Link created. Anyone with it can view and download.');
    }

    public function revokeLink(Request $request, string $type, int $id)
    {
        $this->drive->revokeLinks($request->user(), $this->item($type, $id));

        return back()->with('success', 'The link no longer works.');
    }

    public function sendToChat(Request $request, string $type, int $id)
    {
        $data = $request->validate(['conversation_id' => ['required', 'integer'], 'note' => ['nullable', 'string', 'max:1000']]);
        $conversation = Conversation::findOrFail($data['conversation_id']);
        $this->drive->sendToChat($request->user(), $this->item($type, $id), $conversation, $data['note'] ?? null);

        return redirect()->route('connect.show', $conversation)->with('success', 'Sent.');
    }

    // ── Helpers ─────────────────────────────────────────────────────────

    protected function browse(Request $request, string $title, string $icon, $folders, $files, array $extra = [])
    {
        $me = $request->user();
        $sort = in_array($request->input('sort'), ['name', 'updated', 'size'], true) ? $request->input('sort') : ($extra['section'] === 'recent' ? 'updated' : 'name');
        $files = collect($files);
        $files = (match ($sort) {
            'updated' => $files->sortByDesc('updated_at'),
            'size' => $files->sortByDesc('size'),
            default => $files->sortBy(fn ($f) => mb_strtolower($f->name)),
        })->values();

        return view('modules.drive.browse', $this->side($request) + [
            'title' => $title, 'icon' => $icon, 'folders' => collect($folders), 'files' => $files, 'sort' => $sort,
            'stars' => $this->drive->starredKeys($me), 'folder' => null, 'project' => null, 'trail' => [], 'level' => null,
            'canAdd' => false, 'inTrash' => false, 'showShare' => false, 'showWhere' => false, 'subtitle' => null, 'term' => null,
            'grid' => $request->input('view', $request->cookie('drive_view')) === 'grid',
        ] + $extra);
    }

    /** What the left-hand panel needs on every Drive page. */
    protected function side(Request $request): array
    {
        $me = $request->user();

        return ['projects' => $this->drive->projectsFor($me), 'usage' => $this->drive->usage($me), 'quota' => $this->drive->quota()];
    }

    protected function trail(Folder $folder, User $me): array
    {
        return collect($folder->trail())->filter(fn ($f) => $this->drive->can($f, $me, 'viewer'))->values()->all();
    }

    protected function item(string $type, int $id): Folder|File
    {
        return $type === 'folder' ? Folder::findOrFail($id) : File::findOrFail($id);
    }

    protected function target(array $data): array
    {
        $parent = !empty($data['parent_id']) ? Folder::findOrFail($data['parent_id']) : null;
        $project = !$parent && !empty($data['project_id']) ? Project::findOrFail($data['project_id']) : null;

        return [$parent, $project];
    }

    protected function authorizeItem(Folder|File $item, User $me, string $level): void
    {
        abort_unless($this->drive->can($item, $me, $level), 403);
        abort_if($level === 'viewer' && $this->drive->inTrash($item) && !$this->drive->can($item, $me, 'editor'), 404);
    }

    protected function versionName(File $file, int $version): string
    {
        $ext = $file->extension();
        $base = $ext !== '' ? mb_substr($file->name, 0, -mb_strlen($ext) - 1) : $file->name;

        return $base . ' (v' . $version . ')' . ($ext !== '' ? '.' . $ext : '');
    }
}
