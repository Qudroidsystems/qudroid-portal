<?php

namespace App\Models\Academy;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One go at a quiz. */
class Attempt extends Model
{
    /** Extra time allowed after a time limit, for slow connections. */
    public const GRACE_SECONDS = 120;

    protected $table = 'academy_attempts';

    protected $fillable = [
        'assessment_id', 'enrollment_id', 'attempt_no', 'answers', 'marks', 'score', 'max_score', 'percent',
        'needs_review', 'timed_out', 'started_at', 'submitted_at', 'graded_by', 'graded_at',
    ];

    protected $casts = [
        'answers'      => 'array',
        'marks'        => 'array',
        'score'        => 'decimal:2',
        'max_score'    => 'decimal:2',
        'percent'      => 'decimal:2',
        'needs_review' => 'boolean',
        'timed_out'    => 'boolean',
        'started_at'   => 'datetime',
        'submitted_at' => 'datetime',
        'graded_at'    => 'datetime',
    ];

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function grader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'graded_by');
    }

    public function isSubmitted(): bool
    {
        return $this->submitted_at !== null;
    }

    /** When the time runs out, or null without a time limit. */
    public function deadline(): ?\Illuminate\Support\Carbon
    {
        $minutes = $this->assessment->time_limit_minutes;

        return $minutes ? $this->started_at->copy()->addMinutes($minutes) : null;
    }

    public function secondsLeft(): ?int
    {
        $deadline = $this->deadline();

        return $deadline ? max(0, now()->diffInSeconds($deadline, false)) : null;
    }
}
