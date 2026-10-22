<?php

namespace App\Services\Drive;

use App\Models\Connect\Conversation;
use App\Models\Connect\Message;
use App\Models\Connect\Participant;
use App\Models\Drive\File;
use App\Models\Drive\Folder;
use App\Models\Drive\Link;
use App\Models\Drive\Share;
use App\Models\Drive\Version;
use App\Models\Projects\Project;
use App\Models\Projects\ProjectMember;
use App\Models\User;
use App\Services\Connect\ConnectService;
use App\Services\Messaging\PortalNotifier;
use App\Services\Projects\ProjectService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Workspace Drive.
 *
 *  - My Drive: folders and files a person owns (project_id null).
 *  - Project spaces: each project's shared folder tree. Project managers
 *    (and Manage projects) have full control, members edit, viewers view,
 *    clients see nothing unless something is shared with them directly.
 *  - Sharing: a person can view or edit a file, or a folder and everything
 *    in it. Editors may share too. "Anyone with the link" is view/download only.
 *  - Trash: items stay restorable until the owner (or a project manager)
 *    deletes them for good; only then are the stored files removed.
 *
 * Access levels, lowest to highest: viewer, editor, owner.
 */
class DriveService
{
    public const RANK = ['viewer' => 1, 'editor' => 2, 'owner' => 3];

    public function __construct(protected ProjectService $projects, protected ConnectService $connect) {}

    // ── Access ──────────────────────────────────────────────────────────

    public function access(Folder|File $item, User $user): ?string
    {
        $best = null;
        $raise = function (?string $level) use (&$best) {
            if ($level && (self::RANK[$level] ?? 0) > (self::RANK[$best] ?? 0)) $best = $level;
        };

        if ($item->owner_id === $user->id) $raise('owner');
        if ($item->project_id) $raise($this->projectLevel($item->project_id, $user));
        if ($best === 'owner') return $best;

        $folderIds = $this->ancestorIds($item);
        $role = Share::where('user_id', $user->id)
            ->where(fn ($q) => $q->where(fn ($w) => $w->where('item_type', Folder::TYPE)->whereIn('item_id', $folderIds ?: [0]))
                ->when($item instanceof File, fn ($w) => $w->orWhere(fn ($x) => $x->where('item_type', File::TYPE)->where('item_id', $item->id))))
            ->pluck('role')->sortByDesc(fn ($r) => self::RANK[$r] ?? 0)->first();
        $raise($role);

        return $best;
    }

    public function can(Folder|File|null $item, User $user, string $level): bool
    {
        if (!$item) return false;

        return (self::RANK[$this->access($item, $user)] ?? 0) >= self::RANK[$level];
    }

    /** What a person's project role means in that project's space. */
    public function projectLevel(int $projectId, User $user): ?string
    {
        if ($user->can('Manage projects')) return 'owner';

        return match (ProjectMember::where('project_id', $projectId)->where('user_id', $user->id)->value('role')) {
            'manager' => 'owner',
            'member' => 'editor',
            'viewer' => 'viewer',
            default => null,
        };
    }

    /** Projects whose space the person can open. */
    public function projectsFor(User $user): Collection
    {
        $q = Project::query()->orderBy('name');
        if (!$user->can('Manage projects')) {
            $q->whereIn('id', ProjectMember::where('user_id', $user->id)->whereIn('role', ['manager', 'member', 'viewer'])->pluck('project_id'));
        }

        return $q->get();
    }

    /** Ids of the folders that contain the item (and the item itself when it is a folder). */
    public function ancestorIds(Folder|File $item): array
    {
        $ids = [];
        $id = $item instanceof Folder ? $item->id : $item->folder_id;
        while ($id && !in_array($id, $ids, true)) {
            $ids[] = $id;
            $id = Folder::whereKey($id)->value('parent_id');
        }

        return $ids;
    }

