<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Finance\Account;
use App\Models\Finance\JournalEntry;
use App\Models\Hr\PayrollRun;
use App\Models\Hr\Payslip;
use App\Services\Hr\PayrollService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class PayrollController extends Controller
{
    public function __construct(protected PayrollService $payroll) {}

    public function index()
    {
        $last = PayrollRun::orderByDesc('period')->first();

        return view('modules.hr.payroll.index', [
            'runs' => PayrollRun::with('preparer', 'approver')->withCount('payslips')->orderByDesc('period')->paginate(24),
            'suggested' => $last ? $last->period->copy()->addMonth() : today()->startOfMonth(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['period' => ['required', 'date_format:Y-m']]);
        $run = $this->payroll->start(Carbon::createFromFormat('Y-m-d', $data['period'] . '-01'), $request->user());

        return redirect()->route('hr.payroll.show', $run)->with('success', 'Payroll for ' . $run->label . ' prepared with ' . $run->payslips()->count() . ' payslips.');
    }

    public function show(PayrollRun $run)
    {
        $run->load('preparer', 'approver', 'payer', 'paidFrom');

        return view('modules.hr.payroll.show', [
            'run' => $run,
            'slips' => $run->payslips()->with('employee.user', 'employee.department')->get()->sortBy('employee.user.name')->values(),
            'payFrom' => Account::ofType('asset')->where('is_active', true)->orderBy('code')->get(),
            'entries' => JournalEntry::where('source_type', $run->getMorphClass())->where('source_id', $run->id)->orderBy('id')->get(),
        ]);
    }

    public function generate(PayrollRun $run)
    {
        $run = $this->payroll->generate($run);

        return back()->with('success', 'Payslips recalculated from current employee records: ' . $run->payslips()->count() . ' payslips.');
    }

    public function adjust(Request $request, PayrollRun $run, Payslip $payslip)
    {
        abort_unless($payslip->payroll_run_id === $run->id, 404);
        $data = $request->validate([
            'bonus' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'other_deductions' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'other_deductions_note' => ['nullable', 'string', 'max:255'],
        ]);
        $this->payroll->adjust($payslip, $data);

        return back()->with('success', 'Payslip for ' . $payslip->employee->user->name . ' updated.');
    }

    public function approve(Request $request, PayrollRun $run)
    {
        $this->payroll->approve($run, $request->user());

        return back()->with('success', 'Payroll approved. It can now be paid.');
    }

    public function reopen(Request $request, PayrollRun $run)
    {
        $this->payroll->reopen($run, $request->user());

        return back()->with('success', 'Payroll is a draft again. It will need approving again.');
    }

    public function pay(Request $request, PayrollRun $run)
    {
        $data = $request->validate(['paid_from_account_id' => ['required', 'integer'], 'paid_on' => ['required', 'date']]);
        $this->payroll->pay($run, $request->user(), (int) $data['paid_from_account_id'], Carbon::parse($data['paid_on']));

        return back()->with('success', 'Payroll marked paid and posted to the ledger. Everyone can now see their payslip.');
    }

    public function destroy(PayrollRun $run)
    {
        $label = $run->label;
        $this->payroll->discard($run);

        return redirect()->route('hr.payroll.index')->with('success', 'Draft payroll for ' . $label . ' discarded.');
    }

    public function payslip(Request $request, PayrollRun $run, Payslip $payslip)
    {
        abort_unless($payslip->payroll_run_id === $run->id, 404);

        return $this->pdf($payslip, $request->boolean('view'));
    }

    // ── Self-service ────────────────────────────────────────────────────

    public function mine(Request $request)
    {
        $employee = $request->user()->employee;

        return view('modules.hr.me.payslips', [
            'employee' => $employee,
            'slips' => $employee ? $employee->payslips()->with('run')->whereHas('run', fn ($q) => $q->where('status', 'paid'))
                ->get()->sortByDesc('run.period')->values() : collect(),
        ]);
    }

    public function myPayslip(Request $request, Payslip $payslip)
    {
        $payslip->load('run', 'employee');
        abort_unless($payslip->employee->user_id === $request->user()->id && $payslip->run->status === 'paid', 404);

        return $this->pdf($payslip, $request->boolean('view'));
    }

    protected function pdf(Payslip $payslip, bool $inline)
    {
        $payslip->loadMissing('run', 'employee.user', 'employee.department');
        $name = 'Payslip-' . $payslip->employee->employee_no . '-' . $payslip->run->period->format('Y-m') . '.pdf';
        $pdf = Pdf::loadView('pdf.payslip', ['slip' => $payslip])->setPaper('a4');

        return $inline ? $pdf->stream($name) : $pdf->download($name);
    }
}
