<?php

namespace App\Http\Controllers\Api\V1\Connect;

use App\Http\Controllers\Api\V1\ApiController;
use App\Models\Connect\Conversation;
use App\Models\Connect\Message;
use App\Models\User;
use App\Services\Connect\ConnectService;
use App\Services\Connect\GifSearch;
use App\Services\Presence\Presence;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Qudroid Connect for the mobile app. In the foreground the app calls
 * `poll?since=` every few seconds (new messages, typing and presence for
 * all its chats in one request); in the background it relies on push.
 */
class ConnectApiController extends ApiController
{
    public function __construct(protected ConnectService $connect) {}

    public function conversations(Request $request)
    {
        $me = $request->user();

        return $this->ok($this->connect->inbox($me)->map(fn ($c) => $this->conversation($c, $me))->values(), null, 200, ['unread' => $this->connect->unreadTotal($me)]);
    }

    public function people(Request $request)
    {
        $page = $this->connect->people(trim((string) $request->query('q')) ?: null, $request->user())->paginate(30);

        return $this->paged($page, fn ($u) => ['id' => $u->id, 'name' => $u->name, 'type' => $u->user_type, 'avatar' => $u->avatar_url]);
    }

    public function direct(Request $request)
    {
        $data = $request->validate(['user_id' => ['required', 'integer', 'exists:users,id']]);
        $c = $this->connect->direct($request->user(), User::findOrFail($data['user_id']));

        return $this->ok($this->conversation($c->fresh(), $request->user()));
    }

    /** ?after=ID for new messages, ?before=ID for older ones, neither for the latest page. */
    public function messages(Request $request, Conversation $conversation)
    {
        $me = $request->user();
        if (!$this->connect->canView($conversation, $me)) return $this->fail('Conversation not found.', 404, 'not_found');

        $items = $this->connect->messages($conversation, $request->integer('after') ?: null, $request->integer('before') ?: null, 50);
        if ($items->isNotEmpty() && $this->connect->participant($conversation, $me)) {
            $this->connect->markRead($conversation, $me, (int) $items->max('id'));
        }

        $saved = $this->connect->savedIds($me, $items->pluck('id'));

        return $this->ok($items->map(fn ($m) => $this->message($m, $me, $saved))->values(), null, 200, ['joined' => (bool) $this->connect->participant($conversation, $me)]);
    }

    /**
     * Send a message. Text with `body`; a reply in a thread with `parent_id`; files as `attachment`
     * or `files[]` (one message per file, the text goes with the first); a poll with `kind=poll`,
     * the question in `body` and `meta[options][]` (`meta[multiple]` for pick-any); a GIF with
     * `kind=gif` and `meta[url]` from `gifs`. Returns the message (the first, for several files).
     */
    public function send(Request $request, Conversation $conversation)
    {
        $data = $request->validate([
            'body' => ['nullable', 'string', 'max:5000'], 'reply_to_id' => ['nullable', 'integer'], 'parent_id' => ['nullable', 'integer'],
            'attachment' => ['nullable', 'file', 'max:25600'], 'files' => ['nullable', 'array', 'max:10'], 'files.*' => ['file', 'max:25600'],
            'kind' => ['nullable', 'in:text,poll,gif'],
            'meta' => ['nullable', 'array'], 'meta.options' => ['nullable', 'array', 'max:10'], 'meta.options.*' => ['nullable', 'string', 'max:120'],
            'meta.multiple' => ['nullable', 'boolean'], 'meta.url' => ['nullable', 'string', 'max:500'], 'meta.width' => ['nullable', 'integer', 'min:0', 'max:4000'],
            'meta.height' => ['nullable', 'integer', 'min:0', 'max:4000'], 'meta.title' => ['nullable', 'string', 'max:255'],
        ]);
        $me = $request->user();
        $thread = $data['parent_id'] ?? null;
        $meta = $data['meta'] ?? [];

        $m = match ($data['kind'] ?? 'text') {
            'poll' => $this->connect->sendPoll($conversation, $me, (string) ($data['body'] ?? ''), $meta['options'] ?? [], (bool) ($meta['multiple'] ?? false), $thread),
            'gif' => $this->connect->sendGif($conversation, $me, ['url' => (string) ($meta['url'] ?? ''), 'width' => $meta['width'] ?? null, 'height' => $meta['height'] ?? null, 'title' => $meta['title'] ?? null],
                null, $data['reply_to_id'] ?? null, $thread),
            default => null,
        };
        if (!$m) {
            $files = array_values(array_filter(array_merge([$request->file('attachment')], (array) $request->file('files', []))));
            $first = null;
            foreach ($files ?: [null] as $i => $file) {
                $sent = $this->connect->send($conversation, $me, $i === 0 ? ($data['body'] ?? null) : null, $file, $i === 0 ? ($data['reply_to_id'] ?? null) : null, $thread);
                $first ??= $sent;
            }
            $m = $first;
        }

        return $this->ok($this->message($m->fresh(['user', 'replyTo.user', 'reactions']), $me), 'Sent.', 201);
    }

