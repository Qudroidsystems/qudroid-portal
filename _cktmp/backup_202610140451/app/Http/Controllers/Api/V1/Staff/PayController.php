<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Api\V1\ApiController;
use App\Services\Payroll\PayrollHistoryService;
use App\Services\Payroll\PayslipService;
use App\Support\PayrollDocs;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Staff self-service pay: published payslips, year-to-date totals, payslip PDF. */
class PayController extends ApiController
{
    public function __construct(protected PayrollHistoryService $history, protected PayslipService $payslips) {}

    public function index(Request $request)
    {
        $s = PayrollDocs::staffForUser($request->user()->id);
        if (!$s) return $this->fail('No staff record is linked to your account. Please contact the bursar.', 404, 'no_staff_record');

        $runs = $this->history->runs((int) $s->id)->sortByDesc('start_date')->values();
        $year = (int) ($request->get('year') ?: ($runs->first() ? Carbon::parse($runs->first()->start_date)->year : now()->year));
        $ytd = $this->history->ytd((int) $s->id, $year);

        return $this->ok([
            'year'     => $year,
            'years'    => $runs->map(fn ($r) => (int) Carbon::parse($r->start_date)->year)->unique()->values(),
            'ytd'      => array_map(fn ($v) => is_numeric($v) ? (float) $v : $v, (array) $ytd),
            'payslips' => $runs->map(fn ($r) => [
                'id'               => (int) $r->id,
                'period'           => $r->period_name,
                'start_date'       => $r->start_date,
                'end_date'         => $r->end_date,
                'payment_date'     => $r->payment_date,
                'gross'            => (float) $r->total_earnings,
                'deductions'       => (float) $r->total_deductions,
                'net'              => (float) $r->net_pay,
                'payment_status'   => $r->payment_status,
                'pdf_url'          => route('api.v1.staff.payslips.pdf', $r->id),
            ])->values(),
        ]);
    }

    public function show(Request $request, int $run)
    {
        $r = $this->ownRun($request, $run);
        if (!$r) return $this->fail('Payslip not found.', 404, 'not_found');

        $lines = DB::table('payroll_run_lines')->where('payroll_run_id', $r->id)->orderBy('sort')->get(['code', 'label', 'type', 'amount']);

        return $this->ok([
            'id'           => (int) $r->id,
            'period'       => $r->period_name,
            'payment_date' => $r->payment_date,
            'gross'        => (float) $r->total_earnings,
            'deductions'   => (float) $r->total_deductions,
            'net'          => (float) $r->net_pay,
            'paye'         => (float) $r->paye_tax,
            'pension'      => (float) $r->employee_pension,
            'bank'         => ['name' => $r->bank_name, 'account_number' => $r->account_number ? '••••' . substr((string) $r->account_number, -4) : null],
            'lines'        => $lines->map(fn ($l) => ['code' => $l->code, 'label' => $l->label, 'type' => $l->type, 'amount' => (float) $l->amount])->values(),
            'pdf_url'      => route('api.v1.staff.payslips.pdf', $r->id),
        ]);
    }

    public function pdf(Request $request, int $run)
    {
        $r = $this->ownRun($request, $run);
        if (!$r) return $this->fail('Payslip not found.', 404, 'not_found');

        return response($this->payslips->pdf($r), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $this->payslips->filename($r) . '"',
        ]);
    }

    /** Own, published payslips only. */
    protected function ownRun(Request $request, int $runId): ?object
    {
        $s = PayrollDocs::staffForUser($request->user()->id);
        $run = $this->payslips->run($runId);
        if (!$s || !$run || (int) $run->staff_id !== (int) $s->id) return null;
        if (!in_array($run->period_status, PayrollDocs::PUBLISHED, true)) return null;
        return $run;
    }
}
