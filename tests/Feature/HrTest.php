<?php

use App\Models\Finance\Account;
use App\Models\Finance\JournalEntry;
use App\Models\Hr\Employee;
use App\Models\Hr\Holiday;
use App\Models\Hr\LeaveRequest;
use App\Models\Hr\LeaveType;
use App\Models\Hr\PayrollRun;
use App\Models\User;
use App\Services\Hr\EmployeeService;
use App\Services\Hr\LeaveService;
use App\Services\Hr\PayeCalculator;
use App\Support\Money;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

function hrUser(string $role): User
{
    $u = User::factory()->create(['user_type' => 'staff']);
    $u->assignRole($role);
    $u->forceFill(['must_change_password' => false])->save();

    return $u;
}

function hrEmployee(User $user, array $data = []): Employee
{
    return app(EmployeeService::class)->create(['user_id' => $user->id, 'employment_type' => 'full_time', 'annual_leave_days' => 20,
        'start_date' => '2026-01-05', 'basic_salary' => 300000, 'housing_allowance' => 150000, 'transport_allowance' => 50000,
        'pension_enrolled' => true, 'nhf_enrolled' => false] + $data)[0];
}

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->hr = hrUser('HR Manager');
    $this->md = hrUser('Managing Director');
    $this->boss = hrUser('Staff');
    $this->me = hrUser('Staff');
    hrEmployee($this->boss);
    $this->emp = hrEmployee($this->me, ['manager_id' => $this->boss->id]);
});

test('PAYE follows the 2025 bands, after pension, NHF and rent relief', function () {
    $r = app(PayeCalculator::class)->month($this->emp);
    // ₦500k a month; pension 8% = 40k; taxable a year 5.52m: 0 + 15% of 2.2m + 18% of 2.52m = 783,600 → 65,300 a month
    expect($r['gross'])->toBe(50000000)->and($r['pension'])->toBe(4000000)->and($r['paye'])->toBe(6530000)
        ->and($r['net'])->toBe(39470000)->and($r['employer_pension'])->toBe(5000000);

    $this->emp->update(['nhf_enrolled' => true, 'annual_rent' => 1200000]);
    $r = app(PayeCalculator::class)->month($this->emp->fresh());
    // NHF 2.5% of basic = 7,500; rent relief 240k; taxable 12×452,500 − 240,000 = 5,190,000 → 330,000 + 18% of 2.19m = 724,200 → 60,350
    expect($r['nhf'])->toBe(750000)->and($r['paye'])->toBe(6035000);

    $low = new Employee(['basic_salary' => 40000, 'housing_allowance' => 15000, 'transport_allowance' => 5000, 'pension_enrolled' => true]);
    expect(app(PayeCalculator::class)->month($low)['paye'])->toBe(0);

    // A ₦200k bonus is taxed at the top rate it reaches (18%)
    expect(app(PayeCalculator::class)->month($this->emp->fresh(), 20000000)['paye'])->toBe(6035000 + 3600000);
});

test('leave counts working days, respects the balance and goes to the manager', function () {
    $leave = app(LeaveService::class);
    Holiday::create(['date' => '2026-12-25', 'name' => 'Christmas Day']);
    expect($leave->workingDays(Carbon::parse('2026-12-21'), Carbon::parse('2026-12-27')))->toBe(4);   // Mon–Sun, less Christmas

    $annual = LeaveType::where('is_annual', true)->first();
    $this->actingAs($this->me)->post(route('me.leave.store'), ['leave_type_id' => $annual->id, 'starts_on' => '2026-12-21', 'ends_on' => '2026-12-27'])
        ->assertRedirect(route('me.leave'));
    $req = LeaveRequest::firstOrFail();
    expect($req->days)->toBe(4.0)
        ->and($this->boss->notifications()->count())->toBe(1);

    // overlapping, and more than is left
    $this->actingAs($this->me)->post(route('me.leave.store'), ['leave_type_id' => $annual->id, 'starts_on' => '2026-12-24', 'ends_on' => '2026-12-24'])->assertSessionHasErrors('starts_on');
    $this->actingAs($this->me)->post(route('me.leave.store'), ['leave_type_id' => $annual->id, 'starts_on' => '2026-11-02', 'ends_on' => '2026-11-30'])->assertSessionHasErrors('ends_on');

    // can't approve your own; the manager (no HR permissions) can
    $this->actingAs($this->me)->post(route('hr.leave.decide', $req), ['decision' => 'approve'])->assertSessionHasErrors('status');
    $this->actingAs($this->boss)->get(route('me.leave'))->assertOk()->assertSee("Your team's requests");
    $this->actingAs($this->boss)->get(route('hr.leave.show', $req))->assertOk()->assertSee('Approve');
    $this->actingAs(hrUser('Staff'))->get(route('hr.leave.show', $req))->assertForbidden();
    $this->actingAs($this->boss)->post(route('hr.leave.decide', $req), ['decision' => 'approve'])->assertSessionHasNoErrors();
    expect($req->fresh()->status)->toBe('approved')
        ->and($leave->balances($this->emp, 2026)->firstWhere('type.id', $annual->id)->left)->toBe(16.0);

    // staff can't open the HR screens
    $this->actingAs($this->me)->get(route('hr.index'))->assertForbidden();
    $this->actingAs($this->hr)->get(route('hr.index'))->assertOk()->assertSee('Leave waiting for a decision');
});

