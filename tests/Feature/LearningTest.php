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

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->admin = User::where('email', config('qudroid.super_admin.email'))->first();
    $this->admin->forceFill(['must_change_password' => false])->save();

    $this->course = Course::create(['code' => 'WEB101', 'title' => 'Web Foundations', 'fee' => 100000, 'status' => 'published']);
    $this->cohort = $this->course->cohorts()->create(['code' => 'C1', 'name' => 'Cohort 1', 'status' => 'open']);
    $this->section = $this->course->sections()->create(['title' => 'Week 1', 'position' => 0]);
    $this->intro = $this->course->lessons()->create(['section_id' => $this->section->id, 'title' => 'Welcome', 'type' => 'text', 'body' => "# Hello\n\nSome **bold** text <script>alert(1)</script>\n\n<iframe src=\"https://evil.example\"></iframe>", 'position' => 0, 'is_published' => true, 'is_preview' => true]);
    $this->html = $this->course->lessons()->create(['section_id' => $this->section->id, 'title' => 'HTML basics', 'type' => 'video', 'video_url' => 'https://youtu.be/dQw4w9WgXcQ', 'position' => 1, 'is_published' => true]);
    $this->draft = $this->course->lessons()->create(['section_id' => $this->section->id, 'title' => 'Secret draft', 'type' => 'text', 'position' => 2, 'is_published' => false]);

    $user = User::factory()->create(['user_type' => 'student']);
    $user->assignRole('Student');
    $user->forceFill(['must_change_password' => false])->save();
    $this->me = $user;
    $this->student = Student::create(['user_id' => $user->id, 'student_no' => Student::nextNumber(), 'status' => 'admitted']);
    $this->enrollment = app(EnrollmentService::class)->enroll($this->student, $this->cohort, $this->admin);
});

function openAccess($test): void
{
    app(EnrollmentService::class)->grantAccess($test->enrollment, $test->admin, 'Paid at office');
    $test->enrollment->refresh();
}

test('content pages open for academy staff', function () {
    $this->actingAs($this->admin)->get(route('academy.content.index', $this->course))->assertOk()->assertSee('HTML basics');
    $this->actingAs($this->admin)->get(route('academy.content.lessons.create', $this->course))->assertOk();
    $this->actingAs($this->admin)->get(route('academy.content.lessons.edit', [$this->course, $this->html]))->assertOk();
    $this->actingAs($this->admin)->get(route('academy.learn.lesson', [$this->course, $this->draft]))->assertOk()->assertSee('Draft');
    $this->actingAs($this->admin)->get(route('academy.courses.show', $this->course))->assertOk()->assertSee('Course content');
});

