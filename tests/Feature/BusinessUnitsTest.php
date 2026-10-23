<?php

use App\Models\Academy\Course;
use App\Models\BusinessUnit;
use App\Models\Crm\Deal;
use App\Models\Finance\Invoice;
use App\Models\Finance\Payment;
use App\Models\User;
use App\Services\Reports\CommandCenter;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->admin = User::factory()->create(['user_type' => 'staff', 'name' => 'Admin']);
    $this->admin->assignRole('Staff');
    $this->admin->givePermissionTo(['Manage settings', 'View command center', 'View clients', 'Manage clients']);
    $this->admin->forceFill(['must_change_password' => false])->save();
    $this->qs = BusinessUnit::where('code', 'QS')->first();
    $this->qca = BusinessUnit::where('code', 'QCA')->first();
    $this->vs = BusinessUnit::where('code', 'VS')->first();
});

test('the three units exist and new records get a sensible unit', function () {
    expect(BusinessUnit::ordered()->pluck('name')->all())->toBe(['Qudroid Systems', 'Coding Academy', 'ViteSchool']);

    $course = Course::forceCreate(['code' => 'WEB1', 'title' => 'Web', 'slug' => 'web', 'fee' => 1000, 'currency' => 'NGN', 'status' => 'draft']);
    expect($course->business_unit_id)->toBe($this->qca->id);

    $deal = Deal::forceCreate(['title' => 'X', 'value' => 1, 'currency' => 'NGN', 'stage' => 'new', 'created_by' => $this->admin->id]);
    expect($deal->business_unit_id)->toBe($this->qs->id);
});

test('forms save the chosen unit, and the settings page manages units', function () {
    $this->actingAs($this->admin)->post(route('crm.deals.store'), ['title' => 'School app', 'value' => 500, 'business_unit_id' => $this->vs->id])->assertRedirect();
    $deal = Deal::where('title', 'School app')->first();
    expect($deal->business_unit_id)->toBe($this->vs->id);
    $this->actingAs($this->admin)->get(route('crm.deals.show', $deal))->assertOk()->assertSee('Business unit');

    $this->actingAs($this->admin)->get(route('portal-settings.units'))->assertOk()->assertSee('ViteSchool')->assertSee('1 deals');
    $this->actingAs($this->admin)->post(route('portal-settings.units.store'), ['name' => 'Qudroid Labs', 'code' => 'ql', 'color' => '#6250d6'])->assertSessionHasNoErrors();
    expect(BusinessUnit::where('code', 'QL')->exists())->toBeTrue();

    // switched-off units aren't offered for new records, but the last active one can't be switched off
    $this->actingAs($this->admin)->put(route('portal-settings.units.update', $this->vs), ['name' => 'ViteSchool', 'code' => 'VS', 'color' => '#eb6834', 'position' => 3]);
    expect($this->vs->refresh()->is_active)->toBeFalse()->and(BusinessUnit::options()->pluck('code')->all())->not->toContain('VS')
        ->and(BusinessUnit::options($this->vs->id)->pluck('code')->all())->toContain('VS');
    $this->actingAs($this->admin)->post(route('crm.deals.store'), ['title' => 'Bad', 'value' => 1, 'business_unit_id' => $this->vs->id])->assertSessionHasErrors('business_unit_id');
});

test('the Command Center reports each unit and all of them together', function () {
    $student = User::factory()->create(['user_type' => 'student']);
    $inv = fn ($unit, $total) => Invoice::forceCreate(['number' => 'I' . uniqid(), 'user_id' => $student->id, 'title' => 'x', 'issue_date' => today(), 'due_date' => today()->addWeek(),
        'subtotal' => $total, 'discount_total' => 0, 'total' => $total, 'amount_paid' => 0, 'status' => 'issued', 'business_unit_id' => $unit]);
    $pay = fn ($invoice, $amount) => Payment::forceCreate(['number' => 'R' . uniqid(), 'user_id' => $student->id, 'invoice_id' => $invoice->id, 'amount' => $amount,
        'method' => 'bank', 'status' => 'confirmed', 'paid_at' => now(), 'confirmed_at' => now()]);
    $pay($inv($this->qca->id, 100000), 60000);
    $pay($inv($this->qs->id, 500000), 200000);
    Deal::forceCreate(['title' => 'D', 'value' => 900000, 'currency' => 'NGN', 'stage' => 'qualified', 'created_by' => $this->admin->id, 'business_unit_id' => $this->vs->id]);

    $center = app(CommandCenter::class);
    $all = $center->build('month');
    $rows = $all['units']->keyBy('code');
    expect($rows['QCA']['collected'])->toBe(6_000_000)
        ->and($rows['QS']['collected'])->toBe(20_000_000)
        ->and($rows['VS']['pipeline'])->toBe(90_000_000)
        ->and($all['units']->last()['name'])->toBe('All units')
        ->and($all['units']->last()['collected'])->toBe(26_000_000)
        ->and($all['finance']['collected'])->toBe(26_000_000);

    $academy = $center->build('month', $this->qca->id);
    expect($academy['finance']['collected'])->toBe(6_000_000)
        ->and($academy['finance']['ledger'])->toBeFalse()
        ->and($academy['sales']['pipeline'])->toBe(0)
        ->and($academy['units'])->toBeEmpty();

    $this->actingAs($this->admin)->get(route('command-center'))->assertOk()->assertSee('By business unit')->assertSee('All units');
    $this->actingAs($this->admin)->get(route('command-center', ['unit' => $this->qca->id]))->assertOk()
        ->assertSee('Coding Academy at a glance')->assertSee('Cash result')->assertDontSee('By business unit');
});
