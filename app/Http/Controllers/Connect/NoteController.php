<?php

namespace App\Http\Controllers\Connect;

use App\Http\Controllers\Controller;
use App\Models\Connect\Conversation;
use App\Models\Connect\Message;
use App\Models\Connect\Note;
use App\Models\Connect\NoteRevision;
use App\Services\Connect\ConnectService;
use App\Services\Connect\NoteService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** Notes and files of a chat: the "Notes" and "Files" tabs. */
class NoteController extends Controller
{
    public function __construct(protected NoteService $notes, protected ConnectService $connect) {}

    public function index(Request $request, Conversation $conversation)
    {
        $me = $request->user();
        abort_unless($this->connect->canView($conversation, $me), 404);
        $tab = $request->query('tab') === 'files' ? 'files' : 'notes';

        return view('modules.connect.notes.index', [
            'conversation' => $conversation, 'me' => $me, 'tab' => $tab,
            'canWrite' => $this->connect->participant($conversation, $me) !== null && !$conversation->archived_at,
            'notes' => Note::with('editor:id,name')->where('conversation_id', $conversation->id)->orderByDesc('pinned')->latest('updated_at')->get(),
            'files' => $tab === 'files' ? Message::with('user:id,name')->where('conversation_id', $conversation->id)->whereNotNull('attachment_path')->whereNull('deleted_at')->latest('id')->limit(200)->get() : collect(),
        ]);
    }

    public function store(Request $request, Conversation $conversation)
    {
        $data = $request->validate(['title' => ['required', 'string', 'max:150'], 'body' => ['nullable', 'string', 'max:' . NoteService::MAX]]);
        $note = $this->notes->create($conversation, $request->user(), $data['title'], $data['body'] ?? null);

        return redirect()->route('connect.notes.edit', $note);
    }

    public function show(Request $request, Note $note)
    {
        $me = $request->user();
        abort_unless($this->connect->canView($note->conversation, $me), 404);

        return view('modules.connect.notes.show', [
            'note' => $note->load('editor:id,name', 'creator:id,name'), 'conversation' => $note->conversation, 'me' => $me,
            'canEdit' => $this->notes->canEdit($note, $me), 'canDelete' => $this->notes->canDelete($note, $me),
        ]);
    }

    public function edit(Request $request, Note $note)
    {
        $me = $request->user();
        abort_unless($this->notes->canEdit($note, $me), 403);

        return view('modules.connect.notes.edit', [
            'note' => $note, 'conversation' => $note->conversation, 'me' => $me, 'editing' => $this->notes->editing($note, $me, true),
        ]);
    }

    public function update(Request $request, Note $note)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'], 'body' => ['nullable', 'string', 'max:' . NoteService::MAX], 'version' => ['required', 'integer', 'min:1'],
        ]);
        try {
            $note = $this->notes->save($note, $request->user(), $data['title'], $data['body'] ?? null, (int) $data['version']);
        } catch (ValidationException $e) {
            if ($e->errorBag !== 'conflict') throw $e;
            $fresh = $note->fresh('editor:id,name');
            if (!$request->expectsJson()) return back()->withInput()->with('error', $e->getMessage());

            return response()->json(['conflict' => true, 'message' => ($fresh->editor?->name ?? 'Someone') . ' saved this note while you were editing.',
                'version' => $fresh->version, 'title' => $fresh->title, 'body' => $fresh->body], 409);
        }

        return $request->expectsJson()
            ? response()->json(['ok' => true, 'version' => $note->version, 'saved' => now()->format('H:i')])
            : redirect()->route('connect.notes.show', $note)->with('success', 'Note saved.');
    }

    /** For open notes: has it changed, and who is editing. ?editing=1 also says "I'm editing". */
    public function state(Request $request, Note $note)
    {
        $me = $request->user();
        abort_unless($this->connect->canView($note->conversation, $me), 404);
        $note->load('editor:id,name');
        $out = ['version' => $note->version, 'by' => $note->editor?->name, 'when' => $note->updated_at->diffForHumans(),
            'editing' => $this->notes->editing($note, $me, $request->boolean('editing') && $this->notes->canEdit($note, $me))];
        if ($request->integer('version') !== $note->version) $out += ['title' => $note->title, 'html' => $note->html(), 'body' => $request->boolean('editing') ? $note->body : null];

        return response()->json($out);
    }

    /** Markdown to HTML for the editor's preview (same rules as saved notes). */
    public function preview(Request $request)
    {
        $data = $request->validate(['body' => ['nullable', 'string', 'max:' . NoteService::MAX]]);

        return response()->json(['html' => Note::render($data['body'] ?? '')]);
    }

    public function history(Request $request, Note $note)
    {
        $me = $request->user();
        abort_unless($this->connect->canView($note->conversation, $me), 404);

        return view('modules.connect.notes.history', [
            'note' => $note, 'conversation' => $note->conversation, 'me' => $me, 'canEdit' => $this->notes->canEdit($note, $me),
            'revisions' => $note->revisions()->with('user:id,name')->limit(100)->get(),
            'shown' => $request->integer('v') ? NoteRevision::where('note_id', $note->id)->where('version', $request->integer('v'))->first() : null,
        ]);
    }

    public function restore(Request $request, Note $note, NoteRevision $revision)
    {
        $this->notes->restore($note, $revision, $request->user());

        return redirect()->route('connect.notes.show', $note)->with('success', 'Version ' . $revision->version . ' restored.');
    }

    public function pin(Request $request, Note $note)
    {
        $note = $this->notes->togglePin($note, $request->user());

        return back()->with('success', $note->pinned ? 'Note pinned to the top.' : 'Note unpinned.');
    }

    public function destroy(Request $request, Note $note)
    {
        $conversation = $note->conversation;
        $this->notes->delete($note, $request->user());

        return redirect()->route('connect.notes.index', $conversation)->with('success', 'Note deleted.');
    }
}
