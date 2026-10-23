<?php

namespace App\Http\Controllers\Api\V1\Hr;

use App\Http\Controllers\Api\V1\ApiController;
use App\Models\Finance\Account;
use App\Models\Finance\Expense;
use App\Models\Hr\Holiday;
use App\Models\Hr\LeaveRequest;
use App\Models\Hr\LeaveType;
use App\Models\Hr\Payslip;
use App\Models\Inventory\Asset;
use App\Services\Finance\ExpenseService;
use App\Services\Hr\LeaveService;
use App\Support\Money;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/** Staff self-service in the app: my HR record, leave, payslips, kit, and expenses I recorded. */
class HrApiController extends ApiController
{
    public function __construct(protected LeaveService $leave) {}

    public function me(Request $request)
    {
        $e = $request->user()->employee?->load('department', 'manager');

        return $this->ok([
            'employee' => $e ? [
                'id' => $e->id, 'staff_no' => $e->employee_no, 'job_title' => $e->job_title, 'department' => $e->department?->name,
                'employment_type' => $e->employment_type, 'status' => $e->status,
                'manager' => $e->manager ? ['id' => $e->manager->id, 'name' => $e->manager->name] : null,
                'started_on' => $e->start_date?->toDateString(),
            ] : null,
            'leave_balance' => $e ? $this->balances($e) : [],
            'holidays' => Holiday::whereDate('date', '>=', today())->orderBy('date')->limit(10)->get()
                ->map(fn ($h) => ['name' => $h->name, 'date' => $h->date->toDateString()])->values(),
        ]);
    }

    public function leave(Request $request)
    {
        $e = $request->user()->employee;
        if (!$e) return $this->ok([], null, 200, ['types' => $this->types()]);
        $page = $e->leaveRequests()->with('type', 'decider')->latest('starts_on')->paginate(min(50, max(5, (int) $request->get('per_page', 20))));

        $res = $this->paged($page, fn ($l) => $this->leaveItem($l));
        $body = $res->getData(true);
        $body['meta']['types'] = $this->types();

        return response()->json($body);
    }

    /** ?leave_type_id=&starts_on=&ends_on= → working days and what would be left, before asking. */
    public function preview(Request $request)
    {
        $e = $request->user()->employee;
        if (!$e) return $this->fail('You are not on the staff list.', 403, 'not_employee');
        $d = $request->validate(['leave_type_id' => ['required', 'integer'], 'starts_on' => ['required', 'date'], 'ends_on' => ['required', 'date']]);
        $from = Carbon::parse($d['starts_on'])->startOfDay();
        $to = Carbon::parse($d['ends_on'])->startOfDay();
        $days = $this->leave->workingDays($from, $to);
        $bal = $this->leave->balances($e, $from->year)->firstWhere('type.id', (int) $d['leave_type_id']);

        $warnings = [];
        if ($to->lt($from)) $warnings[] = 'The last day can\'t be before the first.';
        elseif ($from->year !== $to->year) $warnings[] = 'Leave can\'t run into the next year. Make two requests.';
        elseif ($days === 0) $warnings[] = 'Those dates are all weekends or public holidays.';
        if ($bal && $bal->left !== null && $days > $bal->left) $warnings[] = "Only {$bal->left} days of {$bal->type->name} are left this year.";
        if ($e->leaveRequests()->whereIn('status', ['pending', 'approved'])->whereDate('starts_on', '<=', $to)->whereDate('ends_on', '>=', $from)->exists()) {
            $warnings[] = 'You already have leave booked on some of those days.';
        }

        return $this->ok(['days' => $days, 'left_after' => $bal && $bal->left !== null ? max(0, $bal->left - $days) : null, 'warnings' => $warnings]);
    }

