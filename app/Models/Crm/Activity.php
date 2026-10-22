<?php

namespace App\Models\Crm;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** A note, call, email or meeting on the timeline, or a follow-up to do. */
class Activity extends Model
{
    public const TYPES = [
        'note'      => ['Note', 'ri-sticky-note-line'],
        'call'      => ['Call', 'ri-phone-line'],
        'email'     => ['Email', 'ri-mail-line'],
        'meeting'   => ['Meeting', 'ri-group-line'],
        'follow_up' => ['Follow-up', 'ri-alarm-line'],
        'stage'     => ['Stage change', 'ri-git-commit-line'],   // written by the portal
    ];

    /** The types people log by hand. */
    public const LOGGABLE = ['note', 'call', 'email', 'meeting', 'follow_up'];

    protected $table = 'crm_activities';

    protected $fillable = ['subject_type', 'subject_id', 'company_id', 'type', 'body', 'due_at', 'done_at', 'assigned_to', 'user_id'];

    protected $casts = ['due_at' => 'datetime', 'done_at' => 'datetime'];

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /** Follow-ups not yet done. */
    public function scopePending(Builder $q): Builder
    {
        return $q->where('type', 'follow_up')->whereNull('done_at');
    }

    public function isOverdue(): bool
    {
        return $this->type === 'follow_up' && !$this->done_at && $this->due_at && $this->due_at->isPast();
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type][0] ?? ucfirst((string) $this->type);
    }

    public function getIconAttribute(): string
    {
        return self::TYPES[$this->type][1] ?? 'ri-information-line';
    }
}
