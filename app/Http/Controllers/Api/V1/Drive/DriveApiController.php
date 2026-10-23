<?php

namespace App\Http\Controllers\Api\V1\Drive;

use App\Http\Controllers\Api\V1\ApiController;
use App\Models\Drive\File;
use App\Models\Drive\Folder;
use App\Models\Drive\Version;
use App\Models\Projects\Project;
use App\Models\User;
use App\Services\Drive\DriveService;
use Illuminate\Http\Request;

/**
 * Workspace Drive for the mobile app. Items are addressed as {type}/{id},
 * type "folder" or "file". Access is checked in DriveService, as on the web.
 */
class DriveApiController extends ApiController
{
    public function __construct(protected DriveService $drive) {}

    /**
     * A folder's contents (`folder_id`), a project space (`project_id`), My Drive (neither), or a
     * search (`q`, two letters or more). Includes the path back up and what you may do here.
     */
    public function index(Request $request)
    {
        $me = $request->user();
        $stars = $this->drive->starredKeys($me);
        if (mb_strlen($q = trim((string) $request->query('q'))) >= 2) {
            [$folders, $files] = $this->drive->search($me, $q);

            return $this->ok($this->listing($folders, $files, $me, $stars));
        }

        $folder = $request->filled('folder_id') ? Folder::find($request->integer('folder_id')) : null;
        $project = !$folder && $request->filled('project_id') ? Project::find($request->integer('project_id')) : null;
        if (($request->filled('folder_id') && !$folder) || ($request->filled('project_id') && !$project && !$folder)) return $this->fail('Folder not found.', 404, 'not_found');

        $level = $folder ? $this->drive->access($folder, $me) : ($project ? $this->drive->projectLevel($project->id, $me) : 'owner');
        if (!$level || ($folder && $this->drive->inTrash($folder))) return $this->fail('Folder not found.', 404, 'not_found');
        [$folders, $files] = $this->drive->contents($me, $folder, $project);

        $trail = [];
        for ($f = $folder; $f; $f = $f->parent_id ? Folder::find($f->parent_id) : null) {
            if (!$this->drive->access($f, $me)) break;
            array_unshift($trail, ['id' => $f->id, 'name' => $f->name]);
        }

        return $this->ok($this->listing($folders, $files, $me, $stars) + [
            'folder' => $folder ? $this->item($folder, $me, $stars) : null,
            'project' => $project ? ['id' => $project->id, 'name' => $project->name] : null,
            'trail' => $trail, 'level' => $level, 'can_add' => DriveService::RANK[$level] >= 2,
        ], null, 200, ['usage' => $this->drive->usage($me), 'quota' => $this->drive->quota()]);
    }

    /** Project spaces the person can open. */
    public function projects(Request $request)
    {
        $me = $request->user();

        return $this->ok($this->drive->projectsFor($me)->map(fn ($p) => ['id' => $p->id, 'code' => $p->code, 'name' => $p->name, 'level' => $this->drive->projectLevel($p->id, $me)])->values());
    }

    public function shared(Request $request)
    {
        $me = $request->user();
        [$folders, $files] = $this->drive->sharedWithMe($me);

        return $this->ok($this->listing($folders, $files, $me, $this->drive->starredKeys($me)));
    }

    public function recent(Request $request)
    {
        $me = $request->user();

        return $this->ok($this->listing(collect(), $this->drive->recent($me), $me, $this->drive->starredKeys($me)));
    }

    public function starred(Request $request)
    {
        $me = $request->user();
        [$folders, $files] = $this->drive->starred($me);

        return $this->ok($this->listing($folders, $files, $me, $this->drive->starredKeys($me)));
    }

    public function trash(Request $request)
    {
        $me = $request->user();
        [$folders, $files] = $this->drive->trash($me);

        return $this->ok($this->listing($folders, $files, $me, []));
    }

    public function storeFolder(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:150'], 'parent_id' => ['nullable', 'integer'], 'project_id' => ['nullable', 'integer']]);
        [$parent, $project] = $this->target($data);
        $folder = $this->drive->createFolder($request->user(), $data['name'], $parent, $project);

        return $this->ok($this->item($folder, $request->user(), []), 'Folder created.', 201);
    }

