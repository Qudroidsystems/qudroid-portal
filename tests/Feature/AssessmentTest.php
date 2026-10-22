<?php

use App\Models\Academy\Assessment;
use App\Models\Academy\Attempt;
use App\Models\Academy\Certificate;
use App\Models\Academy\Course;
use App\Models\Academy\Student;
use App\Models\Academy\Submission;
use App\Models\User;
use App\Services\Academy\AssessmentService;
use App\Services\Academy\EnrollmentService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->admin = User::where('email', config('qudroid.super_admin.email'))->first();
    $this->admin->forceFill(['must_change_password' => false])->save();

    $this->course = Course::create(['code' => 'WEB101', 'title' => 'Web Foundations', 'fee' => 0, 'status' => 'published', 'pass_grade' => 60]);
    $this->cohort = $this->course->cohorts()->create(['code' => 'C1', 'name' => 'Cohort 1', 'status' => 'open', 'starts_on' => today()->subMonth()]);

    $this->me = User::factory()->create(['user_type' => 'student', 'name' => 'Ada Obi']);
    $this->me->assignRole('Student');
    $this->me->forceFill(['must_change_password' => false])->save();
    $student = Student::create(['user_id' => $this->me->id, 'student_no' => Student::nextNumber(), 'status' => 'admitted']);
    $this->enrollment = app(EnrollmentService::class)->enroll($student, $this->cohort, $this->admin); // free course: access is open

    $this->quiz = $this->course->assessments()->create(['type' => 'quiz', 'title' => 'HTML quiz', 'weight' => 40, 'pass_mark' => 50, 'max_attempts' => 2, 'is_published' => true]);
    $this->q1 = $this->quiz->questions()->create(['type' => 'single', 'question' => 'Which tag makes a link?', 'options' => ['<a>', '<p>', '<div>'], 'correct' => [0], 'points' => 2, 'position' => 0]);
    $this->q2 = $this->quiz->questions()->create(['type' => 'multiple', 'question' => 'Which are block elements?', 'options' => ['div', 'span', 'p', 'a'], 'correct' => [0, 2], 'points' => 2, 'position' => 1]);
    $this->q3 = $this->quiz->questions()->create(['type' => 'short_answer', 'question' => 'What does HTML stand for?', 'accepted_answers' => ['HyperText Markup Language'], 'points' => 1, 'position' => 2]);

    $this->task = $this->course->assessments()->create(['type' => 'assignment', 'title' => 'Build a page', 'weight' => 60, 'pass_mark' => 50, 'max_score' => 20, 'is_published' => true]);
});

test('questions mark themselves, with part marks for several-answer questions', function () {
    expect($this->q1->award(0))->toBe(2.0)->and($this->q1->award([1]))->toBe(0.0)
        ->and($this->q2->award([0, 2]))->toBe(2.0)->and($this->q2->award([0]))->toBe(1.0)->and($this->q2->award([0, 1]))->toBe(0.0)
        ->and($this->q3->award('  hypertext   markup LANGUAGE '))->toBe(1.0)->and($this->q3->award('html'))->toBe(0.0);
});