    /** A thread: its first message and the replies (`?after=` for new ones). */
    public function thread(Request $request, Conversation $conversation, Message $message)
    {
        $me = $request->user();
        if ($message->conversation_id !== $conversation->id || $message->thread_id || !$this->connect->canView($conversation, $me)) return $this->fail('Thread not found.', 404, 'not_found');
        $replies = $this->connect->threadReplies($message, $request->integer('after') ?: null);
        $saved = $this->connect->savedIds($me, $replies->pluck('id')->push($message->id));

        return $this->ok([
            'root' => $this->message($message->load('user', 'replyTo.user', 'reactions'), $me, $saved),
            'replies' => $replies->map(fn ($m) => $this->message($m, $me, $saved))->values(),
        ]);
    }

    public function edit(Request $request, Message $message)
    {
        $data = $request->validate(['body' => ['nullable', 'string', 'max:5000']]);
        $this->connect->edit($message, $request->user(), (string) ($data['body'] ?? ''));

        return $this->ok($this->message($message->fresh(['user', 'replyTo.user', 'reactions']), $request->user()), 'Saved.');
    }

    public function vote(Request $request, Message $message)
    {
        $data = $request->validate(['options' => ['present', 'array', 'max:10'], 'options.*' => ['integer', 'min:0', 'max:9']]);
        $this->connect->vote($message, $request->user(), $data['options']);

        return $this->ok($this->message($message->fresh(['user', 'replyTo.user', 'reactions']), $request->user()));
    }

    public function closePoll(Request $request, Message $message)
    {
        $this->connect->closePoll($message, $request->user());

        return $this->ok($this->message($message->fresh(['user', 'replyTo.user', 'reactions']), $request->user()), 'Poll closed.');
    }

    public function gifs(Request $request, GifSearch $gifs)
    {
        return $this->ok($gifs->search((string) $request->query('q', '')), null, 200, ['enabled' => $gifs->enabled()]);
    }

    public function pin(Request $request, Message $message)
    {
        return $this->ok(['pinned' => $this->connect->togglePin($message, $request->user())]);
    }

    public function save(Request $request, Message $message)
    {
        return $this->ok(['saved' => $this->connect->toggleSave($message, $request->user())]);
    }

    public function saved(Request $request)
    {
        $me = $request->user();
        $items = $this->connect->saved($me);

        return $this->ok($items->map(fn ($m) => $this->message($m, $me, $items->pluck('id')->all()) + ['conversation' => ['id' => $m->conversation_id, 'title' => $m->conversation?->titleFor($me)]])->values());
    }

    public function search(Request $request)
    {
        $me = $request->user();
        $found = $this->connect->search($me, trim((string) $request->query('q')));
        $saved = $this->connect->savedIds($me, $found->pluck('id'));

        return $this->ok($found
            ->map(fn ($m) => $this->message($m->loadMissing('reactions'), $me, $saved) + ['conversation' => ['id' => $m->conversation_id, 'title' => $m->conversation?->titleFor($me)]])->values());
    }

    /** Start a group (`kind=group`, `member_ids[]`) or a channel (`kind=channel`, staff only). */
    public function create(Request $request)
    {
        $data = $request->validate(['kind' => ['required', 'in:group,channel'], 'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'], 'member_ids' => ['nullable', 'array', 'max:200'], 'member_ids.*' => ['integer']]);
        $me = $request->user();
        $c = $data['kind'] === 'channel'
            ? $this->connect->channel($me, $data['name'], $data['description'] ?? null)
            : $this->connect->group($me, $data['name'], $data['member_ids'] ?? []);

