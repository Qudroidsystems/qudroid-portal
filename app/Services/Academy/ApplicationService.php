<?php

namespace App\Services\Academy;

use App\Models\Academy\Application;
use App\Models\Academy\Cohort;
use App\Models\Academy\Student;
use App\Models\User;
use App\Services\Messaging\PortalNotifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Online applications.
 *
 *  - Anyone can apply for a cohort that is taking applications. A new
 *    applicant gets a student account (status "applicant") and an e-mail
 *    to set their password. An e-mail that already has an account must sign
 *    in first, so nobody can attach an application to someone else's account.
 *  - Academy staff admit or decline. Admitting admits the student and puts
 *    them on the cohort they chose (which issues the fee invoice). If the
 *    enrollment can't be made (e.g. the cohort filled up), the student stays
 *    admitted and staff see why, so they can enroll them on another cohort.
 */
class ApplicationService
{
    public function __construct(protected EnrollmentService $enrollments) {}

    /** A new person applying from the public page. */
    public function applyAsGuest(Cohort $cohort, array $person, array $answers, ?string $ip = null): Application
    {
        $email = Str::lower(trim($person['email']));
        $this->check(User::where('email', $email)->exists(), 'email',
            'There is already an account with this e-mail. Sign in, then apply from My academy.');

        $application = DB::transaction(function () use ($cohort, $person, $email, $answers, $ip) {
            $user = User::create([
                'name'         => trim($person['name']),
                'email'        => $email,
                'phone_number' => $person['phone_number'] ?? null,
                'gender'       => $person['gender'] ?? null,
                'user_type'    => User::TYPE_STUDENT,
                'password'     => Str::password(24),
            ]);
            $user->assignRole('Student');   // they choose a password from the e-mail link

            $student = Student::create([
                'user_id'         => $user->id,
                'student_no'      => Student::nextNumber(),
                'status'          => 'applicant',
                'education_level' => $person['education_level'] ?? null,
                'occupation'      => $person['occupation'] ?? null,
                'referral_source' => $person['referral_source'] ?? null,
            ]);

            return $this->create($student, $cohort, $answers, $ip);
        });

        // Lets them choose a password (and proves they own the e-mail).
        try {
            Password::sendResetLink(['email' => $email]);
        } catch (\Throwable $e) {
            Log::warning('Applicant password e-mail failed', ['email' => $email, 'error' => $e->getMessage()]);
        }

        $this->afterSubmit($application);

        return $application;
    }

    /** A signed-in student applying for another cohort. */
    public function applyAsStudent(Student $student, Cohort $cohort, array $answers, ?string $ip = null): Application
    {
        $application = DB::transaction(fn () => $this->create($student, $cohort, $answers, $ip));
        $this->afterSubmit($application);

        return $application;
    }

    /**
     * Admit: the student is admitted (if not already) and enrolled on the
     * chosen cohort. Returns the application and, if the enrollment could
     * not be made, the reason.
     *
     * @return array{0: Application, 1: ?string}
     */
    public function admit(Application $application, User $by, ?string $note = null): array
    {
        $this->check($application->status !== 'submitted', 'status', "This application is already {$application->status_label}.");

        DB::transaction(function () use ($application, $by, $note) {
            $student = $application->student;
            if ($student->status === 'applicant') {
                $student->update(['status' => 'admitted', 'admitted_at' => now(), 'admitted_by' => $by->id]);
            }
            $application->update([
                'status' => 'admitted', 'reviewed_by' => $by->id, 'reviewed_at' => now(),
                'decision_note' => $this->clean($note),
            ]);
        });

        $problem = null;
        try {
            $enrollment = $this->enrollments->enroll($application->student->fresh(), $application->cohort, $by,
                ['notes' => 'From application ' . $application->reference]);
            $application->update(['enrollment_id' => $enrollment->id]);
        } catch (ValidationException $e) {
            $problem = collect($e->errors())->flatten()->first();
        }

        PortalNotifier::toUsers([$application->student->user_id], 'Application accepted: ' . $application->course->title,
            'Congratulations, you have been admitted to ' . config('qudroid.company') . ' Academy.'
            . ($problem ? ' We will confirm your cohort shortly.' : ' Check My academy for your place and fees.'),
            route('academy.my'), 'course');

        return [$application->fresh(), $problem];
    }

