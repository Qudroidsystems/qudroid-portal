<?php

namespace App\Services\Hr;

use App\Models\Finance\Account;
use App\Models\Hr\Employee;
use App\Models\Hr\PayrollRun;
use App\Models\Hr\Payslip;
use App\Models\User;
use App\Services\Finance\LedgerService;
use App\Services\Messaging\PortalNotifier;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Monthly payroll. HR prepares a draft run (one payslip per active
 * employee, worked out by PayeCalculator), adds bonuses and deductions,
 * someone else approves it, then it is paid from a bank account, which
 * posts it to the ledger and lets everyone see their payslip.
 */
class PayrollService
{
    public function __construct(protected PayeCalculator $paye, protected LeaveService $leave, protected LedgerService $ledger) {}

    public function start(Carbon $month, User $by): PayrollRun
    {
        $period = $month->copy()->startOfMonth();
        $this->check(PayrollRun::whereDate('period', $period)->exists(), 'period', 'There is already a payroll for ' . $period->format('F Y') . '.');
        $this->check($period->gt(today()->startOfMonth()->addMonth()), 'period', 'Payroll can be prepared at most a month ahead.');

        return DB::transaction(function () use ($period, $by) {
            $run = PayrollRun::create(['period' => $period, 'status' => 'draft', 'prepared_by' => $by->id]);
            $this->generate($run);

            return $run;
        });
    }

    /** (Re)build the payslips of a draft run from current employee records, keeping bonuses and deductions already entered. */
    public function generate(PayrollRun $run): PayrollRun
    {
        $this->draft($run);
        $start = $run->period->copy()->startOfMonth();
        $end = $run->period->copy()->endOfMonth();
        $unpaid = $this->leave->unpaidDays($run->period);
        $kept = $run->payslips()->get()->keyBy('employee_id');

        $employees = Employee::with('user')->where('status', 'active')
            ->where(fn ($q) => $q->whereNull('start_date')->orWhereDate('start_date', '<=', $end))
            ->get()->filter(fn ($e) => $e->monthlyGrossMinor() > 0);

        DB::transaction(function () use ($run, $employees, $unpaid, $kept) {
            $run->payslips()->whereNotIn('employee_id', $employees->pluck('id'))->delete();
            foreach ($employees as $e) {
                $old = $kept->get($e->id);
                $this->fill($run, $e, $old ? Money::minor($old->bonus) : 0, $old ? Money::minor($old->other_deductions) : 0,
                    $old?->other_deductions_note, (float) ($unpaid[$e->id] ?? 0));
            }
            $this->totals($run);
        });

        return $run->fresh();
    }

    public function adjust(Payslip $slip, array $data): Payslip
    {
        $run = $slip->run;
        $this->draft($run);
        $bonus = Money::minor($data['bonus'] ?? 0);
        $other = Money::minor($data['other_deductions'] ?? 0);
        $this->check($bonus < 0 || $other < 0, 'bonus', 'Amounts can\'t be negative.');
        $this->check($other > 0 && trim((string) ($data['other_deductions_note'] ?? '')) === '', 'other_deductions_note', 'Say what the deduction is for.');

        DB::transaction(function () use ($run, $slip, $bonus, $other, $data) {
            $slip = $this->fill($run, $slip->employee, $bonus, $other, $data['other_deductions_note'] ?? null, (float) $slip->unpaid_days);
            $this->check(Money::minor($slip->net) < 0, 'other_deductions', 'Deductions are more than the pay.');
            $this->totals($run);
        });

        return $slip->fresh();
    }

    public function approve(PayrollRun $run, User $by): PayrollRun
    {
        $this->draft($run);
        $this->check($run->prepared_by === $by->id, 'status', 'Someone other than the person who prepared it must approve the payroll.');
        $this->check(!$by->can('Manage payroll') && !$by->can('Approve finance'), 'status', 'You can\'t approve payroll.');
        $this->check($run->payslips()->count() === 0, 'status', 'There are no payslips in this payroll.');
        $run->update(['status' => 'approved', 'approved_by' => $by->id, 'approved_at' => now()]);

        PortalNotifier::toUsers(array_diff(PortalNotifier::userIdsWithPermission('Manage payroll'), [$by->id]),
            'Payroll approved: ' . $run->label, 'It can now be paid.', route('hr.payroll.show', $run), 'payment');

        return $run;
    }

