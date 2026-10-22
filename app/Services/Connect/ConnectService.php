<?php

namespace App\Services\Connect;

use App\Models\Connect\Conversation;
use App\Models\Connect\Message;
use App\Models\Connect\Participant;
use App\Models\Connect\Reaction;
use App\Models\Projects\Project;
use App\Models\User;
use App\Services\Messaging\PortalNotifier;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Qudroid Connect.
 *
 *  - Direct chats: exactly one per pair of people.
 *  - Groups: private; admins add and remove people.
 *  - Channels: open to everyone with Use connect; anyone can browse and
 *    join, members post. Staff create channels.
 *
 * Direct and group messages notify the other people (bell and phone
 * push), once until they have read the conversation again. Channels do
 * not notify. Unread counts come from each person's last read message.
 */
class ConnectService
{
    // ── Access ──────────────────────────────────────────────────────────

    public function participant(Conversation $c, User $user): ?Participant
    {
        return Participant::where('conversation_id', $c->id)->where('user_id', $user->id)->whereNull('left_at')->first();
    }

    public function canView(Conversation $c, User $user): bool
    {
        return $c->type === 'channel' || $this->participant($c, $user) !== null;
    }

    public function isAdmin(Conversation $c, User $user): bool
    {
        return $this->participant($c, $user)?->role === 'admin';
    }

