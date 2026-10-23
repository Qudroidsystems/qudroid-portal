<?php

namespace App\Models\Mail;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A message being written, or scheduled to send later. Attachments are stored files: [{path, name, mime, size}]. */
class MailDraft extends Model
{
    protected $fillable = ['mail_account_id', 'user_id', 'mode', 'source_message_id', 'to', 'cc', 'bcc', 'subject', 'body_html', 'attachments', 'send_at', 'status', 'error'];

    protected $casts = ['attachments' => 'array', 'send_at' => 'datetime'];

    public function account(): BelongsTo
    {
        return $this->belongsTo(MailAccount::class, 'mail_account_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(MailMessage::class, 'source_message_id');
    }
}
