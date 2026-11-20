<?php

use App\Models\Academy\Course;
use App\Models\Academy\Student;
use App\Models\User;
use App\Models\Website\Page;
use App\Models\Website\StudentProfile;
use App\Models\Website\Testimonial;
use App\Services\Academy\EnrollmentService;
use App\Services\Website\Showcase;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    Storage::fake('public');
    $this->admin = User::where('email', config('qudroid.super_admin.email'))->first();
    $this->admin->forceFill(['must_change_password' => false])->save();

    $this->course = Course::create(['code' => 'WEB1', 'title' => 'Web Foundations', 'slug' => 'web-foundations', 'fee' => 0, 'status' => 'published']);
    $this->cohort = $this->course->cohorts()->create(['code' => 'W-26', 'name' => 'Spring 2026 class', 'status' => 'running', 'starts_on' => '2026-03-01']);

    $make = function (string $name) {
        $u = User::factory()->create(['name' => $name, 'user_type' => 'student']);
        $u->assignRole('Student');
        $u->forceFill(['must_change_password' => false])->save();
        $s = Student::create(['user_id' => $u->id, 'status' => 'admitted']);
        app(EnrollmentService::class)->enroll($s, $this->cohort, $this->admin);

        return [$u, $s->fresh()];
    };
    [$this->ada, $this->adaStudent] = $make('Ada Lovelace');
    [$this->tunde, $this->tundeStudent] = $make('Tunde Bello');
});

function fillProfile($test, bool $share = true)
{
    return $test->actingAs($test->ada)->post(route('academy.my.profile.update'), [
        'headline' => 'Frontend developer', 'bio' => 'I build friendly websites.', 'location' => 'Lagos',
        'skills' => 'HTML, CSS, JavaScript', 'links' => ['github' => 'github.com/ada', 'linkedin' => ''], 'open_to_work' => 1,
        'share' => $share ? 1 : 0, 'photo_file' => UploadedFile::fake()->image('me.jpg', 400, 400),
    ]);
}

test('a student fills in a profile; nothing is public until they agree and staff publish it', function () {
    $this->actingAs($this->ada)->get(route('academy.my.profile'))->assertOk()->assertSee('Show my profile on the website');
    fillProfile($this, false)->assertSessionHas('success');
    $p = StudentProfile::firstOrFail();
    expect($p->status)->toBe('draft')->and($p->consented_at)->toBeNull()->and($p->slug)->toBe('ada-lovelace')
        ->and($p->links['github'])->toBe('https://github.com/ada')->and($p->skills)->toBe(['HTML', 'CSS', 'JavaScript']);
    Storage::disk('public')->assertExists($p->photo);
    $this->get(route('site.students.show', $p))->assertNotFound();

    // Agreeing sends it for approval and tells the website managers.
    fillProfile($this)->assertSessionHas('success');
    expect($p->fresh()->status)->toBe('pending')->and($p->fresh()->consented_at)->not->toBeNull();
    expect($this->admin->notifications()->where('data->title', 'like', 'Student profile to approve%')->exists())->toBeTrue();
    $this->get(route('site.students.show', $p))->assertNotFound();

    // A project with links.
    $this->actingAs($this->ada)->post(route('academy.my.profile.projects.store'), [
        'title' => 'Recipe finder', 'summary' => 'Find meals from what is in your fridge.', 'url' => 'recipes.example.com',
        'repo_url' => 'https://github.com/ada/recipes', 'tags' => 'React, API', 'course_id' => $this->course->id,
    ])->assertRedirect(route('academy.my.profile'));

    // Staff publish it.
    $this->actingAs($this->admin)->get(route('website.students.index'))->assertOk()->assertSee('Ada Lovelace')->assertSee('Waiting for approval');
    $this->actingAs($this->admin)->post(route('website.students.review', $p), ['status' => 'published'])->assertSessionHas('success');
    expect($p->fresh()->isPublic())->toBeTrue();

    $this->get(route('site.students'))->assertOk()->assertSee('Ada Lovelace')->assertSee('Frontend developer')->assertSee('Web Foundations')
        ->assertSee('Spring 2026 class')->assertDontSee('Tunde Bello');
    $this->get(route('site.students.show', $p))->assertOk()->assertSee('I build friendly websites.')->assertSee('Recipe finder')
        ->assertSee('https://recipes.example.com', false)->assertSee('https://github.com/ada/recipes', false)->assertSee('Open to work')
        ->assertSee('Studying now');
    $this->get(route('site.students', ['course' => 'web-foundations', 'q' => 'Ada']))->assertOk()->assertSee('Ada Lovelace');
    $this->get(route('site.students', ['q' => 'nobody-here']))->assertOk()->assertSee('No students match that.');

    // Turning sharing off takes it down at once.
    fillProfile($this, false);
    expect($p->fresh()->status)->toBe('draft');
    $this->get(route('site.students.show', $p))->assertNotFound();
});

