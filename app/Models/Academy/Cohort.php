<?php

namespace App\Models\Academy;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One intake of a course, with its own dates, capacity, instructor and (optionally) fee. */
class Cohort extends Model
{
    public const STATUSES = [
        'planned'   => 'Planned',
        'open'      => 'Open for registration',
        'running'   => 'Running',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
    ];

    /** Statuses in which new students can be enrolled. */
    public const ENROLLABLE = ['open', 'running'];

    protected $table = 'academy_cohorts';

    protected $fillable = [
        'course_id', 'code', 'name', 'starts_on', 'ends_on', 'registration_opens_on',
        'registration_closes_on', 'capacity', 'delivery_mode', 'fee_override',
        'instructor_id', 'schedule', 'status',
        'place_id', 'checkin_mode', 'checkin_opens_minutes', 'late_after_minutes', 'auto_absent',
    ];

    /** How students check themselves in to this class's sessions. */
    public const CHECKIN_MODES = [
        'either' => 'Scan the class QR or tap "I\'m here" (GPS)',
        'gps'    => 'Tap "I\'m here" (GPS) only',
        'qr'     => 'Scan the class QR only',
        'off'    => 'No self check-in: the instructor takes the register',
    ];

    protected $casts = [
        'starts_on'              => 'date',
        'ends_on'                => 'date',
        'registration_opens_on'  => 'date',
        'registration_closes_on' => 'date',
        'capacity'               => 'integer',
        'fee_override'           => 'decimal:2',
        'auto_absent'            => 'boolean',
        'checkin_opens_minutes'  => 'integer',
        'late_after_minutes'     => 'integer',
    ];

    /** The campus students must be at to check in. */
    public function place(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Checkin\Place::class, 'place_id');
    }

    public function allowsQr(): bool
    {
        return in_array($this->checkin_mode ?: 'either', ['qr', 'either'], true);
    }

    /** GPS check-in needs a campus with a location. */
    public function allowsGps(): bool
    {
        return in_array($this->checkin_mode ?: 'either', ['gps', 'either'], true) && $this->place && $this->place->lat !== null && $this->place->lng !== null;
    }

    public function selfCheckin(): bool
    {
        return ($this->checkin_mode ?: 'either') !== 'off';
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'course_id')->withTrashed();
    }

    public function instructor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'instructor_id');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(ClassSession::class, 'cohort_id')->orderBy('date')->orderBy('starts_at');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class, 'cohort_id');
    }

    /** Whether the public can apply now: course published, cohort open or running, inside its registration window. */
    public function acceptingApplications(): bool
    {
        return $this->course && $this->course->isPublished() && !$this->course->trashed()
            && in_array($this->status, self::ENROLLABLE, true)
            && (!$this->registration_opens_on || today()->gte($this->registration_opens_on))
            && (!$this->registration_closes_on || today()->lte($this->registration_closes_on))
            && ($this->capacity === null || $this->seatsLeft() > 0);
    }

    public function scopeAcceptingApplications(\Illuminate\Database\Eloquent\Builder $q): \Illuminate\Database\Eloquent\Builder
    {
        return $q->whereIn('status', self::ENROLLABLE)
            ->whereHas('course', fn ($c) => $c->where('status', 'published'))
            ->where(fn ($w) => $w->whereNull('registration_opens_on')->orWhereDate('registration_opens_on', '<=', today()))
            ->where(fn ($w) => $w->whereNull('registration_closes_on')->orWhereDate('registration_closes_on', '>=', today()));
    }

    /** The fee a new enrollment is charged: the cohort's own fee, or the course fee. */
    public function effectiveFee(): string
    {
        return (string) ($this->fee_override ?? $this->course->fee);
    }

    public function effectiveMode(): string
    {
        return $this->delivery_mode ?: $this->course->delivery_mode;
    }

    /** Enrollments holding a seat (pending or active). */
    public function seatsTaken(): int
    {
        return $this->enrollments()->whereIn('status', Enrollment::HOLDS_SEAT)->count();
    }

    public function seatsLeft(): ?int
    {
        return $this->capacity ? max(0, $this->capacity - $this->seatsTaken()) : null;
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }
}
