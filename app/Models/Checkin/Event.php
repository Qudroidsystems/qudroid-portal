<?php

namespace App\Models\Checkin;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** Something people check in to: a class session, a staff day, a meeting or an event. */
class Event extends Model
{
    protected $table = 'checkin_events';

    public const KINDS = [
        'class' => ['Class', 'ri-book-open-line'],
        'staff' => ['Staff day', 'ri-building-line'],
        'meeting' => ['Meeting', 'ri-video-chat-line'],
        'event' => ['Event', 'ri-calendar-event-line'],
    ];

    public const AUDIENCES = [
        'invited' => 'Only people I add',
        'staff' => 'All staff',
        'students' => 'All students',
        'everyone' => 'Anyone with a portal login',
        'registered' => 'People who registered',
    ];

    protected $fillable = ['kind', 'title', 'description', 'subject_type', 'subject_id', 'place_id', 'day', 'location', 'lat', 'lng', 'radius_m', 'require_location',
        'starts_at', 'ends_at', 'opens_minutes', 'late_minutes', 'audience', 'allow_screen', 'allow_badge', 'public_registration', 'slug', 'capacity',
        'registration_closes_at', 'status', 'secret', 'created_by'];

    protected $hidden = ['secret'];

    protected $casts = ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'registration_closes_at' => 'datetime', 'day' => 'date',
        'require_location' => 'boolean', 'allow_screen' => 'boolean', 'allow_badge' => 'boolean', 'public_registration' => 'boolean', 'lat' => 'float', 'lng' => 'float'];

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class, 'place_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function people(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'checkin_event_user', 'event_id', 'user_id')->withPivot('role')->withTimestamps();
    }

    public function guests(): HasMany
    {
        return $this->hasMany(Guest::class, 'event_id');
    }

    public function records(): HasMany
    {
        return $this->hasMany(Record::class, 'event_id');
    }

    public function kindLabel(): string
    {
        return self::KINDS[$this->kind][0] ?? ucfirst($this->kind);
    }

    public function icon(): string
    {
        return self::KINDS[$this->kind][1] ?? 'ri-qr-code-line';
    }

    public function opensAt(): \Illuminate\Support\Carbon
    {
        return $this->starts_at->copy()->subMinutes($this->opens_minutes);
    }

    public function lateAt(): \Illuminate\Support\Carbon
    {
        return $this->starts_at->copy()->addMinutes($this->late_minutes);
    }

    /** Check-in closes at the end (or 12 hours after the start when there is no end). */
    public function closesAt(): \Illuminate\Support\Carbon
    {
        return ($this->ends_at ?? $this->starts_at->copy()->addHours(12))->copy();
    }

    public function isOpenNow(): bool
    {
        return $this->status === 'open' && now()->between($this->opensAt(), $this->closesAt());
    }

    public function whenLabel(): string
    {
        $s = $this->starts_at;
        $e = $this->ends_at;
        // Staff days stay open after hours for clocking out; show the working hours.
        if ($this->kind === 'staff' && $this->place) return $s->format('D j M Y') . ', ' . $this->place->hoursLabel();

        return $s->format('D j M Y, g:i A') . ($e ? ' – ' . ($e->isSameDay($s) ? $e->format('g:i A') : $e->format('D j M, g:i A')) : '');
    }

    public function hasLocation(): bool
    {
        return $this->lat !== null && $this->lng !== null;
    }
}
