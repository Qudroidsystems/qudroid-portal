<?php

use App\Models\Finance\Account;
use App\Models\Finance\Expense;
use App\Models\Hr\LeaveRequest;
use App\Models\Hr\LeaveType;
use App\Models\Hr\PayrollRun;
use App\Models\User;
use App\Services\Hr\EmployeeService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function ahUser(string $role = 'Staff'): User
{
    $u = User::factory()->create(['user_type' => 'staff']);
    $u->assignRole($role);
    $u->forceFill(['must_change_password' => false])->save();

    return $u;
}

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->boss = ahUser();
    $this->me = ahUser();
    $make = fn (User $u, array $d = []) => app(EmployeeService::class)->create(['user_id' => $u->id, 'employment_type' => 'full_time', 'annual_leave_days' => 20,
        'start_date' => '2026-01-05', 'basic_salary' => 300000, 'housing_allowance' => 150000, 'transport_allowance' => 50000, 'pension_enrolled' => true, 'nhf_enrolled' => false] + $d)[0];
    $make($this->boss);
    $this->emp = $make($this->me, ['manager_id' => $this->boss->id, 'job_title' => 'Developer']);
    $this->annual = LeaveType::where('is_annual', true)->first();
});

test('staff see their record and balances, preview leave, ask for it and cancel it', function () {
    Sanctum::actingAs($this->me);
    $this->getJson(route('api.v1.hr.me'))->assertOk()->assertJsonPath('data.employee.job_title', 'Developer')
        ->assertJsonPath('data.employee.manager.id', $this->boss->id)
        ->assertJsonPath('data.leave_balance.0.left', 20);

    $this->getJson(route('api.v1.hr.leave.preview', ['leave_type_id' => $this->annual->id, 'starts_on' => '2026-12-21', 'ends_on' => '2026-12-24']))
        ->assertOk()->assertJsonPath('data.days', 4)->assertJsonPath('data.left_after', 16)->assertJsonPath('data.warnings', []);
    $this->getJson(route('api.v1.hr.leave.preview', ['leave_type_id' => $this->annual->id, 'starts_on' => '2026-11-02', 'ends_on' => '2026-12-31']))
        ->assertJsonPath('data.warnings.0', 'Only 20 days of ' . $this->annual->name . ' are left this year.');

    $id = $this->postJson(route('api.v1.hr.leave.store'), ['leave_type_id' => $this->annual->id, 'starts_on' => '2026-12-21', 'ends_on' => '2026-12-24', 'reason' => 'Christmas'])
        ->assertCreated()->assertJsonPath('data.status', 'pending')->assertJsonPath('data.can_cancel', true)->json('data.id');
    $this->postJson(route('api.v1.hr.leave.store'), ['leave_type_id' => $this->annual->id, 'starts_on' => '2026-12-22', 'ends_on' => '2026-12-22'])->assertStatus(422);
    $this->getJson(route('api.v1.hr.leave'))->assertOk()->assertJsonPath('data.0.days', 4)->assertJsonPath('meta.types.0.id', $this->annual->id);

    Sanctum::actingAs($this->boss);
    $this->postJson(route('api.v1.hr.leave.cancel', $id))->assertNotFound();
    Sanctum::actingAs($this->me);
    $this->postJson(route('api.v1.hr.leave.cancel', $id))->assertOk()->assertJsonPath('data.status', 'cancelled');
});

