<?php

namespace App\Services\Academy;

use App\Models\Academy\Cohort;
use App\Models\Academy\Enrollment;
use App\Models\Academy\Student;
use App\Models\User;
use App\Services\Finance\InvoiceService;
use App\Services\Messaging\PortalNotifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Every change to an enrollment goes through here.
 *
 * Rules:
 *  - the student must be admitted (or already active/completed);
 *  - the cohort must be open or running, inside its registration window,
 *    and have a free seat;
 *  - prerequisite courses must be completed, unless waived by someone
 *    allowed to grant course access;
 *  - the fee is copied onto the enrollment when it is made;
 *  - course access is granted when the net fee is nil, when Finance
 *    confirms payment, or manually with a reason. Never because a payment
 *    page said "success".
 */
class EnrollmentService
{
    public function __construct(protected InvoiceService $invoices) {}

    /**
     * @param  array{discount_amount?:float|string|null, waive_prerequisites?:bool, notes?:string|null}  $options
     */
    public function enroll(Student $student, Cohort $cohort, User $by, array $options = []): Enrollment
    {
        $enrollment = DB::transaction(function () use ($student, $cohort, $by, $options) {
            // Lock the cohort row so two people can't take the last seat at once.
            $cohort = Cohort::whereKey($cohort->id)->lockForUpdate()->firstOrFail();
            $course = $cohort->course;
            $waive = (bool) ($options['waive_prerequisites'] ?? false);

            $this->check(!$student->canEnroll(), 'student_id', "{$student->user->name} is {$student->status_label}. Admit the student before enrolling them.");
            $this->check($course->trashed() || $course->status === 'archived', 'cohort_id', 'This course has been archived.');
            $this->check(!in_array($cohort->status, Cohort::ENROLLABLE, true), 'cohort_id', "The cohort is {$cohort->status_label}, so it isn't taking registrations.");
            $this->check($cohort->registration_opens_on && today()->lt($cohort->registration_opens_on), 'cohort_id', 'Registration for this cohort opens on ' . $cohort->registration_opens_on?->format('j M Y') . '.');
            $this->check($cohort->registration_closes_on && today()->gt($cohort->registration_closes_on), 'cohort_id', 'Registration for this cohort closed on ' . $cohort->registration_closes_on?->format('j M Y') . '.');

            $existing = Enrollment::where('student_id', $student->id)->where('cohort_id', $cohort->id)->first();
            $this->check($existing && $existing->isOpen(), 'cohort_id', 'The student is already on this cohort.');
            $this->check(
                Enrollment::where('student_id', $student->id)->where('course_id', $course->id)->whereIn('status', Enrollment::HOLDS_SEAT)->exists(),
                'cohort_id',
                "The student already has an open enrollment on {$course->title}."
            );

            if ($cohort->capacity) {
                $this->check($cohort->seatsTaken() >= $cohort->capacity, 'cohort_id', 'This cohort is full.');
            }

            if ($waive) {
                $this->check(!$by->can('Grant course access'), 'waive_prerequisites', 'You are not allowed to waive prerequisites.');
            } else {
                $missing = $course->prerequisites()
                    ->whereNotIn('academy_courses.id', Enrollment::where('student_id', $student->id)->where('status', 'completed')->select('course_id'))
                    ->pluck('title');
                $this->check($missing->isNotEmpty(), 'cohort_id', 'The student must first complete: ' . $missing->implode(', ') . '.');
            }

            $fee = $cohort->effectiveFee();
            $discount = (float) ($options['discount_amount'] ?? 0);
            $this->check($discount < 0 || $discount > (float) $fee, 'discount_amount', 'The discount must be between 0 and the fee.');

            $data = [
                'student_id'           => $student->id,
                'cohort_id'            => $cohort->id,
                'course_id'            => $course->id,
                'status'               => 'pending',
                'fee_amount'           => $fee,
                'discount_amount'      => $discount,
                'currency'             => $course->currency,
                'access_granted'       => false,
                'access_granted_at'    => null,
                'access_granted_by'    => null,
                'access_reason'        => null,
                'prerequisites_waived' => $waive,
                'enrolled_at'          => now(),
                'enrolled_by'          => $by->id,
                'completed_at'         => null,
                'ended_at'             => null,
                'notes'                => $options['notes'] ?? null,
            ];

            // A student who withdrew can be re-enrolled on the same cohort.
            $enrollment = $existing ? tap($existing)->update($data) : Enrollment::create($data);

            if ((float) $enrollment->netFee() <= 0) {
                $this->activate($enrollment, null, 'No fee to pay');
            } else {
                $this->invoices->forEnrollment($enrollment, $by);
            }

            return $enrollment;
        });

        $this->notify($enrollment, 'You are enrolled on ' . $enrollment->course->title,
            $enrollment->access_granted
                ? 'Your place on ' . $enrollment->cohort->name . ' is confirmed and your course access is open.'
                : 'Your place on ' . $enrollment->cohort->name . ' is reserved. Course access opens once your payment is confirmed.');

        return $enrollment->fresh();
    }