    /** All folders below the given ones (not including them). */
    public function descendantIds(array $folderIds): array
    {
        $all = [];
        $level = $folderIds;
        while ($level) {
            $level = Folder::whereIn('parent_id', $level)->pluck('id')->diff($all)->all();
            $all = array_merge($all, $level);
        }

        return $all;
    }

    /** In the trash itself, or inside a folder that is. */
    public function inTrash(Folder|File $item): bool
    {
        if ($item->trashed_at) return true;
        $ids = $this->ancestorIds($item);

        return $ids && Folder::whereIn('id', $ids)->whereNotNull('trashed_at')->exists();
    }

    // ── Lists ───────────────────────────────────────────────────────────

    /** Folders and files directly inside a folder, or the root of My Drive / a project space. */
    public function contents(User $user, ?Folder $folder = null, ?Project $project = null): array
    {
        $folders = Folder::live()->with('owner');
        $files = File::live()->with('owner', 'editor');

        if ($folder) {
            $folders->where('parent_id', $folder->id);
            $files->where('folder_id', $folder->id);
        } elseif ($project) {
            $folders->where('project_id', $project->id)->whereNull('parent_id');
            $files->where('project_id', $project->id)->whereNull('folder_id');
        } else {
            $folders->where('owner_id', $user->id)->whereNull('project_id')->whereNull('parent_id');
            $files->where('owner_id', $user->id)->whereNull('project_id')->whereNull('folder_id');
        }

        return [$folders->orderBy('name')->get(), $files->orderBy('name')->get()];
    }

    /** Items shared with the person directly (newest share first). */
    public function sharedWithMe(User $user): array
    {
        $shares = Share::where('user_id', $user->id)->with('sharer')->latest()->get();
        $folders = Folder::live()->with('owner')->whereIn('id', $shares->where('item_type', Folder::TYPE)->pluck('item_id'))->where('owner_id', '!=', $user->id)->get();
        $files = File::live()->with('owner', 'editor')->whereIn('id', $shares->where('item_type', File::TYPE)->pluck('item_id'))->where('owner_id', '!=', $user->id)->get();
        $by = $shares->keyBy(fn ($s) => $s->item_type . ':' . $s->item_id);
        foreach ($folders as $f) $f->setAttribute('share', $by[Folder::TYPE . ':' . $f->id] ?? null);
        foreach ($files as $f) $f->setAttribute('share', $by[File::TYPE . ':' . $f->id] ?? null);

        return [$folders->filter(fn ($f) => !$this->inTrash($f))->values(), $files->filter(fn ($f) => !$this->inTrash($f))->values()];
    }

    /** Every file the person can reach, as a query (for recent, search and the API). */
    public function reachableFiles(User $user)
    {
        $projectIds = $this->projectsFor($user)->pluck('id');
        $shares = Share::where('user_id', $user->id)->get();
        $sharedFolders = $shares->where('item_type', Folder::TYPE)->pluck('item_id')->all();
        $folderIds = array_merge($sharedFolders, $this->descendantIds($sharedFolders));
        $fileIds = $shares->where('item_type', File::TYPE)->pluck('item_id');

        return File::live()->where(fn ($q) => $q->where('owner_id', $user->id)
            ->orWhereIn('project_id', $projectIds)
            ->orWhereIn('folder_id', $folderIds ?: [0])
            ->orWhereIn('id', $fileIds));
    }

    public function recent(User $user, int $limit = 40): Collection
    {
        return $this->reachableFiles($user)->with('owner', 'editor')->latest('updated_at')->limit($limit * 2)->get()
            ->reject(fn ($f) => $this->inTrash($f))->take($limit)->values();
    }