test('teachers build a quiz with questions of each type', function () {
    $c = $this->course;
    $this->actingAs($this->admin)->get(route('academy.content.index', $c))->assertOk()->assertSee('HTML quiz');
    $this->actingAs($this->admin)->get(route('academy.assessments.create', [$c, 'type' => 'quiz']))->assertOk();
    $this->actingAs($this->admin)->post(route('academy.assessments.store', $c), ['type' => 'quiz', 'title' => 'CSS quiz', 'weight' => 0, 'pass_mark' => 50, 'max_attempts' => 0, 'is_published' => 1])->assertRedirect();
    $quiz = Assessment::where('title', 'CSS quiz')->first();
    expect($quiz->is_published)->toBeFalse(); // waits for questions

    $base = route('academy.assessments.questions.store', [$c, $quiz]);
    $this->actingAs($this->admin)->post($base, ['type' => 'single', 'question' => 'Colour property?', 'points' => 1, 'options' => ['color', '', 'colour'], 'correct' => [2]])->assertSessionHasNoErrors();
    expect($quiz->questions()->first()->options)->toBe(['color', 'colour'])->and($quiz->questions()->first()->correct)->toBe([1]);
    $this->actingAs($this->admin)->post($base, ['type' => 'single', 'question' => 'No answer ticked', 'points' => 1, 'options' => ['a', 'b']])->assertSessionHasErrors('correct');
    $this->actingAs($this->admin)->post($base, ['type' => 'boolean', 'question' => 'CSS cascades', 'points' => 1, 'correct' => [0]])->assertSessionHasNoErrors();
    $this->actingAs($this->admin)->post($base, ['type' => 'short_answer', 'question' => 'Unit?', 'points' => 1, 'accepted' => "px\npixels"])->assertSessionHasNoErrors();
    $this->actingAs($this->admin)->post($base, ['type' => 'essay', 'question' => 'Explain flexbox', 'points' => 5])->assertSessionHasNoErrors();
    expect($quiz->questions()->count())->toBe(4);

    $this->actingAs($this->admin)->put(route('academy.assessments.update', [$c, $quiz]), ['title' => 'CSS quiz', 'weight' => 0, 'pass_mark' => 50, 'is_published' => 1])->assertSessionHasNoErrors();
    expect($quiz->fresh()->is_published)->toBeTrue();
    $this->actingAs($this->admin)->get(route('academy.assessments.edit', [$c, $quiz]))->assertOk()->assertSee('Explain flexbox');
});

test('a student takes a quiz, sees the marked result, and the best attempt counts', function () {
    $c = $this->course;
    $this->actingAs($this->me)->get(route('academy.learn.assessment', [$c, $this->quiz]))->assertOk()->assertSee('Start the quiz');
    $this->actingAs($this->me)->post(route('academy.learn.start', [$c, $this->quiz]))->assertRedirect();
    $attempt = Attempt::first();
    $this->actingAs($this->me)->get(route('academy.learn.attempt', [$c, $attempt]))->assertOk()->assertSee('Which tag makes a link?')->assertDontSee('HyperText Markup Language');

    $this->actingAs($this->me)->post(route('academy.learn.submit', [$c, $attempt]), ['answers' => [$this->q1->id => '1', $this->q2->id => ['0'], $this->q3->id => 'hypertext markup language']]);
    $attempt->refresh();
    expect((float) $attempt->score)->toBe(2.0)->and((float) $attempt->percent)->toBe(40.0);
    $this->actingAs($this->me)->get(route('academy.learn.attempt', [$c, $attempt]))->assertOk()->assertSee('Score 40%')->assertSee('Not passed');

    // Can't resubmit the same attempt.
    $this->actingAs($this->me)->post(route('academy.learn.submit', [$c, $attempt]), ['answers' => []])->assertSessionHasErrors('assessment');

    $second = app(AssessmentService::class)->start($this->quiz, $this->enrollment);
    app(AssessmentService::class)->submit($second, [$this->q1->id => 0, $this->q2->id => [0, 2], $this->q3->id => 'HyperText Markup Language']);
    expect((float) $second->fresh()->percent)->toBe(100.0);

    // Only two attempts allowed.
    $this->actingAs($this->me)->post(route('academy.learn.start', [$c, $this->quiz]))->assertSessionHasErrors('assessment');

    // Grade: quiz 100% x 40 + assignment not done (0) x 60 = 40%.
    expect((float) $this->enrollment->fresh()->grade_percent)->toBe(40.0);
});

test('a timed quiz ignores answers sent after the time limit', function () {
    $this->quiz->update(['time_limit_minutes' => 10]);
    $attempt = app(AssessmentService::class)->start($this->quiz, $this->enrollment);
    $attempt->update(['started_at' => now()->subMinutes(13)]);

    app(AssessmentService::class)->submit($attempt->fresh(), [$this->q1->id => 0]);
    expect($attempt->fresh()->timed_out)->toBeTrue()->and((float) $attempt->fresh()->score)->toBe(0.0);
});

