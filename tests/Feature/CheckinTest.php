<?php

use App\Mail\CheckinTicketMail;
use App\Models\Academy\Attendance;
use App\Models\Academy\Course;
use App\Models\Academy\Student;
use App\Models\Checkin\Event;
use App\Models\Checkin\Guest;
use App\Models\Checkin\Place;
use App\Models\Checkin\Record;
use App\Models\Meetings\Meeting;
use App\Models\User;
use App\Services\Academy\EnrollmentService;
use App\Services\Checkin\CheckinService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function ciUser(string $role, string $name, string $type = 'staff'): User
{
    $u = User::factory()->create(['user_type' => $type, 'name' => $name]);
    $u->assignRole($role);
    $u->forceFill(['must_change_password' => false])->save();

    return $u;
}

/** Scan the door QR as $user: the public link, then the check-in page. */
function ciDoor($test, Event $event, ?User $user, array $post = null)
{
    $token = app(CheckinService::class)->screenToken($event);
    $r = $test->get(route('checkin.door', [$event, $token]));
    $r->assertRedirect(route('checkin.go', $event));
    if (!$user) return $test->get(route('checkin.go', $event));

    return $post === null ? $test->actingAs($user)->get(route('checkin.go', $event)) : $test->actingAs($user)->post(route('checkin.go', $event), $post);
}

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-11-04 08:50:00'));   // a Wednesday
    $this->seed(DatabaseSeeder::class);
    $this->hr = ciUser('HR Manager', 'Halima HR');
    $this->ada = ciUser('Staff', 'Ada Obi');
    $this->ben = ciUser('Staff', 'Ben Eze');
    $this->svc = app(CheckinService::class);
    $this->office = Place::create(['name' => 'Lagos HQ', 'work_starts' => '09:00', 'work_ends' => '17:00', 'grace_minutes' => 15, 'workdays' => [1, 2, 3, 4, 5],
        'lat' => 6.4281, 'lng' => 3.4219, 'radius_m' => 150]);
});

afterEach(fn () => Carbon::setTestNow());

test('staff clock in by scanning the door QR, are late after the grace period, and clock out with a second scan', function () {
    $this->actingAs($this->hr)->get(route('checkin.places.screen', $this->office))->assertRedirect();
    $day = Event::where('place_id', $this->office->id)->first();
    expect($day->kind)->toBe('staff')->and($day->starts_at->format('H:i'))->toBe('09:00');

    ciDoor($this, $day, $this->ada)->assertOk()->assertSee('Clocked in at 8:50 AM');
    Carbon::setTestNow(Carbon::parse('2026-11-04 09:20:00'));
    ciDoor($this, $day, $this->ben)->assertOk()->assertSee('(late)');
    expect(Record::where('user_id', $this->ben->id)->value('status'))->toBe('late');

    Carbon::setTestNow(Carbon::parse('2026-11-04 17:05:00'));
    ciDoor($this, $day, $this->ada)->assertOk()->assertSee('Clocked out at 5:05 PM');
    expect(Record::where('user_id', $this->ada->id)->first()->checked_out_at)->not->toBeNull();

    // A student can't clock in at the office.
    $stu = ciUser('Student', 'Sam Student', 'student');
    ciDoor($this, $day, $stu)->assertSee('Only staff clock in here.');

    // The monthly sheet.
    $this->actingAs($this->hr)->get(route('checkin.staff', ['month' => '2026-11']))->assertOk()->assertSee('Ada Obi')->assertSee('Ben Eze');
    $csv = $this->actingAs($this->hr)->get(route('checkin.staff', ['month' => '2026-11', 'format' => 'csv']))->streamedContent();
    expect($csv)->toContain('"Ada Obi",1,0,08:50,8.3')->toContain('"Ben Eze",1,1,09:20');
});

test('an old door code does not work, but signing in first does not make a fresh one expire', function () {
    $day = $this->svc->staffDay($this->office);
    $old = $this->svc->screenToken($day);
    Carbon::setTestNow(now()->addSeconds(30));
    expect($this->svc->validScreenToken($day, $old))->toBeTrue();
    Carbon::setTestNow(now()->addMinutes(2));
    expect($this->svc->validScreenToken($day, $old))->toBeFalse()
        ->and($this->svc->validScreenToken($day, 'abcdefabcdef'))->toBeFalse();
    $this->get(route('checkin.door', [$day, 'abcdefabcdef']))->assertStatus(410)->assertSee('That code has expired');

    // Not signed in: the code is remembered, then sign in and land on the check-in.
    $this->get(route('checkin.door', [$day, $this->svc->screenToken($day)]))->assertRedirect(route('checkin.go', $day));
    $this->get(route('checkin.go', $day))->assertRedirect(route('login'));
    $this->actingAs($this->ada)->get(route('checkin.go', $day))->assertOk()->assertSee('Clocked in');

    // Without scanning: nothing.
    $this->flushSession();
    $this->actingAs($this->ben)->get(route('checkin.go', $day))->assertSee('That code has expired');
});

