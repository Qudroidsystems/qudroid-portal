<?php

namespace App\Models\Academy;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A student's work for an assignment. Resubmitting replaces it until it is graded. */
class Submission extends Model
{
    public const STATUSES = ['submitted' => 'Awaiting grading', 'returned' => 'Returned for changes', 'graded' => 'Graded'];

    protected $table = 'academy_submissions';

    protected $fillable = [
        'assessment_id', 'enrollment_id', 'body', 'file_path', 'file_name', 'status', 'score', 'percent',
        'feedback', 'submitted_at', 'graded_by', 'graded_at',
    ];

    protected $casts = [
        'score'        => 'decimal:2',
        'percent'      => 'decimal:2',
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

    public function isLate(): bool
    {
        $due = $this->assessment->due_at;

        return $due && $this->submitted_at && $this->submitted_at->gt($due);
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }
}
