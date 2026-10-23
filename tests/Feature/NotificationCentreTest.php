<?php

use App\Models\Finance\Account;
use App\Models\Finance\Expense;
use App\Models\Hr\LeaveRequest;
use App\Models\Hr\LeaveType;
use App\Models\Meetings\Meeting;
use App\Models\User;
use App\Services\Connect\ConnectService;
use App\Services\Finance\ExpenseService;
use App\Services\Hr\EmployeeService;
use App\Services\Meetings\MeetingService;
use App\Services\Messaging\PortalNotifier;
use App\Services\Push\FcmClient;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function ncUser(string $role = 'Staff', string $name = null): User
{
    $u = User::factory()->create(['user_type' => 'staff'] + ($name ? ['name' => $name] : []));
    $u->assignRole($role);
    $u->forceFill(['must_change_password' => false])->save();

    return $u;
}

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

test('a leave request can be approved from the notification, once', function () {
    $boss = ncUser();
    $me = ncUser();
    $make = fn (User $u, array $d = []) => app(EmployeeService::class)->create(['user_id' => $u->id, 'employment_type' => 'full_time', 'annual_leave_days' => 20,
        'start_date' => '2026-01-05', 'basic_salary' => 300000, 'housing_allowance' => 0, 'transport_allowance' => 0, 'pension_enrolled' => false, 'nhf_enrolled' => false] + $d)[0];
    $make($boss);
    $make($me, ['manager_id' => $boss->id]);
    $annual = LeaveType::where('is_annual', true)->first();
    $this->actingAs($me)->post(route('me.leave.store'), ['leave_type_id' => $annual->id, 'starts_on' => '2026-12-21', 'ends_on' => '2026-12-22'])->assertRedirect();

    $n = $boss->notifications()->first();
    expect($n->data['category'])->toBe('approval')->and($n->data['level'])->toBe('urgent')
        ->and(collect($n->data['actions'])->pluck('id')->all())->toBe(['approve', 'decline']);

    // someone else's notification can't be used
    $this->actingAs($me)->postJson(route('notifications.act', [$n->id, 'approve']))->assertNotFound();

    $this->actingAs($boss)->postJson(route('notifications.act', [$n->id, 'approve']))->assertOk()->assertJsonPath('done', 'Approved');
    expect(LeaveRequest::first()->status)->toBe('approved')->and($n->fresh()->read_at)->not->toBeNull();

    // a second tap says it's done instead of approving twice
    $this->actingAs($boss)->postJson(route('notifications.act', [$n->id, 'approve']))->assertStatus(422);
});

test('rejecting an expense from the app needs a reason', function () {
    $md = ncUser('Managing Director');
    $clerk = ncUser('Finance Officer');
    $rent = Account::ofType('expense')->first();
    $expense = app(ExpenseService::class)->submit(['date' => today(), 'payee' => 'Landlord', 'description' => 'Rent', 'account_id' => $rent->id, 'amount' => 1000], $clerk);
    $n = $md->notifications()->first();
    Sanctum::actingAs($md);

    $this->postJson(route('api.v1.notifications.act', [$n->id, 'reject']))->assertStatus(422)->assertJsonPath('code', 'action_failed');
    expect($expense->fresh()->status)->toBe('submitted');

    $this->postJson(route('api.v1.notifications.act', [$n->id, 'reject']), ['input' => 'No receipt'])
        ->assertOk()->assertJsonPath('data.done', 'Rejected')->assertJsonPath('data.actions', []);
    expect($expense->fresh()->status)->toBe('rejected');
});

test('meeting invites can be answered and messages replied to from the notification', function () {
    $pm = ncUser('Project Manager');
    $ada = ncUser();
    $m = app(MeetingService::class)->schedule(['title' => 'Sprint planning', 'starts_at' => Carbon::parse('tomorrow 09:00')->format('Y-m-d\TH:i'),
        'ends_at' => Carbon::parse('tomorrow 10:00')->format('Y-m-d\TH:i')], [$ada->id], $pm);
    $invite = $ada->notifications()->get()->first(fn ($n) => ($n->data['target']['kind'] ?? null) === 'meeting');
    expect($invite)->not->toBeNull();
    $this->actingAs($ada)->postJson(route('notifications.act', [$invite->id, 'accepted']))->assertOk();
    expect($m->attendees()->where('user_id', $ada->id)->value('response'))->toBe('accepted');

    $connect = app(ConnectService::class);
    $chat = $connect->direct($pm, $ada);
    $connect->send($chat, $pm, 'Can you share the deck?');
    $msg = $ada->notifications()->get()->first(fn ($n) => ($n->data['target']['kind'] ?? null) === 'message');
    expect($msg)->not->toBeNull()->and($msg->data['thread'])->toBe('connect:' . $chat->id);
    $this->actingAs($ada)->postJson(route('notifications.act', [$msg->id, 'reply']))->assertStatus(422);
    $this->actingAs($ada)->postJson(route('notifications.act', [$msg->id, 'reply']), ['input' => 'Sending it now'])->assertOk();
    expect($chat->messages()->latest('id')->value('body'))->toBe('Sending it now');
});

