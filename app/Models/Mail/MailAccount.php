<?php

namespace App\Models\Mail;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A mailbox (an e-mail address) the portal reads and sends through IMAP/SMTP. */
class MailAccount extends Model
{
    public const ENCRYPTION = ['ssl' => 'SSL/TLS', 'tls' => 'STARTTLS', 'none' => 'None'];

    protected $fillable = ['email', 'name', 'kind', 'imap_host', 'imap_port', 'imap_encryption', 'smtp_host', 'smtp_port', 'smtp_encryption',
        'username', 'password', 'signature', 'provisioned', 'status', 'last_error', 'last_synced_at', 'created_by'];

    protected $hidden = ['password'];

    protected $casts = ['password' => 'encrypted', 'provisioned' => 'boolean', 'last_synced_at' => 'datetime'];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'mail_account_user')->withPivot('role', 'is_default')->withTimestamps();
    }

    public function folders(): HasMany
    {
        return $this->hasMany(MailFolder::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(MailMessage::class);
    }

    public function folder(string $role): ?MailFolder
    {
        return $this->folders->firstWhere('role', $role) ?? $this->folders()->where('role', $role)->first();
    }

    public function label(): string
    {
        return $this->name ? $this->name . ' <' . $this->email . '>' : $this->email;
    }
}
