<?php

use App\Models\Academy\Assessment;
use App\Models\Academy\AssessmentComment;
use App\Models\Academy\Course;
use App\Models\Academy\Student;
use App\Models\Academy\Submission;
use App\Models\User;
use App\Services\Academy\EnrollmentService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->admin = User::where('email', config('qudroid.super_admin.email'))->first();
    $this->admin->forceFill(['must_change_password' => false])->save();

    $this->course = Course::create(['code' => 'JS101', 'title' => 'JavaScript', 'fee' => 0, 'status' => 'published', 'pass_grade' => 50]);
    $this->cohort = $this->course->cohorts()->create(['code' => 'J1', 'name' => 'Cohort 1', 'status' => 'open', 'starts_on' => today()->subWeek()]);

    $this->teacher = User::factory()->create(['user_type' => 'staff', 'name' => 'Tunde Instructor']);
    $this->teacher->forceFill(['must_change_password' => false])->save();
    $this->cohort->update(['instructor_id' => $this->teacher->id]);

    $this->me = User::factory()->create(['user_type' => 'student', 'name' => 'Ada Obi']);
    $this->me->assignRole('Student');
    $this->me->forceFill(['must_change_password' => false])->save();
    $student = Student::create(['user_id' => $this->me->id, 'status' => 'admitted']);
    $this->enrollment = app(EnrollmentService::class)->enroll($student, $this->cohort, $this->admin);
});

test('instructors add tests, exams, projects, coding exercises and in-class assessments', function () {
    $c = $this->course;
    $this->actingAs($this->admin)->get(route('academy.content.index', $c))->assertOk()->assertSee('Coding exercise')->assertSee('Class participation');

    foreach (['test' => 'quiz', 'exam' => 'quiz', 'project' => 'assignment', 'coding' => 'assignment', 'practical' => 'manual', 'oral' => 'manual'] as $kind => $type) {
        $this->actingAs($this->admin)->get(route('academy.assessments.create', [$c, 'kind' => $kind]))->assertOk();
        $this->actingAs($this->admin)->post(route('academy.assessments.store', $c), [
            'kind' => $kind, 'title' => ucfirst($kind) . ' one', 'weight' => 10, 'pass_mark' => 50, 'max_score' => 20,
            'answer_format' => 'code', 'code_language' => 'python', 'starter_code' => "def add(a, b):\n    pass\n", 'is_published' => 1,
        ])->assertRedirect();
        $a = Assessment::where('title', ucfirst($kind) . ' one')->firstOrFail();
        expect($a->type)->toBe($type)->and($a->kind)->toBe($kind);
    }

    $coding = Assessment::where('kind', 'coding')->first();
    expect($coding->answer_format)->toBe('code')->and($coding->code_language)->toBe('python')->and($coding->starter_code)->toContain('def add')
        ->and($coding->type_label)->toBe('Coding exercise')->and($coding->icon)->toBe('ri-code-s-slash-line');
    // Code settings only apply to hand-in work.
    expect(Assessment::where('kind', 'practical')->first()->code_language)->toBeNull();
    $this->actingAs($this->admin)->get(route('academy.assessments.edit', [$c, $coding]))->assertOk()->assertSee('Starter code')->assertSee('data-code-editor', false);

    // The kind can change within its type, not across.
    $this->actingAs($this->admin)->put(route('academy.assessments.update', [$c, $coding]), ['kind' => 'project', 'title' => 'Coding one', 'weight' => 10, 'pass_mark' => 50, 'answer_format' => 'text_code', 'code_language' => 'javascript'])->assertSessionHasNoErrors();
    expect($coding->fresh()->kind)->toBe('project');
    $this->actingAs($this->admin)->put(route('academy.assessments.update', [$c, $coding]), ['kind' => 'exam', 'title' => 'Coding one', 'weight' => 10, 'pass_mark' => 50])->assertSessionHasErrors('kind');
});