    /** Send an approved run back to draft (before it is paid). */
    public function reopen(PayrollRun $run, User $by): PayrollRun
    {
        $this->check($run->status !== 'approved', 'status', 'Only an approved, unpaid payroll can be reopened.');
        $run->update(['status' => 'draft', 'approved_by' => null, 'approved_at' => null, 'prepared_by' => $by->id]);

        return $run;
    }

    public function pay(PayrollRun $run, User $by, int $fromAccountId, Carbon $paidOn): PayrollRun
    {
        $this->check($run->status !== 'approved', 'status', 'Only an approved payroll can be paid.');
        $from = Account::find($fromAccountId);
        $this->check(!$from || $from->type !== 'asset' || !$from->is_active, 'paid_from_account_id', 'Choose the bank account salaries are paid from.');
        $this->check($paidOn->isFuture(), 'paid_on', 'The payment date can\'t be in the future.');

        DB::transaction(function () use ($run, $by, $from, $paidOn) {
            $run->update(['status' => 'paid', 'paid_by' => $by->id, 'paid_at' => $paidOn, 'paid_from_account_id' => $from->id]);
            $this->ledger->payrollPaid($run->load('payslips'), $by);
        });

        foreach ($run->payslips()->with('employee')->get() as $slip) {
            PortalNotifier::toUsers([$slip->employee->user_id], 'Payslip for ' . $run->label,
                'Net pay ' . Money::format($slip->net) . '.', route('me.payslips.show', $slip), 'payment');
        }

        return $run;
    }

    public function discard(PayrollRun $run): void
    {
        $this->draft($run);
        $run->delete();
    }

    protected function fill(PayrollRun $run, Employee $e, int $bonus, int $other, ?string $note, float $unpaidDays): Payslip
    {
        $r = $this->paye->month($e, $bonus, $other, $unpaidDays);
        $major = fn (string $k) => Money::major($r[$k]);

        return Payslip::updateOrCreate(['payroll_run_id' => $run->id, 'employee_id' => $e->id], [
            'basic' => $major('basic'), 'housing' => $major('housing'), 'transport' => $major('transport'), 'other_allowance' => $major('other_allowance'),
            'bonus' => $major('bonus'), 'unpaid_days' => $unpaidDays, 'unpaid_deduction' => $major('unpaid_deduction'), 'gross' => $major('gross'),
            'pension' => $major('pension'), 'nhf' => $major('nhf'), 'paye' => $major('paye'), 'other_deductions' => $major('other_deductions'),
            'other_deductions_note' => $other > 0 && $note ? mb_substr(trim($note), 0, 255) : null,
            'net' => $major('net'), 'employer_pension' => $major('employer_pension'), 'workings' => $r['workings'],
            'bank' => ['bank' => $e->bank_name, 'account_number' => $e->account_number, 'account_name' => $e->account_name],
        ]);
    }

    protected function totals(PayrollRun $run): void
    {
        $slips = $run->payslips()->get();
        $sum = fn (string $col) => $slips->sum(fn ($p) => Money::minor($p->{$col}));
        $run->update([
            'gross' => Money::major($sum('gross')), 'net' => Money::major($sum('net')),
            'deductions' => Money::major($sum('pension') + $sum('nhf') + $sum('paye') + $sum('other_deductions')),
            'employer_pension' => Money::major($sum('employer_pension')),
        ]);
    }

    protected function draft(PayrollRun $run): void
    {
        $this->check($run->status !== 'draft', 'status', 'This payroll is ' . strtolower($run->status_label) . ' and can\'t be changed.');
    }

    protected function check(bool $failed, string $field, string $message): void
    {
        if ($failed) throw ValidationException::withMessages([$field => $message]);
    }
}