test('staff add sections and lessons of each type, and reorder them', function () {
    Storage::fake('local');
    $c = $this->course;

    $this->actingAs($this->admin)->post(route('academy.content.sections.store', $c), ['title' => 'Week 2'])->assertSessionHasNoErrors();
    $week2 = $c->sections()->where('title', 'Week 2')->first();

    $this->actingAs($this->admin)->post(route('academy.content.lessons.store', $c), ['title' => 'Video', 'type' => 'video', 'section_id' => $week2->id])->assertSessionHasErrors('video_url');
    $this->actingAs($this->admin)->post(route('academy.content.lessons.store', $c), ['title' => 'Slides', 'type' => 'file', 'section_id' => $week2->id, 'is_published' => 1,
        'file' => UploadedFile::fake()->create('slides.pdf', 300, 'application/pdf')])->assertSessionHasNoErrors();
    $this->actingAs($this->admin)->post(route('academy.content.lessons.store', $c), ['title' => 'Bad file', 'type' => 'file', 'file' => UploadedFile::fake()->create('virus.exe', 10)])->assertSessionHasErrors('file');
    $this->actingAs($this->admin)->post(route('academy.content.lessons.store', $c), ['title' => 'MDN', 'type' => 'link', 'external_url' => 'https://developer.mozilla.org', 'section_id' => $week2->id, 'is_published' => 1])->assertSessionHasNoErrors();

    $slides = Lesson::where('title', 'Slides')->first();
    expect($slides->file_name)->toBe('slides.pdf')->and($slides->position)->toBe(1);
    Storage::disk('local')->assertExists($slides->file_path);

    // Move "MDN" above "Slides".
    $mdn = Lesson::where('title', 'MDN')->first();
    $this->actingAs($this->admin)->post(route('academy.content.lessons.move', [$c, $mdn]), ['direction' => 'up']);
    expect($mdn->fresh()->position)->toBe(0)->and($slides->fresh()->position)->toBe(1);

    // Move Week 2 above Week 1.
    $this->actingAs($this->admin)->post(route('academy.content.sections.move', [$c, $week2]), ['direction' => 'up']);
    expect($c->sections()->pluck('title')->all())->toBe(['Week 2', 'Week 1']);

    // A section from another course can't be used.
    $other = Course::create(['code' => 'X1', 'title' => 'Other', 'fee' => 0, 'status' => 'published']);
    $foreign = $other->sections()->create(['title' => 'Elsewhere']);
    $this->actingAs($this->admin)->post(route('academy.content.lessons.store', $c), ['title' => 'T', 'type' => 'text', 'section_id' => $foreign->id])->assertSessionHasErrors('section_id');

    // Deleting a lesson removes its file.
    $this->actingAs($this->admin)->delete(route('academy.content.lessons.destroy', [$c, $slides]));
    Storage::disk('local')->assertMissing($slides->file_path);
});

test('before payment a student only sees free previews', function () {
    $this->actingAs($this->me)->get(route('academy.learn.lesson', [$this->course, $this->intro]))->assertOk()->assertSee('Hello');
    $this->actingAs($this->me)->get(route('academy.learn.lesson', [$this->course, $this->html]))->assertRedirect(route('academy.courses.show', $this->course));
    $this->actingAs($this->me)->post(route('academy.learn.complete', [$this->course, $this->intro]))->assertForbidden();
});

test('with access a student studies, marks lessons complete and progress is tracked', function () {
    openAccess($this);

    $this->actingAs($this->me)->get(route('academy.learn.course', $this->course))->assertRedirect(route('academy.learn.lesson', [$this->course, $this->intro]));
    $res = $this->actingAs($this->me)->get(route('academy.learn.lesson', [$this->course, $this->html]))->assertOk();
    $res->assertSee('youtube-nocookie.com/embed/dQw4w9WgXcQ', false)->assertDontSee('Secret draft');

    $this->actingAs($this->me)->get(route('academy.learn.lesson', [$this->course, $this->draft]))->assertRedirect();

    $this->actingAs($this->me)->post(route('academy.learn.complete', [$this->course, $this->intro]), ['done' => 1, 'next' => 1, 'next_id' => $this->html->id])
        ->assertRedirect(route('academy.learn.lesson', [$this->course, $this->html]));
    expect($this->enrollment->fresh()->progress_percent)->toBe(50);

    // "Continue" goes to the first unfinished lesson.
    $this->actingAs($this->me)->get(route('academy.learn.course', $this->course))->assertRedirect(route('academy.learn.lesson', [$this->course, $this->html]));

    $this->actingAs($this->me)->post(route('academy.learn.complete', [$this->course, $this->html]), ['done' => 1]);
    expect($this->enrollment->fresh()->progress_percent)->toBe(100);

    // Publishing a new lesson brings progress back down; undo works.
    $this->actingAs($this->admin)->put(route('academy.content.lessons.update', [$this->course, $this->draft]), ['title' => 'Secret draft', 'type' => 'text', 'section_id' => $this->section->id, 'is_published' => 1]);
    expect($this->enrollment->fresh()->progress_percent)->toBe(66);
    $this->actingAs($this->me)->post(route('academy.learn.complete', [$this->course, $this->html]), ['done' => 0]);
    expect($this->enrollment->fresh()->progress_percent)->toBe(33);

    $this->actingAs($this->me)->get(route('academy.my'))->assertOk()->assertSee('33%');
    $this->actingAs($this->admin)->get(route('academy.cohorts.show', $this->cohort))->assertOk()->assertSee('33%');
});