test('the door QR can require people to be at the venue', function () {
    $this->office->update(['require_location' => true]);
    $day = $this->svc->staffDay($this->office);
    ciDoor($this, $day, $this->ada)->assertSee('Share my location');
    ciDoor($this, $day, $this->ada, ['lat' => 6.5244, 'lng' => 3.3792])->assertSee('m away');   // Ikeja, ~15 km
    expect(Record::count())->toBe(0);
    ciDoor($this, $day, $this->ada, ['lat' => 6.4285, 'lng' => 3.4221])->assertSee('Clocked in');
    expect(Record::first()->distance_m)->toBeLessThan(100);
});

test('students check in to a class and it goes on the register; closing marks the rest absent', function () {
    $teacher = ciUser('Instructor', 'Tola Teacher');
    $admin = User::where('email', config('qudroid.super_admin.email'))->first();
    $course = Course::create(['code' => 'WEB101', 'title' => 'Web Foundations', 'fee' => 0, 'status' => 'published']);
    $cohort = $course->cohorts()->create(['code' => 'C1', 'name' => 'Cohort 1', 'status' => 'running', 'instructor_id' => $teacher->id]);
    [$s1, $s2] = collect(['Kemi', 'Musa'])->map(function ($n) use ($cohort, $admin) {
        $u = ciUser('Student', $n, 'student');
        $st = Student::create(['user_id' => $u->id, 'student_no' => Student::nextNumber(), 'status' => 'admitted']);
        app(EnrollmentService::class)->enroll($st, $cohort, $admin);

        return $u;
    })->all();
    $session = $cohort->sessions()->create(['date' => today(), 'starts_at' => '09:00', 'ends_at' => '12:00', 'topic' => 'CSS']);

    $this->actingAs($teacher)->post(route('checkin.session', $session))->assertRedirect();
    $event = Event::where('kind', 'class')->first();
    $this->actingAs($teacher)->get(route('checkin.events.show', $event))->assertOk()->assertSee('Not here yet')->assertSee('Kemi')->assertSee('Musa');

    ciDoor($this, $event, $s1)->assertSee('Checked in');
    $att = Attendance::first();
    expect($att->status)->toBe('present')->and($session->fresh()->status)->toBe('held');

    // A student from elsewhere can't.
    ciDoor($this, $event, ciUser('Student', 'Outsider', 'student'))->assertSee('not enrolled in this class');
    // Staff who aren't the instructor can't run it.
    $this->actingAs($this->ada)->get(route('checkin.events.show', $event))->assertForbidden();

    $this->actingAs($teacher)->post(route('checkin.events.close', $event))->assertSessionHas('success', fn ($m) => str_contains($m, '1 student who did not come were marked absent'));
    expect(Attendance::where('status', 'absent')->count())->toBe(1)
        ->and((float) Student::where('user_id', $s1->id)->first()->enrollments()->first()->attendance_percent)->toBe(100.0)
        ->and((float) Student::where('user_id', $s2->id)->first()->enrollments()->first()->attendance_percent)->toBe(0.0);
});