test('essay answers wait for a teacher to mark them', function () {
    $essay = $this->quiz->questions()->create(['type' => 'essay', 'question' => 'Why semantic HTML?', 'points' => 5, 'position' => 3]);
    $attempt = app(AssessmentService::class)->start($this->quiz, $this->enrollment);
    app(AssessmentService::class)->submit($attempt, [$this->q1->id => 0, $essay->id => 'Accessibility and SEO.']);
    expect($attempt->fresh()->needs_review)->toBeTrue();

    $c = $this->course;
    $this->actingAs($this->admin)->get(route('academy.assessments.results', [$c, $this->quiz]))->assertOk()->assertSee('Mark essays');
    $this->actingAs($this->admin)->get(route('academy.assessments.attempt', [$c, $this->quiz, $attempt]))->assertOk()->assertSee('Accessibility and SEO.');
    $this->actingAs($this->admin)->post(route('academy.assessments.attempt.grade', [$c, $this->quiz, $attempt]), ['marks' => [$essay->id => 4]])->assertSessionHasNoErrors();

    $attempt->refresh();
    expect($attempt->needs_review)->toBeFalse()->and((float) $attempt->score)->toBe(6.0)->and((float) $attempt->percent)->toBe(60.0);
});

test('students hand in assignments; teachers return, then grade them', function () {
    Storage::fake('local');
    $c = $this->course;
    $this->actingAs($this->me)->post(route('academy.learn.hand-in', [$c, $this->task]), [])->assertSessionHasErrors('assessment');
    $this->actingAs($this->me)->post(route('academy.learn.hand-in', [$c, $this->task]), ['body' => 'https://github.com/ada/page', 'file' => UploadedFile::fake()->create('page.zip', 20)])->assertSessionHasNoErrors();
    $s = Submission::first();
    Storage::disk('local')->assertExists($s->file_path);
    $this->actingAs($this->me)->get(route('academy.learn.my-file', [$c, $this->task]))->assertOk();

    $grade = route('academy.assessments.submission.grade', [$c, $this->task, $s]);
    $this->actingAs($this->admin)->post($grade, ['action' => 'return', 'feedback' => 'Add a contact form.'])->assertRedirect();
    expect($s->fresh()->status)->toBe('returned');
    $this->actingAs($this->me)->get(route('academy.learn.assessment', [$c, $this->task]))->assertSee('Add a contact form.');
    $this->actingAs($this->me)->post(route('academy.learn.hand-in', [$c, $this->task]), ['body' => 'Done, with a form.'])->assertSessionHasNoErrors();

    $this->actingAs($this->admin)->post($grade, ['action' => 'grade', 'score' => 25])->assertSessionHasErrors('assessment');
    $this->actingAs($this->admin)->post($grade, ['action' => 'grade', 'score' => 18, 'feedback' => 'Great work'])->assertRedirect();
    expect((float) $s->fresh()->percent)->toBe(90.0);

    // Graded work can't be replaced.
    $this->actingAs($this->me)->post(route('academy.learn.hand-in', [$c, $this->task]), ['body' => 'again'])->assertSessionHasErrors('assessment');
    expect((float) $this->enrollment->fresh()->grade_percent)->toBe(54.0); // 0 x 40 + 90 x 60
});

test('only students on the course, with access, can take its assessments', function () {
    $other = User::factory()->create(['user_type' => 'student']);
    $other->assignRole('Student');
    $other->forceFill(['must_change_password' => false])->save();
    $this->actingAs($other)->get(route('academy.learn.assessment', [$this->course, $this->quiz]))->assertForbidden();

    $attempt = app(AssessmentService::class)->start($this->quiz, $this->enrollment);
    $this->actingAs($other)->get(route('academy.learn.attempt', [$this->course, $attempt]))->assertNotFound();
    $this->actingAs($this->me)->get(route('academy.assessments.results', [$this->course, $this->quiz]))->assertForbidden();
});