test('payroll is prepared, approved by someone else, paid to the ledger and released', function () {
    $unpaid = LeaveType::where('is_paid', false)->first();
    LeaveRequest::create(['employee_id' => $this->emp->id, 'leave_type_id' => $unpaid->id, 'starts_on' => '2026-10-05', 'ends_on' => '2026-10-06', 'days' => 2, 'status' => 'approved']);

    $this->actingAs($this->hr)->post(route('hr.payroll.store'), ['period' => '2026-10'])->assertRedirect();
    $run = PayrollRun::firstOrFail();
    expect($run->payslips()->count())->toBe(2);

    $slip = $run->payslips()->where('employee_id', $this->emp->id)->first();
    // 2 of 22 working days unpaid
    expect(Money::minor($slip->unpaid_deduction))->toBe((int) round(50000000 * 2 / 22))->and($slip->unpaid_days)->toBe(2.0);

    $this->actingAs($this->hr)->put(route('hr.payroll.adjust', [$run, $slip]), ['bonus' => 100000, 'other_deductions' => 20000])->assertSessionHasErrors('other_deductions_note');
    $this->actingAs($this->hr)->put(route('hr.payroll.adjust', [$run, $slip]), ['bonus' => 100000, 'other_deductions' => 20000, 'other_deductions_note' => 'Salary advance'])->assertSessionHasNoErrors();
    expect((float) $slip->fresh()->bonus)->toBe(100000.0);

    // regenerating keeps the bonus
    $this->actingAs($this->hr)->post(route('hr.payroll.generate', $run));
    expect((float) $slip->fresh()->bonus)->toBe(100000.0);

    $this->actingAs($this->hr)->post(route('hr.payroll.approve', $run))->assertSessionHasErrors('status');
    $this->actingAs($this->me)->get(route('hr.payroll.show', $run))->assertForbidden();
    $this->actingAs($this->md)->get(route('hr.payroll.show', $run))->assertOk()->assertSee('Approve payroll');
    $this->actingAs($this->hr)->get(route('hr.payroll.show', $run))->assertOk()->assertSee('Salary advance');
    $this->actingAs($this->md)->post(route('hr.payroll.approve', $run))->assertSessionHasNoErrors();
    $this->actingAs($this->hr)->get(route('hr.payroll.show', $run))->assertOk()->assertSee('Mark paid');
    expect($run->fresh()->status)->toBe('approved');

    // nothing visible before it is paid
    $this->actingAs($this->me)->get(route('me.payslips.show', $slip))->assertNotFound();

    $bank = Account::where('code', Account::BANK)->first();
    $this->actingAs($this->hr)->post(route('hr.payroll.pay', $run), ['paid_from_account_id' => $bank->id, 'paid_on' => today()->toDateString()])->assertSessionHasNoErrors();
    $run->refresh();
    expect($run->status)->toBe('paid');
    $this->actingAs($this->md)->get(route('hr.payroll.show', $run))->assertOk();
    $this->actingAs($this->md)->get(route('finance.ledger.journal', JournalEntry::where('kind', 'payroll')->first()))->assertOk()->assertSee('Payroll October 2026');

    $entry = JournalEntry::where('kind', 'payroll')->firstOrFail();
    $lines = $entry->lines()->with('account')->get();
    $debits = $lines->sum(fn ($l) => Money::minor($l->debit));
    expect($debits)->toBe($lines->sum(fn ($l) => Money::minor($l->credit)))
        ->and($debits)->toBe(Money::minor($run->gross) + Money::minor($run->employer_pension))
        ->and(Money::minor($lines->firstWhere('account.code', '1010')->credit))->toBe(Money::minor($run->net));

    $this->actingAs($this->me)->get(route('me.payslips'))->assertOk()->assertSee('October 2026');
    $pdf = $this->actingAs($this->me)->get(route('me.payslips.show', $slip));
    $pdf->assertOk()->assertHeader('content-type', 'application/pdf');
    $this->actingAs($this->boss)->get(route('me.payslips.show', $slip))->assertNotFound();

    $html = view('pdf.payslip', ['slip' => $slip->fresh()->load('run', 'employee.user', 'employee.department')])->render();
    expect($html)->toContain('Salary advance')->toContain('Unpaid leave')->toContain('Net pay');

    // a paid run can't be changed
    $this->actingAs($this->hr)->post(route('hr.payroll.generate', $run))->assertSessionHasErrors('status');
});

test('HR adds an employee with a new staff account and the screens render', function () {
    $this->actingAs($this->hr)->post(route('hr.employees.store'), [
        'name' => 'Tunde Bello', 'email' => 'tunde@qudroid.test', 'job_title' => 'Developer', 'employment_type' => 'full_time',
        'annual_leave_days' => 20, 'basic_salary' => 200000, 'pension_enrolled' => 1,
    ])->assertRedirect()->assertSessionHas('staff_password');
    $e = Employee::whereHas('user', fn ($q) => $q->where('email', 'tunde@qudroid.test'))->firstOrFail();
    expect($e->employee_no)->toBe('QS-0003')->and($e->user->isStaff())->toBeTrue()->and($e->user->hasRole('Staff'))->toBeTrue()
        ->and((float) $e->basic_salary)->toBe(200000.0);

    foreach ([route('hr.employees.index'), route('hr.employees.show', $e), route('hr.employees.edit', $e), route('hr.departments.index'),
              route('hr.leave.index'), route('hr.leave.settings'), route('hr.payroll.index'), route('me.leave'), route('me.payslips')] as $url) {
        $this->actingAs($this->hr)->get($url)->assertOk();
    }
    $this->actingAs($this->me)->get(route('me.leave'))->assertOk()->assertSee('My leave & pay', false);
});
