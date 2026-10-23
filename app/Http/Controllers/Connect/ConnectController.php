<?php

namespace App\Http\Controllers\Connect;

use App\Http\Controllers\Controller;
use App\Models\Connect\Conversation;
use App\Models\Connect\Message;
use App\Models\Projects\Project;
use App\Models\User;
use App\Services\Connect\ConnectService;
use App\Services\Connect\GifSearch;
use App\Services\Projects\ProjectService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * The chat screen: conversation list on the left, the open conversation
 * on the right. The page refreshes itself every few seconds (poll).
 */
class ConnectController extends Controller
{
    public function __construct(protected ConnectService $connect) {}

    public function index(Request $request)
    {
        return view('modules.connect.index', $this->shell($request->user()) + [
            'conversation' => null, 'participant' => null, 'messages' => collect(), 'isAdmin' => false, 'saved' => [],
            'pinned' => collect(), 'seenUpTo' => 0, 'around' => null, 'openThread' => null, 'members' => collect(),
            'liveCall' => null, 'driveFiles' => collect(),
        ]);
    }

    public function show(Request $request, Conversation $conversation)
    {
        $me = $request->user();
        abort_unless($this->connect->canView($conversation, $me), 404);
        $participant = $this->connect->participant($conversation, $me);
        $around = $request->integer('around') ?: null;
        $messages = $this->connect->messages($conversation, around: $around);
        if ($participant) $this->connect->markRead($conversation, $me);
        $conversation->load('members.user', 'project');

        return view('modules.connect.index', $this->shell($me) + [
            'conversation' => $conversation,
            'participant'  => $participant,
            'messages'     => $messages,
            'isAdmin'      => $participant?->role === 'admin',
            'saved'        => $this->connect->savedIds($me, $messages->pluck('id')),
            'pinned'       => $this->connect->pinned($conversation),
            'seenUpTo'     => $this->connect->seenUpTo($conversation, $me),
            'around'       => $around,
            'openThread'   => $request->integer('thread') ?: null,
            'members'      => $conversation->members->map(fn ($p) => ['id' => $p->user_id, 'name' => $p->user?->name])->filter(fn ($m) => $m['name'] && $m['id'] !== $me->id)->sortBy('name')->values(),
            'liveCall'     => null,
            'callBar'      => app(\App\Services\Connect\CallService::class)->summary($conversation),
            'driveFiles'   => \Illuminate\Support\Facades\Route::has('drive.send') && $me->can('Use connect') ? app(\App\Services\Drive\DriveService::class)->recent($me, 15) : collect(),
        ]);
    }

