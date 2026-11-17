<?php

namespace App\Models\Academy;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One course (and the class chosen for it) on an admission request. */
class ApplicationCourse extends Model
{
    public const STATUSES = ['pending' => 'Awaiting review', 'admitted' => 'Admitted', 'declined' => 'Not offered'];

    protected $table = 'academy_application_courses';

    protected $fillable = ['application_id', 'course_id', 'cohort_id', 'enrollment_id', 'status', 'problem'];

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class)->withTrashed();
    }

    public function cohort(): BelongsTo
    {
        return $this->belongsTo(Cohort::class);
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
