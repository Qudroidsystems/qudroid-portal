<?php

namespace App\Services\Connect;

use App\Models\Connect\Call;
use App\Models\Connect\CallPerson;
use App\Models\Connect\CallRecording;
use App\Models\Connect\Conversation;
use App\Models\Connect\Message;
use App\Models\Connect\Note;
use App\Models\Connect\TranscriptLine;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Recording and transcribing calls, and the recap afterwards.
 *
 *  - Recording: one person's call page mixes everyone's sound (and lays the
 *    videos out on a canvas) and uploads a piece every few seconds, so a
 *    closed tab loses at most a few seconds. Everyone in the call sees that
 *    it is being recorded, and the chat says who started and stopped it.
 *  - Transcribing: when someone turns it on, each person's browser turns
 *    their own speech into text and sends it here, so each line has the
 *    right speaker. Lines show as captions in the call.
 *  - Recap: recordings, transcript and who came, with a button that turns
 *    it into a shared note in the chat (and, for a meeting, its minutes).
 */
class CallRecordingService
{
    public const MIMES = ['video/webm', 'audio/webm', 'video/mp4', 'audio/mp4', 'audio/ogg'];
    public const CHUNK_MAX_KB = 16384;

    public function __construct(protected ConnectService $connect) {}

    // ── Recording ───────────────────────────────────────────────────────

    public function active(Call $call): ?CallRecording
    {
        return CallRecording::with('user')->where('call_id', $call->id)->where('status', 'recording')->latest('id')->first();
    }

    public function start(Call $call, User $me, string $mime): CallRecording
    {
        $this->check($call->ended_at !== null, 'call', 'This call has ended.');
        $this->check(!$this->isIn($call, $me), 'call', 'Join the call first.');
        $base = strtolower(trim(explode(';', $mime)[0]));
        $this->check(!in_array($base, self::MIMES, true), 'mime', 'This browser records in a format the portal does not accept.');

        return DB::transaction(function () use ($call, $me, $base) {
            $running = CallRecording::where('call_id', $call->id)->where('status', 'recording')->lockForUpdate()->first();
            $this->check($running !== null, 'call', ($running?->user?->name ?? 'Someone') . ' is already recording.');
            $rec = CallRecording::create(['call_id' => $call->id, 'user_id' => $me->id, 'mime' => $base, 'started_at' => now()]);
            $rec->update(['path' => 'calls/' . $call->id . '/recording-' . $rec->id . '.' . (str_contains($base, 'mp4') ? 'mp4' : (str_contains($base, 'ogg') ? 'ogg' : 'webm'))]);
            $this->announce($call->conversation, $me->name . ' started recording');

            return $rec;
        });
    }

    /** Add the next piece. Pieces must arrive in order; a repeat of one already stored is ignored. */
    public function append(CallRecording $rec, User $me, int $seq, UploadedFile $piece): CallRecording
    {
        $this->check($rec->user_id !== $me->id, 'recording', 'This is someone else\'s recording.');
        $this->check($rec->status !== 'recording', 'recording', 'This recording has stopped.');
        if ($seq < $rec->chunks) return $rec;
        $this->check($seq > $rec->chunks, 'seq', 'A piece of the recording is missing.');
        $max = max(1, (int) config('qudroid.calls.recording_max_mb', 1024)) * 1048576;
        $this->check($rec->size + $piece->getSize() > $max, 'recording', 'The recording reached its size limit.');

        $disk = Storage::disk('local');
        if (!$disk->exists(dirname($rec->path))) $disk->makeDirectory(dirname($rec->path));
        $out = fopen($disk->path($rec->path), $seq === 0 ? 'wb' : 'ab');
        $in = fopen($piece->getRealPath(), 'rb');
        stream_copy_to_stream($in, $out);
        fclose($in);
        fclose($out);
        $rec->update(['chunks' => $seq + 1, 'size' => $rec->size + $piece->getSize()]);

        return $rec;
    }

