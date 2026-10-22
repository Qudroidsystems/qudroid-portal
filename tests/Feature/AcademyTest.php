<?php

use App\Models\Academy\Cohort;
use App\Models\Academy\Course;
use App\Models\Academy\Enrollment;
use App\Models\Academy\Programme;
use App\Models\Academy\Student;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->admin = User::where('email', config('qudroid.super_admin.email'))->first();
    $this->admin->forceFill(['must_change_password' => false])->save();

    $this->course = Course::create(['code' => 'WEB101', 'title' => 'Web Foundations', 'fee' => 150000, 'currency' => 'NGN', 'status' => 'published']);
    $this->cohort = $this->course->cohorts()->create(['code' => 'WEB101-26-01', 'name' => 'Cohort 1', 'status' => 'open', 'capacity' => 2]);
});

function makeStudent(string $status = 'admitted', string $email = null): Student
{
    $user = User::factory()->create(['user_type' => 'student', 'email' => $email ?? fake()->unique()->safeEmail()]);
    $user->assignRole('Student');

    return Student::create(['user_id' => $user->id, 'student_no' => Student::nextNumber(), 'status' => $status]);
}

function enroll($test, Student $student, Cohort $cohort, array $extra = [])
{
    return $test->actingAs($test->admin)->post(route('academy.enrollments.store'), ['student_id' => $student->id, 'cohort_id' => $cohort->id] + $extra);
}

test('academy pages open for the super administrator', function (string $route, bool $withCourse = false, bool $withCohort = false, bool $withStudent = false) {
    $student = makeStudent();
    $params = $withCourse ? [$this->course] : ($withCohort ? [$this->cohort] : ($withStudent ? [$student] : []));
    $this->actingAs($this->admin)->get(route($route, $params))->assertOk();
})->with([
    ['academy.index'], ['academy.my'], ['academy.programmes.index'], ['academy.courses.index'], ['academy.courses.create'],
    ['academy.courses.show', true], ['academy.courses.edit', true], ['academy.cohorts.create', true],
    ['academy.cohorts.show', false, true], ['academy.cohorts.edit', false, true],
    ['academy.students.index'], ['academy.students.create'], ['academy.students.show', false, false, true], ['academy.students.edit', false, false, true],
    ['academy.enrollments.index'],
]);

test('the academy appears in the launcher once installed', function () {
    $this->actingAs($this->admin)->get(route('dashboard'))->assertOk()->assertSee(route('academy.index'), false);
});

test('creating a student makes a sign-in account with a student number and temporary password', function () {
    $this->actingAs($this->admin)->post(route('academy.students.store'), [
        'name' => 'Tobi Ade', 'email' => 'tobi@example.com', 'status' => 'admitted', 'guardian_name' => 'Mrs Ade',
    ])->assertRedirect()->assertSessionHas('temp_password');

    $user = User::where('email', 'tobi@example.com')->first();
    $student = Student::where('user_id', $user->id)->first();
    expect($user->user_type)->toBe('student')
        ->and($user->hasRole('Student'))->toBeTrue()
        ->and($user->must_change_password)->toBeTrue()
        ->and($student->status)->toBe('admitted')
        ->and($student->admitted_by)->toBe($this->admin->id)
        ->and($student->student_no)->toBe('QCA/' . now()->year . '/0001');

    $second = makeStudent();
    expect($second->student_no)->toBe('QCA/' . now()->year . '/0002');
});

test('courses and programmes can be created, and fee changes do not touch existing enrollments', function () {
    $this->actingAs($this->admin)->post(route('academy.programmes.store'), ['name' => 'Web Development'])->assertRedirect();
    $programme = Programme::first();

    $this->actingAs($this->admin)->post(route('academy.courses.store'), [
        'programme_id' => $programme->id, 'code' => 'web201', 'title' => 'Laravel', 'level' => 'intermediate',
        'delivery_mode' => 'online', 'fee' => '200000', 'currency' => 'ngn', 'status' => 'published',
        'prerequisites' => [$this->course->id],
    ])->assertRedirect();
    $laravel = Course::where('code', 'WEB201')->first();
    expect($laravel->currency)->toBe('NGN')->and($laravel->prerequisites->pluck('id')->all())->toBe([$this->course->id]);

    $student = makeStudent();
    enroll($this, $student, $this->cohort)->assertSessionHasNoErrors();
    $this->actingAs($this->admin)->put(route('academy.courses.update', $this->course), [
        'code' => 'WEB101', 'title' => 'Web Foundations', 'level' => 'beginner', 'delivery_mode' => 'hybrid',
        'fee' => '999999', 'currency' => 'NGN', 'status' => 'published',
    ])->assertRedirect();

    expect(Enrollment::first()->fee_amount)->toBe('150000.00');
});