test('students hand in code from the editor; instructors comment on a line and grade it', function () {
    $c = $this->course;
    $task = $c->assessments()->create(['type' => 'assignment', 'kind' => 'coding', 'title' => 'FizzBuzz', 'answer_format' => 'code', 'code_language' => 'javascript',
        'starter_code' => "function fizzbuzz(n) {\n}\n", 'allow_file' => false, 'weight' => 100, 'pass_mark' => 50, 'max_score' => 10, 'is_published' => true]);

    $this->actingAs($this->me)->get(route('academy.learn.assessment', [$c, $task]))->assertOk()
        ->assertSee('Your code')->assertSee('function fizzbuzz(n)')->assertDontSee('type="file"', false);
    $this->actingAs($this->me)->post(route('academy.learn.hand-in', [$c, $task]), ['code' => '   '])->assertSessionHasErrors('assessment');

    $code = "function fizzbuzz(n) {\n    return n % 15 === 0 ? 'FizzBuzz' : n;\n}\n";
    $this->actingAs($this->me)->post(route('academy.learn.hand-in', [$c, $task]), ['code' => $code, 'body' => 'ignored: this asks for code only'])->assertSessionHas('success');
    $s = Submission::firstOrFail();
    expect($s->code)->toBe(rtrim($code))->and($s->code_language)->toBe('javascript')->and($s->body)->toBeNull();

    // The cohort's instructor marks it: a comment on line 2, then the grade.
    $this->teacher->givePermissionTo('Manage course content');
    $this->actingAs($this->teacher)->get(route('academy.assessments.results', [$c, $task]))->assertOk()->assertSee('return n % 15', false)->assertSee('Click a line number');
    $this->actingAs($this->teacher)->post(route('academy.assessments.submission.comment', [$c, $task, $s]), ['body' => 'Handle Fizz and Buzz too.', 'line' => 2])
        ->assertSessionHas('success');
    expect(AssessmentComment::first())->line->toBe(2)->user_id->toBe($this->teacher->id);
    expect($this->me->notifications()->where('data', 'like', '%Comment on your work%')->exists())->toBeTrue();

    // The student sees it and replies; the instructor is told.
    $this->actingAs($this->me)->get(route('academy.learn.assessment', [$c, $task]))->assertOk()->assertSee('Handle Fizz and Buzz too.')->assertSee('Line 2');
    $this->actingAs($this->me)->post(route('academy.learn.comment', [$c, $task]), ['body' => 'Fixed, thanks!'])->assertRedirect();
    expect($this->teacher->notifications()->where('data', 'like', '%Ada Obi commented%')->exists())->toBeTrue();

    $this->actingAs($this->teacher)->post(route('academy.assessments.submission.grade', [$c, $task, $s]), ['score' => 8, 'feedback' => 'Good work', 'action' => 'grade']);
    expect($s->fresh()->percent)->toBe('80.00')->and((float) $this->enrollment->fresh()->grade_percent)->toBe(80.0);

    // Strangers can't comment.
    $other = User::factory()->create();
    $this->actingAs($other)->post(route('academy.learn.comment', [$c, $task]), ['body' => 'hi'])->assertForbidden();
});

test('assignments can ask for a written answer and code together, plus a file', function () {
    $task = $this->course->assessments()->create(['type' => 'assignment', 'kind' => 'project', 'title' => 'Portfolio', 'answer_format' => 'text_code', 'code_language' => 'html',
        'allow_file' => true, 'weight' => 0, 'pass_mark' => 50, 'max_score' => 10, 'is_published' => true]);
    $this->actingAs($this->me)->post(route('academy.learn.hand-in', [$this->course, $task]), ['body' => 'My site', 'code' => '<h1>Hi</h1>'])->assertSessionHas('success');
    expect(Submission::first())->body->toBe('My site')->code->toBe('<h1>Hi</h1>');
});

test('code questions in a test are marked by the instructor', function () {
    $test = $this->course->assessments()->create(['type' => 'quiz', 'kind' => 'test', 'title' => 'Mid-term test', 'weight' => 100, 'pass_mark' => 50, 'max_attempts' => 1, 'is_published' => true]);
    $this->actingAs($this->admin)->post(route('academy.assessments.questions.store', [$this->course, $test]), ['type' => 'code', 'language' => 'python', 'question' => 'Write a function that doubles a number.', 'points' => 4])
        ->assertSessionHasNoErrors();
    $q = $test->questions()->first();
    expect($q->language)->toBe('python')->and($q->isManual())->toBeTrue();

    $this->actingAs($this->me)->post(route('academy.learn.start', [$this->course, $test]));
    $attempt = $test->attempts()->first();
    $this->actingAs($this->me)->get(route('academy.learn.attempt', [$this->course, $attempt]))->assertOk()->assertSee('data-code-editor', false);
    $this->actingAs($this->me)->post(route('academy.learn.submit', [$this->course, $attempt]), ['answers' => [$q->id => "def double(x):\n    return x * 2\n"]]);
    $attempt->refresh();
    expect($attempt->needs_review)->toBeTrue()->and($attempt->answers[$q->id])->toBe("def double(x):\n    return x * 2");

    $this->actingAs($this->admin)->get(route('academy.assessments.attempt', [$this->course, $test, $attempt]))->assertOk()->assertSee('return x * 2');
    $this->actingAs($this->admin)->post(route('academy.assessments.attempt.comment', [$this->course, $test, $attempt]), ['body' => 'Nice and short.', 'question_id' => $q->id])->assertSessionHas('success');
    $this->actingAs($this->admin)->post(route('academy.assessments.attempt.grade', [$this->course, $test, $attempt]), ['marks' => [$q->id => 4]]);
    expect((float) $attempt->fresh()->percent)->toBe(100.0);
    $this->actingAs($this->me)->get(route('academy.learn.attempt', [$this->course, $attempt]))->assertSee('Nice and short.')->assertSee('Question 1');
});