test('staff cannot publish without the student agreeing, but can record agreement', function () {
    $this->actingAs($this->admin)->post(route('website.students.store'), ['student_id' => $this->tundeStudent->id])->assertRedirect();
    $p = StudentProfile::where('student_id', $this->tundeStudent->id)->firstOrFail();

    $this->actingAs($this->admin)->post(route('website.students.review', $p), ['status' => 'published'])->assertSessionHasErrors('status');
    expect($p->fresh()->status)->toBe('draft');

    $this->actingAs($this->admin)->put(route('website.students.update', $p), ['headline' => 'Data analyst', 'status' => 'published', 'consent' => 1, 'featured' => 1])
        ->assertSessionHas('success');
    expect($p->fresh()->isPublic())->toBeTrue()->and($p->fresh()->featured)->toBeTrue();
    $this->actingAs($this->admin)->get(route('website.students.edit', $p))->assertOk()->assertSee('Data analyst')->assertSee('Web Foundations');
    $this->actingAs($this->admin)->post(route('website.students.projects.store', $p), ['title' => 'Sales dashboard', 'url' => 'https://dash.example'])->assertRedirect(route('website.students.edit', $p));
    $pr = $p->projects()->first();
    $this->actingAs($this->admin)->get(route('website.students.edit', [$p, 'project' => $pr->id]))->assertOk()->assertSee('Edit "Sales dashboard"');
    $this->get(route('site.students'))->assertSee('Tunde Bello')->assertSee('Featured');
    expect($this->tunde->notifications()->where('data->title', 'Your profile is on the website')->exists())->toBeTrue();
});

test('students only change their own projects, up to the limit', function () {
    fillProfile($this);
    $p = StudentProfile::firstOrFail();
    $pr = app(Showcase::class)->saveProject($p, null, ['title' => 'Mine'], request());
    $this->actingAs($this->tunde)->get(route('academy.my.profile'))->assertOk();
    $this->actingAs($this->tunde)->put(route('academy.my.profile.projects.update', $pr), ['title' => 'Hacked'])->assertNotFound();
    $this->actingAs($this->tunde)->delete(route('academy.my.profile.projects.destroy', $pr))->assertNotFound();
    expect($pr->fresh()->title)->toBe('Mine');

    for ($i = 1; $i < Showcase::MAX_PROJECTS; $i++) app(Showcase::class)->saveProject($p, null, ['title' => "P$i"], request());
    $this->actingAs($this->ada)->post(route('academy.my.profile.projects.store'), ['title' => 'One too many'])->assertSessionHasErrors('title');

    // Bad links are refused; non-students have no profile page.
    $this->actingAs($this->ada)->post(route('academy.my.profile.projects.store'), ['title' => 'x', 'url' => 'not a link'])->assertSessionHasErrors();
    $this->actingAs($this->admin)->get(route('academy.my.profile'))->assertNotFound();
});

test('student testimonials wait for approval and show on the students page', function () {
    $this->actingAs($this->ada)->post(route('academy.my.testimonial'), ['quote' => 'The best place to learn to code in Lagos!', 'rating' => 5, 'course_id' => $this->course->id, 'consent' => 1])
        ->assertSessionHas('success');
    $t = Testimonial::firstOrFail();
    expect($t->status)->toBe('pending')->and($t->kind)->toBe('student')->and($t->name)->toBe('Ada Lovelace')->and($t->source)->toBe('student');
    $this->get(route('site.testimonials'))->assertDontSee('The best place to learn');

    $this->actingAs($this->admin)->post(route('website.testimonials.review', $t), ['status' => 'published']);
    $this->get(route('site.testimonials.kind', 'students'))->assertOk()->assertSee('The best place to learn to code in Lagos!')->assertSee('Web Foundations');
    $this->get(route('site.testimonials.kind', 'clients'))->assertOk()->assertDontSee('The best place to learn');
    $this->get(route('site.testimonials'))->assertOk()->assertSee('5 out of 5');

    // Needs agreement; course must be one of theirs.
    $this->actingAs($this->ada)->post(route('academy.my.testimonial'), ['quote' => 'Another nice long testimonial here.'])->assertSessionHasErrors('consent');
    $other = Course::create(['code' => 'X', 'title' => 'Other', 'fee' => 0, 'status' => 'published']);
    $this->actingAs($this->ada)->post(route('academy.my.testimonial'), ['quote' => 'Another nice long testimonial here.', 'consent' => 1, 'course_id' => $other->id])->assertSessionHasErrors('course_id');
});

