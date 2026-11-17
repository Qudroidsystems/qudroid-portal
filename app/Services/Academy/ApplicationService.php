<?php

namespace App\Services\Academy;

use App\Mail\AcademyMail;
use App\Models\Academy\Application;
use App\Models\Academy\ApplicationCourse;
use App\Models\Academy\Cohort;
use App\Models\Academy\Student;
use App\Models\User;
use App\Services\Messaging\PortalNotifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;

/**
 * Admission requests (online applications).
 *
 *  - Anyone can apply for one or more courses (the website's course cart),
 *    choosing a class (cohort) for each that is taking applications. A new
 *    applicant gets a student account (status "applicant", no admission
 *    number yet) and an e-mail to set their password. An e-mail that already
 *    has an account must sign in first, so nobody can attach a request to
 *    someone else's account.
 *  - Academy staff admit or decline the request. Admitting gives the student
 *    their admission number, puts them on the classes they chose (staff can
 *    leave some out), issues one invoice for all of them and e-mails the
 *    student with a link to pay. If a class can't take them (e.g. it filled
 *    up), the student stays admitted and staff see why, so they can enroll
 *    them on another class.
 */
class ApplicationService
{
    /** Most courses on one request. */
    public const MAX_COURSES = 10;

    public function __construct(protected EnrollmentService $enrollments, protected \App\Services\Finance\InvoiceService $invoices) {}

