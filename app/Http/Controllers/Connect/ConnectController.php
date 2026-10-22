<?php

namespace App\Http\Controllers\Connect;

use App\Http\Controllers\Controller;
use App\Models\Connect\Conversation;
use App\Models\Connect\Message;
use App\Models\Projects\Project;
use App\Models\User;
use App\Services\Connect\ConnectService;
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
        return view('modules.connect.index', $this->shell($request->user()) + ['conversation' => null]);
    }

    public function show(Request $request, Conversation $conversation)
    {
        $me = $request->user();
        abort_unless($this->connect->canView($conversation, $me), 404);
        $participant = $this->connect->participant($conversation, $me);
        $messages = $this->connect->messages($conversation);
        if ($participant) $this->connect->markRead($conversation, $me);

        return view('modules.connect.index', $this->shell($me) + [
            'conversation' => $conversation->load('members.user', 'project'),
            'participant'  => $participant,
            'messages'     => $messages,
            'isAdmin'      => $participant?->role === 'admin',
        ]);
    }

    /** New messages since an id, and the refreshed conversation list. */
    public function poll(Request $request, ?Conversation $conversation = null)
    {
        $me = $request->user();
        $out = [];
        if ($conversation) {
            abort_unless($this->connect->canView($conversation, $me), 404);
            $after = max(0, $request->integer('after'));
            $isAdmin = $this->connect->isAdmin($conversation, $me);
            $render = fn ($m) => view('modules.connect._message', ['m' => $m, 'me' => $me, 'conversation' => $conversation, 'isAdmin' => $isAdmin])->render();

            $new = $this->connect->messages($conversation, after: $after ?: null);
            $out['html'] = $new->map($render)->implode('');
            $out['last_id'] = max($after, (int) $new->max('id'));
            // Edits, deletions and reactions on messages already on screen.
            $since = now()->subSeconds(max(5, min(120, $request->integer('since', 10))));
            $out['changed'] = Message::with(['user', 'replyTo.user', 'reactions'])->where('conversation_id', $conversation->id)
                ->where('id', '<=', $after)->where('updated_at', '>=', $since)->get()
                ->mapWithKeys(fn ($m) => [$m->id => $render($m)]);
            if ($new->isNotEmpty() && $this->connect->participant($conversation, $me)) {
                $this->connect->markRead($conversation, $me, $out['last_id']);
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

        return response()->json([
            'html' => $older->map(fn ($m) => view('modules.connect._message', ['m' => $m, 'me' => $me, 'conversation' => $conversation, 'isAdmin' => $isAdmin])->render())->implode(''),
            'more' => $older->count() === 40,
        ]);
    }

    public function send(Request $request, Conversation $conversation)
    {
        $data = $request->validate([
            'body'        => ['nullable', 'string', 'max:5000'],
            'reply_to_id' => ['nullable', 'integer'],
            'attachment'  => ['nullable', 'file', 'max:25600'],
        ]);
        $m = $this->connect->send($conversation, $request->user(), $data['body'] ?? null, $request->file('attachment'), $data['reply_to_id'] ?? null);

        return $request->expectsJson()
            ? response()->json(['ok' => true, 'id' => $m->id])
            : redirect()->route('connect.show', $conversation);
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

    protected function shell(User $me): array
    {
        return [
            'me'       => $me,
            'inbox'    => $this->connect->inbox($me),
            'people'   => $this->connect->people(except: $me)->limit(500)->get(['id', 'name', 'email', 'user_type']),
            'channels' => Conversation::where('type', 'channel')->whereNull('archived_at')->withCount('members')->orderBy('name')->get(),
        ];
    }
}
