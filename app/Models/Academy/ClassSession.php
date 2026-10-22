<?php

namespace App\Models\Academy;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One meeting of a cohort, and its register. */
class ClassSession extends Model
{
    public const STATUSES = ['scheduled' => 'Scheduled', 'held' => 'Register taken', 'cancelled' => 'Cancelled'];

    protected $table = 'academy_class_sessions';

    protected $fillable = ['cohort_id', 'date', 'starts_at', 'ends_at', 'topic', 'status', 'cancel_reason', 'register_taken_at', 'register_taken_by'];

    protected $casts = ['date' => 'date', 'register_taken_at' => 'datetime'];

    public function cohort(): BelongsTo
    {
        return $this->belongsTo(Cohort::class);
    }

    public function attendance(): HasMany
    {
        return $this->hasMany(Attendance::class, 'class_session_id');
    }

    public function takenBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'register_taken_by');
    }

    /** "4:00pm – 7:00pm", or null without times. */
    public function timeRange(): ?string
    {
        $fmt = fn ($t) => $t ? \Illuminate\Support\Carbon::parse($t)->format('g:ia') : null;

        return $this->starts_at ? trim($fmt($this->starts_at) . ($this->ends_at ? ' – ' . $fmt($this->ends_at) : '')) : null;
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }
}
