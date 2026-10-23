<?php

namespace App\Services\Connect;

use App\Models\Connect\Conversation;
use App\Models\Connect\Message;
use App\Models\Connect\Note;
use App\Models\Connect\NoteRevision;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Shared notes in a chat: anyone in the conversation can write and edit them.
 * Saves are checked against the version the editor started from, so two
 * people never silently overwrite each other; every save keeps a revision.
 */
class NoteService
{
    public const MAX = 200_000;

    public function __construct(protected ConnectService $connect) {}

    public function canEdit(Note $note, User $me): bool
    {
        return $this->connect->participant($note->conversation, $me) !== null && $note->conversation->archived_at === null;
    }

    public function canDelete(Note $note, User $me): bool
    {
        return $note->created_by === $me->id || $this->connect->isAdmin($note->conversation, $me);
    }

    public function create(Conversation $c, User $me, string $title, ?string $body = null): Note
    {
        $title = trim($title);
        $this->check(!$this->connect->participant($c, $me), 'title', 'Join the conversation to add notes.');
        $this->check($title === '' || mb_strlen($title) > 150, 'title', 'Give the note a title of up to 150 characters.');
        $this->check(mb_strlen((string) $body) > self::MAX, 'body', 'That note is too long.');

        return DB::transaction(function () use ($c, $me, $title, $body) {
            $note = Note::create(['conversation_id' => $c->id, 'title' => $title, 'body' => $body, 'created_by' => $me->id, 'updated_by' => $me->id]);
            NoteRevision::create(['note_id' => $note->id, 'version' => 1, 'title' => $title, 'body' => $body, 'user_id' => $me->id]);
            $m = Message::create(['conversation_id' => $c->id, 'body' => $me->name . ' added a note: ' . $title, 'is_system' => true]);
            $c->update(['last_message_id' => $m->id, 'last_message_at' => $m->created_at]);

            return $note;
        });
    }

    /**
     * Save a new version. $base is the version the editor loaded; if someone saved since,
     * this throws (code "conflict") and nothing is written.
     */
    public function save(Note $note, User $me, string $title, ?string $body, int $base): Note
    {
        $title = trim($title);
        $this->check(!$this->canEdit($note, $me), 'body', 'You can no longer edit this note.');
        $this->check($title === '' || mb_strlen($title) > 150, 'title', 'Give the note a title of up to 150 characters.');
        $this->check(mb_strlen((string) $body) > self::MAX, 'body', 'That note is too long.');
        if ($title === $note->title && (string) $body === (string) $note->body) return $note;

        return DB::transaction(function () use ($note, $me, $title, $body, $base) {
            $updated = Note::whereKey($note->id)->where('version', $base)->update([
                'title' => $title, 'body' => $body, 'version' => $base + 1, 'updated_by' => $me->id, 'updated_at' => now(),
            ]);
            if (!$updated) {
                throw ValidationException::withMessages(['version' => 'Someone else saved this note while you were editing.'])->errorBag('conflict');
            }
            // Keep a revision per save, but fold quick successive autosaves by the same person into one.
            $last = NoteRevision::where('note_id', $note->id)->orderByDesc('version')->first();
            if ($last && $last->user_id === $me->id && $last->created_at?->gt(now()->subMinutes(10)) && $last->version === $base && $base > 1) {
                $last->update(['version' => $base + 1, 'title' => $title, 'body' => $body]);
            } else {
                NoteRevision::create(['note_id' => $note->id, 'version' => $base + 1, 'title' => $title, 'body' => $body, 'user_id' => $me->id]);
            }

            return $note->refresh();
        });
    }

    public function restore(Note $note, NoteRevision $rev, User $me): Note
    {
        $this->check($rev->note_id !== $note->id, 'version', 'That version is not from this note.');

        return $this->save($note, $me, $rev->title, $rev->body, $note->version);
    }

    public function togglePin(Note $note, User $me): Note
    {
        $this->check(!$this->canEdit($note, $me), 'note', 'You cannot change this note.');
        $note->forceFill(['pinned' => !$note->pinned])->save();

        return $note;
    }

    public function delete(Note $note, User $me): void
    {
        $this->check(!$this->canDelete($note, $me), 'note', 'Only the person who wrote the note, or an admin, can delete it.');
        $note->delete();
    }

    /** Who has the note open in the editor now (first names), besides me. */
    public function editing(Note $note, User $me, bool $heartbeat = false): array
    {
        $key = 'cx:note-editing:' . $note->id;
        $now = now()->timestamp;
        $list = array_filter((array) Cache::get($key, []), fn ($e) => ($e['at'] ?? 0) > $now - 20);
        if ($heartbeat) {
            $list[$me->id] = ['name' => $me->first_name ?: $me->name, 'at' => $now];
            Cache::put($key, $list, 60);
        }
        unset($list[$me->id]);

        return array_values(array_map(fn ($e) => $e['name'], $list));
    }

    protected function check(bool $failed, string $field, string $message): void
    {
        if ($failed) throw ValidationException::withMessages([$field => $message]);
    }
}
