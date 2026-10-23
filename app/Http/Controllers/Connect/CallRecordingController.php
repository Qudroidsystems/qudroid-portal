<?php

namespace App\Http\Controllers\Connect;

use App\Http\Controllers\Controller;
use App\Models\Connect\Call;
use App\Models\Connect\CallRecording;
use App\Services\Connect\CallRecordingService;
use App\Services\Connect\CallService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Recording, transcript and recap of a call. The call page talks to the
 * JSON actions; the recap page is for afterwards.
 */
class CallRecordingController extends Controller
{
    public function __construct(protected CallRecordingService $recordings, protected CallService $calls) {}

    public function start(Request $request, Call $call)
    {
        abort_unless($this->calls->canJoin($call, $request->user()), 403);
        $data = $request->validate(['mime' => ['required', 'string', 'max:120']]);
        $rec = $this->recordings->start($call, $request->user(), $data['mime']);

        return response()->json([
            'id' => $rec->id,
            'chunk' => route('connect.recordings.chunk', $rec),
            'stop' => route('connect.recordings.stop', $rec),
        ]);
    }

    public function chunk(Request $request, CallRecording $recording)
    {
        $data = $request->validate([
            'seq' => ['required', 'integer', 'min:0'],
            'piece' => ['required', 'file', 'max:' . CallRecordingService::CHUNK_MAX_KB],
        ]);
        $rec = $this->recordings->append($recording, $request->user(), (int) $data['seq'], $request->file('piece'));

        return response()->json(['ok' => true, 'next' => $rec->chunks]);
    }

    public function stop(Request $request, CallRecording $recording)
    {
        abort_unless($recording->user_id === $request->user()->id, 403);
        $seconds = $request->filled('seconds') ? max(0, min(86400, $request->integer('seconds'))) : null;
        $rec = $this->recordings->finish($recording, $request->user(), $seconds);

        return response()->json(['ok' => true, 'status' => $rec->status, 'recap' => route('connect.calls.recap', $rec->call_id)]);
    }

    public function show(Request $request, CallRecording $recording)
    {
        abort_unless($recording->status === 'ready' && $this->recordings->canSee($recording, $request->user()), 404);
        $disk = Storage::disk('local');
        abort_unless($recording->path && $disk->exists($recording->path), 404);
        $name = 'recording-' . $recording->started_at->format('Y-m-d-Hi') . '.' . pathinfo($recording->path, PATHINFO_EXTENSION);

        // A file response supports seeking (range requests) in the player.
        return response()->file($disk->path($recording->path), [
            'Content-Type' => $recording->mime,
            'Content-Disposition' => ($request->boolean('download') ? 'attachment' : 'inline') . '; filename="' . $name . '"',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    public function destroy(Request $request, CallRecording $recording)
    {
        abort_unless($this->recordings->canSee($recording, $request->user()), 404);
        $call = $recording->call_id;
        $this->recordings->delete($recording, $request->user());

        return redirect()->route('connect.calls.recap', $call)->with('success', 'Recording deleted.');
    }

    public function transcribe(Request $request, Call $call)
    {
        abort_unless($this->calls->canJoin($call, $request->user()), 403);
        $this->recordings->setTranscribing($call, $request->user(), $request->boolean('on'));

        return response()->json(['ok' => true, 'transcribing' => (bool) $call->refresh()->transcribing_by]);
    }

    public function say(Request $request, Call $call)
    {
        abort_unless($this->calls->canJoin($call, $request->user()), 403);
        $data = $request->validate(['text' => ['required', 'string', 'max:2000']]);
        $line = $this->recordings->say($call, $request->user(), $data['text']);

        return response()->json(['ok' => (bool) $line, 'id' => $line?->id]);
    }

    public function recap(Request $request, Call $call)
    {
        $me = $request->user();
        abort_unless($this->calls->canJoin($call, $me), 404);
        $this->calls->sweep($call);
        $call->refresh()->load('conversation', 'starter', 'note');

        return view('modules.connect.recap', [
            'call' => $call, 'me' => $me, 'title' => $call->conversation->titleFor($me),
            'recordings' => $call->recordings()->with('user')->whereIn('status', ['ready', 'recording'])->latest('id')->get(),
            'lines' => $call->transcript()->with('user:id,name')->get(),
            'people' => $this->recordings->attendance($call),
            'isAdmin' => app(\App\Services\Connect\ConnectService::class)->isAdmin($call->conversation, $me),
        ]);
    }

    public function transcript(Request $request, Call $call)
    {
        abort_unless($this->calls->canJoin($call, $request->user()), 404);
        $name = 'transcript-' . $call->started_at->format('Y-m-d-Hi') . '.txt';

        return response($this->recordings->transcriptText($call) . "\n", 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $name . '"',
        ]);
    }

    public function note(Request $request, Call $call)
    {
        abort_unless($this->calls->canJoin($call, $request->user()), 404);
        $note = $this->recordings->makeNote($call, $request->user());

        return redirect()->route('connect.notes.edit', $note);
    }
}
