<?php

namespace App\Http\Controllers\Api\V1\Connect;

use App\Http\Controllers\Api\V1\ApiController;
use App\Models\Connect\Conversation;
use App\Models\Connect\Message;
use App\Models\User;
use App\Services\Connect\ConnectService;
use Illuminate\Http\Request;

/**
 * Qudroid Connect for the mobile app. The app polls `messages?after=`
 * while a chat is open, and relies on push notifications otherwise.
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

        return $this->ok($items->map(fn ($m) => $this->message($m, $me))->values(), null, 200, ['joined' => (bool) $this->connect->participant($conversation, $me)]);
    }

    public function send(Request $request, Conversation $conversation)
    {
        $data = $request->validate(['body' => ['nullable', 'string', 'max:5000'], 'reply_to_id' => ['nullable', 'integer'], 'attachment' => ['nullable', 'file', 'max:25600']]);
        $m = $this->connect->send($conversation, $request->user(), $data['body'] ?? null, $request->file('attachment'), $data['reply_to_id'] ?? null);

        return $this->ok($this->message($m->load('user', 'replyTo.user', 'reactions'), $request->user()), 'Sent.', 201);
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
            'id' => $c->id, 'type' => $c->type, 'title' => $c->titleFor($me),
            'avatar' => $other?->avatar_url, 'unread' => (int) ($c->unread ?? 0), 'muted' => (bool) ($c->muted ?? false),
            'last_message' => $last ? ['id' => $last->id, 'preview' => $last->preview(100), 'from' => $last->user?->name, 'at' => $last->created_at?->toIso8601String()] : null,
            'project_id' => $c->project_id,
        ];
    }

    protected function message(Message $m, User $me): array
    {
        return [
            'id' => $m->id, 'system' => $m->is_system, 'mine' => $m->user_id === $me->id,
            'user' => $m->user ? ['id' => $m->user->id, 'name' => $m->user->name, 'avatar' => $m->user->avatar_url] : null,
            'body' => $m->isDeleted() ? null : $m->body, 'deleted' => $m->isDeleted(), 'edited' => (bool) $m->edited_at,
            'reply_to' => $m->replyTo ? ['id' => $m->replyTo->id, 'from' => $m->replyTo->user?->name, 'preview' => $m->replyTo->preview(90)] : null,
            'attachment' => $m->attachment_path ? ['name' => $m->attachment_name, 'mime' => $m->attachment_mime, 'size' => $m->attachment_size, 'url' => route('api.v1.connect.file', $m)] : null,
            'reactions' => $m->reactions->groupBy('emoji')->map(fn ($rs, $e) => ['emoji' => $e, 'count' => $rs->count(), 'mine' => $rs->contains('user_id', $me->id)])->values(),
            'at' => $m->created_at->toIso8601String(),
        ];
    }

    public function file(Request $request, Message $message)
    {
        if (!$message->attachment_path || !$this->connect->canView($message->conversation, $request->user())) return $this->fail('File not found.', 404, 'not_found');

        return \Illuminate\Support\Facades\Storage::disk('local')->download($message->attachment_path, $message->attachment_name);
    }
}
