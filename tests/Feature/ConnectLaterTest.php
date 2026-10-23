<?php

use App\Models\Connect\Message;
use App\Models\Connect\Reminder;
use App\Models\Connect\ScheduledMessage;
use App\Models\User;
use App\Services\Connect\ConnectService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function laterUser(string $name): User
{
    $u = User::factory()->create(['user_type' => 'staff', 'name' => $name]);
    $u->assignRole('Staff');
    $u->forceFill(['must_change_password' => false])->save();

    return $u;
}

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->ada = laterUser('Ada Obi');
    $this->ben = laterUser('Ben Eze');
    $this->cy = laterUser('Cy Uche');
    $this->svc = app(ConnectService::class);
    $this->group = $this->svc->group($this->ada, 'Design', [$this->ben->id]);
});

test('a scheduled message sends itself at the time, from its author', function () {
    $this->actingAs($this->ada)->postJson(route('connect.schedule', $this->group), ['body' => 'Morning all', 'when' => '60'])->assertOk();
    $s = ScheduledMessage::first();
    expect($s->send_at->between(now()->addMinutes(59), now()->addMinutes(61)))->toBeTrue();
    $this->actingAs($this->ada)->postJson(route('connect.schedule', $this->group), ['body' => 'Too soon', 'at' => now()->subHour()->toDateTimeString()])->assertStatus(422);
    $this->actingAs($this->cy)->postJson(route('connect.schedule', $this->group), ['body' => 'Not mine', 'when' => '20'])->assertStatus(422);

    // nothing yet; others can't touch it
    $this->artisan('connect:later')->assertSuccessful();
    expect(Message::where('body', 'Morning all')->exists())->toBeFalse();
    $this->actingAs($this->ben)->deleteJson(route('connect.scheduled.cancel', $s))->assertStatus(422);

    $this->actingAs($this->ada)->get(route('connect.show', $this->group))->assertSee('1 scheduled message here');
    $this->get(route('connect.later'))->assertOk()->assertSee('Morning all');

    $this->travel(61)->minutes();
    $this->artisan('connect:later')->assertSuccessful();
    $m = Message::where('body', 'Morning all')->first();
    expect($m)->not->toBeNull()->and($m->user_id)->toBe($this->ada->id)->and($s->fresh()->message_id)->toBe($m->id);
    $this->artisan('connect:later')->assertSuccessful();
    expect(Message::where('body', 'Morning all')->count())->toBe(1);   // never twice
});

test('a scheduled message that can no longer be sent says why and tells the author', function () {
    $s = app(\App\Services\Connect\LaterService::class)->schedule($this->group, $this->ben, 'Bye', now()->addMinutes(5));
    $this->svc->leave($this->group, $this->ben);
    $this->travel(6)->minutes();
    $this->artisan('connect:later')->assertSuccessful();
    expect($s->fresh()->failed_reason)->not->toBeNull()->and($s->fresh()->sent_at)->toBeNull()
        ->and($this->ben->notifications()->get()->pluck('data.title'))->toContain('A scheduled message was not sent');
});

test('reminders about a message or a note arrive in the bell and can be done or snoozed', function () {
    $m = $this->svc->send($this->group, $this->ben, 'Please review the logo by Friday');
    $this->actingAs($this->ada)->postJson(route('connect.reminders.store'), ['message_id' => $m->id, 'when' => '20'])->assertOk();
    $this->actingAs($this->ada)->postJson(route('connect.reminders.store'), ['note' => 'Call the printer', 'when' => 'tomorrow9'])->assertOk();
    $this->actingAs($this->cy)->postJson(route('connect.reminders.store'), ['message_id' => $m->id, 'when' => '20'])->assertStatus(422);   // can't see it
    $this->ada->notifications()->delete();

    $this->travel(21)->minutes();
    $this->artisan('connect:later');
    $n = $this->ada->notifications()->get();
    expect($n)->toHaveCount(1)->and($n[0]->data['title'])->toBe('Reminder: message from Ben Eze')->and($n[0]->data['url'])->toContain('around=' . $m->id);

    $r = Reminder::whereNotNull('message_id')->first();
    $this->actingAs($this->ada)->postJson(route('connect.reminders.snooze', $r), ['when' => '60'])->assertOk();
    $this->travel(61)->minutes();
    $this->artisan('connect:later');
    expect($this->ada->notifications()->count())->toBe(2);
    $this->actingAs($this->ada)->postJson(route('connect.reminders.done', $r))->assertOk();
    expect($r->fresh()->done_at)->not->toBeNull();
    $this->actingAs($this->ben)->postJson(route('connect.reminders.done', $r))->assertStatus(422);
    $this->actingAs($this->ada)->get(route('connect.later'))->assertOk()->assertSee('Call the printer');
});
