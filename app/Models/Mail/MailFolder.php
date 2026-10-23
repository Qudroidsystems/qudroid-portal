<?php

namespace App\Models\Mail;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** An IMAP folder. Standard ones carry a role; the rest show as labels. */
class MailFolder extends Model
{
    /** role => [label, icon], in sidebar order */
    public const ROLES = [
        'inbox' => ['Inbox', 'ri-inbox-line'],
        'sent' => ['Sent', 'ri-send-plane-line'],
        'drafts' => ['Drafts', 'ri-draft-line'],
        'archive' => ['All mail', 'ri-archive-line'],
        'junk' => ['Spam', 'ri-spam-2-line'],
        'trash' => ['Bin', 'ri-delete-bin-line'],
    ];

    protected $fillable = ['mail_account_id', 'path', 'name', 'role', 'delimiter', 'uidvalidity', 'last_uid', 'unread', 'total'];

    public function account(): BelongsTo
    {
        return $this->belongsTo(MailAccount::class, 'mail_account_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(MailMessage::class);
    }

    public function label(): string
    {
        return self::ROLES[$this->role][0] ?? $this->name;
    }

    public function icon(): string
    {
        return self::ROLES[$this->role][1] ?? 'ri-price-tag-3-line';
    }
}
