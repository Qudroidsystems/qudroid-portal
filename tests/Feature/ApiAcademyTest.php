<?php

use App\Models\Academy\Course;
use App\Models\Academy\Lesson;
use App\Models\Academy\Student;
use App\Models\User;
use App\Services\Academy\EnrollmentService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $admin = User::where('email', config('qudroid.super_admin.email'))->first();
    $this->course = Course::create(['code' => 'WEB101', 'title' => 'Web Foundations', 'fee' => 0, 'status' => 'published', 'pass_grade' => 60, 'summary' => 'HTML and CSS']);
    $cohort = $this->course->cohorts()->create(['code' => 'C1', 'name' => 'Cohort 1', 'status' => 'open', 'starts_on' => today()->subMonth()]);
    $this->me = User::factory()->create(['user_type' => 'student']);
    $this->me->assignRole('Student');
    $student = Student::create(['user_id' => $this->me->id, 'student_no' => Student::nextNumber(), 'status' => 'admitted']);
    $this->enrollment = app(EnrollmentService::class)->enroll($student, $cohort, $admin);

    $section = $this->course->sections()->create(['title' => 'Basics', 'position' => 1]);
    $this->l1 = Lesson::create(['course_id' => $this->course->id, 'section_id' => $section->id, 'title' => 'What is HTML', 'type' => 'text', 'body' => '**Tags**', 'position' => 1, 'is_published' => true, 'duration_minutes' => 5]);
    $this->l2 = Lesson::create(['course_id' => $this->course->id, 'section_id' => $section->id, 'title' => 'Links', 'type' => 'video', 'video_url' => 'https://youtu.be/dQw4w9WgXcQ', 'position' => 2, 'is_published' => true]);

    $this->quiz = $this->course->assessments()->create(['type' => 'quiz', 'title' => 'HTML quiz', 'weight' => 40, 'pass_mark' => 50, 'max_attempts' => 1, 'is_published' => true, 'show_answers' => true]);
    $this->q1 = $this->quiz->questions()->create(['type' => 'single', 'question' => 'Which tag makes a link?', 'options' => ['<a>', '<p>'], 'correct' => [0], 'points' => 2, 'position' => 0]);
    $this->q2 = $this->quiz->questions()->create(['type' => 'short_answer', 'question' => 'HTML stands for?', 'accepted_answers' => ['HyperText Markup Language'], 'points' => 1, 'position' => 1]);
    $this->task = $this->course->assessments()->create(['type' => 'assignment', 'title' => 'Build a page', 'weight' => 60, 'pass_mark' => 50, 'max_score' => 20, 'is_published' => true]);
});

test('a student sees their course outline, opens lessons and tracks progress', function () {
    Sanctum::actingAs($this->me);
    $this->getJson(route('api.v1.academy.my'))->assertOk()->assertJsonPath('data.0.course.code', 'WEB101')->assertJsonPath('data.0.next_lesson.id', $this->l1->id);
    $this->getJson(route('api.v1.academy.enrollments.show', $this->enrollment))->assertOk()
        ->assertJsonPath('data.modules.0.title', 'Basics')->assertJsonPath('data.modules.0.lessons.1.kind', 'video')
        ->assertJsonPath('data.assessments.0.status', 'open')->assertJsonCount(2, 'data.assessments');

    $this->getJson(route('api.v1.academy.lessons.show', $this->l1))->assertOk()->assertJsonPath('data.next_id', $this->l2->id)
        ->assertJsonPath('data.body_html', "<p><strong>Tags</strong></p>\n");
    $this->postJson(route('api.v1.academy.lessons.complete', $this->l1))->assertOk()->assertJsonPath('data.progress', 50);
    $this->getJson(route('api.v1.academy.my'))->assertJsonPath('data.0.next_lesson.id', $this->l2->id);

    // someone not on the course can't open full lessons
    $other = User::factory()->create(['user_type' => 'student']);
    $other->assignRole('Student');
    Sanctum::actingAs($other);
    $this->getJson(route('api.v1.academy.lessons.show', $this->l1))->assertForbidden()->assertJsonPath('code', 'locked');
    $this->getJson(route('api.v1.academy.enrollments.show', $this->enrollment))->assertNotFound();
});

test('quizzes autosave, resume, submit and mark; assignments are handed in with a file', function () {
    Storage::fake('local');
    Sanctum::actingAs($this->me);
    $start = $this->postJson(route('api.v1.academy.assessments.start', $this->quiz))->assertCreated()->json('data');
    expect($start['questions'][0])->not->toHaveKey('correct')->and($start['questions'][0]['options'][0])->toBe(['id' => 0, 'text' => '<a>']);

    $this->putJson(route('api.v1.academy.attempts.save', $start['attempt_id']), ['answers' => [$this->q1->id => 0]])->assertOk();
    // starting again resumes with the saved answer
    $this->postJson(route('api.v1.academy.assessments.start', $this->quiz))->assertOk()->assertJsonPath('data.attempt_id', $start['attempt_id'])
        ->assertJsonPath('data.answers.' . $this->q1->id, 0);

    $this->postJson(route('api.v1.academy.attempts.submit', $start['attempt_id']), ['answers' => [$this->q2->id => 'hypertext markup language']])->assertOk()
        ->assertJsonPath('data.score', 3)->assertJsonPath('data.percent', 100)->assertJsonPath('data.questions.0.correct', [0]);
    $this->putJson(route('api.v1.academy.attempts.save', $start['attempt_id']), ['answers' => [$this->q1->id => 1]])->assertStatus(409);
    $this->getJson(route('api.v1.academy.assessments.show', $this->quiz))->assertJsonPath('data.can_start', false)->assertJsonPath('data.reason_if_not', 'You have used all your attempts.')
        ->assertJsonPath('data.status', 'graded');

    $this->post(route('api.v1.academy.assessments.hand-in', $this->task), ['body' => 'My page', 'file' => UploadedFile::fake()->create('page.zip', 20, 'application/zip')], ['Accept' => 'application/json'])
        ->assertCreated()->assertJsonPath('data.status', 'submitted');
    $this->getJson(route('api.v1.academy.assessments.show', $this->task))->assertJsonPath('data.submission.file_name', 'page.zip')->assertJsonPath('data.status', 'submitted');

    // someone else's attempt
    Sanctum::actingAs(User::factory()->create());
    $this->getJson(route('api.v1.academy.attempts.show', $start['attempt_id']))->assertNotFound();
});

test('the catalogue lists published courses with cohorts open for applications', function () {
    Sanctum::actingAs($this->me);
    $this->getJson(route('api.v1.academy.courses', ['q' => 'web']))->assertOk()->assertJsonPath('data.0.title', 'Web Foundations')
        ->assertJsonPath('data.0.cohorts.0.name', 'Cohort 1');
    $this->getJson(route('api.v1.academy.courses.show', $this->course->id))->assertOk()->assertJsonPath('data.lessons_count', 2)
        ->assertJsonPath('data.my_enrollment_id', $this->enrollment->id);
    $this->getJson(route('api.v1.academy.certificates'))->assertOk()->assertJsonCount(0, 'data');
});