test('the feed stacks a conversation and filters mentions and approvals', function () {
    $ada = ncUser();
    foreach (['one', 'two', 'three'] as $i => $t) {
        PortalNotifier::toUsers([$ada->id], 'Ben', $t, null, 'message', "connect:7:$i");
        $this->travel(1)->seconds();
    }
    PortalNotifier::toUsers([$ada->id], 'Ben mentioned you', 'hey @Ada', null, 'message', 'connect-mention:9:1');
    PortalNotifier::toUsers([$ada->id], 'Expense to approve', 'x', null, 'approval', 'expense:1:submitted');

    $feed = $this->actingAs($ada)->getJson(route('notifications.feed'))->assertOk()->json();
    $stack = collect($feed['items'])->firstWhere('thread', 'connect:7');
    expect($feed['unread'])->toBe(5)->and($stack['stack'])->toBe(2)->and($stack['body'])->toBe('three');

    Sanctum::actingAs($ada);
    expect($this->getJson(route('api.v1.notifications.index', ['filter' => 'mentions']))->json('data'))->toHaveCount(1)
        ->and($this->getJson(route('api.v1.notifications.index', ['filter' => 'approvals']))->json('data.0.title'))->toBe('Expense to approve')
        ->and($this->getJson(route('api.v1.notifications.index', ['stacked' => 1]))->json('data'))->toHaveCount(3);

    // clearing a stack, and marking unread again
    $this->deleteJson(route('api.v1.notifications.destroy', $stack['id']), ['ids' => $stack['stack_ids']])->assertOk()->assertJsonPath('meta.unread', 2);
    $one = $ada->notifications()->first();
    $this->postJson(route('api.v1.notifications.read', $one->id))->assertJsonPath('meta.unread', 1);
    $this->postJson(route('api.v1.notifications.read', $one->id), ['unread' => true])->assertJsonPath('meta.unread', 2);
});

test('quiet hours hold back pushes except urgent ones, and the badge counts unread', function () {
    config(['services.fcm.enabled' => true]);
    $sent = [];
    $fcm = Mockery::mock(FcmClient::class)->makePartial();
    $fcm->shouldReceive('configured')->andReturn(true);
    $fcm->shouldReceive('send')->andReturnUsing(function ($token, $title, $body, $data) use (&$sent) { $sent[] = $data + ['title' => $title]; return 'ok'; });
    app()->instance(FcmClient::class, $fcm);

    $ada = ncUser();
    \DB::table('mobile_devices')->insert(['user_id' => $ada->id, 'device_name' => 'Phone', 'platform' => 'ios', 'push_token' => 'tok', 'created_at' => now(), 'updated_at' => now()]);
    $this->travelTo(Carbon::parse('2026-11-02 23:30'));
    $ada->forceFill(['notify_quiet_from' => '22:00', 'notify_quiet_to' => '07:00'])->save();
    expect(PortalNotifier::inQuietHours($ada->fresh()))->toBeTrue();

    \Illuminate\Support\Facades\Bus::fake();
    PortalNotifier::toUsers([$ada->id], 'Ben', 'late message', null, 'message', 'connect:1:1');
    PortalNotifier::toUsers([$ada->id], 'Leave to approve', 'x', null, 'leave', 'leave:1:request');
    $pushed = \Illuminate\Support\Facades\Bus::dispatchedAfterResponse(\App\Jobs\SendPushNotification::class);
    expect($pushed)->toHaveCount(1)->and($pushed->first()->title)->toBe('Leave to approve');
    $job = $pushed->first();
    $job->handle();

    expect($sent)->toHaveCount(1)
        ->and($sent[0]['badge'])->toBe(2)->and($sent[0]['notification_id'])->toBe($ada->notifications()->get()->firstWhere('data.title', 'Leave to approve')->id)
        ->and($sent[0]['category'])->toBe('approval')->and($sent[0]['thread_id'])->toBe('leave:1')
        ->and(FcmClient::channelFor('mention'))->toBe('messages')->and(FcmClient::channelFor('approval'))->toBe('approvals');

    $this->travelTo(Carbon::parse('2026-11-03 08:00'));
    expect(PortalNotifier::inQuietHours($ada->fresh()))->toBeFalse();
});

test('settings save from the web and the app', function () {
    $ada = ncUser();
    $this->actingAs($ada)->post(route('notifications.settings'), ['on' => ['people', 'meetings'], 'quiet' => 1, 'quiet_from' => '21:00', 'quiet_to' => '06:30', 'digest' => 1])
        ->assertRedirect();
    $ada->refresh();
    expect($ada->notify_quiet_from)->toStartWith('21:00')->and($ada->notify_digest)->toBeTrue()->and($ada->muted_notifications)->toContain('messages');

    Sanctum::actingAs($ada);
    $this->getJson(route('api.v1.notifications.settings'))->assertOk()->assertJsonPath('data.digest', true);
    $this->putJson(route('api.v1.notifications.settings'), ['groups' => ['messages' => true], 'quiet_from' => null, 'quiet_to' => null])->assertOk()
        ->assertJsonPath('data.quiet_from', null);
    expect($ada->fresh()->muted_notifications)->not->toContain('messages');
    $this->putJson(route('api.v1.notifications.settings'), ['quiet_from' => '22:00'])->assertStatus(422);
});

test('the morning digest e-mails people who opted in and have unread items', function () {
    Mail::fake();
    $ada = ncUser();
    $ben = ncUser();
    $ada->forceFill(['notify_digest' => true])->save();
    $ben->forceFill(['notify_digest' => true])->save();
    PortalNotifier::toUsers([$ada->id], 'Invoice paid', 'x', null, 'finance');

    $this->artisan('notifications:digest')->assertSuccessful();
    Mail::assertSent(\App\Mail\NotificationDigestMail::class, fn ($m) => $m->hasTo($ada->email));
    Mail::assertNotSent(\App\Mail\NotificationDigestMail::class, fn ($m) => $m->hasTo($ben->email));

    // not twice in one day
    $this->artisan('notifications:digest')->assertSuccessful();
    Mail::assertSent(\App\Mail\NotificationDigestMail::class, 1);
});