    /** Stop a recording. With something stored it is posted to the chat; otherwise it is dropped. */
    public function finish(CallRecording $rec, ?User $by = null, ?int $seconds = null): CallRecording
    {
        $updated = CallRecording::whereKey($rec->id)->where('status', 'recording')->update(['status' => 'finishing']);
        if (!$updated) return $rec->refresh();
        $rec->refresh();
        $ok = $rec->size > 0 && Storage::disk('local')->exists((string) $rec->path);
        $seconds ??= (int) $rec->started_at->diffInSeconds(now(), true);
        $rec->update(['status' => $ok ? 'ready' : 'failed', 'ended_at' => now(), 'duration_seconds' => max(0, $seconds)]);
        if (!$ok && $rec->path) Storage::disk('local')->delete($rec->path);

        $c = $rec->call->conversation;
        $this->announce($c, ($by ?? $rec->user)?->name . ' stopped recording');
        if ($ok) {
            $m = Message::create(['conversation_id' => $c->id, 'user_id' => $rec->user_id, 'body' => 'Recording · ' . $rec->durationLabel(),
                'kind' => 'recording', 'meta' => ['recording_id' => $rec->id, 'call_id' => $rec->call_id, 'duration' => $rec->durationLabel(), 'audio' => $rec->isAudio()]]);
            $c->update(['last_message_id' => $m->id, 'last_message_at' => $m->created_at]);
        }

        return $rec;
    }

    /** Recordings whose recorder has left (or a call that ended) are closed with what was uploaded. */
    public function closeOrphans(Call $call): void
    {
        $call->refresh();
        if (!$call->ended_at && !CallRecording::where('call_id', $call->id)->where('status', 'recording')->exists()) return;
        CallRecording::with('call.conversation', 'user')->where('call_id', $call->id)->where('status', 'recording')->get()
            ->each(function (CallRecording $rec) use ($call) {
                if ($call->ended_at || !$rec->user_id || !CallPerson::where('call_id', $call->id)->where('user_id', $rec->user_id)->whereNotNull('joined_at')->whereNull('left_at')->exists()) {
                    $this->finish($rec);
                }
            });
        if ($call->ended_at && $call->transcribing_by) $call->update(['transcribing_by' => null]);
    }

    public function canSee(CallRecording $rec, User $me): bool
    {
        return $this->connect->participant($rec->call->conversation, $me) !== null;
    }

    public function canDelete(CallRecording $rec, User $me): bool
    {
        return $rec->user_id === $me->id || $this->connect->isAdmin($rec->call->conversation, $me);
    }

    public function delete(CallRecording $rec, User $me): void
    {
        $this->check(!$this->canDelete($rec, $me), 'recording', 'Only the person who recorded it, or a chat admin, can delete it.');
        $this->check($rec->status === 'recording', 'recording', 'Stop the recording first.');
        if ($rec->path) Storage::disk('local')->delete($rec->path);
        Message::where('conversation_id', $rec->call->conversation_id)->where('kind', 'recording')->get()
            ->filter(fn ($m) => (int) ($m->meta['recording_id'] ?? 0) === $rec->id)
            ->each(fn ($m) => $m->update(['body' => 'Recording deleted', 'kind' => null, 'meta' => null, 'deleted_at' => now()]));
        $rec->delete();
    }

    // ── Transcript ──────────────────────────────────────────────────────

    public function setTranscribing(Call $call, User $me, bool $on): void
    {
        $this->check($call->ended_at !== null, 'call', 'This call has ended.');
        $this->check(!$this->isIn($call, $me), 'call', 'Join the call first.');
        if ((bool) $call->transcribing_by === $on) return;
        $call->update(['transcribing_by' => $on ? $me->id : null]);
        $this->announce($call->conversation, $me->name . ($on ? ' turned on the transcript' : ' turned off the transcript'));
    }