    public function search(User $user, string $term): array
    {
        $term = trim($term);
        $files = $this->reachableFiles($user)->with('owner', 'editor')->where('name', 'like', '%' . $term . '%')->latest('updated_at')->limit(100)->get()
            ->reject(fn ($f) => $this->inTrash($f))->values();

        $projectIds = $this->projectsFor($user)->pluck('id');
        $shared = Share::where('user_id', $user->id)->where('item_type', Folder::TYPE)->pluck('item_id')->all();
        $folders = Folder::live()->with('owner')->where('name', 'like', '%' . $term . '%')
            ->where(fn ($q) => $q->where('owner_id', $user->id)->orWhereIn('project_id', $projectIds)
                ->orWhereIn('id', array_merge($shared, $this->descendantIds($shared)) ?: [0]))
            ->orderBy('name')->limit(50)->get()->reject(fn ($f) => $this->inTrash($f))->values();

        return [$folders, $files];
    }

    public function starred(User $user): array
    {
        $stars = DB::table('drive_stars')->where('user_id', $user->id)->get();
        $folders = Folder::live()->with('owner')->whereIn('id', $stars->where('item_type', Folder::TYPE)->pluck('item_id'))->orderBy('name')->get();
        $files = File::live()->with('owner', 'editor')->whereIn('id', $stars->where('item_type', File::TYPE)->pluck('item_id'))->orderBy('name')->get();
        $ok = fn ($i) => !$this->inTrash($i) && $this->can($i, $user, 'viewer');

        return [$folders->filter($ok)->values(), $files->filter($ok)->values()];
    }

    public function starredKeys(User $user): array
    {
        return DB::table('drive_stars')->where('user_id', $user->id)->get()->map(fn ($s) => $s->item_type . ':' . $s->item_id)->flip()->all();
    }

    /** Things the person put in the trash, or own, or manage the project of. */
    public function trash(User $user): array
    {
        $managed = $this->projectsFor($user)->filter(fn ($p) => $this->projectLevel($p->id, $user) === 'owner')->pluck('id');
        $scope = fn ($q) => $q->whereNotNull('trashed_at')->where(fn ($w) => $w->where(fn ($m) => $m->where('owner_id', $user->id)->whereNull('project_id'))
            ->orWhere('trashed_by', $user->id)->orWhereIn('project_id', $managed));

        return [Folder::where($scope)->with('owner')->latest('trashed_at')->get(), File::where($scope)->with('owner')->latest('trashed_at')->get()];
    }

    /** Files sent in the person's chats (newest first). */
    public function chatFiles(User $user, int $limit = 100): Collection
    {
        $conversationIds = Participant::where('user_id', $user->id)->whereNull('left_at')->pluck('conversation_id');

        return Message::with('user', 'conversation')->whereIn('conversation_id', $conversationIds)
            ->whereNotNull('attachment_path')->whereNull('deleted_at')->latest('id')->limit($limit)->get();
    }

    /** Bytes the person's own files and their earlier versions take up. */
    public function usage(User $user): int
    {
        return (int) File::where('owner_id', $user->id)->sum('size')
            + (int) Version::whereIn('file_id', File::where('owner_id', $user->id)->select('id'))->sum('size');
    }

    public function quota(): int
    {
        return max(1, (int) config('qudroid.drive.quota_mb', 5120)) * 1024 * 1024;
    }

    /** People an item can be shared with: active accounts that can use the workspace. */
    public function people(User $except)
    {
        return User::whereIn('id', PortalNotifier::userIdsWithPermission('Use connect'))->where('is_disabled', false)
            ->whereKeyNot($except->id)->orderBy('name');
    }

    // ── Folders and files ──────────────────────────────────────────────

    public function createFolder(User $by, string $name, ?Folder $parent = null, ?Project $project = null): Folder
    {
        $this->assertCanAddTo($by, $parent, $project);

        return Folder::create([
            'name' => $this->cleanName($name), 'parent_id' => $parent?->id, 'owner_id' => $by->id,
            'project_id' => $parent ? $parent->project_id : $project?->id,
        ]);
    }