    /**
     * Everything that changed since the last check: new messages, edits and reactions, the open
     * thread, who is typing, how far the other person has read, and the conversation list.
     */
    public function poll(Request $request, ?Conversation $conversation = null)
    {
        $me = $request->user();
        $out = [];
        if ($conversation) {
            abort_unless($this->connect->canView($conversation, $me), 404);
            $after = max(0, $request->integer('after'));
            $isAdmin = $this->connect->isAdmin($conversation, $me);
            $since = now()->subSeconds(max(5, min(120, $request->integer('since', 10))));

            $new = $this->connect->messages($conversation, after: $after ?: null);
            $changed = Message::with(['user', 'replyTo.user', 'reactions'])->where('conversation_id', $conversation->id)->whereNull('thread_id')
                ->where('id', '<=', $after)->where('updated_at', '>=', $since)->get();
            $saved = $this->connect->savedIds($me, $new->pluck('id')->merge($changed->pluck('id')));
            $render = fn ($m) => $this->render($m, $me, $conversation, $isAdmin, $saved);

            $out['html'] = $new->map($render)->implode('');
            $out['last_id'] = max($after, (int) $new->max('id'));
            $out['changed'] = $changed->mapWithKeys(fn ($m) => [$m->id => $render($m)]);
            // new messages from other people, for the message tone
            $out['incoming'] = $new->filter(fn ($m) => !$m->is_system && $m->user_id !== $me->id)->count();
            if ($new->isNotEmpty() && $this->connect->participant($conversation, $me)) {
                $this->connect->markRead($conversation, $me, $out['last_id']);
            }

            if ($threadId = $request->integer('thread')) {
                $root = Message::where('conversation_id', $conversation->id)->find($threadId);
                if ($root) {
                    $tAfter = max(0, $request->integer('thread_after'));
                    $replies = $this->connect->threadReplies($root, $tAfter ?: null);
                    $tChanged = Message::with(['user', 'replyTo.user', 'reactions'])->where('thread_id', $root->id)->where('id', '<=', $tAfter)->where('updated_at', '>=', $since)->get();
                    $tSaved = $this->connect->savedIds($me, $replies->pluck('id')->merge($tChanged->pluck('id')));
                    $out['thread'] = [
                        'id' => $root->id,
                        'html' => $replies->map(fn ($m) => $this->render($m, $me, $conversation, $isAdmin, $tSaved))->implode(''),
                        'last_id' => max($tAfter, (int) $replies->max('id')),
                        'incoming' => $tAfter ? $replies->filter(fn ($m) => $m->user_id !== $me->id)->count() : 0,
                        'changed' => $tChanged->mapWithKeys(fn ($m) => [$m->id => $this->render($m, $me, $conversation, $isAdmin, $tSaved)]),
                    ];
                }
            }

            $out['typing'] = $this->connect->typingNames($conversation, $me);
            $out['call'] = app(\App\Services\Connect\CallService::class)->summary($conversation);
            $out['seen'] = $this->connect->seenUpTo($conversation, $me);
            if ($conversation->isDirect()) {
                $other = $conversation->otherUser($me)?->refresh();
                $p = app(\App\Services\Presence\Presence::class);
                $out['presence'] = $p->line($other);
                $out['presence_state'] = $p->of($other)['state'];
                $out['presence_color'] = $p->of($other)['color'];
            }
        }
        // After marking read, so the counts include what was just shown.
        $out['total'] = $this->connect->unreadTotal($me);
        if ($request->boolean('inbox')) {
            $out['inbox'] = view('modules.connect._inbox', ['inbox' => $this->connect->inbox($me), 'active' => $conversation, 'me' => $me])->render();
        }

        return response()->json($out);
    }

    public function older(Request $request, Conversation $conversation)
    {
        $me = $request->user();
        abort_unless($this->connect->canView($conversation, $me), 404);
        $older = $this->connect->messages($conversation, before: $request->integer('before'), limit: 40);
        $isAdmin = $this->connect->isAdmin($conversation, $me);
        $saved = $this->connect->savedIds($me, $older->pluck('id'));

        return response()->json([
            'html' => $older->map(fn ($m) => $this->render($m, $me, $conversation, $isAdmin, $saved))->implode(''),
            'more' => $older->count() === 40,
        ]);
    }

    /** A thread: its first message and every reply. */
    public function thread(Request $request, Message $message)
    {
        $me = $request->user();
        $conversation = $message->conversation;
        abort_unless($this->connect->canView($conversation, $me) && !$message->thread_id && !$message->is_system, 404);
        $isAdmin = $this->connect->isAdmin($conversation, $me);
        $replies = $this->connect->threadReplies($message);
        $saved = $this->connect->savedIds($me, $replies->pluck('id')->push($message->id));

        return response()->json([
            'root' => $this->render($message->load('user', 'replyTo.user', 'reactions'), $me, $conversation, $isAdmin, $saved, inThread: true),
            'html' => $replies->map(fn ($m) => $this->render($m, $me, $conversation, $isAdmin, $saved))->implode(''),
            'last_id' => (int) $replies->max('id'),
        ]);
    }