    /**
     * A new person applying from the public pages.
     *
     * @param  Cohort|iterable<Cohort>  $cohorts
     */
    public function applyAsGuest(Cohort|iterable $cohorts, array $person, array $answers, ?string $ip = null): Application
    {
        $email = Str::lower(trim($person['email']));
        $this->check(User::where('email', $email)->exists(), 'email',
            'There is already an account with this e-mail. Sign in, then register.');
        $cohorts = $this->cohorts($cohorts);

        $application = DB::transaction(function () use ($cohorts, $person, $email, $answers, $ip) {
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
                'status'          => 'applicant',   // the admission number comes with admission
                'education_level' => $person['education_level'] ?? null,
                'occupation'      => $person['occupation'] ?? null,
                'referral_source' => $person['referral_source'] ?? null,
            ]);

            return $this->create($student, $cohorts, $answers, $ip);
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

    /**
     * A signed-in student applying for more courses.
     *
     * @param  Cohort|iterable<Cohort>  $cohorts
     */
    public function applyAsStudent(Student $student, Cohort|iterable $cohorts, array $answers, ?string $ip = null): Application
    {
        $cohorts = $this->cohorts($cohorts);
        $application = DB::transaction(fn () => $this->create($student, $cohorts, $answers, $ip));
        $this->afterSubmit($application);

        return $application;
    }

    /**
     * Admit: the student is admitted (and given their admission number if they
     * have none), enrolled on the classes chosen, and sent one invoice for
     * them all. $choiceIds limits it to some of the courses; the rest are
     * marked "not offered". Returns the application and, if any enrollment
     * could not be made, the reasons.
     *
     * @param  array<int>|null  $choiceIds
     * @return array{0: Application, 1: ?string}
     */
    public function admit(Application $application, User $by, ?string $note = null, ?array $choiceIds = null): array
    {
        $this->check($application->status !== 'submitted', 'status', "This application is already {$application->status_label}.");
        $choices = $application->choices()->with('cohort', 'course')->get();
        $chosen = $choiceIds === null ? $choices : $choices->whereIn('id', array_map('intval', $choiceIds));
        $this->check($chosen->isEmpty(), 'choices', 'Tick at least one course to admit the student to.');

        DB::transaction(function () use ($application, $by, $note, $choices, $chosen) {
            $student = Student::whereKey($application->student_id)->lockForUpdate()->first();
            if ($student->status === 'applicant') {
                $student->update(['status' => 'admitted', 'admitted_at' => now(), 'admitted_by' => $by->id]);
            } elseif (blank($student->student_no)) {
                $student->update(['student_no' => Student::nextNumber()]);
            }
            foreach ($choices as $choice) {
                $choice->update(['status' => $chosen->contains('id', $choice->id) ? 'admitted' : 'declined']);
            }
            $application->update([
                'status' => 'admitted', 'reviewed_by' => $by->id, 'reviewed_at' => now(),
                'decision_note' => $this->clean($note),
            ]);
        });

        $student = $application->student->fresh();
        $problems = [];
        $enrolled = collect();
        foreach ($chosen as $choice) {
            try {
                $enrollment = $this->enrollments->enroll($student, $choice->cohort, $by,
                    ['notes' => 'From application ' . $application->reference, 'invoice' => false]);
                $choice->update(['enrollment_id' => $enrollment->id, 'problem' => null]);
                $enrolled->push($enrollment);
            } catch (ValidationException $e) {
                $problem = collect($e->errors())->flatten()->first();
                $choice->update(['problem' => mb_substr((string) $problem, 0, 255)]);
                $problems[] = $choice->course->title . ': ' . $problem;
            }
        }

        $invoice = $enrolled->isNotEmpty() ? $this->invoices->forEnrollments($enrolled, $by) : null;
        $application->update([
            'enrollment_id' => $enrolled->first()?->id,
            'invoice_id'    => $invoice?->id,
        ]);
        $application = $application->fresh(['choices.course', 'choices.cohort', 'student.user', 'invoice']);
        $problem = $problems ? implode(' ', $problems) : null;

        PortalNotifier::toUsers([$application->student->user_id], 'Admission granted: ' . $application->student->student_no,
            'Congratulations, you have been admitted to ' . config('qudroid.company') . ' Academy. Your admission number is ' . $application->student->student_no . '.'
            . ($problem ? ' We will confirm some of your classes shortly.' : ' Check My academy for your classes and fees.'),
            route('academy.my'), 'course');
        $this->mailAdmitted($application, $problem !== null);

        return [$application, $problem];
    }

    public function decline(Application $application, User $by, string $note): Application
    {
        $this->check($application->status !== 'submitted', 'status', "This application is already {$application->status_label}.");
        $this->check(trim($note) === '', 'decision_note', 'Give a short reason. The applicant will see it.');

        $application->update([
            'status' => 'declined', 'reviewed_by' => $by->id, 'reviewed_at' => now(), 'decision_note' => $this->clean($note),
        ]);
        $application->choices()->update(['status' => 'declined']);

        PortalNotifier::toUsers([$application->student->user_id], 'Your application for ' . $application->courses_label,
            'We are unable to offer you a place this time. ' . $application->decision_note, route('academy.my'), 'course');

        $user = $application->student->user;
        $this->mail($user->email, new AcademyMail(
            'Your application ' . $application->reference,
            'Hello ' . Str::of($user->name)->before(' ') . ',',
            [
                'Thank you for applying to ' . config('qudroid.company') . ' Academy for ' . $application->courses_label . '.',
                'We are unable to offer you a place this time. ' . $application->decision_note,
                'You are welcome to apply again for a later class.',
            ],
            [], 'See our courses', \Illuminate\Support\Facades\Route::has('site.academy') ? route('site.academy') : null,
        ));

        return $application;
    }

    /** The applicant changed their mind. */
    public function withdraw(Application $application): Application
    {
        $this->check($application->status !== 'submitted', 'status', "This application is already {$application->status_label}.");
        $application->update(['status' => 'withdrawn']);

        return $application;
    }

    /**
     * E-mail the student a link to pay an invoice online (no sign-in needed).
     * Returns the link, so staff can also copy it or send it by WhatsApp.
     */
    public function sendPayLink(\App\Models\Finance\Invoice $invoice, bool $mail = true): string
    {
        $url = self::payUrl($invoice);
        if ($mail && $invoice->user?->email) {
            $user = $invoice->user;
            $this->mail($user->email, new AcademyMail(
                'Pay your fees: invoice ' . $invoice->number,
                'Hello ' . Str::of($user->name)->before(' ') . ',',
                ['Here is a secure link to pay invoice ' . $invoice->number . ' (' . $invoice->title . ') online. You do not need to sign in.'],
                [['Invoice', $invoice->number], ['Amount due', \App\Support\Money::format($invoice->balance(), $invoice->currency)]]
                    + ($invoice->due_date ? [2 => ['Due by', $invoice->due_date->format('j M Y')]] : []),
                'Pay now', $url,
                ['The link works for 30 days. Your receipt is e-mailed to you as soon as the payment is confirmed.'],
            ));
        }

        return $url;
    }

    public static function payUrl(\App\Models\Finance\Invoice $invoice): string
    {
        return URL::temporarySignedRoute('site.pay', now()->addDays(30), ['invoice' => $invoice->id]);
    }

    public static function nextReference(): string
    {
        $prefix = 'APP-' . now()->year . '-';
        $last = DB::table('academy_applications')->where('reference', 'like', $prefix . '%')
            ->lockForUpdate()->orderByDesc('reference')->value('reference');
        $n = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Cohort>  $cohorts
     */
    protected function create(Student $student, \Illuminate\Support\Collection $cohorts, array $answers, ?string $ip): Application
    {
        $cohorts = Cohort::with('course')->whereKey($cohorts->pluck('id'))->get()->sortBy(fn ($c) => $cohorts->search(fn ($x) => $x->id === $c->id))->values();
        $this->check($cohorts->isEmpty(), 'cohort_id', 'Choose at least one course.');
        $this->check($cohorts->count() > self::MAX_COURSES, 'cohort_id', 'You can register for up to ' . self::MAX_COURSES . ' courses at a time.');
        $this->check($cohorts->pluck('course_id')->unique()->count() !== $cohorts->count(), 'cohort_id', 'Choose one class per course.');

        foreach ($cohorts as $cohort) {
            $title = $cohort->course->title;
            $this->check(!$cohort->acceptingApplications(), 'cohort_id', "{$title} ({$cohort->name}) is not taking registrations any more. Remove it and try again.");
            $this->check(
                ApplicationCourse::where('course_id', $cohort->course_id)
                    ->whereHas('application', fn ($q) => $q->where('student_id', $student->id)->where('status', 'submitted'))->exists(),
                'cohort_id', "You already have a registration for {$title} awaiting review."
            );
            $this->check(
                $student->enrollments()->where('course_id', $cohort->course_id)->whereIn('status', \App\Models\Academy\Enrollment::HOLDS_SEAT)->exists(),
                'cohort_id', "You are already enrolled on {$title}."
            );
        }

        $first = $cohorts->first();
        $application = Application::create([
            'reference'        => self::nextReference(),
            'student_id'       => $student->id,
            'cohort_id'        => $first->id,
            'course_id'        => $first->course_id,
            'status'           => 'submitted',
            'motivation'       => $this->clean($answers['motivation'] ?? null, 2000),
            'experience_level' => $answers['experience_level'] ?? null,
            'has_laptop'       => isset($answers['has_laptop']) ? (bool) $answers['has_laptop'] : null,
            'ip_address'       => $ip,
        ]);
        foreach ($cohorts as $cohort) {
            $application->choices()->create(['course_id' => $cohort->course_id, 'cohort_id' => $cohort->id, 'status' => 'pending']);
        }

        return $application;
    }

    /** @return \Illuminate\Support\Collection<int, Cohort> */
    protected function cohorts(Cohort|iterable $cohorts): \Illuminate\Support\Collection
    {
        return $cohorts instanceof Cohort ? collect([$cohorts]) : collect($cohorts)->values();
    }

    protected function mailAdmitted(Application $application, bool $someProblem): void
    {
        $user = $application->student->user;
        $invoice = $application->invoice;
        $money = fn ($v, $c) => \App\Support\Money::format($v, $c);
        $rows = [['Admission number', (string) $application->student->student_no]];
        foreach ($application->choices->where('status', 'admitted') as $choice) {
            $rows[] = [$choice->course->title, $choice->enrollment_id ? $choice->cohort->name . ($choice->cohort->starts_on ? ', starts ' . $choice->cohort->starts_on->format('j M Y') : '') : 'class to be confirmed'];
        }
        if ($invoice && $invoice->balanceMinor() > 0) {
            $rows[] = ['Fees due (invoice ' . $invoice->number . ')', $money($invoice->balance(), $invoice->currency)];
        }
        $declined = $application->choices->where('status', 'declined');

        $this->mail($user->email, new AcademyMail(
            'You are admitted: ' . $application->student->student_no,
            'Hello ' . Str::of($user->name)->before(' ') . ',',
            array_values(array_filter([
                'Congratulations! Your admission request ' . $application->reference . ' has been accepted and you are now a student of ' . config('qudroid.company') . ' Academy.',
                $declined->isNotEmpty() ? 'We could not offer you ' . $declined->map(fn ($c) => $c->course->title)->implode(', ') . ' this time.' : null,
                $someProblem ? 'We will confirm the class for some of your courses shortly.' : null,
                $application->decision_note ? 'Note from the academy: ' . $application->decision_note : null,
            ])),
            $rows,
            $invoice && $invoice->balanceMinor() > 0 ? 'Pay your fees' : 'Open My academy',
            $invoice && $invoice->balanceMinor() > 0 ? self::payUrl($invoice) : route('academy.my'),
            [
                $invoice && $invoice->balanceMinor() > 0 ? 'Course access opens as soon as your payment is confirmed, and your receipt is e-mailed to you.' : 'Your courses are ready in My academy.',
                'Sign in at ' . url('/login') . ' with ' . $user->email . '. If you have not chosen a password yet, use "Forgot password".',
            ],
        ));
    }

    protected function mail(?string $to, \Illuminate\Mail\Mailable $mail): void
    {
        if (!$to) return;
        try {
            Mail::to($to)->send($mail);
        } catch (\Throwable $e) {
            Log::warning('Academy e-mail failed', ['to' => $to, 'subject' => $mail->subjectLine ?? null, 'error' => $e->getMessage()]);
        }
    }

    protected function afterSubmit(Application $application): void
    {
        $application->load('student.user', 'choices.course', 'choices.cohort');
        $list = $application->choices->map(fn ($c) => $c->course->title . ' (' . $c->cohort->name . ')')->implode(', ');

        PortalNotifier::toUsers([$application->student->user_id], 'Application received: ' . $application->reference,
            'Thank you for registering for ' . $list . '. We will let you know once it has been reviewed.',
            route('academy.my'), 'course');

        PortalNotifier::toUsers(PortalNotifier::userIdsWithPermission('Manage academy'), 'New admission request: ' . $application->student->user->name,
            $application->courses_label . ' · ' . $application->reference,
            route('academy.applications.show', $application), 'course');

        $user = $application->student->user;
        $rows = $application->choices->map(fn ($c) => [$c->course->title, $c->cohort->name . ' · ' . \App\Support\Money::format($c->cohort->effectiveFee(), $c->course->currency)])->all();
        $this->mail($user->email, new AcademyMail(
            'We received your registration ' . $application->reference,
            'Hello ' . Str::of($user->name)->before(' ') . ',',
            ['Thank you for registering with ' . config('qudroid.company') . ' Academy. Your reference is ' . $application->reference . '.',
                'We will review it and e-mail you. Once you are admitted you get your admission number and a link to pay your fees.'],
            $rows,
        ));
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