    public function upload(User $by, UploadedFile $upload, ?Folder $parent = null, ?Project $project = null): File
    {
        $this->assertCanAddTo($by, $parent, $project);
        $this->assertRoom($by, (int) $upload->getSize());

        $file = File::create([
            'name' => $this->cleanName($upload->getClientOriginalName() ?: 'file'), 'folder_id' => $parent?->id, 'owner_id' => $by->id,
            'project_id' => $parent ? $parent->project_id : $project?->id,
            'path' => $this->store($upload), 'mime' => $upload->getMimeType(), 'size' => (int) $upload->getSize(), 'updated_by' => $by->id,
        ]);
        $this->notifyShared($file, $by, 'added');

        return $file;
    }

    public function newVersion(User $by, File $file, UploadedFile $upload): File
    {
        $this->assertLevel($file, $by, 'editor');
        $this->assertRoom($file->owner, (int) $upload->getSize());

        DB::transaction(function () use ($by, $file, $upload) {
            $this->keepCurrent($file);
            $file->update(['path' => $this->store($upload), 'mime' => $upload->getMimeType(), 'size' => (int) $upload->getSize(),
                'version' => $file->version + 1, 'updated_by' => $by->id]);
        });
        $this->notifyShared($file, $by, 'updated');

        return $file->refresh();
    }

    /** Bring an earlier version back as the newest one (nothing is lost). */
    public function restoreVersion(User $by, File $file, Version $version): File
    {
        $this->assertLevel($file, $by, 'editor');
        $this->check($version->file_id !== $file->id, 'version', 'That version belongs to another file.');
        $disk = Storage::disk('local');
        $this->check(!$disk->exists($version->path), 'version', 'That version\'s file is missing.');

        DB::transaction(function () use ($by, $file, $version, $disk) {
            $this->keepCurrent($file);
            $copy = 'drive/' . now()->format('Y/m') . '/' . Str::uuid() . '.' . pathinfo($version->path, PATHINFO_EXTENSION);
            $disk->copy($version->path, $copy);
            $file->update(['path' => $copy, 'mime' => $version->mime, 'size' => $version->size, 'version' => $file->version + 1, 'updated_by' => $by->id]);
        });

        return $file->refresh();
    }

    public function rename(User $by, Folder|File $item, string $name): void
    {
        $this->assertLevel($item, $by, 'editor');
        $item->update(['name' => $this->cleanName($name)]);
    }

    public function describe(User $by, File $file, ?string $description): void
    {
        $this->assertLevel($file, $by, 'editor');
        $file->update(['description' => $description ? mb_substr(trim($description), 0, 500) : null]);
    }

    /**
     * Move into a folder, or to the root of My Drive ($target and $project null) or of a project space.
     * Everything inside a moved folder follows it into the new space.
     */
    public function move(User $by, Folder|File $item, ?Folder $target = null, ?Project $project = null): void
    {
        $this->assertLevel($item, $by, 'editor');
        $this->assertCanAddTo($by, $target, $project);
        $this->check(!$target && !$project && $item->owner_id !== $by->id, 'target', 'Only the owner can move this to their own drive.');

        if ($item instanceof Folder && $target) {
            $this->check($target->id === $item->id || in_array($item->id, $this->ancestorIds($target), true), 'target', 'A folder can\'t be moved inside itself.');
        }

        $projectId = $target ? $target->project_id : $project?->id;
        DB::transaction(function () use ($item, $target, $projectId) {
            if ($item instanceof Folder) {
                $item->update(['parent_id' => $target?->id, 'project_id' => $projectId]);
                $ids = array_merge([$item->id], $this->descendantIds([$item->id]));
                Folder::whereIn('id', $ids)->update(['project_id' => $projectId]);
                File::whereIn('folder_id', $ids)->update(['project_id' => $projectId]);
            } else {
                $item->update(['folder_id' => $target?->id, 'project_id' => $projectId]);
            }
        });
    }

    public function trashItem(User $by, Folder|File $item): void
    {
        $this->assertLevel($item, $by, 'editor');
        $item->update(['trashed_at' => now(), 'trashed_by' => $by->id]);
    }

