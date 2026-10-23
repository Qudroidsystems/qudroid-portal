<?php

namespace App\Models\Connect;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Message extends Model
{
    /** Tell open chats and inboxes at once (when Reverb is on); see App\Services\Realtime\Realtime. */
    protected static function booted(): void
    {
        static::saved(function (Message $m) {
            if (!\App\Services\Realtime\Realtime::enabled()) return;
            $new = $m->wasRecentlyCreated;
            \App\Services\Realtime\Realtime::poke('connect.c.' . $m->conversation_id, $new ? 'message' : 'changed', ['id' => $m->id, 'thread_id' => $m->thread_id]);
            if ($new) {
                $ids = Participant::where('conversation_id', $m->conversation_id)->whereNull('left_at')->pluck('user_id')->all();
                \App\Services\Realtime\Realtime::toUsers($ids, 'inbox', ['conversation_id' => $m->conversation_id, 'from' => $m->user_id]);
            }
        });
    }

    protected $table = 'connect_messages';

    protected $fillable = ['conversation_id', 'user_id', 'body', 'kind', 'meta', 'mentions', 'reply_to_id', 'thread_id', 'reply_count', 'last_reply_at',
        'attachment_path', 'attachment_name', 'attachment_mime', 'attachment_size', 'is_system', 'edited_at', 'deleted_at', 'pinned_at', 'pinned_by'];

    protected $casts = ['is_system' => 'boolean', 'edited_at' => 'datetime', 'deleted_at' => 'datetime', 'last_reply_at' => 'datetime',
        'pinned_at' => 'datetime', 'mentions' => 'array', 'meta' => 'array'];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'reply_to_id');
    }

    public function thread(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'thread_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(Message::class, 'thread_id')->orderBy('id');
    }

    public function pinner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pinned_by');
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(Reaction::class);
    }

    public function votes(): HasMany
    {
        return $this->hasMany(PollVote::class);
    }

    public function isPoll(): bool
    {
        return $this->kind === 'poll' && !$this->isDeleted();
    }

    public function isGif(): bool
    {
        return $this->kind === 'gif' && !$this->isDeleted() && !empty($this->meta['url']);
    }

    public function pollClosed(): bool
    {
        return !empty($this->meta['closed_at']);
    }

    /**
     * Poll results: each option with its votes and voters, plus totals.
     *
     * @return array{options: array<int, array{text: string, count: int, pct: int, mine: bool, names: string[]}>, voters: int, total: int}
     */
    public function pollResults(?User $me = null): array
    {
        $votes = $this->relationLoaded('votes') ? $this->votes : $this->votes()->with('user:id,name')->get();
        $total = $votes->count();
        $options = [];
        foreach ($this->meta['options'] ?? [] as $i => $text) {
            $mine = $votes->where('option', $i);
            $options[$i] = [
                'text'  => $text,
                'count' => $mine->count(),
                'pct'   => $total ? (int) round($mine->count() / $total * 100) : 0,
                'mine'  => $me && $mine->contains('user_id', $me->id),
                'names' => $mine->map(fn ($v) => $v->user?->name)->filter()->values()->all(),
            ];
        }

        return ['options' => $options, 'voters' => $votes->pluck('user_id')->unique()->count(), 'total' => $total];
    }

    public function isDeleted(): bool
    {
        return $this->deleted_at !== null;
    }

    public function mentionsUser(User $user): bool
    {
        $m = $this->mentions ?? [];

        return in_array('all', $m, true) || in_array($user->id, $m, true);
    }

    public function isImage(): bool
    {
        return $this->attachment_path && str_starts_with((string) $this->attachment_mime, 'image/');
    }

    /** The body as safe HTML: escaped, links clickable, @mentions highlighted (yours stand out). */
    public function html(?User $me = null): string
    {
        $html = e((string) $this->body);
        $mentions = $this->mentions ?? [];
        if ($mentions) {
            static $names = [];
            $ids = array_values(array_filter($mentions, 'is_int'));
            $missing = array_diff($ids, array_keys($names));
            if ($missing) $names += User::whereIn('id', $missing)->pluck('name', 'id')->all();
            $words = [];
            foreach ($ids as $id) {
                if (!isset($names[$id])) continue;
                $words[e($names[$id])] = $id;
                $words[e(explode(' ', $names[$id])[0])] ??= $id;
            }
            if (in_array('all', $mentions, true)) foreach (['all', 'everyone', 'channel', 'here'] as $w) $words[$w] = 'all';
            uksort($words, fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a)); // longest first, so full names win
            $alt = implode('|', array_map(fn ($w) => preg_quote($w, '~'), array_keys($words)));
            $html = preg_replace_callback('~(^|[^\w&])@(' . $alt . ')(?![\w])~iu', function ($m) use ($words, $me) {
                $id = $words[$m[2]] ?? collect($words)->first(fn ($v, $k) => mb_strtolower($k) === mb_strtolower($m[2]));
                $mine = $me && ($id === 'all' || $id === $me->id);

                return $m[1] . '<span class="cx-mention' . ($mine ? ' me' : '') . '">@' . $m[2] . '</span>';
            }, $html);
        }
        $html = preg_replace('~(https?://[^\s<"]+)~', '<a href="$1" target="_blank" rel="noopener">$1</a>', $html);

        return nl2br($html);
    }

    /** One line for previews and notifications. */
    public function preview(int $limit = 80): string
    {
        if ($this->isDeleted()) return 'Message deleted';
        if ($this->kind === 'poll') return 'Poll: ' . \Illuminate\Support\Str::limit((string) $this->body, $limit - 6);
        if ($this->kind === 'recording') return 'Call recording' . (!empty($this->meta['duration']) ? ' · ' . $this->meta['duration'] : '');
        if ($this->kind === 'gif') return 'GIF' . (!empty($this->meta['title']) ? ': ' . \Illuminate\Support\Str::limit($this->meta['title'], $limit - 5) : '');
        $text = trim((string) $this->body);
        if ($text === '' && $this->attachment_path) $text = ($this->isImage() ? 'Photo: ' : 'File: ') . $this->attachment_name;

        return \Illuminate\Support\Str::limit(preg_replace('/\s+/', ' ', $text), $limit);
    }
}