    /**
     * Open course access by hand, e.g. an approved instalment plan or a
     * verified bank transfer recorded outside the portal. Needs a reason.
     */
    public function grantAccess(Enrollment $enrollment, User $by, string $reason): Enrollment
    {
        $this->check(!$by->can('Grant course access'), 'reason', 'You are not allowed to grant course access.');
        $this->check(!$enrollment->isOpen(), 'reason', "This enrollment is {$enrollment->status_label}.");
        $this->check($enrollment->access_granted, 'reason', 'Access is already open.');
        $this->check(trim($reason) === '', 'reason', 'Give a reason for opening access.');

        DB::transaction(fn () => $this->activate($enrollment, $by, trim($reason)));

        $this->notify($enrollment, 'Course access open: ' . $enrollment->course->title, 'You can now start ' . $enrollment->course->title . '.');

        return $enrollment->fresh();
    }

    /**
     * Called by Finance when a payment for this enrollment is verified with
     * the payment provider (or a bank transfer is confirmed by Finance).
     */
    public function paymentConfirmed(Enrollment $enrollment, ?User $by = null, string $reference = ''): Enrollment
    {
        if ($enrollment->isOpen() && !$enrollment->access_granted) {
            DB::transaction(fn () => $this->activate($enrollment, $by, trim('Payment confirmed ' . $reference)));
            $this->notify($enrollment, 'Payment confirmed: ' . $enrollment->course->title, 'Your course access is now open.');
        }

        return $enrollment->fresh();
    }

    /**
     * A payment that opened access was reversed and the invoice no longer
     * meets the threshold: lock access again, unless it was opened by hand
     * for another reason (e.g. an approved instalment plan).
     */
    public function paymentReversed(Enrollment $enrollment, string $reason): Enrollment
    {
        if ($enrollment->status === 'active' && $enrollment->access_granted
            && str_starts_with((string) $enrollment->access_reason, 'Payment confirmed')) {
            $enrollment->update([
                'status' => 'pending', 'access_granted' => false, 'access_granted_at' => null,
                'access_reason' => mb_substr($reason, 0, 255),
            ]);
        }

        return $enrollment;
    }

    public function complete(Enrollment $enrollment, User $by): Enrollment
    {
        $this->check($enrollment->status !== 'active', 'status', 'Only an active enrollment can be marked completed.');
        $enrollment->update(['status' => 'completed', 'completed_at' => now()]);

        // A certificate follows straight away when the course awards one and the grade is a pass.
        app(CertificateService::class)->issueIfEligible($enrollment, $by);

        return $enrollment;
    }

    /** Withdraw (the student left) or cancel (made in error / cohort cancelled). Frees the seat and closes access. */
    public function end(Enrollment $enrollment, User $by, string $status, ?string $note = null): Enrollment
    {
        $this->check(!in_array($status, ['withdrawn', 'cancelled'], true), 'status', 'Unknown status.');
        $this->check(!$enrollment->isOpen(), 'status', "This enrollment is already {$enrollment->status_label}.");

        $notes = trim(($enrollment->notes ? $enrollment->notes . "\n" : '') . ($note ? now()->format('j M Y') . ' (' . $by->name . '): ' . $note : ''));
        DB::transaction(function () use ($enrollment, $status, $notes, $by) {
            $enrollment->update(['status' => $status, 'ended_at' => now(), 'access_granted' => false, 'notes' => $notes ?: null]);

            // An unpaid course-fee invoice goes with it. Paid ones stay for Finance (refunds).
            foreach ($enrollment->invoices()->where('status', 'issued')->where('amount_paid', 0)->get() as $invoice) {
                $this->invoices->void($invoice, $by, 'Enrollment ' . $status);
            }
        });

        return $enrollment;
    }

    protected function activate(Enrollment $enrollment, ?User $by, string $reason): void
    {
        $enrollment->update([
            'status'            => 'active',
            'access_granted'    => true,
            'access_granted_at' => now(),
            'access_granted_by' => $by?->id,
            'access_reason'     => mb_substr($reason, 0, 255),
        ]);

        // First active course makes an admitted student an active student.
        $student = $enrollment->student;
        if ($student->status === 'admitted') {
            $student->update(['status' => 'active']);
        }
    }

    protected function notify(Enrollment $enrollment, string $title, string $body): void
    {
        PortalNotifier::toUsers([$enrollment->student->user_id], $title, $body, route('academy.my'), 'course');
    }

    protected function check(bool $failed, string $field, string $message): void
    {
        if ($failed) {
            throw ValidationException::withMessages([$field => $message]);
        }
    }
}