    public function restore(User $by, Folder|File $item): void
    {
        $this->check(!$item->trashed_at, 'item', 'That isn\'t in the trash.');
        $this->assertLevel($item, $by, 'editor');

        $parent = $item instanceof Folder ? $item->parent : $item->folder;
        $changes = ['trashed_at' => null, 'trashed_by' => null];
        // Its folder is gone or still in the trash: put it back at the top of its space.
        if ($parent && $this->inTrash($parent)) $changes[$item instanceof Folder ? 'parent_id' : 'folder_id'] = null;
        $item->update($changes);
    }

    /** Delete for good, with everything inside. Owners and project managers only. */
    public function destroy(User $by, Folder|File $item): void
    {
        $this->assertLevel($item, $by, 'owner');

        $paths = [];
        DB::transaction(function () use ($item, &$paths) {
            $files = $item instanceof Folder
                ? File::whereIn('folder_id', array_merge([$item->id], $this->descendantIds([$item->id])))->get()
                : collect([$item]);
            foreach ($files as $f) {
                $paths[] = $f->path;
                array_push($paths, ...Version::where('file_id', $f->id)->pluck('path')->all());
                $this->forget(File::TYPE, $f->id);
            }
            if ($item instanceof Folder) {
                foreach (array_merge([$item->id], $this->descendantIds([$item->id])) as $id) $this->forget(Folder::TYPE, $id);
                File::whereIn('id', $files->pluck('id'))->delete();
            }
            $item->delete();
        });

        Storage::disk('local')->delete(array_filter($paths));
    }

    public function emptyTrash(User $by): int
    {
        [$folders, $files] = $this->trash($by);
        $n = 0;
        foreach ($folders->concat($files) as $item) {
            if ($item->exists && $this->can($item, $by, 'owner') && ($item instanceof File ? File::whereKey($item->id)->exists() : Folder::whereKey($item->id)->exists())) {
                $this->destroy($by, $item);
                $n++;
            }
        }

        return $n;
    }

    public function toggleStar(User $user, Folder|File $item): bool
    {
        $key = ['user_id' => $user->id, 'item_type' => $item::TYPE, 'item_id' => $item->id];
        if (DB::table('drive_stars')->where($key)->delete()) return false;
        DB::table('drive_stars')->insert($key + ['created_at' => now()]);

        return true;
    }

    // ── Sharing ─────────────────────────────────────────────────────────

    public function share(User $by, Folder|File $item, User $with, string $role): Share
    {
        $this->assertLevel($item, $by, 'editor');
        $this->check(!isset(Share::ROLES[$role]), 'role', 'Choose view or edit.');
        $this->check($with->id === $item->owner_id, 'user_id', $with->name . ' already owns this.');
        $this->check($with->id === $by->id, 'user_id', 'You already have access.');
        $this->check((bool) $with->is_disabled, 'user_id', 'That account is disabled.');

        $share = Share::where(['item_type' => $item::TYPE, 'item_id' => $item->id, 'user_id' => $with->id])->first();
        $isNew = !$share;
        $share ??= new Share(['item_type' => $item::TYPE, 'item_id' => $item->id, 'user_id' => $with->id]);
        $share->fill(['role' => $role, 'shared_by' => $by->id])->save();

        if ($isNew) {
            PortalNotifier::toUsers([$with->id], $by->name . ' shared ' . ($item instanceof Folder ? 'a folder' : 'a file'),
                $item->name . ' (' . strtolower(Share::ROLES[$role]) . ')', $this->url($item), 'drive', 'drive:share:' . $item::TYPE . ':' . $item->id);
        }

        return $share;
    }

    /** Editors remove anyone; a person can always remove themselves. */
    public function unshare(User $by, Folder|File $item, int $userId): void
    {
        if ($userId !== $by->id) $this->assertLevel($item, $by, 'editor');
        Share::where(['item_type' => $item::TYPE, 'item_id' => $item->id, 'user_id' => $userId])->delete();
    }

    public function shares(Folder|File $item): Collection
    {
        return Share::with('user', 'sharer')->where('item_type', $item::TYPE)->where('item_id', $item->id)->get()->sortBy('user.name')->values();
    }