test('completing with a pass grade issues a certificate that anyone can verify', function () {
    $svc = app(AssessmentService::class);
    $a = $svc->start($this->quiz, $this->enrollment);
    $svc->submit($a, [$this->q1->id => 0, $this->q2->id => [0, 2], $this->q3->id => 'HyperText Markup Language']);
    $sub = $svc->handIn($this->task, $this->enrollment, 'my page', null);
    $svc->gradeSubmission($sub, $this->admin, 14, null); // 70% → grade 82%

    $this->actingAs($this->admin)->get(route('academy.cohorts.gradebook', $this->cohort))->assertOk()->assertSee('82%');
    $this->actingAs($this->admin)->post(route('academy.enrollments.complete', $this->enrollment))->assertSessionHas('success');

    $cert = Certificate::first();
    expect($cert->serial)->toBe('QUD-CERT-' . now()->year . '-0001')->and($cert->get('name'))->toBe('Ada Obi')->and($cert->get('grade'))->toEqual(82);

    $this->actingAs($this->me)->get(route('academy.my'))->assertOk()->assertSee('Certificate');
    $this->actingAs($this->me)->get(route('certificates.index'))->assertOk()->assertSee($cert->serial);
    $this->actingAs($this->me)->get(route('certificates.show', $cert))->assertOk()->assertSee('Certificate of Completion')->assertSee('<svg', false);

    auth()->logout();
    $this->get(route('certificates.verify', $cert->verify_token))->assertOk()->assertSee('Valid certificate')->assertSee('Web Foundations');
    $this->get(route('certificates.verify', str_repeat('x', 40)))->assertOk()->assertSee('Certificate not found');
    expect($cert->fresh()->verify_count)->toBe(1);

    // Revoked: the verify page says so; another student can't see it.
    $this->actingAs($this->admin)->post(route('certificates.revoke', $cert), ['reason' => 'Issued in error'])->assertRedirect();
    $this->get(route('certificates.verify', $cert->verify_token))->assertSee('revoked')->assertSee('Issued in error');
    $other = User::factory()->create();
    $other->assignRole('Student');
    $other->forceFill(['must_change_password' => false])->save();
    $this->actingAs($other)->get(route('certificates.show', $cert))->assertNotFound();
});

test('below the pass grade there is no certificate unless someone overrides with a reason', function () {
    $this->actingAs($this->admin)->post(route('academy.enrollments.complete', $this->enrollment))->assertSessionHas('success');
    expect(Certificate::count())->toBe(0)->and((float) $this->enrollment->fresh()->grade_percent)->toBe(0.0);

    $this->actingAs($this->admin)->get(route('certificates.index'))->assertOk()->assertSee('below the pass grade');
    $this->actingAs($this->admin)->post(route('certificates.issue', $this->enrollment))->assertSessionHasErrors('certificate');
    $this->actingAs($this->admin)->post(route('certificates.issue', $this->enrollment), ['override' => 1])->assertSessionHasErrors('certificate');
    $this->actingAs($this->admin)->post(route('certificates.issue', $this->enrollment), ['override' => 1, 'note' => 'Completed the project offline'])->assertRedirect();
    expect(Certificate::first()->get('override_note'))->toBe('Completed the project offline');

    // A student can't issue one.
    $this->actingAs($this->me)->post(route('certificates.issue', $this->enrollment))->assertForbidden();
});

test('a course without assessments gives a certificate on completion', function () {
    Assessment::query()->delete();
    $this->actingAs($this->admin)->post(route('academy.enrollments.complete', $this->enrollment));
    expect(Certificate::count())->toBe(1)->and(Certificate::first()->get('grade'))->toBeNull();
});

test('the other assessment pages open', function () {
    $c = $this->course;
    $lesson = $c->lessons()->create(['title' => 'Intro', 'type' => 'text', 'body' => 'Hi', 'is_published' => true, 'position' => 0]);
    $this->actingAs($this->admin)->get(route('academy.assessments.create', [$c, 'type' => 'assignment']))->assertOk()->assertSee('Marked out of');
    $this->actingAs($this->admin)->get(route('academy.assessments.edit', [$c, $this->task]))->assertOk();
    $this->actingAs($this->admin)->get(route('academy.assessments.results', [$c, $this->task]))->assertOk()->assertSee('Ada Obi');
    $this->actingAs($this->admin)->get(route('academy.learn.assessment', [$c, $this->quiz]))->assertOk()->assertSee('viewing this as a teacher');
    $this->actingAs($this->admin)->get(route('academy.cohorts.show', $this->cohort))->assertOk()->assertSee('Gradebook');
    $this->actingAs($this->admin)->get(route('certificates.index'))->assertOk();
    $this->actingAs($this->admin)->get(route('academy.courses.edit', $c))->assertOk()->assertSee('Pass grade');
    $this->actingAs($this->me)->get(route('academy.learn.lesson', [$c, $lesson]))->assertOk()->assertSee('HTML quiz');
    $this->actingAs($this->me)->get(route('academy.learn.assessment', [$c, $this->task]))->assertOk()->assertSee('Hand in your work');
});
