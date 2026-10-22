<?php

namespace App\Models\Academy;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A course certificate. The snapshot keeps the name, course and grade as
 * they were when it was issued, so later edits never change a certificate.
 */
class Certificate extends Model
{
    protected $table = 'academy_certificates';

    protected $fillable = [
        'enrollment_id', 'student_id', 'course_id', 'serial', 'verify_token', 'status', 'snapshot',
        'issued_at', 'issued_by', 'revoked_at', 'revoked_by', 'revoke_reason', 'verify_count',
    ];

    protected $casts = ['snapshot' => 'array', 'issued_at' => 'datetime', 'revoked_at' => 'datetime'];

    protected $hidden = ['verify_token'];

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class)->withTrashed();
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function revoker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    public function isValid(): bool
    {
        return $this->status === 'issued';
    }

    public function verifyUrl(): string
    {
        return route('certificates.verify', $this->verify_token);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return data_get($this->snapshot, $key, $default);
    }
}