    public function link(Folder|File $item): ?Link
    {
        return Link::where('item_type', $item::TYPE)->where('item_id', $item->id)->whereNull('revoked_at')->latest('id')->get()
            ->first(fn ($l) => $l->isActive());
    }

    public function createLink(User $by, Folder|File $item, ?int $days = null): Link
    {
        $this->assertLevel($item, $by, 'editor');
        $this->revokeLinks($by, $item);

        return Link::create([
            'item_type' => $item::TYPE, 'item_id' => $item->id, 'token' => Str::random(40), 'created_by' => $by->id,
            'expires_at' => $days ? now()->addDays($days) : null,
        ]);
    }

    public function revokeLinks(User $by, Folder|File $item): void
    {
        $this->assertLevel($item, $by, 'editor');
        Link::where('item_type', $item::TYPE)->where('item_id', $item->id)->whereNull('revoked_at')->update(['revoked_at' => now()]);
    }

    /** The live item a public link opens, or null. */
    public function resolveLink(string $token): ?array
    {
        $link = Link::where('token', $token)->first();
        if (!$link || !$link->isActive()) return null;
        $item = $link->item();
        if (!$item || $this->inTrash($item)) return null;

        return [$link, $item];
    }

    /** Is the file the link's file, or somewhere inside the link's folder? */
    public function linkCovers(Folder|File $root, File $file): bool
    {
        if ($root instanceof File) return $root->id === $file->id;

        return $file->folder_id && in_array($root->id, $this->ancestorIds($file), true) && !$this->inTrash($file);
    }

    public function linkCoversFolder(Folder|File $root, Folder $folder): bool
    {
        return $root instanceof Folder && in_array($root->id, $this->ancestorIds($folder), true) && !$this->inTrash($folder);
    }

    // ── Chat ────────────────────────────────────────────────────────────

    /** Copy a file someone sent in chat into My Drive. */
    public function saveFromChat(User $by, Message $message, ?Folder $parent = null): File
    {
        $this->check(!$message->attachment_path || $message->deleted_at, 'message', 'That message has no file.');
        $this->check(!$this->connect->canView($message->conversation, $by), 'message', 'You are not in that conversation.');
        $disk = Storage::disk('local');
        $this->check(!$disk->exists($message->attachment_path), 'message', 'The file is no longer available.');
        $this->assertCanAddTo($by, $parent, null);
        $this->assertRoom($by, (int) $message->attachment_size);

        $copy = 'drive/' . now()->format('Y/m') . '/' . Str::uuid() . '.' . pathinfo($message->attachment_path, PATHINFO_EXTENSION);
        $disk->copy($message->attachment_path, $copy);

        return File::create([
            'name' => $this->cleanName($message->attachment_name ?: 'file'), 'folder_id' => $parent?->id, 'owner_id' => $by->id,
            'project_id' => $parent?->project_id, 'path' => $copy, 'mime' => $message->attachment_mime,
            'size' => (int) $message->attachment_size, 'updated_by' => $by->id,
        ]);
    }

    /** Post a file or folder into a conversation; everyone there gets view access. */
    public function sendToChat(User $by, Folder|File $item, Conversation $conversation, ?string $note = null): Message
    {
        $this->assertLevel($item, $by, 'viewer');
        $this->check(!$this->connect->participant($conversation, $by), 'conversation_id', 'You are not in that conversation.');

        $canShare = $this->can($item, $by, 'editor');
        $memberIds = Participant::where('conversation_id', $conversation->id)->whereNull('left_at')->pluck('user_id');
        $missing = User::whereIn('id', $memberIds)->get()->reject(fn ($u) => $this->can($item, $u, 'viewer'));
        $this->check(!$canShare && $missing->isNotEmpty(), 'conversation_id', 'Some people there can\'t open it, and only editors can share it with them.');
        foreach ($missing as $u) {
            if (!$u->is_disabled) $this->share($by, $item, $u, 'viewer');
        }

        $body = trim(($note ? trim($note) . "\n" : '') . ($item instanceof Folder ? '📁 ' : '📄 ') . $item->name . ' ' . $this->url($item));

        return $this->connect->send($conversation, $by, $body);
    }

