<?php

namespace App\Services\Academy;

use App\Models\Academy\Certificate;
use App\Models\Academy\Enrollment;
use App\Models\User;
use App\Services\Finance\DocumentNumber;
use App\Services\Messaging\PortalNotifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Course certificates. One valid certificate per completed enrollment. A certificate is never
 * edited; a wrong one is revoked (the verify page then says so) and, if
 * needed, the enrollment gets a new one.
 */
class CertificateService
{
    public function __construct(protected AssessmentService $assessments) {}

    /** Why this enrollment can't have a certificate yet, or null if it can. */
    public function blocker(Enrollment $enrollment): ?string
    {
        $course = $enrollment->course;
        if ($enrollment->status !== 'completed') return 'The enrollment is not completed.';
        if (!$course->certificate_enabled) return 'This course does not award certificates.';
        if (Certificate::where('enrollment_id', $enrollment->id)->where('status', 'issued')->exists()) return 'A certificate has already been issued.';

        $grade = $this->assessments->recompute($enrollment);
        if ($grade !== null && $grade < $course->pass_grade) {
            return 'The grade (' . rtrim(rtrim(number_format($grade, 1), '0'), '.') . '%) is below the pass grade of ' . $course->pass_grade . '%.';
        }

        return null;
    }

    /** Issue if eligible; returns null (and says nothing) when not. Used when an enrollment is completed. */
    public function issueIfEligible(Enrollment $enrollment, ?User $by): ?Certificate
    {
        return $this->blocker($enrollment) ? null : $this->issue($enrollment, $by);
    }

    /**
     * @param  bool  $override  issue even though the grade is below the pass grade (needs Manage certificates)
     */
    public function issue(Enrollment $enrollment, ?User $by, bool $override = false, ?string $note = null): Certificate
    {
        $blocker = $this->blocker($enrollment);
        $gradeOnly = $blocker && str_starts_with($blocker, 'The grade');
        if ($blocker && !($override && $gradeOnly)) {
            throw ValidationException::withMessages(['certificate' => $blocker]);
        }
        if ($override) {
            $this->check(!$by?->can('Manage certificates'), 'You are not allowed to override the pass grade.');
            $this->check(trim((string) $note) === '', 'Give a reason for issuing below the pass grade.');
        }

        $enrollment->loadMissing('student.user', 'course', 'cohort');
        $certificate = DB::transaction(function () use ($enrollment, $by, $override, $note) {
            return Certificate::create([
                'enrollment_id' => $enrollment->id,
                'student_id'    => $enrollment->student_id,
                'course_id'     => $enrollment->course_id,
                'serial'        => DocumentNumber::next('certificate'),
                'verify_token'  => Str::random(40),
                'status'        => 'issued',
                'issued_at'     => now(),
                'issued_by'     => $by?->id,
                'snapshot'      => [
                    'name'         => $enrollment->student->user->name,
                    'student_no'   => $enrollment->student->student_no,
                    'course'       => $enrollment->course->title,
                    'course_code'  => $enrollment->course->code,
                    'cohort'       => $enrollment->cohort->name,
                    'started_on'   => $enrollment->cohort->starts_on?->toDateString(),
                    'completed_on' => ($enrollment->completed_at ?? now())->toDateString(),
                    'grade'        => $enrollment->grade_percent !== null ? (float) $enrollment->grade_percent : null,
                    'duration_weeks' => $enrollment->course->duration_weeks,
                    'override_note'  => $override ? trim($note) : null,
                ],
            ]);
        });

        PortalNotifier::toUsers([$enrollment->student->user_id], 'Your certificate is ready',
            'Congratulations on completing ' . $enrollment->course->title . '. Certificate ' . $certificate->serial . '.',
            route('certificates.show', $certificate), 'certificate');

        return $certificate;
    }

    public function revoke(Certificate $certificate, User $by, string $reason): Certificate
    {
        $this->check(!$certificate->isValid(), 'This certificate is already revoked.');
        $this->check(trim($reason) === '', 'Give a reason. It is shown on the verify page.');

        $certificate->update(['status' => 'revoked', 'revoked_at' => now(), 'revoked_by' => $by->id, 'revoke_reason' => mb_substr(trim($reason), 0, 300)]);

        return $certificate;
    }

    protected function check(bool $failed, string $message): void
    {
        if ($failed) {
            throw ValidationException::withMessages(['certificate' => $message]);
        }
    }
}
