<?php

namespace App\Models\Academy;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** An application to join a cohort, reviewed by academy staff. */
class Application extends Model
{
    public const STATUSES = [
        'submitted' => 'Awaiting review',
        'admitted'  => 'Admitted',
        'declined'  => 'Declined',
        'withdrawn' => 'Withdrawn',
    ];

    public const EXPERIENCE = [
        'none'        => 'New to coding',
        'some'        => 'Some experience',
        'experienced' => 'Experienced',
    ];

    protected $table = 'academy_applications';

    protected $fillable = [
        'reference', 'student_id', 'cohort_id', 'course_id', 'status', 'motivation', 'experience_level',
        'has_laptop', 'reviewed_by', 'reviewed_at', 'decision_note', 'enrollment_id', 'ip_address',
    ];

    protected $casts = ['reviewed_at' => 'datetime', 'has_laptop' => 'boolean'];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function cohort(): BelongsTo
    {
        return $this->belongsTo(Cohort::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class)->withTrashed();
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }
}