test('payslips appear once the run is paid, with the breakdown and a PDF', function () {
    $run = PayrollRun::create(['period' => '2026-09-01', 'status' => 'approved']);
    $slip = $run->payslips()->create(['employee_id' => $this->emp->id, 'basic' => 300000, 'housing' => 150000, 'transport' => 50000,
        'gross' => 500000, 'pension' => 40000, 'paye' => 65300, 'net' => 394700, 'employer_pension' => 50000]);
    Sanctum::actingAs($this->me);
    $this->getJson(route('api.v1.hr.payslips'))->assertOk()->assertJsonCount(0, 'data');
    $this->getJson(route('api.v1.hr.payslips.show', $slip))->assertNotFound();

    $run->update(['status' => 'paid', 'paid_at' => '2026-09-28 10:00']);
    $this->getJson(route('api.v1.hr.payslips'))->assertJsonPath('data.0.period', '2026-09')->assertJsonPath('data.0.net', 39470000)
        ->assertJsonPath('data.0.net_label', '₦394,700.00');
    $lines = collect($this->getJson(route('api.v1.hr.payslips.show', $slip))->assertOk()->json('data.lines'));
    expect($lines->pluck('label')->all())->toBe(['Basic salary', 'Housing', 'Transport', 'Pension (8%)', 'PAYE tax'])
        ->and($lines->last()['kind'])->toBe('deduction');
    $this->get(route('api.v1.hr.payslips.pdf', $slip))->assertOk();

    Sanctum::actingAs($this->boss);
    $this->getJson(route('api.v1.hr.payslips.show', $slip))->assertNotFound();
});

test('approvals list what I can decide, and deciding closes the notification for everyone', function () {
    $hr = ahUser('HR Manager');
    $leave = app(\App\Services\Hr\LeaveService::class)->request($this->emp, ['leave_type_id' => $this->annual->id, 'starts_on' => '2026-12-21', 'ends_on' => '2026-12-22'], $this->me);

    Sanctum::actingAs($this->me);
    $this->getJson(route('api.v1.approvals'))->assertOk()->assertJsonCount(0, 'data');
    $this->postJson(route('api.v1.approvals.decide', ['leave', $leave->id, 'approve']))->assertForbidden();

    Sanctum::actingAs($this->boss);
    $this->getJson(route('api.v1.approvals'))->assertJsonPath('data.0.type', 'leave')->assertJsonPath('data.0.requested_by.id', $this->me->id);
    $this->postJson(route('api.v1.approvals.decide', ['leave', $leave->id, 'decline']))->assertStatus(422);
    $this->postJson(route('api.v1.approvals.decide', ['leave', $leave->id, 'approve']))->assertOk()->assertJsonPath('data.status', 'approved');
    $this->postJson(route('api.v1.approvals.decide', ['leave', $leave->id, 'approve']))->assertStatus(409);

    // HR got the same request; their notification now says who decided it and has no buttons
    $n = $hr->notifications()->get()->first(fn ($n) => ($n->data['target']['id'] ?? null) === $leave->id);
    expect($n->data['done'])->toBe('Approved by ' . $this->boss->name);
    Sanctum::actingAs($hr);
    $this->getJson(route('api.v1.notifications.index'))->assertJsonPath('data.0.actions', []);

    // expenses: only finance approvers, never your own
    $md = ahUser('Managing Director');
    $clerk = ahUser('Finance Officer');
    Sanctum::actingAs($clerk);
    $acct = Account::ofType('expense')->where('is_active', true)->first();
    $x = $this->postJson(route('api.v1.expenses.store'), ['payee' => 'Ikeja Electric', 'description' => 'Power', 'account_id' => $acct->id, 'amount' => 2500000, 'date' => today()->toDateString()])
        ->assertCreated()->assertJsonPath('data.amount', 2500000)->json('data');
    $this->getJson(route('api.v1.expenses.mine'))->assertJsonPath('data.0.id', $x['id'])->assertJsonPath('meta.can_add', true);
    Sanctum::actingAs($this->me);
    $this->postJson(route('api.v1.expenses.store'), ['payee' => 'x', 'description' => 'y', 'account_id' => $acct->id, 'amount' => 1, 'date' => today()->toDateString()])->assertForbidden();
    $this->postJson(route('api.v1.approvals.decide', ['expense', $x['id'], 'approve']))->assertForbidden();

    Sanctum::actingAs($md);
    $this->getJson(route('api.v1.approvals', ['type' => 'expense']))->assertJsonPath('data.0.amount_label', '₦25,000.00');
    $this->postJson(route('api.v1.approvals.decide', ['expense', $x['id'], 'decline']), ['note' => 'Wrong account'])->assertOk()->assertJsonPath('data.status', 'rejected');
    expect(Expense::find($x['id'])->rejection_reason)->toBe('Wrong account');
});