    public function say(Call $call, User $me, string $text): ?TranscriptLine
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text));
        if ($text === '' || !$call->transcribing_by || $call->ended_at || !$this->isIn($call, $me)) return null;

        return TranscriptLine::create(['call_id' => $call->id, 'user_id' => $me->id, 'text' => mb_substr($text, 0, 1000), 'spoken_at' => now()]);
    }

    /** New lines for captions: after an id, at most the last 20. */
    public function captions(Call $call, int $after): array
    {
        return TranscriptLine::with('user:id,name')->where('call_id', $call->id)->where('id', '>', $after)->latest('id')->limit(20)->get()
            ->reverse()->map(fn ($l) => ['id' => $l->id, 'who' => $l->user?->name ?? 'Someone', 'me' => $l->user_id, 'text' => $l->text])->values()->all();
    }

    /** What the call page needs on each check-in. */
    public function state(Call $call, User $me, int $captionsAfter): array
    {
        $rec = $this->active($call);

        return [
            'recording' => $rec ? ['id' => $rec->id, 'by' => $rec->user?->name, 'mine' => $rec->user_id === $me->id] : null,
            'transcribing' => (bool) $call->transcribing_by,
            'captions' => $call->transcribing_by || $captionsAfter ? $this->captions($call, $captionsAfter) : [],
        ];
    }

    public function transcriptText(Call $call): string
    {
        return $call->transcript()->with('user:id,name')->get()
            ->map(fn ($l) => '[' . $l->spoken_at->format('H:i:s') . '] ' . ($l->user?->name ?? 'Someone') . ': ' . $l->text)->implode("\n");
    }

    // ── Recap ───────────────────────────────────────────────────────────

    /** Everyone who joined, with how long they were in. */
    public function attendance(Call $call): Collection
    {
        return CallPerson::with('user')->where('call_id', $call->id)->whereNotNull('joined_at')->orderBy('joined_at')->get();
    }

    /** Turn the recap into a shared note in the chat (once; later calls open the same note). */
    public function makeNote(Call $call, User $me): Note
    {
        if ($call->note_id && ($note = Note::find($call->note_id))) return $note;
        $c = $call->conversation;
        $people = $this->attendance($call)->map(fn ($p) => $p->user?->name)->filter()->unique()->implode(', ');
        $lines = $call->transcript()->with('user:id,name')->get();

        $body = '**When:** ' . $call->started_at->format('j M Y, H:i') . ($call->ended_at ? ' to ' . $call->ended_at->format('H:i') : '') . ' (' . $call->durationLabel() . ")\n"
            . '**Who came:** ' . ($people ?: '-') . "\n"
            . ($call->recordings()->where('status', 'ready')->exists() ? '**Recording:** [open the recap](' . route('connect.calls.recap', $call) . ")\n" : '')
            . "\n## Summary\n\nWhat was discussed.\n\n## Decisions\n\n- \n\n## Action items\n\n- [ ] Who does what, by when\n";
        if ($lines->isNotEmpty()) {
            $transcript = $lines->map(fn ($l) => '**' . ($l->user?->name ?? 'Someone') . '** (' . $l->spoken_at->format('H:i') . '): ' . $l->text)->implode("\n\n");
            $body .= "\n## Transcript\n\n" . mb_substr($transcript, 0, NoteService::MAX - mb_strlen($body) - 200);
        }

        $title = mb_substr(($call->is_huddle ? 'Huddle' : 'Call') . ' notes · ' . $c->titleFor($me) . ' · ' . $call->started_at->format('j M'), 0, 150);
        $note = app(NoteService::class)->create($c, $me, $title, $body);
        $call->update(['note_id' => $note->id]);

        return $note;
    }

    // ── Helpers ─────────────────────────────────────────────────────────

    protected function isIn(Call $call, User $me): bool
    {
        return CallPerson::where('call_id', $call->id)->where('user_id', $me->id)->whereNotNull('joined_at')->whereNull('left_at')->exists();
    }

    protected function announce(Conversation $c, string $text): void
    {
        $m = Message::create(['conversation_id' => $c->id, 'body' => $text, 'is_system' => true]);
        $c->update(['last_message_id' => $m->id, 'last_message_at' => $m->created_at]);
    }

    protected function check(bool $failed, string $field, string $message): void
    {
        if ($failed) throw ValidationException::withMessages([$field => $message]);
    }
}
