<?php

use App\Models\Crm\Company;
use App\Models\Finance\Invoice;
use App\Models\User;
use App\Services\Connect\ConnectService;
use App\Services\Messaging\PortalNotifier;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function snUser(string $name, array $perms = []): User
{
    $u = User::factory()->create(['user_type' => 'staff', 'name' => $name]);
    $u->assignRole('Staff');
    if ($perms) $u->givePermissionTo($perms);
    $u->forceFill(['must_change_password' => false])->save();

    return $u;
}

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

test('search finds records across modules, only where the person may look', function () {
    $finance = snUser('Fola Finance', ['View finance', 'View clients']);
    $plain = snUser('Pat Plain');
    $student = User::factory()->create(['user_type' => 'student', 'name' => 'Zainab Bello']);
    Invoice::forceCreate(['number' => 'QUD-INV-0099', 'user_id' => $student->id, 'title' => 'Zebra website build', 'issue_date' => today(), 'due_date' => today(),
        'subtotal' => 100, 'discount_total' => 0, 'total' => 100, 'amount_paid' => 0, 'status' => 'issued']);
    Company::forceCreate(['name' => 'Zebra Logistics Ltd', 'status' => 'client', 'created_by' => $finance->id]);

    $res = $this->actingAs($finance)->getJson(route('search', ['q' => 'zebra']))->assertOk()->json('results');
    expect(collect($res)->pluck('category')->unique()->values()->all())->toEqualCanonicalizing(['Invoices', 'Clients'])
        ->and(collect($res)->firstWhere('category', 'Invoices')['title'])->toContain('QUD-INV-0099');

    // someone without finance or CRM access finds neither
    expect($this->actingAs($plain)->getJson(route('search', ['q' => 'zebra']))->json('results'))->toBe([]);

    // the full page, and messages the person is part of
    $chat = app(ConnectService::class)->direct($finance, $plain);
    app(ConnectService::class)->send($chat, $plain, 'The zebra crossing photos are in Drive');
    $this->actingAs($plain)->get(route('search', ['q' => 'zebra']))->assertOk()->assertSee('Messages')->assertSee('zebra crossing photos')->assertDontSee('Zebra Logistics');
    $this->actingAs($plain)->get(route('search', ['q' => 'z']))->assertOk()->assertSee('at least two letters');
});

test('notifications can be filtered by kind, switched off by kind, and cleared', function () {
    $ada = snUser('Ada');
    PortalNotifier::toUsers([$ada->id], 'New message', 'hi', null, 'message');
    $this->travel(1)->seconds();
    PortalNotifier::toUsers([$ada->id], 'Leave approved', 'yes', null, 'leave');

    $this->actingAs($ada)->get(route('notifications.index', ['kind' => 'people']))->assertOk()->assertSee('Leave approved')->assertDontSee('New message');
    $this->actingAs($ada)->getJson(route('notifications.feed'))->assertJsonPath('latest.type', 'leave');

    // switch off messages: new ones don't arrive; announcements always do
    $this->actingAs($ada)->post(route('notifications.settings'), ['on' => ['people', 'meetings']])->assertRedirect();
    expect($ada->refresh()->muted_notifications)->toContain('messages')->not->toContain('people');
    expect(PortalNotifier::toUsers([$ada->id], 'Another message', 'x', null, 'message'))->toBe(0)
        ->and(PortalNotifier::toUsers([$ada->id], 'Office closed Friday', 'x', null, 'announcement'))->toBe(1);

    $ada->unreadNotifications->markAsRead();
    $this->actingAs($ada)->post(route('notifications.clear-read'))->assertRedirect();
    expect($ada->notifications()->count())->toBe(0);
});

test('every page has the app launcher and sounds, and chat polling reports new messages from others', function () {
    $ada = snUser('Ada');
    $ben = snUser('Ben');
    $this->actingAs($ada)->get(route('dashboard'))->assertOk()->assertSee('id="al-btn"', false)->assertSee('window.QSound', false);

    $connect = app(ConnectService::class);
    $chat = $connect->direct($ada, $ben);
    $first = $connect->send($chat, $ada, 'one');
    $connect->send($chat, $ben, 'two');
    $connect->send($chat, $ada, 'three');
    $this->actingAs($ada)->getJson(route('connect.poll', $chat) . '?after=' . $first->id)->assertJsonPath('incoming', 1);
});