    /** People who can be messaged: active accounts with Use connect. */
    public function people(?string $search = null, ?User $except = null)
    {
        $ids = PortalNotifier::userIdsWithPermission('Use connect');

        return User::whereIn('id', $ids)->where('is_disabled', false)
            ->when($except, fn ($q) => $q->whereKeyNot($except->id))
            ->when($search, fn ($q, $s) => $q->where(fn ($w) => $w->where('name', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%")))
            ->orderBy('name');
    }

    // ── Lists ───────────────────────────────────────────────────────────

    /** The person's conversations, newest activity first, each with `unread`. */
    public function inbox(User $user): Collection
    {
        return Conversation::query()
            ->join('connect_participants as p', fn ($j) => $j->on('p.conversation_id', '=', 'connect_conversations.id')->where('p.user_id', $user->id)->whereNull('p.left_at'))
            ->select('connect_conversations.*', 'p.muted', 'p.last_read_message_id')
            ->selectSub(fn ($q) => $q->from('connect_messages as m')->selectRaw('count(*)')
                ->whereColumn('m.conversation_id', 'connect_conversations.id')
                ->whereColumn('m.id', '>', 'p.last_read_message_id')
                ->where('m.is_system', false)->whereNull('m.deleted_at')->whereNull('m.thread_id')
                ->where(fn ($w) => $w->whereNull('m.user_id')->orWhere('m.user_id', '!=', $user->id)), 'unread')
            ->whereNull('connect_conversations.archived_at')
            ->with(['lastMessage.user', 'members.user'])
            ->orderByRaw('coalesce(connect_conversations.last_message_at, connect_conversations.created_at) desc')
            ->get();
    }

    public function unreadTotal(User $user): int
    {
        return (int) DB::table('connect_participants as p')
            ->join('connect_messages as m', 'm.conversation_id', '=', 'p.conversation_id')
            ->where('p.user_id', $user->id)->whereNull('p.left_at')->where('p.muted', false)
            ->whereColumn('m.id', '>', 'p.last_read_message_id')
            ->where('m.is_system', false)->whereNull('m.deleted_at')->whereNull('m.thread_id')
            ->where(fn ($w) => $w->whereNull('m.user_id')->orWhere('m.user_id', '!=', $user->id))
            ->count();
    }

    /**
     * The main timeline (thread replies left out), oldest first: the latest page, those after/before
     * an id, or a page around one message (for jumping to a search result).
     */
    public function messages(Conversation $c, ?int $after = null, ?int $before = null, int $limit = 50, ?int $around = null): Collection
    {
        $q = fn () => Message::with(['user', 'replyTo.user', 'reactions'])->where('conversation_id', $c->id)->whereNull('thread_id');
        if ($after) {
            return $q()->where('id', '>', $after)->orderBy('id')->limit(200)->get();
        }
        if ($around) {
            $older = $q()->where('id', '<=', $around)->orderByDesc('id')->limit(25)->get();
            $newer = $q()->where('id', '>', $around)->orderBy('id')->limit(25)->get();

            return $older->reverse()->values()->concat($newer)->values();
        }

        return $q()->when($before, fn ($w) => $w->where('id', '<', $before))->orderByDesc('id')->limit($limit)->get()->reverse()->values();
    }

    /** A thread's replies, oldest first (optionally only those after an id). */
    public function threadReplies(Message $root, ?int $after = null): Collection
    {
        return Message::with(['user', 'replyTo.user', 'reactions'])->where('thread_id', $root->id)
            ->when($after, fn ($q) => $q->where('id', '>', $after))->orderBy('id')->limit(500)->get();
    }

    public function pinned(Conversation $c): Collection
    {
        return Message::with('user', 'pinner')->where('conversation_id', $c->id)->whereNotNull('pinned_at')->whereNull('deleted_at')
            ->latest('pinned_at')->limit(25)->get();
    }

    /** Messages the person saved, newest first, from conversations they can still see. */
    public function saved(User $user): Collection
    {
        $ids = DB::table('connect_saved')->where('user_id', $user->id)->orderByDesc('id')->limit(200)->pluck('message_id');

        return Message::with('user', 'conversation')->whereIn('id', $ids)->whereNull('deleted_at')->get()
            ->filter(fn ($m) => $this->canView($m->conversation, $user))
            ->sortByDesc(fn ($m) => $ids->search($m->id) === false ? 0 : -$ids->search($m->id))->values();
    }

    /** @return array<int, true> */
    public function savedIds(User $user, iterable $messageIds): array
    {
        $ids = collect($messageIds)->all();
        if (!$ids) return [];

        return DB::table('connect_saved')->where('user_id', $user->id)->whereIn('message_id', $ids)->pluck('message_id')->flip()->map(fn () => true)->all();
    }

    /** Search the person's conversations (and channels they are in) by text or file name. */
    public function search(User $user, string $term, int $limit = 60): Collection
    {
        $term = trim($term);
        if (mb_strlen($term) < 2) return new Collection();
        $ids = Participant::where('user_id', $user->id)->whereNull('left_at')->pluck('conversation_id');
        $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $term) . '%';

        return Message::with('user', 'conversation')->whereIn('conversation_id', $ids)->whereNull('deleted_at')->where('is_system', false)
            ->where(fn ($q) => $q->where('body', 'like', $like)->orWhere('attachment_name', 'like', $like))
            ->latest('id')->limit($limit)->get();
    }

    // ── Presence and typing ────────────────────────────────────────────

    /** Seen in the last two minutes (any page or app request updates last_seen_at). */
    public static function online(?User $user): bool
    {
        return $user && $user->last_seen_at && $user->last_seen_at->gt(now()->subMinutes(2));
    }

    public function typing(Conversation $c, User $me): void
    {
        if (!$this->participant($c, $me)) return;
        $key = 'cx:typing:' . $c->id;
        $now = now()->timestamp;
        $list = array_filter((array) Cache::get($key, []), fn ($t) => ($t['at'] ?? 0) > $now - 6);
        $list[$me->id] = ['name' => $me->first_name ?: $me->name, 'at' => $now];
        Cache::put($key, $list, 15);
    }

    /** First names of the others typing in the conversation right now. */
    public function typingNames(Conversation $c, User $me): array
    {
        $now = now()->timestamp;

        return collect((array) Cache::get('cx:typing:' . $c->id, []))
            ->filter(fn ($t, $id) => (int) $id !== $me->id && ($t['at'] ?? 0) > $now - 6)
            ->pluck('name')->values()->all();
    }

    public function stopTyping(Conversation $c, User $me): void
    {
        $key = 'cx:typing:' . $c->id;
        $list = (array) Cache::get($key, []);
        if (isset($list[$me->id])) {
            unset($list[$me->id]);
            Cache::put($key, $list, 15);
        }
    }

    /** How far the other person in a direct chat has read. */
    public function seenUpTo(Conversation $c, User $me): int
    {
        if (!$c->isDirect()) return 0;

        return (int) Participant::where('conversation_id', $c->id)->where('user_id', '!=', $me->id)->value('last_read_message_id');
    }

    // ── Starting conversations ──────────────────────────────────────────

    public function direct(User $me, User $other): Conversation
    {
        $this->check($me->id === $other->id, 'user', 'Pick someone else to message.');
        $this->check(!$other->can('Use connect') || $other->is_disabled, 'user', 'That person cannot use Connect.');

        return DB::transaction(function () use ($me, $other) {
            $c = Conversation::firstOrCreate(['direct_key' => Conversation::directKey($me->id, $other->id)], ['type' => 'direct', 'created_by' => $me->id]);
            foreach ([$me, $other] as $u) {
                Participant::updateOrCreate(['conversation_id' => $c->id, 'user_id' => $u->id], ['left_at' => null]);
            }

            return $c;
        });
    }

    /** @param int[] $userIds */
    public function group(User $me, string $name, array $userIds): Conversation
    {
        $name = trim($name);
        $this->check($name === '', 'name', 'Give the group a name.');
        $ids = $this->people()->whereIn('id', $userIds)->whereKeyNot($me->id)->pluck('id');
        $this->check($ids->isEmpty(), 'users', 'Add at least one other person.');

        return DB::transaction(function () use ($me, $name, $ids) {
            $c = Conversation::create(['type' => 'group', 'name' => mb_substr($name, 0, 100), 'created_by' => $me->id]);
            Participant::create(['conversation_id' => $c->id, 'user_id' => $me->id, 'role' => 'admin']);
            foreach ($ids as $id) {
                Participant::create(['conversation_id' => $c->id, 'user_id' => $id]);
            }
            $this->system($c, $me->name . ' created the group');

            return $c;
        });
    }

    public function channel(User $me, string $name, ?string $description): Conversation
    {
        $this->check(!$me->isStaff(), 'name', 'Only staff can create channels.');
        $name = strtolower(trim(preg_replace('/[^A-Za-z0-9\-_ ]/', '', $name)));
        $name = preg_replace('/\s+/', '-', $name);
        $this->check($name === '', 'name', 'Use letters, numbers and dashes.');
        $this->check(Conversation::where('type', 'channel')->where('name', $name)->exists(), 'name', 'There is already a #' . $name . ' channel.');

        return DB::transaction(function () use ($me, $name, $description) {
            $c = Conversation::create(['type' => 'channel', 'name' => mb_substr($name, 0, 100), 'description' => $description ? mb_substr(trim($description), 0, 255) : null, 'created_by' => $me->id]);
            Participant::create(['conversation_id' => $c->id, 'user_id' => $me->id, 'role' => 'admin']);
            $this->system($c, $me->name . ' created #' . $name);

            return $c;
        });
    }

    /** The project's team chat: a group of its managers and members (not clients), kept in step. */
    public function forProject(Project $project, User $me): Conversation
    {
        return DB::transaction(function () use ($project, $me) {
            $c = Conversation::firstOrCreate(['project_id' => $project->id, 'type' => 'group'], ['name' => $project->name, 'created_by' => $me->id]);
            $team = $project->members()->whereIn('role', ['manager', 'member', 'viewer'])->get();
            foreach ($team as $m) {
                if (!$m->user?->can('Use connect')) continue;
                $p = Participant::firstOrNew(['conversation_id' => $c->id, 'user_id' => $m->user_id]);
                $p->role = $m->role === 'manager' ? 'admin' : 'member';
                $p->left_at = null;
                $p->save();
            }
            Participant::where('conversation_id', $c->id)->whereNotIn('user_id', $team->pluck('user_id'))->whereNull('left_at')->update(['left_at' => now()]);

            return $c;
        });
    }

    // ── Membership ──────────────────────────────────────────────────────

    public function join(Conversation $c, User $me): void
    {
        $this->check($c->type !== 'channel', 'conversation', 'Only channels are open to join.');
        $p = Participant::firstOrNew(['conversation_id' => $c->id, 'user_id' => $me->id]);
        if ($p->exists && !$p->left_at) return;
        $p->fill(['left_at' => null, 'last_read_message_id' => (int) $c->last_message_id])->save();
        $this->system($c, $me->name . ' joined');
    }

    public function leave(Conversation $c, User $me): void
    {
        $this->check($c->isDirect(), 'conversation', 'You cannot leave a direct chat.');
        $this->check($c->isManaged(), 'conversation', $c->meeting_id ? 'Meeting chats follow the invite list.' : 'Project chats follow the project team.');
        $p = $this->participant($c, $me);
        if (!$p) return;
        DB::transaction(function () use ($c, $me, $p) {
            $p->update(['left_at' => now(), 'role' => 'member']);
            if (!$c->members()->where('role', 'admin')->exists()) {
                $c->members()->orderBy('id')->first()?->update(['role' => 'admin']);
            }
            $this->system($c, $me->name . ' left');
        });
    }

    /** @param int[] $userIds */
    public function add(Conversation $c, User $by, array $userIds): int
    {
        $this->check($c->isDirect() || $c->isManaged(), 'users', 'People cannot be added here.');
        $this->check($c->type === 'group' && !$this->isAdmin($c, $by), 'users', 'Only group admins add people.');
        $this->check($c->type === 'channel' && !$this->participant($c, $by), 'users', 'Join the channel first.');

        $users = $this->people()->whereIn('id', $userIds)->get();
        $added = 0;
        foreach ($users as $u) {
            $p = Participant::firstOrNew(['conversation_id' => $c->id, 'user_id' => $u->id]);
            if ($p->exists && !$p->left_at) continue;
            $p->fill(['left_at' => null, 'role' => 'member'])->save();
            $added++;
        }
        if ($added) $this->system($c, $by->name . ' added ' . $users->pluck('name')->implode(', '));

        return $added;
    }

    public function remove(Conversation $c, User $by, User $user): void
    {
        $this->check($c->isDirect() || $c->isManaged(), 'users', 'People cannot be removed here.');
        $this->check(!$this->isAdmin($c, $by), 'users', 'Only admins remove people.');
        $this->check($by->id === $user->id, 'users', 'Use Leave instead.');
        $this->participant($c, $user)?->update(['left_at' => now(), 'role' => 'member']);
        $this->system($c, $by->name . ' removed ' . $user->name);
    }

    public function mute(Conversation $c, User $me, bool $muted): void
    {
        $this->participant($c, $me)?->update(['muted' => $muted]);
    }

    // ── Messages ────────────────────────────────────────────────────────

    public function send(Conversation $c, User $me, ?string $body, ?UploadedFile $file = null, ?int $replyTo = null, ?int $threadId = null): Message
    {
        $p = $this->participant($c, $me);
        $this->check(!$p, 'body', $c->type === 'channel' ? 'Join the channel to post.' : 'You are not in this conversation.');
        $this->check($c->archived_at !== null, 'body', 'This conversation is archived.');
        $body = trim((string) $body);
        $this->check($body === '' && !$file, 'body', 'Write a message or attach a file.');
        $this->check(mb_strlen($body) > 5000, 'body', 'That message is too long.');
        $this->check($replyTo && !Message::where('conversation_id', $c->id)->whereKey($replyTo)->exists(), 'reply_to_id', 'That message is not in this conversation.');

        $root = $threadId ? Message::where('conversation_id', $c->id)->whereNull('thread_id')->where('is_system', false)->find($threadId) : null;
        $this->check($threadId && !$root, 'thread_id', 'That thread is not in this conversation.');

        $message = DB::transaction(function () use ($c, $me, $p, $body, $file, $replyTo, $root) {
            $m = Message::create([
                'conversation_id' => $c->id, 'user_id' => $me->id, 'body' => $body !== '' ? $body : null, 'reply_to_id' => $replyTo ?: null,
                'thread_id' => $root?->id, 'mentions' => $this->parseMentions($c, $body, $me),
                'attachment_path' => $file?->store('connect/' . $c->id, 'local'),
                'attachment_name' => $file ? mb_substr($file->getClientOriginalName(), 0, 255) : null,
                'attachment_mime' => $file?->getMimeType(),
                'attachment_size' => $file?->getSize(),
            ]);
            if ($root) {
                // Thread replies stay out of the main timeline; the first message shows the count.
                $root->forceFill(['reply_count' => $root->reply_count + 1, 'last_reply_at' => $m->created_at, 'updated_at' => now()])->save();
            } else {
                $c->update(['last_message_id' => $m->id, 'last_message_at' => $m->created_at]);
                $p->update(['last_read_message_id' => $m->id]);
            }

            return $m;
        });

        $this->stopTyping($c, $me);
        $this->notify($c, $me, $message, $root);

        return $message;
    }

    /**
     * Who a message mentions: people in the conversation named as "@Full Name" (or "@First" when only
     * one person there has that first name), or "all" for @all / @everyone / @channel.
     */
    public function parseMentions(Conversation $c, string $body, User $me): ?array
    {
        if (!str_contains($body, '@')) return null;
        $ids = [];
        if (!$c->isDirect() && preg_match('/(^|\W)@(all|everyone|channel|here)\b/i', $body)) $ids[] = 'all';

        $members = User::whereIn('id', Participant::where('conversation_id', $c->id)->whereNull('left_at')->pluck('user_id'))->get(['id', 'name']);
        $firsts = $members->groupBy(fn ($u) => mb_strtolower((string) $u->first_name));
        foreach ($members as $u) {
            if ($u->id === $me->id) continue;
            $full = preg_quote((string) $u->name, '/');
            $first = preg_quote((string) $u->first_name, '/');
            if (preg_match('/(^|\W)@' . $full . '(?!\w)/iu', $body)
                || ($u->first_name && ($firsts[mb_strtolower((string) $u->first_name)] ?? collect())->count() === 1 && preg_match('/(^|\W)@' . $first . '(?!\w)/iu', $body))) {
                $ids[] = $u->id;
            }
        }

        return $ids ?: null;
    }

    // ── Pins and saved messages ────────────────────────────────────────

    public function togglePin(Message $m, User $me): bool
    {
        $this->check(!$this->participant($m->conversation, $me), 'message', 'Join the conversation first.');
        $this->check($m->isDeleted() || $m->is_system, 'message', 'You cannot pin this.');
        $on = !$m->pinned_at;
        $m->forceFill(['pinned_at' => $on ? now() : null, 'pinned_by' => $on ? $me->id : null, 'updated_at' => now()])->save();

        return $on;
    }

    public function toggleSave(Message $m, User $me): bool
    {
        $this->check(!$this->canView($m->conversation, $me), 'message', 'You cannot see that message.');
        $key = ['user_id' => $me->id, 'message_id' => $m->id];
        if (DB::table('connect_saved')->where($key)->delete()) return false;
        DB::table('connect_saved')->insert($key + ['created_at' => now()]);

        return true;
    }

    public function edit(Message $m, User $me, string $body): Message
    {
        $this->check($m->user_id !== $me->id || $m->is_system, 'body', 'You can only edit your own messages.');
        $this->check($m->isDeleted(), 'body', 'This message was deleted.');
        $body = trim($body);
        $this->check($body === '' && !$m->attachment_path, 'body', 'Write something, or delete the message.');
        $m->update(['body' => $body ?: null, 'mentions' => $this->parseMentions($m->conversation, $body, $me), 'edited_at' => now()]);

        return $m;
    }

    public function delete(Message $m, User $me): Message
    {
        $mine = $m->user_id === $me->id;
        $this->check(!$mine && !$this->isAdmin($m->conversation, $me), 'message', 'You can only delete your own messages.');
        $this->check($m->is_system, 'message', 'System messages stay.');
        if ($m->attachment_path) Storage::disk('local')->delete($m->attachment_path);
        $m->update(['body' => null, 'attachment_path' => null, 'attachment_name' => null, 'attachment_mime' => null, 'attachment_size' => null, 'deleted_at' => now()]);
        $m->reactions()->delete();

        return $m;
    }

    public function react(Message $m, User $me, string $emoji): void
    {
        $this->check(!in_array($emoji, Reaction::ALLOWED, true), 'emoji', 'Pick one of the reactions offered.');
        $this->check($m->isDeleted() || $m->is_system, 'emoji', 'You cannot react to this.');
        $this->check(!$this->participant($m->conversation, $me), 'emoji', 'Join the conversation first.');
        $existing = Reaction::where(['message_id' => $m->id, 'user_id' => $me->id, 'emoji' => $emoji])->first();
        $existing ? $existing->delete() : Reaction::create(['message_id' => $m->id, 'user_id' => $me->id, 'emoji' => $emoji]);
        $m->touch(); // so open chats pick up the change
    }

    public function markRead(Conversation $c, User $me, ?int $upTo = null): void
    {
        $upTo ??= (int) $c->last_message_id;
        Participant::where('conversation_id', $c->id)->where('user_id', $me->id)->where('last_read_message_id', '<', $upTo)->update(['last_read_message_id' => $upTo]);
        // Clear the bell for this conversation too.
        if (PortalNotifier::available()) {
            $me->unreadNotifications()->where('data', 'like', '%"key":"connect:' . $c->id . ':%')->update(['read_at' => now()]);
        }
    }

    // ── Helpers ─────────────────────────────────────────────────────────

    protected function notify(Conversation $c, User $me, Message $m, ?Message $root = null): void
    {
        $where = $c->isDirect() ? '' : ' in ' . $c->titleFor($me);
        $told = [$me->id];

        // @mentions reach people even in channels and muted chats.
        $mentions = $m->mentions ?? [];
        if ($mentions) {
            $ids = in_array('all', $mentions, true)
                ? Participant::where('conversation_id', $c->id)->whereNull('left_at')->pluck('user_id')->all()
                : array_values(array_filter($mentions, 'is_int'));
            $ids = array_values(array_diff($ids, $told));
            PortalNotifier::toUsers($ids, $me->name . ' mentioned you' . $where, $m->preview(140), route('connect.show', [$c, 'around' => $root?->id ?? $m->id, 'thread' => $root?->id]), 'message', 'connect-mention:' . $m->id);
            $told = array_merge($told, $ids);
        }

        if ($root) {
            // A thread reply tells the thread's starter and the people who replied before.
            $ids = Message::where('thread_id', $root->id)->pluck('user_id')->push($root->user_id)->filter()->unique()->diff($told)->values()->all();
            $muted = Participant::where('conversation_id', $c->id)->whereIn('user_id', $ids)->where('muted', true)->pluck('user_id')->all();
            PortalNotifier::toUsers(array_values(array_diff($ids, $muted)), $me->name . ' replied in a thread' . $where, $m->preview(140),
                route('connect.show', [$c, 'around' => $root->id, 'thread' => $root->id]), 'message', 'connect-thread:' . $root->id . ':' . $root->reply_count);

            return;
        }

        if ($c->type === 'channel') return;

        $title = $c->isDirect() ? $me->name : $me->name . ' in ' . $c->name;
        $others = Participant::where('conversation_id', $c->id)->whereNull('left_at')->where('muted', false)->whereNotIn('user_id', $told)->get();
        foreach ($others as $p) {
            // One notification until they read the conversation again.
            PortalNotifier::toUsers([$p->user_id], $title, $m->preview(140), route('connect.show', $c), 'message', 'connect:' . $c->id . ':' . $p->last_read_message_id);
        }
    }

    protected function system(Conversation $c, string $text): Message
    {
        $m = Message::create(['conversation_id' => $c->id, 'body' => $text, 'is_system' => true]);
        $c->update(['last_message_id' => $m->id, 'last_message_at' => $m->created_at]);

        return $m;
    }

    protected function check(bool $failed, string $field, string $message): void
    {
        if ($failed) throw ValidationException::withMessages([$field => $message]);
    }
}