    /** Multipart: `file` or `files[]`, plus `folder_id` or `project_id` (neither = My Drive). */
    public function upload(Request $request)
    {
        $max = max(1, (int) config('qudroid.drive.max_upload_mb', 200)) * 1024;
        $data = $request->validate([
            'file' => ['nullable', 'file', 'max:' . $max], 'files' => ['nullable', 'array', 'max:50'], 'files.*' => ['file', 'max:' . $max],
            'folder_id' => ['nullable', 'integer'], 'project_id' => ['nullable', 'integer'],
        ]);
        $uploads = array_values(array_filter(array_merge([$request->file('file')], (array) $request->file('files', []))));
        if (!$uploads) return $this->fail('Choose a file to upload.', 422, 'validation_failed', ['file' => ['Choose a file to upload.']]);
        [$parent, $project] = $this->target(['parent_id' => $data['folder_id'] ?? null, 'project_id' => $data['project_id'] ?? null]);
        $saved = collect($uploads)->map(fn ($u) => $this->drive->upload($request->user(), $u, $parent, $project));

        return $this->ok($saved->map(fn ($f) => $this->item($f, $request->user(), []))->values(), $saved->count() === 1 ? 'Uploaded.' : 'Uploaded ' . $saved->count() . ' files.', 201);
    }

    /** A file's details, versions, who it is shared with and its public link. */
    public function file(Request $request, File $file)
    {
        $me = $request->user();
        $level = $this->drive->access($file, $me);
        if (!$level || ($this->drive->inTrash($file) && DriveService::RANK[$level] < 2)) return $this->fail('File not found.', 404, 'not_found');
        $file->load('owner', 'editor', 'versions.uploader');
        $link = $this->drive->link($file);

        return $this->ok($this->item($file, $me, $this->drive->starredKeys($me)) + [
            'level' => $level, 'description' => $file->description,
            'versions' => $file->versions->map(fn ($v) => ['id' => $v->id, 'version' => $v->version, 'size' => $v->size, 'by' => $v->uploader?->name,
                'at' => $v->created_at?->toIso8601String(), 'download_url' => route('api.v1.drive.download', [$file, 'version' => $v->id])])->values(),
            'shares' => $this->drive->shares($file)->map(fn ($s) => ['user_id' => $s->user_id, 'name' => $s->user?->name, 'role' => $s->role])->values(),
            'link' => $link ? ['url' => route('drive.public', $link->token), 'expires_at' => $link->expires_at?->toIso8601String()] : null,
        ]);
    }

    /** The file (`?version=` for an earlier one, `?inline=1` to view rather than save). */
    public function download(Request $request, File $file)
    {
        $me = $request->user();
        if (!$this->drive->can($file, $me, 'viewer') || ($this->drive->inTrash($file) && !$this->drive->can($file, $me, 'editor'))) return $this->fail('File not found.', 404, 'not_found');
        if ($request->filled('version')) {
            $v = Version::where('file_id', $file->id)->find($request->integer('version'));
            if (!$v) return $this->fail('Version not found.', 404, 'not_found');

            return $this->drive->stream(new File(['name' => $file->name, 'mime' => $v->mime]), false, $v->path);
        }

        return $this->drive->stream($file, $request->boolean('inline'));
    }

    /** Rename (`name`) and/or move (`to`: "mine", "folder:ID" or "project:ID"). */
    public function update(Request $request, string $type, int $id)
    {
        $data = $request->validate(['name' => ['nullable', 'string', 'max:150'], 'to' => ['nullable', 'string', 'regex:/^(mine|folder:\d+|project:\d+)$/']]);
        $item = $this->find($type, $id);
        if (!$item) return $this->fail('Not found.', 404, 'not_found');
        if (!empty($data['name']) && $data['name'] !== $item->name) $this->drive->rename($request->user(), $item, $data['name']);
        if (!empty($data['to'])) {
            [$kind, $toId] = array_pad(explode(':', $data['to']), 2, null);
            $target = $kind === 'folder' ? Folder::find((int) $toId) : null;
            $project = $kind === 'project' ? Project::find((int) $toId) : null;
            if (($kind === 'folder' && !$target) || ($kind === 'project' && !$project)) return $this->fail('Destination not found.', 404, 'not_found');
            $this->drive->move($request->user(), $item, $target, $project);
        }

        return $this->ok($this->item($item->refresh(), $request->user(), []), 'Saved.');
    }

    /** To the trash; `?forever=1` deletes an item that is already in the trash for good. */
    public function destroy(Request $request, string $type, int $id)
    {
        $item = $this->find($type, $id);
        if (!$item) return $this->fail('Not found.', 404, 'not_found');
        if ($request->boolean('forever')) {
            $this->drive->destroy($request->user(), $item);

            return $this->ok(null, 'Deleted for good.');
        }
        $this->drive->trashItem($request->user(), $item);

        return $this->ok(null, 'Moved to the trash.');
    }

    public function restore(Request $request, string $type, int $id)
    {
        $item = $this->find($type, $id);
        if (!$item) return $this->fail('Not found.', 404, 'not_found');
        $this->drive->restore($request->user(), $item);

        return $this->ok($this->item($item->refresh(), $request->user(), []), 'Restored.');
    }