test('hosts scan badges at a meeting; forged badges and people not invited are turned away', function () {
    $m = Meeting::create(['title' => 'Board review', 'starts_at' => now()->addMinutes(10), 'ends_at' => now()->addHour(), 'organizer_id' => $this->hr->id]);
    $m->attendees()->create(['user_id' => $this->ada->id, 'response' => 'accepted']);
    $this->actingAs($this->hr)->post(route('checkin.meeting', $m))->assertRedirect();
    $event = Event::where('kind', 'meeting')->first();

    $scan = fn ($code) => $this->actingAs($this->hr)->postJson(route('checkin.events.scan', $event), ['code' => $code]);
    $scan(CheckinService::badgePayload($this->ada))->assertOk()->assertJsonPath('name', 'Ada Obi')->assertJsonPath('state', 'in');
    $scan(CheckinService::badgePayload($this->ada))->assertOk()->assertJsonPath('state', 'already');
    $scan(CheckinService::badgePayload($this->ben))->assertStatus(422)->assertJsonPath('message', 'You are not invited to this meeting.');
    $scan('QD1:U:' . $this->ben->id . ':0000000000000000')->assertStatus(422)->assertJsonPath('ok', false);
    $scan('hello')->assertStatus(422);

    // Ada can't run the scanner for someone else's meeting.
    $this->actingAs($this->ada)->postJson(route('checkin.events.scan', $event), ['code' => CheckinService::badgePayload($this->ben)])->assertForbidden();

    // Mark Ben by hand, then undo.
    $m->attendees()->create(['user_id' => $this->ben->id, 'response' => 'pending']);
    $this->actingAs($this->hr)->post(route('checkin.events.mark', $event), ['user_id' => $this->ben->id, 'status' => 'late'])->assertSessionHas('success');
    $rec = Record::where('user_id', $this->ben->id)->first();
    expect($rec->method)->toBe('manual')->and($rec->status)->toBe('late')->and($rec->scanned_by)->toBe($this->hr->id);
    $this->actingAs($this->hr)->delete(route('checkin.records.undo', $rec))->assertSessionHas('success');
    expect(Record::count())->toBe(1);

    $csv = $this->actingAs($this->hr)->get(route('checkin.events.export', $event))->streamedContent();
    expect($csv)->toContain('Ada Obi')->toContain('Badge scanned')->toContain('"Ben Eze",')->toContain('Absent');
});

test('a conference takes public sign-ups, e-mails tickets and checks guests in at the door', function () {
    Mail::fake();
    $pm = ciUser('Project Manager', 'Pat Manager');
    $this->actingAs($pm)->post(route('checkin.events.store'), [
        'title' => 'Lagos Tech Summit', 'starts_at' => '2026-11-04 09:00', 'ends_at' => '2026-11-04 17:00', 'location' => 'Landmark Centre',
        'audience' => 'registered', 'allow_screen' => 1, 'allow_badge' => 1, 'public_registration' => 1, 'capacity' => 2, 'opens_minutes' => 60, 'late_minutes' => 30,
    ])->assertSessionHasNoErrors();
    $event = Event::where('kind', 'event')->first();
    expect($event->slug)->toBe('lagos-tech-summit');

    $this->get(route('checkin.public.register', 'lagos-tech-summit'))->assertOk()->assertSee('Lagos Tech Summit')->assertSee('Register');
    $this->post(route('checkin.public.store', 'lagos-tech-summit'), ['name' => 'Ngozi Bello', 'email' => 'ngozi@example.test', 'organisation' => 'Paystack'])->assertRedirect();
    $g = Guest::first();
    Mail::assertSent(CheckinTicketMail::class, fn ($m) => $m->hasTo('ngozi@example.test'));
    $this->get(route('checkin.public.ticket', ['lagos-tech-summit', $g->code]))->assertOk()->assertSee($g->code)->assertSee('<svg', false);

    // Same e-mail again: same ticket, sent again. Bots filling the hidden field are refused.
    $this->post(route('checkin.public.store', 'lagos-tech-summit'), ['name' => 'Ngozi B', 'email' => 'NGOZI@example.test']);
    expect(Guest::count())->toBe(1);
    $this->post(route('checkin.public.store', 'lagos-tech-summit'), ['name' => 'Bot', 'email' => 'b@x.test', 'website' => 'spam'])->assertSessionHasErrors('website');
    $this->post(route('checkin.public.store', 'lagos-tech-summit'), ['name' => 'Tunde', 'email' => 't@x.test']);
    $this->post(route('checkin.public.store', 'lagos-tech-summit'), ['name' => 'Late Comer', 'email' => 'l@x.test'])->assertSessionHasErrors('email');
    $this->get(route('checkin.public.register', 'lagos-tech-summit'))->assertSee('this event is full');

    // At the door: scan the ticket QR, or type the code.
    $this->actingAs($pm)->postJson(route('checkin.events.scan', $event), ['code' => CheckinService::guestPayload($g)])->assertOk()
        ->assertJsonPath('name', 'Ngozi Bello')->assertJsonPath('org', 'Paystack');
    $tunde = Guest::where('name', 'Tunde')->first();
    $tunde->update(['cancelled_at' => now()]);
    $this->actingAs($pm)->postJson(route('checkin.events.scan', $event), ['code' => strtolower($tunde->code)])->assertStatus(422)->assertJsonPath('message', 'This ticket was cancelled.');

    $this->actingAs($pm)->get(route('checkin.events.show', $event))->assertOk()->assertSee('Ngozi Bello')->assertSee('Registered guests');
    $data = $this->actingAs($pm)->getJson(route('checkin.events.screen.data', $event))->assertOk()->json();
    expect($data['open'])->toBeTrue()->and($data['qr'])->toContain('<svg')->and($data['stats']['in'])->toBe(1)->and($data['recent'][0]['name'])->toBe('Ngozi Bello');
    $this->actingAs($pm)->get(route('checkin.events.screen', $event))->assertOk();
    $this->actingAs($pm)->get(route('checkin.events.scanner', $event))->assertOk()->assertSee('html5-qrcode');

    // Staff can't create events.
    $this->actingAs($this->ada)->get(route('checkin.events.create'))->assertForbidden();
});

