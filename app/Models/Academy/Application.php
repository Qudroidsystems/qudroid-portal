<?php

namespace App\Models\Academy;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An admission request, reviewed by academy staff. It lists one or more
 * courses (each with the class chosen); course_id and cohort_id are the first.
 */
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
        'has_laptop', 'reviewed_by', 'reviewed_at', 'decision_note', 'enrollment_id', 'invoice_id', 'ip_address',
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

    /** The courses on this request, in the order they were chosen. */
    public function choices(): HasMany
    {
        return $this->hasMany(ApplicationCourse::class)->orderBy('id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Finance\Invoice::class);
    }

    /** "Web Development" or "Web Development + 2 more". */
    public function getCoursesLabelAttribute(): string
    {
        $choices = $this->relationLoaded('choices') ? $this->choices : $this->choices()->with('course')->get();
        if ($choices->isEmpty()) {
            return (string) $this->course?->title;
        }
        $first = (string) $choices->first()->course?->title;

        return $choices->count() > 1 ? $first . ' + ' . ($choices->count() - 1) . ' more' : $first;
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }
}