    // ── Helpers ─────────────────────────────────────────────────────────

    public function url(Folder|File $item): string
    {
        return $item instanceof Folder ? route('drive.folder', $item) : route('drive.file', $item);
    }

    /** Where to go "up" from a folder or file: its folder, project space, or My Drive. */
    public function parentUrl(Folder|File $item, User $user): string
    {
        $parent = $item instanceof Folder ? $item->parent : $item->folder;
        if ($parent && $this->can($parent, $user, 'viewer')) return route('drive.folder', $parent);
        if ($item->project_id && $this->projectLevel($item->project_id, $user)) return route('drive.project', $item->project_id);

        return $item->owner_id === $user->id ? route('drive.index') : route('drive.shared');
    }

    /** Folders the person can put things into (for the move picker). */
    public function targets(User $user, Folder|File|null $moving = null): Collection
    {
        $projectIds = $this->projectsFor($user)->filter(fn ($p) => in_array($this->projectLevel($p->id, $user), ['owner', 'editor'], true))->pluck('id');
        $shared = Share::where('user_id', $user->id)->where('item_type', Folder::TYPE)->where('role', 'editor')->pluck('item_id')->all();
        $ids = array_merge($shared, $this->descendantIds($shared));
        $exclude = $moving instanceof Folder ? array_merge([$moving->id], $this->descendantIds([$moving->id])) : [];

        return Folder::live()->with('project')->where(fn ($q) => $q->where(fn ($w) => $w->where('owner_id', $user->id)->whereNull('project_id'))
                ->orWhereIn('project_id', $projectIds)->orWhereIn('id', $ids ?: [0]))
            ->whereNotIn('id', $exclude ?: [0])->get()
            ->reject(fn ($f) => $this->inTrash($f))
            ->map(function ($f) {
                $f->setAttribute('path_label', collect($f->trail())->pluck('name')->implode(' / '));
                $f->setAttribute('space_label', $f->project_id ? ($f->project?->name ?? 'Project') : ($f->owner_id === auth()->id() ? 'My Drive' : 'Shared with me'));

                return $f;
            })
            ->sortBy(fn ($f) => $f->space_label . '/' . $f->path_label)->values();
    }

    public function stream(File $file, bool $inline, ?string $path = null, ?string $mime = null)
    {
        $path ??= $file->path;
        $disk = Storage::disk('local');
        abort_unless($disk->exists($path), 404);
        $headers = ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, max-age=300'];

        if ($inline && $file->previewKind()) {
            if ($file->previewKind() === 'text') $headers['Content-Type'] = 'text/plain; charset=UTF-8';

            return $disk->response($path, $file->name, $headers);
        }

        return $disk->download($path, $file->name, $headers);
    }