    public function decline(Application $application, User $by, string $note): Application
    {
        $this->check($application->status !== 'submitted', 'status', "This application is already {$application->status_label}.");
        $this->check(trim($note) === '', 'decision_note', 'Give a short reason. The applicant will see it.');

        $application->update([
            'status' => 'declined', 'reviewed_by' => $by->id, 'reviewed_at' => now(), 'decision_note' => $this->clean($note),
        ]);

        PortalNotifier::toUsers([$application->student->user_id], 'Your application for ' . $application->course->title,
            'We are unable to offer you a place this time. ' . $application->decision_note, route('academy.my'), 'course');

        return $application;
    }

    /** The applicant changed their mind. */
    public function withdraw(Application $application): Application
    {
        $this->check($application->status !== 'submitted', 'status', "This application is already {$application->status_label}.");
        $application->update(['status' => 'withdrawn']);

        return $application;
    }

    public static function nextReference(): string
    {
        $prefix = 'APP-' . now()->year . '-';
        $last = DB::table('academy_applications')->where('reference', 'like', $prefix . '%')
            ->lockForUpdate()->orderByDesc('reference')->value('reference');
        $n = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }

    protected function create(Student $student, Cohort $cohort, array $answers, ?string $ip): Application
    {
        $cohort = Cohort::with('course')->whereKey($cohort->id)->firstOrFail();
        $this->check(!$cohort->acceptingApplications(), 'cohort_id', 'This cohort is not taking applications.');
        $this->check(
            Application::where('student_id', $student->id)->where('course_id', $cohort->course_id)->where('status', 'submitted')->exists(),
            'cohort_id', 'You already have an application for this course awaiting review.'
        );
        $this->check(
            $student->enrollments()->where('course_id', $cohort->course_id)->whereIn('status', \App\Models\Academy\Enrollment::HOLDS_SEAT)->exists(),
            'cohort_id', 'You are already enrolled on this course.'
        );

        return Application::create([
            'reference'        => self::nextReference(),
            'student_id'       => $student->id,
            'cohort_id'        => $cohort->id,
            'course_id'        => $cohort->course_id,
            'status'           => 'submitted',
            'motivation'       => $this->clean($answers['motivation'] ?? null, 2000),
            'experience_level' => $answers['experience_level'] ?? null,
            'has_laptop'       => isset($answers['has_laptop']) ? (bool) $answers['has_laptop'] : null,
            'ip_address'       => $ip,
        ]);
    }

    protected function afterSubmit(Application $application): void
    {
        $application->loadMissing('student.user', 'course', 'cohort');

        PortalNotifier::toUsers([$application->student->user_id], 'Application received: ' . $application->reference,
            'Thank you for applying for ' . $application->course->title . ' (' . $application->cohort->name . '). We will let you know once it has been reviewed.',
            route('academy.my'), 'course');

        PortalNotifier::toUsers(PortalNotifier::userIdsWithPermission('Manage academy'), 'New application: ' . $application->student->user->name,
            $application->course->title . ' · ' . $application->cohort->name . ' · ' . $application->reference,
            route('academy.applications.show', $application), 'course');
    }

    protected function clean(?string $text, int $max = 500): ?string
    {
        $text = trim((string) $text);

        return $text === '' ? null : mb_substr($text, 0, $max);
    }

    protected function check(bool $failed, string $field, string $message): void
    {
        if ($failed) {
            throw ValidationException::withMessages([$field => $message]);
        }
    }
}
