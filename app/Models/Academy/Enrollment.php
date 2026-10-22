<?php

namespace App\Models\Academy;

use App\Models\Finance\Invoice;
use App\Models\User;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A student's place on a cohort. Changes go through EnrollmentService so the
 * rules (eligibility, seats, fee snapshot, access) are applied in one place.
 */
class Enrollment extends Model
{
    public const STATUSES = [
        'pending'   => 'Awaiting payment',
        'active'    => 'Active',
        'completed' => 'Completed',
        'withdrawn' => 'Withdrawn',
        'cancelled' => 'Cancelled',
    ];

    /** Statuses that occupy a seat on the cohort. */
    public const HOLDS_SEAT = ['pending', 'active'];

    protected $table = 'academy_enrollments';

    protected $fillable = [
        'student_id', 'cohort_id', 'course_id', 'status', 'fee_amount', 'discount_amount', 'currency',
        'access_granted', 'access_granted_at', 'access_granted_by', 'access_reason', 'prerequisites_waived',
        'enrolled_at', 'enrolled_by', 'completed_at', 'ended_at', 'notes', 'progress_percent', 'grade_percent', 'last_activity_at',
    ];

    protected $casts = [
        'fee_amount'           => 'decimal:2',
        'discount_amount'      => 'decimal:2',
        'access_granted'       => 'boolean',
        'prerequisites_waived' => 'boolean',
        'access_granted_at'    => 'datetime',
        'enrolled_at'          => 'datetime',
        'completed_at'         => 'datetime',
        'ended_at'             => 'datetime',
        'last_activity_at'     => 'datetime',
        'progress_percent'     => 'integer',
        'grade_percent'        => 'decimal:2',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function cohort(): BelongsTo
    {
        return $this->belongsTo(Cohort::class, 'cohort_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'course_id')->withTrashed();
    }

    public function enrolledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enrolled_by');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'enrollment_id');
    }

    public function progress(): HasMany
    {
        return $this->hasMany(LessonProgress::class, 'enrollment_id');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(Attempt::class, 'enrollment_id');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class, 'enrollment_id');
    }

    public function certificate(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Certificate::class, 'enrollment_id')->latestOfMany();
    }

    /** Whether the student can open the full course (completed students keep access to revise). */
    public function canLearn(): bool
    {
        return $this->access_granted && in_array($this->status, ['active', 'completed'], true);
    }

    public function accessGrantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'access_granted_by');
    }

    /** What the student owes for this enrollment, after discount. */
    public function netFee(): string
    {
        return Money::major(max(0, Money::minor($this->fee_amount) - Money::minor($this->discount_amount)));
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::HOLDS_SEAT, true);
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }
}