test('everyone has a personal QR, and the home page lists what they can check in to', function () {
    $this->svc->staffDay($this->office);
    $this->actingAs($this->ada)->get(route('checkin.index'))->assertOk()->assertSee('My QR')->assertSee('Lagos HQ')->assertDontSee('Manage');
    $this->actingAs($this->hr)->get(route('checkin.index'))->assertOk()->assertSee('Manage')->assertSee('Staff attendance');
    $this->actingAs($this->ada)->get(route('checkin.badge'))->assertOk()->assertSee('Ada Obi')->assertSee('<svg', false);

    $client = ciUser('Client', 'Cora Client', 'client');
    $this->actingAs($client)->get(route('checkin.index'))->assertForbidden();

    // Places are managed by HR.
    $this->actingAs($this->hr)->post(route('checkin.places.store'), ['name' => 'Abuja', 'work_starts' => '08:30', 'work_ends' => '16:30', 'grace_minutes' => 10, 'radius_m' => 200, 'workdays' => [1, 2, 3, 4, 5]])
        ->assertSessionHasNoErrors();
    $this->actingAs($this->ada)->get(route('checkin.places.index'))->assertForbidden();
    expect(Place::where('name', 'Abuja')->value('grace_minutes'))->toBe(10);
});

test('the mobile app shows my badge, checks me in from a scanned door QR, and lets hosts scan', function () {
    $this->actingAs($this->hr)->get(route('checkin.places.screen', $this->office));
    $day = Event::where('place_id', $this->office->id)->first();

    Sanctum::actingAs($this->ada);
    $me = $this->getJson('/api/v1/checkin/me')->assertOk();
    expect($me->json('data.badge.payload'))->toStartWith('QD1:U:' . $this->ada->id . ':')
        ->and(collect($me->json('data.today'))->pluck('id'))->toContain($day->id);

    $url = route('checkin.door', [$day, $this->svc->screenToken($day)]);
    $this->postJson('/api/v1/checkin/door', ['url' => $url])->assertOk()->assertJsonPath('data.state', 'in')->assertJsonPath('data.record.status', 'present');
    $this->postJson('/api/v1/checkin/door', ['url' => 'https://example.test/nope'])->assertStatus(422)->assertJsonPath('code', 'not_checkin');
    Carbon::setTestNow(Carbon::parse('2026-11-04 09:30:00'));
    $this->postJson('/api/v1/checkin/door', ['url' => $url])->assertStatus(410)->assertJsonPath('code', 'expired');
    $this->getJson('/api/v1/checkin/history')->assertOk()->assertJsonPath('data.0.event.id', $day->id);

    // Staff can't run the office day; HR can, and scans Ben's badge.
    $this->postJson("/api/v1/checkin/events/{$day->id}/scan", ['code' => CheckinService::badgePayload($this->ben)])->assertForbidden();
    Sanctum::actingAs($this->hr);
    $this->postJson("/api/v1/checkin/events/{$day->id}/scan", ['code' => CheckinService::badgePayload($this->ben)])->assertOk()
        ->assertJsonPath('data.record.name', 'Ben Eze')->assertJsonPath('data.record.status', 'late')->assertJsonPath('data.stats.in', 2);
    $this->getJson("/api/v1/checkin/events/{$day->id}")->assertOk()->assertJsonPath('data.stats.late', 1)->assertJsonCount(2, 'data.recent');
});
