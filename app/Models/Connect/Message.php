<?php

namespace App\Models\Connect;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Message extends Model
{
    protected $table = 'connect_messages';

    protected $fillable = ['conversation_id', 'user_id', 'body', 'mentions', 'reply_to_id', 'thread_id', 'reply_count', 'last_reply_at',
        'attachment_path', 'attachment_name', 'attachment_mime', 'attachment_size', 'is_system', 'edited_at', 'deleted_at', 'pinned_at', 'pinned_by'];

    protected $casts = ['is_system' => 'boolean', 'edited_at' => 'datetime', 'deleted_at' => 'datetime', 'last_reply_at' => 'datetime',
        'pinned_at' => 'datetime', 'mentions' => 'array'];

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
        $text = trim((string) $this->body);
        if ($text === '' && $this->attachment_path) $text = ($this->isImage() ? 'Photo: ' : 'File: ') . $this->attachment_name;

        return \Illuminate\Support\Str::limit(preg_replace('/\s+/', ' ', $text), $limit);
    }
}
