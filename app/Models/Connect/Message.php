<?php

namespace App\Models\Connect;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Message extends Model
{
    protected $table = 'connect_messages';

    protected $fillable = ['conversation_id', 'user_id', 'body', 'reply_to_id', 'attachment_path', 'attachment_name', 'attachment_mime',
        'attachment_size', 'is_system', 'edited_at', 'deleted_at'];

    protected $casts = ['is_system' => 'boolean', 'edited_at' => 'datetime', 'deleted_at' => 'datetime'];

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

    public function reactions(): HasMany
    {
        return $this->hasMany(Reaction::class);
    }

    public function isDeleted(): bool
    {
        return $this->deleted_at !== null;
    }

    public function isImage(): bool
    {
        return $this->attachment_path && str_starts_with((string) $this->attachment_mime, 'image/');
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