    public function send(Request $request, Conversation $conversation)
    {
        $data = $request->validate([
            'body'           => ['nullable', 'string', 'max:5000'],
            'reply_to_id'    => ['nullable', 'integer'],
            'thread_id'      => ['nullable', 'integer'],
            'attachment'     => ['nullable', 'file', 'max:25600'],
            'poll_question'  => ['nullable', 'string', 'max:300'],
            'poll_options'   => ['nullable', 'array', 'max:10'],
            'poll_options.*' => ['nullable', 'string', 'max:120'],
            'poll_multiple'  => ['nullable', 'boolean'],
            'gif_url'        => ['nullable', 'string', 'max:500'],
            'gif_width'      => ['nullable', 'integer', 'min:0', 'max:4000'],
            'gif_height'     => ['nullable', 'integer', 'min:0', 'max:4000'],
            'gif_title'      => ['nullable', 'string', 'max:255'],
        ]);
        $me = $request->user();
        $m = match (true) {
            $request->filled('poll_question') => $this->connect->sendPoll($conversation, $me, $data['poll_question'], $data['poll_options'] ?? [], (bool) ($data['poll_multiple'] ?? false), $data['thread_id'] ?? null),
            $request->filled('gif_url') => $this->connect->sendGif($conversation, $me, ['url' => $data['gif_url'], 'width' => $data['gif_width'] ?? null, 'height' => $data['gif_height'] ?? null, 'title' => $data['gif_title'] ?? null],
                $data['body'] ?? null, $data['reply_to_id'] ?? null, $data['thread_id'] ?? null),
            default => $this->connect->send($conversation, $me, $data['body'] ?? null, $request->file('attachment'), $data['reply_to_id'] ?? null, $data['thread_id'] ?? null),
        };

        return $request->expectsJson()
            ? response()->json(['ok' => true, 'id' => $m->id])
            : redirect()->route('connect.show', $conversation);
    }

    public function typing(Request $request, Conversation $conversation)
    {
        $this->connect->typing($conversation, $request->user());

        return response()->json(['ok' => true]);
    }

    public function pin(Request $request, Message $message)
    {
        $on = $this->connect->togglePin($message, $request->user());

        return $request->expectsJson() ? response()->json(['pinned' => $on]) : back()->with('success', $on ? 'Pinned.' : 'Unpinned.');
    }

    public function save(Request $request, Message $message)
    {
        $on = $this->connect->toggleSave($message, $request->user());

        return $request->expectsJson() ? response()->json(['saved' => $on]) : back()->with('success', $on ? 'Saved.' : 'Removed from saved.');
    }

    public function saved(Request $request)
    {
        return view('modules.connect.list', $this->shell($request->user()) + [
            'title' => 'Saved messages', 'icon' => 'ri-bookmark-line', 'results' => $this->connect->saved($request->user()), 'term' => null,
        ]);
    }

    public function search(Request $request)
    {
        $term = trim((string) $request->input('q'));

        return view('modules.connect.list', $this->shell($request->user()) + [
            'title' => 'Search', 'icon' => 'ri-search-line', 'results' => $this->connect->search($request->user(), $term), 'term' => $term,
        ]);
    }

    public function edit(Request $request, Message $message)
    {
        $this->connect->edit($message, $request->user(), (string) $request->input('body'));

        return $request->expectsJson() ? response()->json(['ok' => true]) : back();
    }

    public function destroy(Request $request, Message $message)
    {
        $this->connect->delete($message, $request->user());

        return $request->expectsJson() ? response()->json(['ok' => true]) : back();
    }

    public function react(Request $request, Message $message)
    {
        $this->connect->react($message, $request->user(), (string) $request->input('emoji'));

        return $request->expectsJson() ? response()->json(['ok' => true]) : back();
    }

    public function vote(Request $request, Message $message)
    {
        $data = $request->validate(['options' => ['present', 'array', 'max:10'], 'options.*' => ['integer', 'min:0', 'max:9']]);
        $this->connect->vote($message, $request->user(), $data['options']);

        return $request->expectsJson() ? response()->json(['ok' => true]) : back();
    }

    public function closePoll(Request $request, Message $message)
    {
        $this->connect->closePoll($message, $request->user());

        return $request->expectsJson() ? response()->json(['ok' => true]) : back();
    }

    /** GIF search for the composer (trending when the box is empty). */
    public function gifs(Request $request, GifSearch $gifs)
    {
        return response()->json(['enabled' => $gifs->enabled(), 'results' => $gifs->search((string) $request->query('q', ''))]);
    }