test('in-class assessments: the instructor enters scores and they count in the grade', function () {
    $c = $this->course;
    $lab = $c->assessments()->create(['type' => 'manual', 'kind' => 'practical', 'title' => 'Lab 1', 'weight' => 30, 'pass_mark' => 50, 'max_score' => 10, 'is_published' => true]);
    $task = $c->assessments()->create(['type' => 'assignment', 'kind' => 'assignment', 'title' => 'Essay', 'weight' => 70, 'pass_mark' => 50, 'max_score' => 10, 'is_published' => true]);

    $this->actingAs($this->me)->get(route('academy.learn.assessment', [$c, $lab]))->assertOk()->assertSee('marked in class')->assertDontSee('Hand in');
    $this->actingAs($this->me)->post(route('academy.learn.hand-in', [$c, $lab]), ['body' => 'x'])->assertSessionHasErrors('assessment');

    $this->actingAs($this->admin)->get(route('academy.assessments.results', [$c, $lab]))->assertOk()->assertSee('Save scores')->assertSee('Ada Obi');
    $this->actingAs($this->admin)->post(route('academy.assessments.scores', [$c, $lab]), ['scores' => [$this->enrollment->id => ['score' => 11]]])->assertSessionHasErrors('assessment');
    $this->actingAs($this->admin)->post(route('academy.assessments.scores', [$c, $lab]), ['scores' => [$this->enrollment->id => ['score' => 9, 'feedback' => 'Neat wiring']]])
        ->assertSessionHas('success', '1 score saved. Students have been told.');

    expect(Submission::where('assessment_id', $lab->id)->first())->status->toBe('graded')->percent->toBe('90.00')
        ->and((float) $this->enrollment->fresh()->grade_percent)->toBe(27.0);   // 30% × 90, essay not done yet
    $this->actingAs($this->me)->get(route('academy.learn.assessment', [$c, $lab]))->assertSee('9 / 10')->assertSee('Neat wiring');
    $this->actingAs($this->admin)->get(route('academy.cohorts.gradebook', $this->cohort))->assertOk()->assertSee('Lab 1');

    // Saving the same scores again changes nothing.
    $this->actingAs($this->admin)->post(route('academy.assessments.scores', [$c, $lab]), ['scores' => [$this->enrollment->id => ['score' => 9, 'feedback' => 'Neat wiring']]])
        ->assertSessionHas('success', 'Nothing changed.');
});

test('the phone app gets coding assignments, hands in code and comments', function () {
    $task = $this->course->assessments()->create(['type' => 'assignment', 'kind' => 'coding', 'title' => 'Sum', 'answer_format' => 'code', 'code_language' => 'dart',
        'starter_code' => 'int sum(List<int> xs) {}', 'allow_file' => false, 'weight' => 0, 'pass_mark' => 50, 'max_score' => 10, 'is_published' => true]);
    $token = $this->me->createToken('phone')->plainTextToken;
    $api = fn () => $this->withHeader('Authorization', 'Bearer ' . $token);

    $api()->getJson('/api/v1/academy/assessments/' . $task->id)->assertOk()
        ->assertJsonPath('data.wants_code', true)->assertJsonPath('data.accepts_file', false)->assertJsonPath('data.code_language', 'dart')
        ->assertJsonPath('data.starter_code', 'int sum(List<int> xs) {}')->assertJsonPath('data.category', 'coding')->assertJsonPath('data.kind_label', 'Coding exercise')
        ->assertJsonPath('data.can_comment', false);
    $api()->postJson('/api/v1/academy/assessments/' . $task->id . '/comments', ['body' => 'Hello?'])->assertStatus(422);

    $api()->post('/api/v1/academy/assessments/' . $task->id . '/hand-in', ['code' => 'int sum(List<int> xs) => xs.fold(0, (a, b) => a + b);'], ['Accept' => 'application/json'])->assertCreated();
    $api()->postJson('/api/v1/academy/assessments/' . $task->id . '/comments', ['body' => 'Is fold OK here?', 'line' => 1])->assertCreated()
        ->assertJsonPath('data.from_student', true)->assertJsonPath('data.line', 1);
    $api()->getJson('/api/v1/academy/assessments/' . $task->id)->assertOk()
        ->assertJsonPath('data.submission.code', 'int sum(List<int> xs) => xs.fold(0, (a, b) => a + b);')
        ->assertJsonPath('data.submission.comments.0.body', 'Is fold OK here?')->assertJsonPath('data.can_comment', true);
});