test('enrolling reserves a seat, keeps access locked until payment and notifies the student', function () {
    $student = makeStudent();
    enroll($this, $student, $this->cohort, ['discount_amount' => 50000])->assertSessionHasNoErrors();

    $e = Enrollment::first();
    expect($e->status)->toBe('pending')
        ->and($e->access_granted)->toBeFalse()
        ->and($e->netFee())->toBe('100000.00')
        ->and($e->enrolled_by)->toBe($this->admin->id)
        ->and($student->user->notifications()->count())->toBe(1);
});

test('a free cohort opens access straight away and makes the student active', function () {
    $this->cohort->update(['fee_override' => 0]);
    $student = makeStudent();
    enroll($this, $student, $this->cohort)->assertSessionHasNoErrors();

    expect(Enrollment::first()->access_granted)->toBeTrue()
        ->and(Enrollment::first()->status)->toBe('active')
        ->and($student->fresh()->status)->toBe('active');
});

test('enrollment rules are enforced', function () {
    // Applicants must be admitted first.
    enroll($this, makeStudent('applicant'), $this->cohort)->assertSessionHasErrors('student_id');

    // Only open or running cohorts.
    $planned = $this->course->cohorts()->create(['code' => 'P1', 'name' => 'Planned', 'status' => 'planned']);
    enroll($this, makeStudent(), $planned)->assertSessionHasErrors('cohort_id');

    // Registration window.
    $closed = $this->course->cohorts()->create(['code' => 'C1', 'name' => 'Closed', 'status' => 'open', 'registration_closes_on' => today()->subDay()]);
    enroll($this, makeStudent(), $closed)->assertSessionHasErrors('cohort_id');

    // No double enrollment.
    $s = makeStudent();
    enroll($this, $s, $this->cohort)->assertSessionHasNoErrors();
    enroll($this, $s, $this->cohort)->assertSessionHasErrors('cohort_id');

    // Capacity (2 seats).
    enroll($this, makeStudent(), $this->cohort)->assertSessionHasNoErrors();
    enroll($this, makeStudent(), $this->cohort)->assertSessionHasErrors('cohort_id');

    // Discount larger than the fee.
    $roomy = $this->course->cohorts()->create(['code' => 'R1', 'name' => 'Roomy', 'status' => 'open']);
    enroll($this, makeStudent(), $roomy, ['discount_amount' => 200000])->assertSessionHasErrors('discount_amount');

    expect(Enrollment::count())->toBe(2);
});

test('prerequisites must be completed unless waived', function () {
    $advanced = Course::create(['code' => 'WEB201', 'title' => 'Laravel', 'fee' => 0, 'status' => 'published']);
    $advanced->prerequisites()->attach($this->course->id);
    $cohort = $advanced->cohorts()->create(['code' => 'WEB201-1', 'name' => 'L1', 'status' => 'open']);

    $student = makeStudent();
    enroll($this, $student, $cohort)->assertSessionHasErrors('cohort_id');
    enroll($this, $student, $cohort, ['waive_prerequisites' => 1])->assertSessionHasNoErrors();
    expect(Enrollment::first()->prerequisites_waived)->toBeTrue();

    // Completing the prerequisite also works without a waiver.
    $other = makeStudent();
    $this->cohort->update(['fee_override' => 0]);
    enroll($this, $other, $this->cohort)->assertSessionHasNoErrors();
    $first = Enrollment::where('student_id', $other->id)->first();
    $this->actingAs($this->admin)->post(route('academy.enrollments.complete', $first))->assertSessionHasNoErrors();
    enroll($this, $other, $cohort)->assertSessionHasNoErrors();
});

