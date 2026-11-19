<?php

use App\Models\Academy\Attendance;
use App\Models\Academy\ClassSession;
use App\Models\Academy\Course;
use App\Models\Academy\Student;
use App\Models\Checkin\Event;
use App\Models\Checkin\Place;
use App\Models\Checkin\Record;
use App\Models\User;
use App\Services\Academy\EnrollmentService;
use App\Services\Checkin\CheckinService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

// Campus at Yaba, Lagos; a point ~40 m away and one ~2 km away.
const CAMPUS = [6.5095, 3.3711];
const NEARBY = [6.5098, 3.3713];
const FAR = [6.5250, 3.3800];

function ccStudent(string $name): User
{
    $u = User::factory()->create(['user_type' => 'student', 'name' => $name]);
    $u->assignRole('Student');
    $u->forceFill(['must_change_password' => false])->save();

    return $u;
}

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-11-18 08:50:00'));
    $this->seed(DatabaseSeeder::class);
    $this->admin = User::where('email', config('qudroid.super_admin.email'))->first();
    $this->admin->forceFill(['must_change_password' => false])->save();

    $this->campus = Place::create(['name' => 'Yaba campus', 'lat' => CAMPUS[0], 'lng' => CAMPUS[1], 'radius_m' => 100, 'work_starts' => '08:00', 'work_ends' => '17:00', 'is_active' => true]);
    $this->course = Course::create(['code' => 'WEB1', 'title' => 'Web Development', 'fee' => 0, 'status' => 'published']);
    $this->cohort = $this->course->cohorts()->create(['code' => 'W1', 'name' => 'Morning class', 'status' => 'running', 'place_id' => $this->campus->id, 'checkin_mode' => 'either']);
    $this->session = $this->cohort->sessions()->create(['date' => '2026-11-18', 'starts_at' => '09:00', 'ends_at' => '12:00', 'topic' => 'Forms']);

    $this->ada = ccStudent('Ada Obi');
    $this->ike = ccStudent('Ike Okafor');
    foreach ([$this->ada, $this->ike] as $u) {
        app(EnrollmentService::class)->enroll(Student::create(['user_id' => $u->id, 'status' => 'admitted']), $this->cohort, $this->admin);
    }
});

test('students are told when check-in opens, once, and the class check-in follows the class settings', function () {
    Carbon::setTestNow('2026-11-18 08:40:00');
    $this->artisan('attendance:tick')->expectsOutput('Reminded 0 classes, closed 0.');   // opens 15 min before

    Carbon::setTestNow('2026-11-18 08:46:00');
    $this->artisan('attendance:tick')->expectsOutput('Reminded 1 classes, closed 0.');
    $this->artisan('attendance:tick')->expectsOutput('Reminded 0 classes, closed 0.');

    expect($this->ada->notifications()->where('data', 'like', '%Check in: Web Development%')->count())->toBe(1)
        ->and($this->ada->notifications()->where('data', 'like', '%Check in: Web Development%')->first()->data['url'])->toBe(route('checkin.index'));
    $e = Event::where('kind', 'class')->firstOrFail();
    expect($e->allow_gps)->toBeTrue()->and($e->allow_screen)->toBeTrue()->and($e->require_location)->toBeTrue()
        ->and((int) $e->radius_m)->toBe(100)->and($e->place_id)->toBe($this->campus->id);
});

