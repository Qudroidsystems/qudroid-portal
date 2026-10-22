<?php

namespace App\Models\Meetings;

use App\Models\Crm\Company;
use App\Models\Projects\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Meeting extends Model
{
    protected $fillable = ['title', 'agenda', 'starts_at', 'ends_at', 'room_id', 'location', 'online_url', 'organizer_id', 'project_id',
        'company_id', 'guests', 'status', 'cancel_reason', 'remind_minutes', 'reminded_at', 'minutes'];

    protected $casts = ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'reminded_at' => 'datetime', 'guests' => 'array'];

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'organizer_id');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function attendees(): HasMany
    {
        return $this->hasMany(Attendee::class);
    }

    /** Meetings this person organises or is invited to. */
    public function scopeFor(Builder $q, User $user): Builder
    {
        return $q->where(fn ($w) => $w->where('organizer_id', $user->id)
            ->orWhereHas('attendees', fn ($a) => $a->where('user_id', $user->id)));
    }

    public function scopeBetween(Builder $q, $from, $to): Builder
    {
        return $q->where('starts_at', '<', $to)->where('ends_at', '>', $from);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('status', 'scheduled');
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function isOver(): bool
    {
        return $this->ends_at->isPast();
    }

    public function whereLabel(): ?string
    {
        return $this->room?->name ?? $this->location ?? ($this->online_url ? 'Online' : null);
    }

    /** "Tue 3 Nov, 10:00–11:00" */
    public function whenLabel(): string
    {
        $s = $this->starts_at;
        $e = $this->ends_at;

        return $s->format('D j M, H:i') . '–' . ($s->isSameDay($e) ? $e->format('H:i') : $e->format('D j M, H:i'));
    }
}