    public function attachment(Request $request, Message $message)
    {
        abort_unless($message->attachment_path && $this->connect->canView($message->conversation, $request->user()), 404);
        abort_unless(Storage::disk('local')->exists($message->attachment_path), 404);

        return $message->isImage() && !$request->boolean('download')
            ? Storage::disk('local')->response($message->attachment_path, $message->attachment_name, ['Cache-Control' => 'private, max-age=86400'])
            : Storage::disk('local')->download($message->attachment_path, $message->attachment_name);
    }

    // ── Starting and managing conversations ─────────────────────────────

    public function direct(Request $request, User $user)
    {
        return redirect()->route('connect.show', $this->connect->direct($request->user(), $user));
    }

    public function storeGroup(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100'], 'users' => ['required', 'array', 'min:1', 'max:200'], 'users.*' => ['integer']]);

        return redirect()->route('connect.show', $this->connect->group($request->user(), $data['name'], $data['users']));
    }

    public function storeChannel(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:60'], 'description' => ['nullable', 'string', 'max:255']]);

        return redirect()->route('connect.show', $this->connect->channel($request->user(), $data['name'], $data['description'] ?? null));
    }

    public function project(Request $request, Project $project)
    {
        $projects = app(ProjectService::class);
        abort_unless($projects->canView($project, $request->user()) && !$projects->isClientView($project, $request->user()), 403);

        return redirect()->route('connect.show', $this->connect->forProject($project, $request->user()));
    }

    /** The project's chat shared with the client: open to the team and the project's clients. */
    public function projectClients(Request $request, Project $project)
    {
        $me = $request->user();
        $projects = app(ProjectService::class);
        abort_unless($projects->canView($project, $me), 403);
        if ($this->connect->isGuest($me) || $projects->isClientView($project, $me)) {
            $existing = Conversation::where('client_project_id', $project->id)->first();
            abort_unless($existing && $this->connect->participant($existing, $me), 404);

            return redirect()->route('connect.show', $existing);
        }

        return redirect()->route('connect.show', $this->connect->forProjectClients($project, $me));
    }

    public function guests(Request $request, Conversation $conversation)
    {
        $on = $request->boolean('on');
        $this->connect->setGuests($conversation, $request->user(), $on);

        return back()->with('success', $on ? 'Clients can now be added to this chat.' : 'This chat is now team only.');
    }

    public function join(Request $request, Conversation $conversation)
    {
        $this->connect->join($conversation, $request->user());

        return redirect()->route('connect.show', $conversation);
    }

    public function leave(Request $request, Conversation $conversation)
    {
        $this->connect->leave($conversation, $request->user());

        return redirect()->route('connect.index')->with('success', 'You left ' . $conversation->titleFor($request->user()) . '.');
    }

    public function add(Request $request, Conversation $conversation)
    {
        $data = $request->validate(['users' => ['required', 'array', 'min:1'], 'users.*' => ['integer']]);
        $n = $this->connect->add($conversation, $request->user(), $data['users']);

        return back()->with('success', $n ? "Added {$n}." : 'They are already here.');
    }

    public function remove(Request $request, Conversation $conversation, User $user)
    {
        $this->connect->remove($conversation, $request->user(), $user);

        return back()->with('success', $user->name . ' removed.');
    }

    public function mute(Request $request, Conversation $conversation)
    {
        $this->connect->mute($conversation, $request->user(), $request->boolean('muted'));

        return back();
    }

    protected function render(Message $m, User $me, Conversation $conversation, bool $isAdmin, array $saved, bool $inThread = false): string
    {
        return view('modules.connect._message', compact('m', 'me', 'conversation', 'isAdmin', 'saved', 'inThread'))->render();
    }

    protected function shell(User $me): array
    {
        $guest = $this->connect->isGuest($me);

        return [
            'me'       => $me,
            'isGuest'  => $guest,
            'inbox'    => $this->connect->inbox($me),
            'people'   => $this->connect->people(except: $me)->limit(500)->get(['id', 'name', 'email', 'user_type']),
            'guests'   => $guest ? collect() : $this->connect->guests()->limit(500)->get(['id', 'name', 'email', 'user_type']),
            'channels' => $guest ? collect() : Conversation::where('type', 'channel')->whereNull('archived_at')->withCount('members')->orderBy('name')->get(),
        ];
    }
}
