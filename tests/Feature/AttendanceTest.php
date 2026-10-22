<?php

use App\Models\Academy\Attendance;
use App\Models\Academy\ClassSession;
use App\Models\Academy\Course;
use App\Models\Academy\Student;
use App\Models\User;
use App\Services\Academy\EnrollmentService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->admin = User::where('email', config('qudroid.super_admin.email'))->first();
    $this->admin->forceFill(['must_change_password' => false])->save();

    $this->teacher = User::factory()->create(['name' => 'Tola Teacher']);
    $this->teacher->assignRole('Instructor');
    $this->teacher->forceFill(['must_change_password' => false])->save();

    $course = Course::create(['code' => 'WEB101', 'title' => 'Web Foundations', 'fee' => 0, 'status' => 'published']);
    $this->cohort = $course->cohorts()->create(['code' => 'C1', 'name' => 'Cohort 1', 'status' => 'running', 'instructor_id' => $this->teacher->id]);
    $this->other = $course->cohorts()->create(['code' => 'C2', 'name' => 'Cohort 2', 'status' => 'running']);

    $this->enrollments = collect(['Ada', 'Bola', 'Chidi'])->map(function ($name) {
        $u = User::factory()->create(['user_type' => 'student', 'name' => $name]);
        $u->assignRole('Student');
        $u->forceFill(['must_change_password' => false])->save();
        $s = Student::create(['user_id' => $u->id, 'student_no' => Student::nextNumber(), 'status' => 'admitted']);

        return app(EnrollmentService::class)->enroll($s, $this->cohort, $this->admin);
    });
});

test('sessions are added for a period on chosen weekdays, without duplicates', function () {
    $route = route('attendance.sessions.schedule', $this->cohort);
    $data = ['from' => '2026-11-02', 'to' => '2026-11-15', 'weekdays' => [2, 4], 'starts_at' => '16:00', 'ends_at' => '19:00']; // Tue & Thu
    $this->actingAs($this->teacher)->post($route, $data)->assertSessionHas('success', '4 sessions added.');
    $this->actingAs($this->teacher)->post($route, $data)->assertSessionHas('success');
    expect($this->cohort->sessions()->count())->toBe(4)
        ->and($this->cohort->sessions()->first()->date->toDateString())->toBe('2026-11-03');

    $this->actingAs($this->teacher)->post($route, ['from' => '2026-11-02', 'to' => '2026-11-15'])->assertSessionHasErrors('weekdays');
});

test('the instructor takes the register and attendance rates follow', function () {
    [$ada, $bola, $chidi] = $this->enrollments->all();
    $s1 = $this->cohort->sessions()->create(['date' => today()->subDays(2), 'starts_at' => '16:00']);
    $s2 = $this->cohort->sessions()->create(['date' => today()]);

    $this->actingAs($this->teacher)->get(route('attendance.index'))->assertOk()->assertSee('Cohort 1')->assertSee('Registers not taken');
    $this->actingAs($this->teacher)->get(route('attendance.register', $s1))->assertOk()->assertSee('Everyone present')->assertSee('Bola');

    $this->actingAs($this->teacher)->post(route('attendance.register.save', $s1), ['topic' => 'HTML forms', 'marks' => [
        $ada->id => ['status' => 'present'], $bola->id => ['status' => 'absent', 'note' => 'Sick'], $chidi->id => ['status' => 'excused'],
    ]])->assertRedirect(route('attendance.cohort', $this->cohort));
    $this->actingAs($this->teacher)->post(route('attendance.register.save', $s2), ['marks' => [
        $ada->id => ['status' => 'late'], $bola->id => ['status' => 'present'], $chidi->id => ['status' => 'absent'],
    ]]);

    expect($s1->fresh()->status)->toBe('held')->and($s1->fresh()->topic)->toBe('HTML forms')
        ->and((float) $ada->fresh()->attendance_percent)->toBe(100.0)
        ->and((float) $bola->fresh()->attendance_percent)->toBe(50.0)
        ->and((float) $chidi->fresh()->attendance_percent)->toBe(0.0); // excused doesn't count

    // Correcting a mark updates the rate.
    $this->actingAs($this->teacher)->post(route('attendance.register.save', $s1), ['marks' => [$bola->id => ['status' => 'present']]]);
    expect((float) $bola->fresh()->attendance_percent)->toBe(100.0)->and(Attendance::count())->toBe(6);

    $this->actingAs($this->teacher)->get(route('attendance.cohort', $this->cohort))->assertOk()->assertSee('2 of 2 registers taken');
    $this->actingAs($ada->student->user)->get(route('academy.my'))->assertOk()->assertSee('Attendance: <strong>100%</strong>', false);
    $this->actingAs($this->admin)->get(route('academy.cohorts.gradebook', $this->cohort))->assertOk();
});

test('cancelled sessions drop out of the rate; future registers wait for the day', function () {
    [$ada] = $this->enrollments->all();
    $s1 = $this->cohort->sessions()->create(['date' => today()->subDay()]);
    $this->actingAs($this->teacher)->post(route('attendance.register.save', $s1), ['marks' => [$ada->id => ['status' => 'absent']]]);
    expect((float) $ada->fresh()->attendance_percent)->toBe(0.0);

    $this->actingAs($this->teacher)->post(route('attendance.sessions.cancel', $s1), ['reason' => 'Power cut']);
    expect($s1->fresh()->status)->toBe('cancelled')->and($ada->fresh()->attendance_percent)->toBeNull();

    $future = $this->cohort->sessions()->create(['date' => today()->addWeek()]);
    $this->actingAs($this->teacher)->post(route('attendance.register.save', $future), ['marks' => [$ada->id => ['status' => 'present']]])->assertSessionHasErrors('register');
    $this->actingAs($this->teacher)->delete(route('attendance.sessions.destroy', $future))->assertSessionHas('success');
    expect(ClassSession::find($future->id))->toBeNull();
});

test('an instructor only takes registers for the cohorts they teach; students cannot open attendance', function () {
    $s = $this->other->sessions()->create(['date' => today()]);
    $this->actingAs($this->teacher)->post(route('attendance.register.save', $s), ['marks' => [1 => ['status' => 'present']]])->assertSessionHasErrors('register');
    $this->actingAs($this->teacher)->post(route('attendance.sessions.store', $this->other), ['date' => today()->toDateString()])->assertForbidden();

    $student = $this->enrollments->first()->student->user;
    $this->actingAs($student)->get(route('attendance.index'))->assertForbidden();

    // Academy staff can take any register.
    $this->actingAs($this->admin)->get(route('attendance.cohort', $this->other))->assertOk()->assertSee('Add sessions for a period');
});