    public function star(Request $request, string $type, int $id)
    {
        $item = $this->find($type, $id);
        if (!$item || !$this->drive->can($item, $request->user(), 'viewer')) return $this->fail('Not found.', 404, 'not_found');

        return $this->ok(['starred' => $this->drive->toggleStar($request->user(), $item)]);
    }

    /** Share with people: `{user_ids[], permission: viewer|editor}`. DELETE with `user_id` to stop. */
    public function share(Request $request, string $type, int $id)
    {
        $data = $request->validate(['user_ids' => ['required', 'array', 'min:1', 'max:50'], 'user_ids.*' => ['integer'], 'permission' => ['required', 'in:viewer,editor']]);
        $item = $this->find($type, $id);
        if (!$item) return $this->fail('Not found.', 404, 'not_found');
        $allowed = $this->drive->people($request->user())->pluck('id')->flip();
        $people = User::whereIn('id', $data['user_ids'])->get()->filter(fn ($u) => isset($allowed[$u->id]));
        foreach ($people as $person) $this->drive->share($request->user(), $item, $person, $data['permission']);

        return $this->ok(['shared_with' => $people->pluck('name')->values()], $people->isEmpty() ? 'Nobody to share with.' : 'Shared.');
    }

    public function unshare(Request $request, string $type, int $id)
    {
        $data = $request->validate(['user_id' => ['required', 'integer']]);
        $item = $this->find($type, $id);
        if (!$item) return $this->fail('Not found.', 404, 'not_found');
        $this->drive->unshare($request->user(), $item, (int) $data['user_id']);

        return $this->ok(null, 'Access removed.');
    }

    /** A view-and-download link for anyone (`days`: 1, 7, 30 or 90; none = no expiry). DELETE turns it off. */
    public function link(Request $request, string $type, int $id)
    {
        $data = $request->validate(['days' => ['nullable', 'integer', 'in:1,7,30,90']]);
        $item = $this->find($type, $id);
        if (!$item) return $this->fail('Not found.', 404, 'not_found');
        $link = $this->drive->createLink($request->user(), $item, $data['days'] ?? null);

        return $this->ok(['url' => route('drive.public', $link->token), 'expires_at' => $link->expires_at?->toIso8601String()], 'Link created.', 201);
    }

    public function unlink(Request $request, string $type, int $id)
    {
        $item = $this->find($type, $id);
        if (!$item) return $this->fail('Not found.', 404, 'not_found');
        $this->drive->revokeLinks($request->user(), $item);

        return $this->ok(null, 'The link no longer works.');
    }

    /** People an item can be shared with. */
    public function people(Request $request)
    {
        $q = trim((string) $request->query('q'));

        return $this->ok($this->drive->people($request->user())
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('name', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%")))
            ->limit(50)->get(['id', 'name', 'email'])->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'avatar' => $u->avatar_url])->values());
    }

    // ── Helpers ─────────────────────────────────────────────────────────

    protected function listing($folders, $files, User $me, array $stars): array
    {
        return [
            'folders' => collect($folders)->map(fn ($f) => $this->item($f, $me, $stars))->values(),
            'files' => collect($files)->map(fn ($f) => $this->item($f, $me, $stars))->values(),
        ];
    }

    protected function item(Folder|File $item, User $me, array $stars): array
    {
        $out = [
            'type' => $item::TYPE, 'id' => $item->id, 'name' => $item->name,
            'owner' => $item->owner ? ['id' => $item->owner->id, 'name' => $item->owner->name] : null,
            'mine' => $item->owner_id === $me->id, 'project_id' => $item->project_id,
            'starred' => isset($stars[$item::TYPE . ':' . $item->id]),
            'trashed' => $item->trashed_at !== null,
            'updated_at' => $item->updated_at?->toIso8601String(),
        ];
        if ($item instanceof File) {
            $out += [
                'folder_id' => $item->folder_id, 'mime' => $item->mime, 'size' => $item->size, 'version' => (int) $item->version,
                'preview' => $item->previewKind(),
                'download_url' => route('api.v1.drive.download', $item),
                'thumb_url' => $item->previewKind() === 'image' ? route('api.v1.drive.download', [$item, 'inline' => 1]) : null,
            ];
        } else {
            $out += ['parent_id' => $item->parent_id, 'color' => $item->color];
        }

        return $out;
    }

    protected function find(string $type, int $id): Folder|File|null
    {
        return match ($type) { 'folder' => Folder::find($id), 'file' => File::find($id), default => null };
    }

    protected function target(array $data): array
    {
        $parent = !empty($data['parent_id']) ? Folder::findOrFail($data['parent_id']) : null;
        $project = !$parent && !empty($data['project_id']) ? Project::findOrFail($data['project_id']) : null;

        return [$parent, $project];
    }
}
