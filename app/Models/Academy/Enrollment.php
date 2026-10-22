<?php

namespace App\Models\Academy;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        'enrolled_at', 'enrolled_by', 'completed_at', 'ended_at', 'notes',
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

    public function accessGrantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'access_granted_by');
    }

    /** What the student owes for this enrollment, after discount. */
    public function netFee(): string
    {
        $kobo = (int) round((float) $this->fee_amount * 100) - (int) round((float) $this->discount_amount * 100);

        return number_format(max(0, $kobo) / 100, 2, '.', '');
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