test('opening access by hand needs the permission and a reason', function () {
    $student = makeStudent();
    enroll($this, $student, $this->cohort);
    $e = Enrollment::first();

    $officer = User::factory()->create();
    $officer->givePermissionTo('Manage academy');
    $this->actingAs($officer)->post(route('academy.enrollments.access', $e), ['reason' => 'x'])->assertForbidden();

    $this->actingAs($this->admin)->post(route('academy.enrollments.access', $e), ['reason' => ''])->assertSessionHasErrors('reason');
    $this->actingAs($this->admin)->post(route('academy.enrollments.access', $e), ['reason' => 'Instalment plan approved'])->assertSessionHasNoErrors();

    $e->refresh();
    expect($e->access_granted)->toBeTrue()
        ->and($e->status)->toBe('active')
        ->and($e->access_granted_by)->toBe($this->admin->id)
        ->and($e->access_reason)->toBe('Instalment plan approved');
});

test('withdrawing frees the seat and the student can come back', function () {
    $this->cohort->update(['capacity' => 1]);
    $student = makeStudent();
    enroll($this, $student, $this->cohort);
    $e = Enrollment::first();

    $this->actingAs($this->admin)->post(route('academy.enrollments.end', $e), ['status' => 'withdrawn', 'note' => 'Moved abroad'])->assertSessionHasNoErrors();
    expect($e->fresh()->status)->toBe('withdrawn')->and($e->fresh()->access_granted)->toBeFalse()->and($this->cohort->seatsLeft())->toBe(1);

    enroll($this, $student, $this->cohort)->assertSessionHasNoErrors();
    expect(Enrollment::count())->toBe(1)->and($e->fresh()->status)->toBe('pending');
});

test('payment confirmation from finance opens access once', function () {
    $student = makeStudent();
    enroll($this, $student, $this->cohort);
    $e = Enrollment::first();

    app(\App\Services\Academy\EnrollmentService::class)->paymentConfirmed($e, null, 'PSK-123');
    app(\App\Services\Academy\EnrollmentService::class)->paymentConfirmed($e->fresh(), null, 'PSK-123');

    expect($e->fresh()->access_granted)->toBeTrue()->and($e->fresh()->access_reason)->toBe('Payment confirmed PSK-123');
});

test('students see their own courses but not academy administration or draft courses', function () {
    $student = makeStudent();
    $student->user->forceFill(['must_change_password' => false])->save();
    enroll($this, $student, $this->cohort);
    $draft = Course::create(['code' => 'DRAFT1', 'title' => 'Secret draft', 'fee' => 0, 'status' => 'draft']);
    $me = $student->user;

    $this->actingAs($me)->get(route('academy.index'))->assertRedirect(route('academy.my'));
    $this->actingAs($me)->get(route('academy.my'))->assertOk()->assertSee('Web Foundations');
    $this->actingAs($me)->get(route('academy.courses.index'))->assertOk()->assertDontSee('Secret draft');
    $this->actingAs($me)->get(route('academy.courses.show', $this->course))->assertOk();
    $this->actingAs($me)->get(route('academy.courses.show', $draft))->assertNotFound();
    $this->actingAs($me)->get(route('academy.students.index'))->assertForbidden();
    $this->actingAs($me)->get(route('academy.cohorts.show', $this->cohort))->assertForbidden();
    $this->actingAs($me)->post(route('academy.enrollments.store'), ['student_id' => $student->id, 'cohort_id' => $this->cohort->id])->assertForbidden();
});

test('a course with enrollments is archived instead of deleted', function () {
    enroll($this, makeStudent(), $this->cohort);
    $this->actingAs($this->admin)->delete(route('academy.courses.destroy', $this->course))->assertRedirect();
    expect($this->course->fresh()->status)->toBe('archived')->and($this->course->fresh()->trashed())->toBeFalse();

    enroll($this, makeStudent(), $this->cohort)->assertSessionHasErrors('cohort_id');
});

test('pages show enrollments once there are some', function () {
    $student = makeStudent();
    enroll($this, $student, $this->cohort, ['discount_amount' => 10000]);
    $this->cohort->update(['instructor_id' => $this->admin->id]);

    $this->actingAs($this->admin)->get(route('academy.cohorts.show', $this->cohort))->assertOk()->assertSee($student->student_no)->assertSee('₦140,000.00', false);
    $this->actingAs($this->admin)->get(route('academy.students.show', $student))->assertOk()->assertSee('Web Foundations');
    $this->actingAs($this->admin)->get(route('academy.enrollments.index', ['access' => 'locked']))->assertOk()->assertSee($student->user->name);
    $this->actingAs($this->admin)->get(route('academy.index'))->assertOk()->assertSee('Cohort 1');
    $this->actingAs($this->admin)->get(route('academy.my'))->assertOk()->assertSee('Cohorts I teach');
});
