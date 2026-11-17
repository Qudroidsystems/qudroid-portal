<?php

namespace App\Models\Academy;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * A student's academy record. Name, e-mail, phone, photo, gender, date of
 * birth and address live on the user account; this holds what only the
 * academy needs.
 */
class Student extends Model
{
    public const STATUSES = [
        'applicant' => 'Applicant',
        'admitted'  => 'Admitted',
        'active'    => 'Active',
        'suspended' => 'Suspended',
        'completed' => 'Completed',
        'withdrawn' => 'Withdrawn',
    ];

    /** Statuses that allow a new enrollment. */
    public const CAN_ENROLL = ['admitted', 'active', 'completed'];

    protected $table = 'academy_students';

    protected $fillable = [
        'user_id', 'student_no', 'status', 'education_level', 'institution', 'occupation',
        'emergency_contact_name', 'emergency_contact_phone', 'guardian_name', 'guardian_phone',
        'referral_source', 'notes', 'admitted_at', 'admitted_by',
    ];

    protected $casts = ['admitted_at' => 'datetime'];

    /** Statuses that carry an admission number. */
    public const NUMBERED = ['admitted', 'active', 'suspended', 'completed', 'withdrawn'];

    protected static function booted(): void
    {
        // The admission number is given when someone is admitted, not when they apply.
        static::saving(function (Student $student) {
            if (blank($student->student_no) && in_array($student->status, self::NUMBERED, true)) {
                $student->student_no = self::nextNumber();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function admittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admitted_by');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class, 'student_id')->latest('id');
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class, 'student_id')->latest('id');
    }

    public function canEnroll(): bool
    {
        return in_array($this->status, self::CAN_ENROLL, true);
    }

    /** The admission number, or a note that there isn't one yet. */
    public function getNumberLabelAttribute(): string
    {
        return $this->student_no ?: 'Not admitted yet';
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }

    /** Next admission number, e.g. QCA/2026/0007. Given automatically when a student is saved as admitted. */
    public static function nextNumber(): string
    {
        $prefix = config('qudroid.academy.student_no_prefix', 'QCA') . '/' . now()->year . '/';
        $last = DB::table('academy_students')->where('student_no', 'like', $prefix . '%')
            ->lockForUpdate()->orderByDesc('student_no')->value('student_no');
        $n = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }
}
