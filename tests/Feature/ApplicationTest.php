<?php

use App\Models\Academy\Application;
use App\Models\Academy\Course;
use App\Models\Academy\Student;
use App\Models\User;
use App\Services\Academy\EnrollmentService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->admin = User::where('email', config('qudroid.super_admin.email'))->first();
    $this->admin->forceFill(['must_change_password' => false])->save();

    $this->course = Course::create(['code' => 'WEB101', 'title' => 'Web Foundations', 'fee' => 100000, 'status' => 'published']);
    $this->cohort = $this->course->cohorts()->create(['code' => 'C1', 'name' => 'Cohort 1', 'status' => 'open', 'capacity' => 10]);
});

function applicant(array $overrides = []): array
{
    return array_merge([
        'name' => 'Ada Obi', 'email' => 'ada@example.com', 'phone_number' => '08030000000', 'consent' => 1,
        'experience_level' => 'none', 'has_laptop' => 1, 'motivation' => 'I want to build apps for my community.',
    ], $overrides);
}

test('the public Apply page lists only cohorts taking applications', function () {
    $this->course->cohorts()->create(['code' => 'C0', 'name' => 'Planned cohort', 'status' => 'planned']);
    $this->course->cohorts()->create(['code' => 'C2', 'name' => 'Closed cohort', 'status' => 'open', 'registration_closes_on' => today()->subDay()]);
    $draft = Course::create(['code' => 'DRAFT', 'title' => 'Hidden course', 'fee' => 1, 'status' => 'draft']);
    $draft->cohorts()->create(['code' => 'D1', 'name' => 'Draft cohort', 'status' => 'open']);

    $this->get(route('apply.index'))->assertOk()
        ->assertSee('Cohort 1')->assertDontSee('Planned cohort')->assertDontSee('Closed cohort')->assertDontSee('Hidden course');
    $this->get(route('apply.create', $this->cohort))->assertOk()->assertSee('Full name');
    $this->get(route('apply.create', $draft->cohorts()->first()))->assertNotFound();
});

test('a new person applies, gets an account and a password e-mail, and staff are told', function () {
    Notification::fake();

    $this->post(route('apply.store', $this->cohort), applicant())
        ->assertRedirect(route('apply.done', 'APP-' . now()->year . '-0001'));

    $user = User::where('email', 'ada@example.com')->first();
    expect($user->hasRole('Student'))->toBeTrue()->and($user->user_type)->toBe('student');
    $application = Application::first();
    expect($application->student->user_id)->toBe($user->id)
        ->and($application->student->status)->toBe('applicant')
        ->and($application->status)->toBe('submitted')
        ->and($application->has_laptop)->toBeTrue();
    Notification::assertSentTo($user, ResetPassword::class);

    expect($this->admin->notifications()->where('data', 'like', '%New application%')->exists())->toBeTrue();

    // The thank-you page shows only in the browser that applied.
    $this->get(route('apply.done', $application->reference))->assertOk()->assertSee($application->reference);
    $this->flushSession();
    $this->get(route('apply.done', $application->reference))->assertNotFound();
});

test('an e-mail that already has an account must sign in first', function () {
    User::factory()->create(['email' => 'ada@example.com']);

    $this->post(route('apply.store', $this->cohort), applicant())->assertSessionHasErrors('email');
    expect(Application::count())->toBe(0);
});

test('bots filling the hidden field are ignored, and bad input is refused', function () {
    $this->post(route('apply.store', $this->cohort), applicant(['website' => 'http://spam.example']))->assertRedirect(route('apply.index'));
    $this->post(route('apply.store', $this->cohort), applicant(['motivation' => 'short', 'consent' => null]))->assertSessionHasErrors(['motivation', 'consent']);
    expect(Application::count())->toBe(0)->and(User::where('email', 'ada@example.com')->exists())->toBeFalse();
});

