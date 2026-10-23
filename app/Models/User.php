<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * One account per person, whatever their relationship with Qudroid:
 * staff, students, clients and partners all sign in here. `user_type`
 * says which part of the portal is their home; roles and permissions
 * (spatie/laravel-permission) say what they may do.
 *
 * Module records (employee files, student records, client accounts …)
 * link back to users.id rather than copying names and e-mails.
 */
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    public const TYPE_STAFF   = 'staff';
    public const TYPE_STUDENT = 'student';
    public const TYPE_CLIENT  = 'client';
    public const TYPE_PARTNER = 'partner';

    /** Folder on the "public" disk that holds profile photos. */
    public const AVATAR_DIR = 'avatars';

    protected $fillable = [
        'name',
        'email',
        'username',
        'phone_number',
        'user_type',
        'avatar',
        'gender',
        'date_of_birth',
        'job_title',
        'address',
        'bio',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at'    => 'datetime',
            'date_of_birth'        => 'date',
            'must_change_password' => 'boolean',
            'is_disabled'          => 'boolean',
            'last_login_at'        => 'datetime',
            'last_seen_at'         => 'datetime',
            'presence_until'       => 'datetime',
            'status_until'         => 'datetime',
            'password'             => 'hashed',
            'muted_notifications'  => 'array',
        ];
    }

    // ── Account type ───────────────────────────────────────────────────

    public function isStaff(): bool
    {
        return $this->user_type === self::TYPE_STAFF;
    }

    public function isStudent(): bool
    {
        return $this->user_type === self::TYPE_STUDENT;
    }

    public function isClient(): bool
    {
        return $this->user_type === self::TYPE_CLIENT;
    }

    public function isPartner(): bool
    {
        return $this->user_type === self::TYPE_PARTNER;
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(config('qudroid.super_admin_role', 'Super Administrator'));
    }

    public function isActive(): bool
    {
        return !$this->is_disabled;
    }

    public function getUserTypeLabelAttribute(): string
    {
        return config('qudroid.user_types.' . $this->user_type, ucfirst((string) $this->user_type));
    }

    /** The HR record, for staff on the payroll. */
    /** Qmail mailboxes this person can read and send from. */
    public function mailAccounts(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(\App\Models\Mail\MailAccount::class, 'mail_account_user')->withPivot('role', 'is_default')->withTimestamps();
    }

    public function employee(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(\App\Models\Hr\Employee::class);
    }

    // ── Names and photo ────────────────────────────────────────────────

    public function getFirstNameAttribute(): string
    {
        return explode(' ', trim((string) $this->name))[0] ?? '';
    }

    public function getLastNameAttribute(): string
    {
        $parts = preg_split('/\s+/', trim((string) $this->name));
        return count($parts) > 1 ? end($parts) : '';
    }

    public function getInitialsAttribute(): string
    {
        $parts = preg_split('/\s+/', trim((string) $this->name)) ?: [];
        $initials = collect($parts)->filter()->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->take(2)->implode('');
        return $initials !== '' ? $initials : 'U';
    }

    /** Public URL of the profile photo, or null when there is none. */
    public function getAvatarPathUrlAttribute(): ?string
    {
        if (!$this->avatar) return null;
        $path = self::AVATAR_DIR . '/' . basename($this->avatar);
        return Storage::disk('public')->exists($path) ? asset('storage/' . $path) : null;
    }

    /** Profile photo, or a generated initials picture. Always returns a URL. */
    public function getAvatarUrlAttribute(): string
    {
        return $this->avatar_path_url
            ?? 'https://ui-avatars.com/api/?name=' . urlencode((string) $this->name) . '&color=FFFFFF&background=' . ltrim((string) config('qudroid.primary_color', '#405189'), '#');
    }

    public function getFormattedDobAttribute(): ?string
    {
        return $this->date_of_birth?->format('d M Y');
    }

    // ── Scopes ─────────────────────────────────────────────────────────

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('user_type', $type);
    }

    public function scopeStaff(Builder $query): Builder
    {
        return $query->where('user_type', self::TYPE_STAFF);
    }

    public function scopeStudents(Builder $query): Builder
    {
        return $query->where('user_type', self::TYPE_STUDENT);
    }

    public function scopeClients(Builder $query): Builder
    {
        return $query->where('user_type', self::TYPE_CLIENT);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_disabled', false);
    }
}