test('clients write a testimonial through a signed link', function () {
    $this->actingAs($this->admin)->post(route('website.testimonials.link'), ['organisation' => 'Acme Ltd'])->assertSessionHas('client_link');
    $link = session('client_link');
    auth()->logout();

    $this->get(route('site.testimonials.share'))->assertForbidden();
    $this->get($link)->assertOk()->assertSee('Share your experience')->assertSee('Acme Ltd');
    $this->post($link, ['name' => 'Ngozi Okafor', 'role' => 'CEO', 'organisation' => 'Acme Ltd', 'quote' => 'They delivered our portal on time and it just works.', 'rating' => 5,
        'website_url' => 'acme.example', 'consent' => 1, 'photo_file' => UploadedFile::fake()->image('logo.png')])->assertOk()->assertSee('Thank you.');
    $t = Testimonial::firstOrFail();
    expect($t->kind)->toBe('client')->and($t->status)->toBe('pending')->and($t->source)->toBe('link')->and($t->website_url)->toBe('https://acme.example');

    $this->actingAs($this->admin)->get(route('website.testimonials.index'))->assertOk()->assertSee('Ngozi Okafor')->assertSee('sent by the client');
    $this->actingAs($this->admin)->get(route('website.testimonials.edit', $t))->assertOk()->assertSee('They delivered our portal');
    $this->actingAs($this->admin)->get(route('website.settings'))->assertOk()->assertSee('Our students');
    $this->actingAs($this->admin)->put(route('website.testimonials.update', $t), ['kind' => 'client', 'name' => 'Ngozi Okafor', 'role' => 'CEO', 'organisation' => 'Acme Ltd',
        'quote' => $t->quote, 'status' => 'published', 'featured' => 1])->assertSessionHas('success');
    $this->get(route('site.testimonials.kind', 'clients'))->assertOk()->assertSee('They delivered our portal on time')->assertSee('CEO, Acme Ltd');
});

test('staff add testimonials, and blocks and the menu show students and quotes', function () {
    $this->actingAs($this->admin)->get(route('website.testimonials.create', ['kind' => 'client']))->assertOk();
    $this->actingAs($this->admin)->post(route('website.testimonials.store'), ['kind' => 'client', 'name' => 'Bisi', 'organisation' => 'Bank', 'quote' => 'Great partner.', 'status' => 'published'])->assertRedirect();

    fillProfile($this);
    app(Showcase::class)->review(StudentProfile::firstOrFail(), 'published', $this->admin);

    Page::create(['title' => 'People', 'slug' => 'people', 'status' => 'published', 'blocks' => [
        ['type' => 'students', 'heading' => 'Meet the class', 'featured_only' => false],
        ['type' => 'testimonials', 'heading' => 'Kind words', 'source' => 'clients', 'items' => []],
    ]]);
    $this->get('/people')->assertOk()->assertSee('Meet the class')->assertSee('Ada Lovelace')->assertSee('Great partner.')->assertSee('Read more stories');
    $this->get(route('site.contact'))->assertSee(route('site.students'), false)->assertSee(route('site.testimonials'), false);
});

test('alumni filter and the yearbook show only graduates who agreed', function () {
    // Ada (published) and Tunde (no profile) both graduate from the class.
    fillProfile($this);
    app(Showcase::class)->review(StudentProfile::firstOrFail(), 'published', $this->admin);
    \App\Models\Academy\Enrollment::query()->update(['status' => 'completed']);
    $this->cohort->update(['ends_on' => '2026-08-30']);

    $this->get(route('site.students', ['show' => 'alumni']))->assertOk()->assertSee('Meet our alumni.')->assertSee('Ada Lovelace')->assertSee('Class of 2026');
    $this->get(route('site.students', ['show' => 'current']))->assertOk()->assertDontSee('Ada Lovelace');

    // Not in the yearbook until staff tick it on the class.
    $this->get(route('site.yearbook'))->assertOk()->assertSee('The yearbook is on its way.');
    $this->actingAs($this->admin)->put(route('academy.cohorts.update', $this->cohort), [
        'code' => 'W-26', 'name' => 'Spring 2026 class', 'status' => 'completed', 'starts_on' => '2026-03-01', 'ends_on' => '2026-08-30',
        'in_yearbook' => 1, 'yearbook_note' => 'Our first full-stack class.', 'yearbook_photo_file' => UploadedFile::fake()->image('class.jpg', 1200, 500),
    ])->assertSessionHasNoErrors();
    $c = $this->cohort->fresh();
    expect($c->in_yearbook)->toBeTrue();
    Storage::disk('public')->assertExists($c->yearbook_photo);

    $this->get(route('site.yearbook'))->assertOk()->assertSee('Class of 2026')->assertSee('Spring 2026 class')->assertSee('Our first full-stack class.')
        ->assertSee('2 graduates')->assertSee('Ada Lovelace')->assertDontSee('Tunde Bello');
    $this->get(route('site.yearbook', ['year' => 2026]))->assertOk()->assertSee('Ada Lovelace');
    $this->get(route('site.contact'))->assertSee(route('site.yearbook'), false);

    // A block can show alumni only.
    Page::create(['title' => 'Alumni', 'slug' => 'our-alumni', 'status' => 'published', 'blocks' => [['type' => 'students', 'heading' => 'Our alumni', 'who' => 'alumni']]]);
    $this->get('/our-alumni')->assertOk()->assertSee('Ada Lovelace')->assertSee('Meet all our alumni');
});