test('a signed-in student applies without retyping details, once per course', function () {
    $user = User::factory()->create(['user_type' => 'student']);
    $user->assignRole('Student');
    $user->forceFill(['must_change_password' => false])->save();
    $student = Student::create(['user_id' => $user->id, 'student_no' => Student::nextNumber(), 'status' => 'active']);

    $answers = ['experience_level' => 'some', 'has_laptop' => 0, 'motivation' => 'Next step after my first course.'];
    $this->actingAs($user)->get(route('apply.create', $this->cohort))->assertOk()->assertSee('Applying as')->assertDontSee('Full name');
    $this->actingAs($user)->post(route('apply.store', $this->cohort), $answers)->assertRedirect();
    $this->actingAs($user)->post(route('apply.store', $this->cohort), $answers)->assertSessionHasErrors('cohort_id');

    expect($student->applications()->count())->toBe(1);
    $this->actingAs($user)->get(route('academy.my'))->assertOk()->assertSee('My applications');

    // Staff can't apply; they enroll people instead.
    $this->actingAs($this->admin)->post(route('apply.store', $this->cohort), $answers)->assertForbidden();
});

test('admitting enrolls the applicant on the chosen cohort and issues the invoice', function () {
    $this->post(route('apply.store', $this->cohort), applicant());
    $application = Application::first();

    $this->actingAs($this->admin)->get(route('academy.applications.index'))->assertOk()->assertSee('Ada Obi');
    $this->actingAs($this->admin)->get(route('academy.applications.show', $application))->assertOk()->assertSee('Admit and enroll');
    $this->actingAs($this->admin)->get(route('academy.index'))->assertOk()->assertSee($application->reference);

    $this->actingAs($this->admin)->post(route('academy.applications.admit', $application))->assertSessionHas('success');

    $application->refresh();
    expect($application->status)->toBe('admitted')
        ->and($application->reviewed_by)->toBe($this->admin->id)
        ->and($application->enrollment->status)->toBe('pending')
        ->and($application->enrollment->invoices()->count())->toBe(1)
        ->and($application->student->status)->toBe('admitted');

    // Decided once only.
    $this->actingAs($this->admin)->post(route('academy.applications.decline', $application), ['decision_note' => 'x'])->assertSessionHasErrors('status');
});

test('if the cohort is full, the applicant is still admitted and staff see why', function () {
    $this->post(route('apply.store', $this->cohort), applicant());
    $application = Application::first();
    $this->cohort->update(['capacity' => 1]);

    $other = User::factory()->create(['user_type' => 'student']);
    $s = Student::create(['user_id' => $other->id, 'student_no' => Student::nextNumber(), 'status' => 'admitted']);
    app(EnrollmentService::class)->enroll($s, $this->cohort, $this->admin);

    $this->actingAs($this->admin)->post(route('academy.applications.admit', $application))->assertSessionHas('error');
    $application->refresh();
    expect($application->status)->toBe('admitted')->and($application->enrollment_id)->toBeNull()
        ->and($application->student->status)->toBe('admitted');
});

test('declining needs a reason the applicant sees; applicants can withdraw their own', function () {
    $this->post(route('apply.store', $this->cohort), applicant());
    $this->post(route('apply.store', $this->cohort), applicant(['email' => 'bola@example.com', 'name' => 'Bola']));
    [$ada, $bola] = Application::orderBy('id')->get()->all();

    $this->actingAs($this->admin)->post(route('academy.applications.decline', $ada), ['decision_note' => ''])->assertSessionHasErrors('decision_note');
    $this->actingAs($this->admin)->post(route('academy.applications.decline', $ada), ['decision_note' => 'The cohort is for adults.'])->assertRedirect();
    expect($ada->fresh()->status)->toBe('declined');

    $adaUser = $ada->student->user;
    $adaUser->forceFill(['must_change_password' => false])->save();
    $this->actingAs($adaUser)->get(route('academy.my'))->assertOk()->assertSee('The cohort is for adults.');

    // Someone else's application can't be withdrawn.
    $this->actingAs($adaUser)->post(route('academy.applications.withdraw', $bola))->assertNotFound();
    $bolaUser = $bola->student->user;
    $bolaUser->forceFill(['must_change_password' => false])->save();
    $this->actingAs($bolaUser)->post(route('academy.applications.withdraw', $bola))->assertRedirect(route('academy.my'));
    expect($bola->fresh()->status)->toBe('withdrawn');
});

test('students cannot open the review pages', function () {
    $this->post(route('apply.store', $this->cohort), applicant());
    $user = User::where('email', 'ada@example.com')->first();

    $this->actingAs($user)->get(route('academy.applications.index'))->assertForbidden();
    $this->actingAs($user)->post(route('academy.applications.admit', Application::first()))->assertForbidden();
});