test('"I\'m here" works only on campus, with a precise, real location', function () {
    $e = app(CheckinService::class)->forClassSession($this->session);
    Sanctum::actingAs($this->ada, ['*']);
    $here = fn (array $p) => $this->postJson('/api/v1/checkin/events/' . $e->id . '/here', $p);

    $here(['lat' => FAR[0], 'lng' => FAR[1], 'accuracy' => 10])->assertStatus(422)->assertJsonPath('code', 'location')
        ->assertJsonPath('message', fn ($m) => str_contains($m, 'from Yaba campus'));
    $here(['lat' => NEARBY[0], 'lng' => NEARBY[1], 'accuracy' => 900])->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'not precise enough'));
    $here(['lat' => NEARBY[0], 'lng' => NEARBY[1], 'accuracy' => 12, 'mocked' => true])->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'fake location'));

    $here(['lat' => NEARBY[0], 'lng' => NEARBY[1], 'accuracy' => 12])->assertOk()->assertJsonPath('data.state', 'in');
    $r = Record::firstOrFail();
    expect($r->method)->toBe('gps')->and($r->status)->toBe('present')->and($r->distance_m)->toBeLessThan(60)->and($r->accuracy_m)->toBe(12);
    $a = Attendance::firstOrFail();
    expect($a->status)->toBe('present')->and($a->note)->toBe('Checked in on campus (GPS)');

    $here(['lat' => NEARBY[0], 'lng' => NEARBY[1], 'accuracy' => 12])->assertOk()->assertJsonPath('data.state', 'already');

    // Not enrolled: refused.
    Sanctum::actingAs(ccStudent('Stranger'), ['*']);
    $here(['lat' => NEARBY[0], 'lng' => NEARBY[1], 'accuracy' => 12])->assertStatus(422)->assertJsonPath('code', 'refused');
});

test('arriving after the late time is marked late; before opening is refused', function () {
    $e = app(CheckinService::class)->forClassSession($this->session);
    Sanctum::actingAs($this->ada, ['*']);
    Carbon::setTestNow('2026-11-18 08:30:00');
    $this->postJson('/api/v1/checkin/events/' . $e->id . '/here', ['lat' => NEARBY[0], 'lng' => NEARBY[1], 'accuracy' => 10])->assertStatus(422);
    Carbon::setTestNow('2026-11-18 09:20:00');
    $this->postJson('/api/v1/checkin/events/' . $e->id . '/here', ['lat' => NEARBY[0], 'lng' => NEARBY[1], 'accuracy' => 10])->assertOk();
    expect(Attendance::first()->status)->toBe('late');
});

test('the class mode decides QR, GPS or neither', function () {
    $svc = app(CheckinService::class);
    $this->cohort->update(['checkin_mode' => 'qr']);
    $e = $svc->forClassSession($this->session);
    Sanctum::actingAs($this->ada, ['*']);
    $this->postJson('/api/v1/checkin/events/' . $e->id . '/here', ['lat' => NEARBY[0], 'lng' => NEARBY[1], 'accuracy' => 10])
        ->assertStatus(422)->assertJsonPath('message', 'Scan the class QR code to check in.');

    // QR still needs the student on campus.
    $url = route('checkin.door', [$e, $svc->screenToken($e)]);
    $this->postJson('/api/v1/checkin/door', ['url' => $url, 'lat' => FAR[0], 'lng' => FAR[1]])->assertStatus(422)->assertJsonPath('code', 'location');
    $this->postJson('/api/v1/checkin/door', ['url' => $url, 'lat' => NEARBY[0], 'lng' => NEARBY[1]])->assertOk();

    $this->cohort->update(['checkin_mode' => 'gps']);
    $e = $svc->forClassSession($this->session);   // open check-ins follow the class's settings
    Sanctum::actingAs($this->ike, ['*']);
    $this->postJson('/api/v1/checkin/door', ['url' => route('checkin.door', [$e, $svc->screenToken($e)]), 'lat' => NEARBY[0], 'lng' => NEARBY[1]])
        ->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'tap "I\'m here"'));

    $this->cohort->update(['checkin_mode' => 'off']);
    Carbon::setTestNow('2026-11-18 08:50:00');
    $this->artisan('attendance:tick')->expectsOutput('Reminded 0 classes, closed 0.');
});

