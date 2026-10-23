<?php

use App\Models\Crm\Deal;
use App\Models\Finance\Payment;
use App\Models\User;
use App\Http\Controllers\Api\V1\Reports\CommandCenterApiController;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->boss = User::factory()->create(['user_type' => 'staff']);
    $this->boss->assignRole('Staff');
    $this->boss->givePermissionTo('View command center');
});

test('the app gets the Command Center figures in kobo with short labels, compared with the period before', function () {
    $student = User::factory()->create(['user_type' => 'student']);
    $pay = fn (string $amount, $when) => Payment::forceCreate(['number' => 'R' . uniqid(), 'user_id' => $student->id, 'amount' => $amount,
        'method' => 'bank_transfer', 'status' => 'confirmed', 'paid_at' => $when, 'confirmed_at' => $when]);
    $pay('12500000', now()->startOfMonth()->addHour());
    $pay('10000000', now()->subMonthNoOverflow()->startOfMonth()->addHour());
    Deal::forceCreate(['title' => 'School portal', 'value' => '2000000', 'currency' => 'NGN', 'stage' => 'proposal', 'expected_close_on' => today()->subDay(),
        'created_by' => $this->boss->id, 'owner_id' => $this->boss->id]);

    Sanctum::actingAs($this->boss);
    $res = $this->getJson(route('api.v1.command-center', ['period' => 'month']))->assertOk()
        ->assertJsonPath('data.period.key', 'month')->assertJsonPath('data.period.compare_label', 'vs last month')
        ->assertJsonCount(4, 'data.periods')->json('data');

    $collected = collect($res['kpis'])->firstWhere('key', 'collected');
    expect($collected['value'])->toBe(1250000000)->and($collected['value_label'])->toBe('₦12.5m')
        ->and($collected['previous'])->toBe(1000000000)->and($collected['change_pct'])->toEqual(25)->and($collected['good_when'])->toBe('up');
    expect(collect($res['sales']['stages'])->firstWhere('stage', 'proposal')['value_label'])->toBe('₦2m')
        ->and(collect($res['attention'])->pluck('deep_link'))->toContain('crm/deals')
        ->and($res['trend'])->toHaveCount(12);

    foreach (['quarter', 'year', 'last_month', 'nonsense'] as $p) $this->getJson(route('api.v1.command-center', ['period' => $p]))->assertOk();
});

test('it needs the permission', function () {
    Sanctum::actingAs(User::factory()->create(['user_type' => 'staff']));
    $this->getJson(route('api.v1.command-center'))->assertForbidden()->assertJsonPath('code', 'forbidden');
});

test('short money labels', function () {
    expect(CommandCenterApiController::short(45000000))->toBe('₦450k')->and(CommandCenterApiController::short(98000))->toBe('₦980')
        ->and(CommandCenterApiController::short(-150000000))->toBe('-₦1.5m')->and(CommandCenterApiController::short(0))->toBe('₦0');
});
