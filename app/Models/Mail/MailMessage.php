<?php

namespace App\Models\Mail;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MailMessage extends Model
{
    protected $fillable = ['mail_account_id', 'mail_folder_id', 'uid', 'message_id', 'in_reply_to', 'references', 'thread_key', 'subject', 'from_name', 'from_email',
        'to', 'cc', 'bcc', 'reply_to', 'sent_at', 'snippet', 'body_text', 'body_html', 'has_attachments', 'size', 'seen', 'flagged', 'answered', 'snoozed_until'];

    protected $casts = ['to' => 'array', 'cc' => 'array', 'bcc' => 'array', 'reply_to' => 'array', 'sent_at' => 'datetime', 'snoozed_until' => 'datetime',
        'has_attachments' => 'boolean', 'seen' => 'boolean', 'flagged' => 'boolean', 'answered' => 'boolean'];

    public function account(): BelongsTo
    {
        return $this->belongsTo(MailAccount::class, 'mail_account_id');
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(MailFolder::class, 'mail_folder_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(MailAttachment::class);
    }

    public function fromLabel(): string
    {
        return $this->from_name ?: ($this->from_email ?: '(unknown sender)');
    }

    /** "Ada, Ben" from a list of [email, name] addresses. */
    public static function names(?array $list): string
    {
        return collect($list ?? [])->map(fn ($a) => ($a['name'] ?? '') ?: ($a['email'] ?? ''))->filter()->implode(', ');
    }
}