test('lesson text is rendered as markdown without raw html', function () {
    $this->actingAs($this->me)->get(route('academy.learn.lesson', [$this->course, $this->intro]))
        ->assertSee('<strong>bold</strong>', false)->assertDontSee('<script>alert(1)</script>', false)->assertDontSee('evil.example');
});

test('lesson files are private to people who can open the lesson', function () {
    Storage::fake('local');
    $path = UploadedFile::fake()->create('notes.pdf', 10)->store('academy/lessons/' . $this->course->id, 'local');
    $file = $this->course->lessons()->create(['section_id' => $this->section->id, 'title' => 'Notes', 'type' => 'file', 'file_path' => $path, 'file_name' => 'notes.pdf', 'position' => 3, 'is_published' => true]);

    $this->actingAs($this->me)->get(route('academy.learn.download', [$this->course, $file]))->assertForbidden();
    openAccess($this);
    $this->actingAs($this->me)->get(route('academy.learn.download', [$this->course, $file]))->assertOk()->assertDownload('notes.pdf');

    $stranger = User::factory()->create(['user_type' => 'student']);
    $stranger->assignRole('Student');
    $stranger->forceFill(['must_change_password' => false])->save();
    $this->actingAs($stranger)->get(route('academy.learn.download', [$this->course, $file]))->assertForbidden();
});

test('a withdrawn student loses access; a completed one can still revise but not change progress', function () {
    openAccess($this);
    app(EnrollmentService::class)->complete($this->enrollment, $this->admin);
    $this->actingAs($this->me)->get(route('academy.learn.lesson', [$this->course, $this->html]))->assertOk();
    $this->actingAs($this->me)->post(route('academy.learn.complete', [$this->course, $this->html]))->assertForbidden();

    $other = User::factory()->create(['user_type' => 'student']);
    $other->forceFill(['must_change_password' => false])->save();
    $s2 = Student::create(['user_id' => $other->id, 'student_no' => Student::nextNumber(), 'status' => 'admitted']);
    $e2 = app(EnrollmentService::class)->enroll($s2, $this->cohort, $this->admin);
    app(EnrollmentService::class)->grantAccess($e2, $this->admin, 'ok');
    app(EnrollmentService::class)->end($e2->fresh(), $this->admin, 'withdrawn');
    $other->assignRole('Student');
    $this->actingAs($other)->get(route('academy.learn.lesson', [$this->course, $this->html]))->assertRedirect();
});

test('instructors can build content; students cannot', function () {
    $instructor = User::factory()->create();
    $instructor->assignRole('Instructor');
    $this->actingAs($instructor)->get(route('academy.content.index', $this->course))->assertOk();
    $this->actingAs($instructor)->post(route('academy.content.sections.store', $this->course), ['title' => 'Week 9'])->assertSessionHasNoErrors();

    $this->actingAs($this->me)->get(route('academy.content.index', $this->course))->assertForbidden();
    $this->actingAs($this->me)->post(route('academy.content.sections.store', $this->course), ['title' => 'Hack'])->assertForbidden();
});

test('video links become privacy-friendly embeds; other hosts stay plain links', function () {
    $l = new Lesson();
    foreach ([
        'https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=10' => 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?rel=0',
        'https://youtu.be/dQw4w9WgXcQ'                     => 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?rel=0',
        'https://vimeo.com/76979871'                       => 'https://player.vimeo.com/video/76979871',
        'https://evil.example/youtube.com/watch?v=dQw4w9WgXcQ' => null,
        'javascript:alert(1)'                              => null,
    ] as $url => $embed) {
        $l->video_url = $url;
        expect($l->embedUrl())->toBe($embed);
    }
});