        return $this->ok($this->conversation($c->fresh(), $me), 'Created.', 201);
    }

    public function members(Request $request, Conversation $conversation)
    {
        $me = $request->user();
        if (!$this->connect->canView($conversation, $me)) return $this->fail('Conversation not found.', 404, 'not_found');

        return $this->ok($conversation->members()->with('user')->get()->filter(fn ($p) => $p->user)->map(fn ($p) => [
            'id' => $p->user_id, 'name' => $p->user->name, 'avatar' => $p->user->avatar_url, 'admin' => $p->role === 'admin',
            'client' => !$p->user->can('Use connect'),
        ])->sortBy('name')->values());
    }

    public function addMembers(Request $request, Conversation $conversation)
    {
        $data = $request->validate(['user_ids' => ['required', 'array', 'min:1', 'max:200'], 'user_ids.*' => ['integer']]);
        $n = $this->connect->add($conversation, $request->user(), $data['user_ids']);

        return $this->ok(['added' => $n], $n ? "Added {$n}." : 'They are already here.');
    }

    public function removeMember(Request $request, Conversation $conversation, User $user)
    {
        $user->id === $request->user()->id
            ? $this->connect->leave($conversation, $user)
            : $this->connect->remove($conversation, $request->user(), $user);

        return $this->ok(null, $user->id === $request->user()->id ? 'You left.' : 'Removed.');
    }

    /** Toggle, or set with `muted=true|false`. */
    public function mute(Request $request, Conversation $conversation)
    {
        $p = $this->connect->participant($conversation, $request->user());
        if (!$p) return $this->fail('Join the conversation first.', 422, 'not_member');
        $muted = $request->has('muted') ? $request->boolean('muted') : !$p->muted;
        $this->connect->mute($conversation, $request->user(), $muted);

        return $this->ok(['muted' => $muted]);
    }

    public function typing(Request $request, Conversation $conversation)
    {
        $request->boolean('stop') ? $this->connect->stopTyping($conversation, $request->user()) : $this->connect->typing($conversation, $request->user());

        return $this->ok(null);
    }

    /**
     * Everything new across the person's chats since `since` (the `cursor` from the last call;
     * leave it out the first time): new messages, messages edited or reacted to in the last
     * `window` seconds (default 15), who is typing, presence of direct-chat partners and unread
     * counts. Pass the returned `cursor` next time.
     */
    public function poll(Request $request, Presence $presence)
    {
        $me = $request->user();
        $since = max(0, $request->integer('since'));
        $ids = DB::table('connect_participants')->where('user_id', $me->id)->whereNull('left_at')->pluck('conversation_id');
        $cursor = (int) Message::whereIn('conversation_id', $ids)->max('id');
        $window = now()->subSeconds(max(5, min(120, $request->integer('window', 15))));

        $new = $since ? Message::with(['user', 'replyTo.user', 'reactions'])->whereIn('conversation_id', $ids)->where('id', '>', $since)->orderBy('id')->limit(300)->get() : collect();
        $changed = $since ? Message::with(['user', 'replyTo.user', 'reactions'])->whereIn('conversation_id', $ids)->where('id', '<=', $since)->where('updated_at', '>=', $window)->limit(100)->get() : collect();
        if ($new->count() === 300) $cursor = (int) $new->max('id');
        $saved = $this->connect->savedIds($me, $new->pluck('id')->merge($changed->pluck('id')));
        $map = fn ($m) => $this->message($m, $me, $saved) + ['conversation_id' => $m->conversation_id];

        $inbox = $this->connect->inbox($me);
        $typing = [];
        foreach ($inbox as $c) {
            if ($names = $this->connect->typingNames($c, $me)) $typing[$c->id] = $names;
        }
        $others = $inbox->filter(fn ($c) => $c->isDirect())->map(fn ($c) => $c->otherUser($me))->filter();
        $presence->prime($others->pluck('id'));

        return $this->ok([
            'cursor' => $cursor,
            'messages' => $new->map($map)->values(),
            'changed' => $changed->map($map)->values(),
            'typing' => (object) $typing,
            'presence' => (object) $others->mapWithKeys(fn ($u) => [$u->id => ['state' => $presence->of($u)['state'], 'line' => $presence->line($u)]])->all(),
            'unread' => (object) $inbox->filter(fn ($c) => $c->unread)->mapWithKeys(fn ($c) => [$c->id => (int) $c->unread])->all(),
            'unread_total' => $this->connect->unreadTotal($me),
        ]);
    }

    public function read(Request $request, Conversation $conversation)
    {
        $this->connect->markRead($conversation, $request->user());

        return $this->ok(['unread' => $this->connect->unreadTotal($request->user())]);
    }

    public function react(Request $request, Message $message)
    {
        $this->connect->react($message, $request->user(), (string) $request->input('emoji'));

        return $this->ok($this->message($message->fresh(['user', 'replyTo.user', 'reactions']), $request->user()));
    }

    public function destroy(Request $request, Message $message)
    {
        $this->connect->delete($message, $request->user());

        return $this->ok(null, 'Deleted.');
    }

    protected function conversation(Conversation $c, User $me): array
    {
        $other = $c->isDirect() ? $c->otherUser($me) : null;
        $last = $c->lastMessage;

        return [
            'id' => $c->id, 'type' => $c->type, 'kind' => $c->type, 'title' => $c->titleFor($me), 'description' => $c->description,
            'avatar' => $other?->avatar_url, 'unread' => (int) ($c->unread ?? 0), 'muted' => (bool) ($c->muted ?? false),
            'last_message' => $last ? ['id' => $last->id, 'preview' => $last->preview(100), 'from' => $last->user?->name, 'at' => $last->created_at?->toIso8601String()] : null,
            'project_id' => $c->project_id, 'meeting_id' => $c->meeting_id, 'shared_with_clients' => (bool) $c->guests_allowed,
            'other_user_id' => $other?->id,
        ];
    }

    /** @param  int[]|null  $saved  ids of messages the person saved (looked up when null) */
    protected function message(Message $m, User $me, ?array $saved = null): array
    {
        $deleted = $m->isDeleted();
        $file = !$deleted && $m->attachment_path ? [
            'id' => $m->id, 'name' => $m->attachment_name, 'mime' => $m->attachment_mime, 'size' => $m->attachment_size,
            'url' => route('api.v1.connect.file', $m), 'thumb_url' => $m->isImage() ? route('api.v1.connect.file', [$m, 'inline' => 1]) : null,
        ] : null;

        return [
            'id' => $m->id, 'conversation_id' => $m->conversation_id, 'system' => $m->is_system, 'mine' => $m->user_id === $me->id,
            'kind' => $deleted ? 'text' : ($m->kind ?: ($file ? 'file' : 'text')),
            'user' => $m->user ? ['id' => $m->user->id, 'name' => $m->user->name, 'avatar' => $m->user->avatar_url, 'client' => $m->user->user_type === 'client'] : null,
            'body' => $deleted ? null : $m->body, 'deleted' => $deleted, 'edited' => (bool) $m->edited_at, 'edited_at' => $m->edited_at?->toIso8601String(),
            'meta' => $deleted ? null : $this->meta($m, $me),
            'parent_id' => $m->thread_id, 'thread_count' => (int) $m->reply_count, 'last_reply_at' => $m->last_reply_at?->toIso8601String(),
            'reply_to' => $m->replyTo ? ['id' => $m->replyTo->id, 'from' => $m->replyTo->user?->name, 'preview' => $m->replyTo->preview(90)] : null,
            'attachment' => $file, 'attachments' => $file ? [$file] : [],
            'reactions' => $m->reactions->groupBy('emoji')->map(fn ($rs, $e) => ['emoji' => $e, 'count' => $rs->count(), 'mine' => $rs->contains('user_id', $me->id)])->values(),
            'pinned' => (bool) $m->pinned_at,
            'saved' => in_array($m->id, $saved ?? $this->connect->savedIds($me, [$m->id]), true),
            'at' => $m->created_at->toIso8601String(),
        ];
    }

    protected function meta(Message $m, User $me): ?array
    {
        return match ($m->kind) {
            'poll' => (function () use ($m, $me) {
                $r = $m->pollResults($me);

                return [
                    'question' => $m->body, 'multiple' => !empty($m->meta['multiple']), 'closed' => $m->pollClosed(), 'voters' => $r['voters'],
                    'options' => collect($r['options'])->map(fn ($o, $i) => ['index' => $i, 'text' => $o['text'], 'count' => $o['count'], 'pct' => $o['pct'], 'mine' => $o['mine']])->values(),
                    'my_vote' => collect($r['options'])->filter(fn ($o) => $o['mine'])->keys()->values(),
                ];
            })(),
            'gif' => ['url' => $m->meta['url'] ?? null, 'width' => $m->meta['width'] ?? null, 'height' => $m->meta['height'] ?? null, 'title' => $m->meta['title'] ?? null],
            'recording' => ['duration' => $m->meta['duration'] ?? null, 'audio' => !empty($m->meta['audio']), 'call_id' => $m->meta['call_id'] ?? null,
                // Recordings play on the web for now; this opens the recap page in the browser.
                'recap_url' => !empty($m->meta['call_id']) ? route('connect.calls.recap', $m->meta['call_id']) : null],
            default => null,
        };
    }

    public function file(Request $request, Message $message)
    {
        if (!$message->attachment_path || !$this->connect->canView($message->conversation, $request->user())) return $this->fail('File not found.', 404, 'not_found');

        $disk = \Illuminate\Support\Facades\Storage::disk('local');
        if (!$disk->exists($message->attachment_path)) return $this->fail('File not found.', 404, 'not_found');

        return $request->boolean('inline') && $message->isImage()
            ? $disk->response($message->attachment_path, $message->attachment_name, ['Cache-Control' => 'private, max-age=86400'])
            : $disk->download($message->attachment_path, $message->attachment_name);
    }
}