test('the app lists today\'s class with how to check in and the student\'s own status', function () {
    Sanctum::actingAs($this->ada, ['*']);
    $today = $this->getJson('/api/v1/checkin/me')->assertOk()->json('data.today');
    expect($today)->toHaveCount(1)
        ->and($today[0]['kind'])->toBe('class')->and($today[0]['allow_gps'])->toBeTrue()->and($today[0]['allow_qr'])->toBeTrue()
        ->and($today[0]['place']['name'])->toBe('Yaba campus')->and($today[0]['place']['radius_m'])->toBe(100)
        ->and($today[0]['mine'])->toBeNull();

    $this->postJson('/api/v1/checkin/events/' . $today[0]['id'] . '/here', ['lat' => NEARBY[0], 'lng' => NEARBY[1], 'accuracy' => 10])->assertOk();
    expect($this->getJson('/api/v1/checkin/me')->json('data.today.0.mine.status'))->toBe('present');
});

test('when the class ends, students who did not check in are marked absent', function () {
    $e = app(CheckinService::class)->forClassSession($this->session);
    app(CheckinService::class)->checkIn($e, $this->ada, null, 'gps', null, ['lat' => NEARBY[0], 'lng' => NEARBY[1], 'accuracy' => 10]);

    Carbon::setTestNow('2026-11-18 12:01:00');
    $this->artisan('attendance:tick')->expectsOutput('Reminded 0 classes, closed 1.');
    expect(Attendance::where('status', 'absent')->count())->toBe(1)
        ->and($e->fresh()->status)->toBe('closed')->and($this->session->fresh()->status)->toBe('held');

    // The student's own attendance in the app.
    Sanctum::actingAs($this->ike, ['*']);
    $mine = $this->getJson('/api/v1/academy/attendance')->assertOk()->json('data.0');
    expect($mine['course'])->toBe('Web Development')->and($mine['rate'])->toEqual(0)->and($mine['counts']['absent'])->toBe(1)
        ->and($mine['sessions'][0]['status'])->toBe('absent')->and($mine['campus'])->toBe('Yaba campus');
    Sanctum::actingAs($this->ada, ['*']);
    expect($this->getJson('/api/v1/academy/attendance')->json('data.0.sessions.0.method'))->toBe('gps');
});

test('without auto-absent the register is left for the instructor', function () {
    $this->cohort->update(['auto_absent' => false]);
    $e = app(CheckinService::class)->forClassSession($this->session);
    Carbon::setTestNow('2026-11-18 12:01:00');
    $this->artisan('attendance:tick');
    expect(Attendance::count())->toBe(0)->and($e->fresh()->status)->toBe('closed');
});

test('staff set the campus and check-in mode on the class', function () {
    $data = ['code' => 'W2', 'name' => 'Evening class', 'status' => 'open', 'checkin_mode' => 'gps', 'checkin_opens_minutes' => 20, 'late_after_minutes' => 10, 'auto_absent' => 1];
    $this->actingAs($this->admin)->get(route('academy.cohorts.create', $this->course))->assertOk()->assertSee('Yaba campus')->assertSee('How students check in');
    $this->actingAs($this->admin)->post(route('academy.cohorts.store', $this->course), $data)->assertSessionHasErrors('place_id');
    $this->actingAs($this->admin)->post(route('academy.cohorts.store', $this->course), $data + ['place_id' => $this->campus->id])->assertSessionHasNoErrors();
    $c = $this->course->cohorts()->where('code', 'W2')->first();
    expect($c->checkin_mode)->toBe('gps')->and($c->place_id)->toBe($this->campus->id)->and($c->checkin_opens_minutes)->toBe(20)->and($c->auto_absent)->toBeTrue();
});