    public function storeLeave(Request $request)
    {
        $e = $request->user()->employee;
        if (!$e) return $this->fail('You are not on the staff list.', 403, 'not_employee');
        $d = $request->validate([
            'leave_type_id' => ['required', 'exists:hr_leave_types,id'],
            'starts_on' => ['required', 'date', 'after_or_equal:' . today()->subDays(30)->toDateString()],
            'ends_on' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);
        $leave = $this->leave->request($e, $d, $request->user());

        return $this->ok($this->leaveItem($leave->load('type')), "Leave request sent: {$leave->days} working days.", 201);
    }

    public function cancelLeave(Request $request, LeaveRequest $leave)
    {
        if ($leave->employee->user_id !== $request->user()->id) return $this->fail('Leave request not found.', 404, 'not_found');
        $this->leave->cancel($leave, $request->user());

        return $this->ok($this->leaveItem($leave->fresh('type')), 'Leave request cancelled.');
    }

    public function payslips(Request $request)
    {
        $e = $request->user()->employee;
        $slips = $e ? $e->payslips()->with('run')->whereHas('run', fn ($q) => $q->where('status', 'paid'))->get()->sortByDesc('run.period')->values() : collect();

        return $this->ok($slips->map(fn ($s) => $this->slip($s))->values());
    }

    public function payslip(Request $request, Payslip $payslip)
    {
        $payslip->load('run', 'employee');
        if ($payslip->employee->user_id !== $request->user()->id || $payslip->run->status !== 'paid') return $this->fail('Payslip not found.', 404, 'not_found');
        $m = fn ($v) => Money::format($v ?? 0);
        $lines = collect([
            ['Basic salary', $payslip->basic, 'earning'], ['Housing', $payslip->housing, 'earning'], ['Transport', $payslip->transport, 'earning'],
            ['Other allowance', $payslip->other_allowance, 'earning'], ['Bonus', $payslip->bonus, 'earning'],
            ['Unpaid leave (' . (float) $payslip->unpaid_days . ' days)', $payslip->unpaid_deduction, 'deduction'],
            ['Pension (8%)', $payslip->pension, 'deduction'], ['NHF', $payslip->nhf, 'deduction'], ['PAYE tax', $payslip->paye, 'deduction'],
            [$payslip->other_deductions_note ?: 'Other deductions', $payslip->other_deductions, 'deduction'],
        ])->filter(fn ($l) => (float) $l[1] > 0)->map(fn ($l) => ['label' => $l[0], 'amount' => Money::minor($l[1]), 'amount_label' => $m($l[1]), 'kind' => $l[2]])->values();

        return $this->ok($this->slip($payslip) + ['lines' => $lines, 'employer_pension_label' => $m($payslip->employer_pension)]);
    }

    public function payslipPdf(Request $request, Payslip $payslip)
    {
        $payslip->load('run', 'employee.user', 'employee.department');
        abort_unless($payslip->employee->user_id === $request->user()->id && $payslip->run->status === 'paid', 404);

        return Pdf::loadView('pdf.payslip', ['slip' => $payslip])->setPaper('a4')
            ->download('Payslip-' . $payslip->employee->employee_no . '-' . $payslip->run->period->format('Y-m') . '.pdf');
    }

    /** Company kit signed out to me. */
    public function assets(Request $request)
    {
        if (!Schema::hasTable((new Asset)->getTable())) return $this->ok([]);

        return $this->ok(Asset::with('category')->where('assigned_to', $request->user()->id)->orderBy('name')->get()->map(fn ($a) => [
            'id' => $a->id, 'name' => $a->name, 'tag' => $a->tag, 'category' => $a->category?->name, 'make_model' => $a->make_model,
            'serial_number' => $a->serial_number, 'assigned_on' => $a->assigned_at?->toDateString(), 'condition' => $a->condition,
            'status' => $a->status, 'status_label' => $a->status_label,
        ])->values());
    }

    /** Expenses I recorded (Finance staff record company spending; others get an empty list). */
    public function myExpenses(Request $request)
    {
        $page = Expense::with('account')->where('submitted_by', $request->user()->id)->latest('date')->latest('id')
            ->paginate(min(50, max(5, (int) $request->get('per_page', 20))));
        $res = $this->paged($page, fn ($x) => $this->expense($x));
        $body = $res->getData(true);
        $body['meta']['can_add'] = $request->user()->can('Manage finance');
        $body['meta']['accounts'] = $request->user()->can('Manage finance')
            ? Account::ofType('expense')->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name'])->values() : [];

        return response()->json($body);
    }

    /** multipart {payee, description, account_id, amount (kobo), date, receipt?} — goes to an approver like the web form. */
    public function storeExpense(Request $request, ExpenseService $expenses)
    {
        if (!$request->user()->can('Manage finance')) return $this->fail('Only finance staff can record expenses.', 403, 'forbidden');
        $d = $request->validate([
            'payee' => ['required', 'string', 'max:150'], 'description' => ['required', 'string', 'max:500'],
            'account_id' => ['required', 'integer'], 'amount' => ['required', 'integer', 'min:1'],
            'date' => ['required', 'date', 'before_or_equal:today'],
            'receipt' => ['nullable', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp,heic'],
        ]);
        $x = $expenses->submit(['payee' => $d['payee'], 'description' => $d['description'], 'account_id' => $d['account_id'],
            'amount' => Money::major($d['amount']), 'date' => $d['date']], $request->user(), $request->file('receipt'));

        return $this->ok($this->expense($x->load('account')), 'Expense sent for approval.', 201);
    }

    protected function balances($e): array
    {
        return $this->leave->balances($e)->map(fn ($b) => ['type_id' => $b->type->id, 'type' => $b->type->name, 'is_paid' => (bool) $b->type->is_paid,
            'allowed' => $b->allowed, 'used' => $b->used, 'pending' => $b->pending, 'left' => $b->left])->values()->all();
    }

    protected function types(): array
    {
        return LeaveType::where('is_active', true)->orderByDesc('is_annual')->orderBy('name')->get(['id', 'name', 'is_paid', 'is_annual'])->toArray();
    }

    protected function leaveItem(LeaveRequest $l): array
    {
        return [
            'id' => $l->id, 'type_id' => $l->leave_type_id, 'type' => $l->type?->name,
            'starts_on' => $l->starts_on?->toDateString(), 'ends_on' => $l->ends_on?->toDateString(), 'days' => (float) $l->days,
            'reason' => $l->reason, 'status' => $l->status, 'status_label' => $l->status_label, 'decision_note' => $l->decision_note,
            'decided_by' => $l->decider ? ['id' => $l->decider->id, 'name' => $l->decider->name] : null,
            'can_cancel' => $l->status === 'pending' || ($l->status === 'approved' && $l->starts_on?->gt(today())),
            'web_url' => route('me.leave'),
        ];
    }

    protected function slip(Payslip $s): array
    {
        return [
            'id' => $s->id, 'period' => $s->run->period->format('Y-m'), 'period_label' => $s->run->period->format('F Y'),
            'pay_date' => $s->run->paid_at?->toDateString(),
        ] + $this->money('gross', $s->gross) + $this->money('net', $s->net) + [
            'pdf_url' => route('api.v1.hr.payslips.pdf', $s), 'web_url' => route('me.payslips'),
        ];
    }

    protected function expense(Expense $x): array
    {
        return ['id' => $x->id, 'number' => $x->number, 'date' => $x->date?->toDateString(), 'payee' => $x->payee, 'description' => $x->description,
            'category' => $x->account?->name] + $this->money('amount', $x->amount, $x->currency) + [
            'status' => $x->status, 'status_label' => $x->status_label, 'rejection_reason' => $x->rejection_reason,
            'has_receipt' => (bool) $x->attachment_path, 'web_url' => route('finance.expenses.show', $x)];
    }
}
