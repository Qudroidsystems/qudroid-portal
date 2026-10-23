<?php

use App\Models\Crm\Deal;
use App\Models\Finance\Expense;
use App\Models\Finance\Invoice;
use App\Models\Finance\Payment;
use App\Models\User;
use App\Services\Reports\CommandCenter;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->boss = User::factory()->create(['user_type' => 'staff', 'name' => 'Boss']);
    $this->boss->assignRole('Staff');
    $this->boss->givePermissionTo('View command center');
    $this->boss->forceFill(['must_change_password' => false])->save();
});

test('only people with the permission can open it, and it works on an empty portal', function () {
    $staff = User::factory()->create(['user_type' => 'staff']);
    $staff->assignRole('Staff');
    $staff->forceFill(['must_change_password' => false])->save();
    $this->actingAs($staff)->get(route('command-center'))->assertForbidden();

    foreach (array_keys(CommandCenter::PERIODS) as $p) {
        $this->actingAs($this->boss)->get(route('command-center', ['period' => $p]))->assertOk()->assertSee('Command Center');
    }
    $this->actingAs($this->boss)->get(route('command-center', ['period' => 'nonsense']))->assertOk()->assertSee('Nothing is waiting on anyone');
});

test('money, deals and the things waiting are counted for the period and compared', function () {
    $student = User::factory()->create(['user_type' => 'student']);
    $mk = fn (string $amount, $when) => Payment::forceCreate(['number' => 'R' . uniqid(), 'user_id' => $student->id, 'amount' => $amount,
        'method' => 'bank', 'status' => 'confirmed', 'paid_at' => $when, 'confirmed_at' => $when]);
    $mk('150000.50', now()->startOfMonth()->addHour());
    $mk('100000', now()->subMonthNoOverflow()->startOfMonth()->addHour());
    Payment::forceCreate(['number' => 'R-pending', 'user_id' => $student->id, 'amount' => '999', 'method' => 'paystack', 'status' => 'pending']);

    $acct = \App\Models\Finance\Account::where('type', 'expense')->value('id') ?? \App\Models\Finance\Account::forceCreate(['code' => '5999', 'name' => 'Sundry', 'type' => 'expense'])->id;
    Expense::forceCreate(['account_id' => $acct, 'number' => 'E1', 'date' => today(), 'payee' => 'NEPA', 'description' => 'Power', 'amount' => '20000', 'status' => 'paid',
        'paid_at' => now()->startOfMonth()->addHours(2), 'submitted_by' => $this->boss->id]);
    Expense::forceCreate(['account_id' => $acct, 'number' => 'E2', 'date' => today(), 'payee' => 'Shop', 'description' => 'Chairs', 'amount' => '5000', 'status' => 'submitted', 'submitted_by' => $this->boss->id]);

    Invoice::forceCreate(['number' => 'I1', 'user_id' => $student->id, 'title' => 'Fees', 'issue_date' => today()->subMonths(2), 'due_date' => today()->subMonth(),
        'subtotal' => '80000', 'discount_total' => 0, 'total' => '80000', 'amount_paid' => '30000', 'status' => 'partially_paid']);

    Deal::forceCreate(['title' => 'School portal', 'value' => '2000000', 'currency' => 'NGN', 'stage' => 'proposal', 'expected_close_on' => today()->subDay(), 'created_by' => $this->boss->id, 'owner_id' => $this->boss->id]);
    Deal::forceCreate(['title' => 'LMS', 'value' => '500000', 'currency' => 'NGN', 'stage' => 'won', 'won_at' => now(), 'created_by' => $this->boss->id, 'owner_id' => $this->boss->id]);

    $center = app(CommandCenter::class);
    $d = $center->build('month');
    expect($d['finance']['collected'])->toBe(15_000_050)
        ->and($d['finance']['collectedPrev'])->toBe(10_000_000)
        ->and(CommandCenter::change($d['finance']['collected'], $d['finance']['collectedPrev']))->toBe(50.0)
        ->and($d['finance']['spent'])->toBe(2_000_000)
        ->and($d['finance']['overdue'])->toBe(5_000_000)
        ->and($d['sales']['pipeline'])->toBe(200_000_000)
        ->and($d['sales']['weighted'])->toBe(120_000_000)
        ->and($d['sales']['won'])->toBe(50_000_000)
        ->and($d['trend'])->toHaveCount(12)
        ->and($d['trend']->last()['in'])->toBe(15_000_050);

    $texts = $center->attention($d)->pluck(1)->implode(' | ');
    expect($texts)->toContain('overdue invoices')->toContain('expenses waiting for approval')->toContain('deals past their expected close date');

    $this->actingAs($this->boss)->get(route('command-center'))->assertOk()
        ->assertSee('₦150,000.50')->assertSee('50%')->assertSee('overdue invoices');
});
