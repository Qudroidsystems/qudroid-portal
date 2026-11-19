<?php

use App\Models\Academy\Course;
use App\Models\Academy\Student;
use App\Models\Academy\Submission;
use App\Models\User;
use App\Services\Academy\AssessmentService;
use App\Services\Academy\EnrollmentService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function staffWithRole(string $name, string $role): User
{
    $u = User::factory()->create(['name' => $name, 'user_type' => 'staff']);
    $u->forceFill(['must_change_password' => false])->save();
    $u->assignRole($role);

    return $u;
}

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->admin = User::where('email', config('qudroid.super_admin.email'))->first();
    $this->admin->forceFill(['must_change_password' => false])->save();

    $this->amaka = staffWithRole('Amaka Teacher', 'Instructor');
    $this->bayo = staffWithRole('Bayo Teacher', 'Instructor');
    $this->chidi = staffWithRole('Chidi Teacher', 'Instructor');

    $this->web = Course::create(['code' => 'WEB1', 'title' => 'Web', 'fee' => 0, 'status' => 'published']);
    $this->morning = $this->web->cohorts()->create(['code' => 'W-AM', 'name' => 'Morning class', 'status' => 'running', 'instructor_id' => $this->amaka->id]);
    $this->evening = $this->web->cohorts()->create(['code' => 'W-PM', 'name' => 'Evening class', 'status' => 'running', 'instructor_id' => $this->bayo->id]);
    $this->py = Course::create(['code' => 'PY1', 'title' => 'Python', 'fee' => 0, 'status' => 'draft']);
    $this->py->cohorts()->create(['code' => 'P-1', 'name' => 'Python class', 'status' => 'running', 'instructor_id' => $this->chidi->id]);

    $enrol = function (string $name, $cohort) {
        $u = User::factory()->create(['name' => $name, 'user_type' => 'student']);
        $u->assignRole('Student');

        return app(EnrollmentService::class)->enroll(Student::create(['user_id' => $u->id, 'status' => 'admitted']), $cohort, $this->admin);
    };
    $this->ada = $enrol('Ada Morning', $this->morning);
    $this->ike = $enrol('Ike Evening', $this->evening);

    $this->task = $this->web->assessments()->create(['type' => 'assignment', 'kind' => 'assignment', 'title' => 'Portfolio', 'weight' => 100, 'pass_mark' => 50, 'max_score' => 10, 'is_published' => true]);
    $svc = app(AssessmentService::class);
    $this->adaWork = $svc->handIn($this->task, $this->ada, 'My page', null);
    $this->ikeWork = $svc->handIn($this->task, $this->ike, 'My site', null);
});

test('instructors open only the courses they teach a class on', function () {
    $this->actingAs($this->amaka)->get(route('academy.content.index', $this->web))->assertOk();
    $this->actingAs($this->chidi)->get(route('academy.content.index', $this->web))->assertForbidden();
    $this->actingAs($this->chidi)->get(route('academy.assessments.create', [$this->web, 'kind' => 'test']))->assertForbidden();
    $this->actingAs($this->chidi)->post(route('academy.assessments.store', $this->web), ['kind' => 'quiz', 'title' => 'x', 'weight' => 0, 'pass_mark' => 50])->assertForbidden();

    // Their own course, even unpublished, with its Content button.
    $this->actingAs($this->chidi)->get(route('academy.courses.show', $this->py))->assertOk()->assertSee('Content');
    $this->actingAs($this->chidi)->get(route('academy.courses.show', $this->web))->assertOk()->assertDontSee(route('academy.content.index', $this->web), false);
    $this->actingAs($this->amaka)->get(route('academy.courses.show', $this->py))->assertNotFound();
});

test('instructors see and mark only the students of their own classes', function () {
    $this->actingAs($this->amaka)->get(route('academy.assessments.results', [$this->web, $this->task]))->assertOk()
        ->assertSee('Ada Morning')->assertDontSee('Ike Evening');

    $this->actingAs($this->amaka)->post(route('academy.assessments.submission.grade', [$this->web, $this->task, $this->ikeWork]), ['score' => 9, 'action' => 'grade'])->assertForbidden();
    $this->actingAs($this->amaka)->post(route('academy.assessments.submission.comment', [$this->web, $this->task, $this->ikeWork]), ['body' => 'Hi'])->assertForbidden();
    expect($this->ikeWork->fresh()->status)->toBe('submitted');

    $this->actingAs($this->amaka)->post(route('academy.assessments.submission.grade', [$this->web, $this->task, $this->adaWork]), ['score' => 9, 'action' => 'grade'])->assertRedirect();
    expect($this->adaWork->fresh()->status)->toBe('graded');

    $this->actingAs($this->amaka)->get(route('academy.cohorts.gradebook', $this->morning))->assertOk()->assertSee('Ada Morning');
    $this->actingAs($this->amaka)->get(route('academy.cohorts.gradebook', $this->evening))->assertForbidden();
});

test('in-class scores only reach the instructor\'s own students', function () {
    $lab = $this->web->assessments()->create(['type' => 'manual', 'kind' => 'practical', 'title' => 'Lab', 'weight' => 0, 'pass_mark' => 50, 'max_score' => 10, 'is_published' => true]);
    $this->actingAs($this->amaka)->post(route('academy.assessments.scores', [$this->web, $lab]), ['scores' => [
        $this->ada->id => ['score' => 7], $this->ike->id => ['score' => 2],
    ]])->assertSessionHas('success', '1 score saved. Students have been told.');
    expect(Submission::where('assessment_id', $lab->id)->pluck('enrollment_id')->all())->toBe([$this->ada->id]);
});

test('academy staff and "Manage all course content" see every class', function () {
    $this->actingAs($this->admin)->get(route('academy.assessments.results', [$this->web, $this->task]))->assertSee('Ada Morning')->assertSee('Ike Evening');

    $head = staffWithRole('Head of Content', 'Staff');
    $head->givePermissionTo(['View academy', 'Manage all course content']);
    $this->actingAs($head)->get(route('academy.content.index', $this->py))->assertOk();
    $this->actingAs($head)->get(route('academy.assessments.results', [$this->web, $this->task]))->assertSee('Ike Evening');
    $this->actingAs($head)->get(route('academy.cohorts.gradebook', $this->evening))->assertOk();
});

test('My academy links instructors to their classes; attendance is limited to them', function () {
    $this->actingAs($this->amaka)->get(route('academy.my'))->assertOk()->assertSee('Classes I teach')->assertSee('Morning class')->assertDontSee('Evening class')
        ->assertSee(route('academy.cohorts.gradebook', $this->morning), false);
    expect($this->amaka->can('View attendance'))->toBeFalse();
    $this->actingAs($this->amaka)->get(route('attendance.cohort', $this->morning))->assertOk();
    $this->actingAs($this->amaka)->get(route('attendance.cohort', $this->evening))->assertForbidden();
});