    /** A zip of a folder's live files (built in a temp file, removed after sending). */
    public function zip(Folder $folder): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'drive') . '.zip';
        $zip = new \ZipArchive();
        $zip->open($tmp, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $disk = Storage::disk('local');
        $total = 0;

        $add = function (Folder $f, string $prefix) use (&$add, $zip, $disk, &$total) {
            $zip->addEmptyDir(rtrim($prefix, '/'));
            $used = [];
            foreach (File::live()->where('folder_id', $f->id)->get() as $file) {
                if (!$disk->exists($file->path)) continue;
                $total += $file->size;
                $this->check($total > 1024 * 1024 * 1024, 'folder', 'That folder is too big to download in one go (over 1 GB).');
                $name = $this->uniqueName($file->name, $used);
                $zip->addFile($disk->path($file->path), $prefix . $name);
            }
            foreach (Folder::live()->where('parent_id', $f->id)->get() as $child) {
                $add($child, $prefix . $this->uniqueName($child->name, $used) . '/');
            }
        };
        $add($folder, $this->cleanName($folder->name) . '/');
        $zip->close();

        return $tmp;
    }

    protected function uniqueName(string $name, array &$used): string
    {
        $name = str_replace(['/', '\\'], '-', $name);
        $base = pathinfo($name, PATHINFO_FILENAME);
        $ext = pathinfo($name, PATHINFO_EXTENSION);
        $try = $name;
        for ($i = 2; isset($used[strtolower($try)]); $i++) $try = $base . " ($i)" . ($ext !== '' ? '.' . $ext : '');
        $used[strtolower($try)] = true;

        return $try;
    }

    protected function keepCurrent(File $file): void
    {
        Version::create(['file_id' => $file->id, 'version' => $file->version, 'path' => $file->path, 'mime' => $file->mime,
            'size' => $file->size, 'uploaded_by' => $file->updated_by ?? $file->owner_id]);
    }

    protected function store(UploadedFile $upload): string
    {
        return $upload->store('drive/' . now()->format('Y/m'), 'local');
    }

    protected function cleanName(string $name): string
    {
        $name = trim(preg_replace('~[\x00-\x1F/\\\\]+~u', ' ', $name));
        $this->check($name === '' || $name === '.' || $name === '..', 'name', 'Give it a name.');

        return mb_substr($name, 0, 150);
    }

    protected function assertCanAddTo(User $by, ?Folder $parent, ?Project $project): void
    {
        if ($parent) {
            $this->check($this->inTrash($parent), 'folder', 'That folder is in the trash.');
            $this->assertLevel($parent, $by, 'editor');
        } elseif ($project) {
            $this->check(!in_array($this->projectLevel($project->id, $by), ['owner', 'editor'], true), 'folder', 'You can only view this project\'s files.');
        }
    }

    protected function assertLevel(Folder|File $item, User $by, string $level): void
    {
        if (!$this->can($item, $by, $level)) {
            throw ValidationException::withMessages(['item' => match ($level) {
                'owner' => 'Only the owner can do that.',
                'editor' => 'You can only view this.',
                default => 'You don\'t have access to that.',
            }]);
        }
    }

    protected function assertRoom(User $owner, int $bytes): void
    {
        $max = max(1, (int) config('qudroid.drive.max_upload_mb', 200)) * 1024 * 1024;
        $this->check($bytes > $max, 'file', 'Files can be up to ' . File::humanSize($max) . '.');
        $this->check($this->usage($owner) + $bytes > $this->quota(), 'file', 'There isn\'t enough space left in ' . ($owner->id === auth()->id() ? 'your' : $owner->name . '\'s') . ' drive. Empty the trash or delete old versions.');
    }

    /** Tell the people a folder is shared with when something is added to it. */
    protected function notifyShared(File $file, User $by, string $what): void
    {
        $folderIds = $this->ancestorIds($file);
        $ids = Share::where(fn ($q) => $q->where(fn ($w) => $w->where('item_type', Folder::TYPE)->whereIn('item_id', $folderIds ?: [0]))
                ->orWhere(fn ($w) => $w->where('item_type', File::TYPE)->where('item_id', $file->id)))
            ->pluck('user_id')->push($file->owner_id)
            ->merge(Folder::whereIn('id', $folderIds ?: [0])->pluck('owner_id'))->unique()->reject(fn ($id) => $id === $by->id)->values()->all();
        if (!$ids) return;

        PortalNotifier::toUsers($ids, $by->name . ' ' . $what . ' a file', $file->name, route('drive.file', $file), 'drive',
            'drive:' . $what . ':' . $file->id . ':' . $file->version);
    }

    protected function forget(string $type, int $id): void
    {
        Share::where('item_type', $type)->where('item_id', $id)->delete();
        Link::where('item_type', $type)->where('item_id', $id)->delete();
        DB::table('drive_stars')->where('item_type', $type)->where('item_id', $id)->delete();
    }

    protected function check(bool $failed, string $field, string $message): void
    {
        if ($failed) throw ValidationException::withMessages([$field => $message]);
    }
}