test('staff see how each student checked in, remind the rest, and download the class sheet', function () {
    $svc = app(CheckinService::class);
    $e = $svc->forClassSession($this->session);
    $svc->checkIn($e, $this->ada, null, 'gps', null, ['lat' => NEARBY[0], 'lng' => NEARBY[1], 'accuracy' => 8]);

    $this->actingAs($this->admin)->get(route('attendance.register', $this->session))->assertOk()
        ->assertSee('On site (GPS)')->assertSee('m from campus', false)->assertSee('±8 m', false)->assertSee('Remind to check in');

    $this->actingAs($this->admin)->post(route('attendance.sessions.remind', $this->session))->assertSessionHas('success', '1 student reminded to check in.');
    expect($this->ike->notifications()->where('data', 'like', '%Check in now%')->count())->toBe(1)
        ->and($this->ada->notifications()->where('data', 'like', '%Check in now%')->count())->toBe(0);

    Carbon::setTestNow('2026-11-18 12:01:00');
    $this->artisan('attendance:tick');
    $csv = $this->actingAs($this->admin)->get(route('attendance.cohort.export', $this->cohort))->assertOk()->streamedContent();
    expect($csv)->toContain('2026-11-18 09:00')->toContain('"Ada Obi",QCA/2026/0001,P,1,0,0,0,100')->toContain('"Ike Okafor",QCA/2026/0002,A,0,0,1,0,0');
});

test('the report filters by class and rate, downloads, and notifies low attenders', function () {
    $svc = app(CheckinService::class);
    $e = $svc->forClassSession($this->session);
    $svc->checkIn($e, $this->ada, null, 'gps', null, ['lat' => NEARBY[0], 'lng' => NEARBY[1], 'accuracy' => 8]);
    Carbon::setTestNow('2026-11-18 12:01:00');
    $this->artisan('attendance:tick');

    $this->actingAs($this->admin)->get(route('attendance.report'))->assertOk()->assertSee('Ada Obi')->assertSee('Ike Okafor')->assertSee('1 below 75%');
    $this->actingAs($this->admin)->get(route('attendance.report', ['below' => 75]))->assertOk()->assertSee('Ike Okafor')->assertDontSee('Ada Obi');
    $csv = $this->actingAs($this->admin)->get(route('attendance.report', ['export' => 1]))->streamedContent();
    expect($csv)->toContain('Ike Okafor')->toContain('Web Development');

    $ike = \App\Models\Academy\Enrollment::whereHas('student', fn ($s) => $s->where('user_id', $this->ike->id))->first();
    $this->actingAs($this->admin)->post(route('attendance.nudge'), ['enrollments' => [$ike->id], 'message' => 'See me on Friday.'])->assertSessionHas('success', '1 student notified.');
    expect($this->ike->notifications()->where('data', 'like', '%Your attendance is 0%')->where('data', 'like', '%See me on Friday.%')->count())->toBe(1);

    // An instructor sees and notifies only their own classes.
    $teacher = User::factory()->create(['user_type' => 'staff']);
    $teacher->assignRole('Instructor');
    $teacher->forceFill(['must_change_password' => false])->save();
    $this->actingAs($teacher)->get(route('attendance.report'))->assertOk()->assertDontSee('Ike Okafor');
    $this->actingAs($teacher)->post(route('attendance.nudge'), ['enrollments' => [$ike->id]])->assertSessionHas('success', 'Nobody was notified.');
    $this->cohort->update(['instructor_id' => $teacher->id]);
    $this->actingAs($teacher)->get(route('attendance.report'))->assertSee('Ike Okafor');
});

test('students see their own attendance on the web', function () {
    $svc = app(CheckinService::class);
    $svc->checkIn($svc->forClassSession($this->session), $this->ada, null, 'gps', null, ['lat' => NEARBY[0], 'lng' => NEARBY[1], 'accuracy' => 8]);
    $mine = \App\Models\Academy\Enrollment::whereHas('student', fn ($s) => $s->where('user_id', $this->ada->id))->first();
    $this->actingAs($this->ada)->get(route('academy.my.attendance', $mine))->assertOk()->assertSee('Present')->assertSee('On site (GPS)')->assertSee('Yaba campus');
    $this->actingAs($this->ike)->get(route('academy.my.attendance', $mine))->assertNotFound();
});
