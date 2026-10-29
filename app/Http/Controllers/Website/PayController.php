<?php

namespace App\Http\Controllers\Website;

use App\Http\Controllers\Controller;
use App\Models\Finance\Invoice;
use App\Models\Finance\Payment;
use App\Services\Finance\PaymentService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Paying an invoice from a link, without signing in (after registering on
 * the website, or from an e-mailed link). The link is signed and expires.
 * As everywhere else, a payment is only confirmed after the gateway is asked.
 */
class PayController extends Controller
{
    public function __construct(protected PaymentService $payments) {}

    public function show(Request $request, Invoice $invoice)
    {
        abort_if($invoice->status === 'void', 404);
        $invoice->load('items', 'user', 'enrollment.course', 'enrollment.cohort');

        return view('site.pay', [
            'invoice' => $invoice,
            'minimum' => Money::major($this->payments->minimumPayment($invoice)),
            'gateways' => $this->payments->onlineGateways(),
            'action' => $request->fullUrl(),
        ]);
    }

    public function start(Request $request, Invoice $invoice)
    {
        abort_if($invoice->status === 'void', 404);
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'gateway' => ['required', 'in:paystack,opay'],
        ]);

        try {
            $url = $this->payments->startOnline($data['gateway'], $invoice, Money::minor($data['amount']), $invoice->user,
                fn (Payment $p) => route('site.pay.return', $p->reference));
        } catch (\RuntimeException $e) {
            Log::error('Website payment start failed', ['gateway' => $data['gateway'], 'invoice' => $invoice->id, 'error' => $e->getMessage()]);
            throw ValidationException::withMessages(['amount' => 'We could not reach the payment provider. Please try again shortly.']);
        }

        return redirect()->away($url);
    }

    /** The gateway sends the payer back here. We ask the gateway before showing anything as paid. */
    public function returned(string $reference)
    {
        $payment = Payment::with('invoice.enrollment.course')->where('reference', $reference)->whereIn('method', ['paystack', 'opay'])->firstOrFail();
        $problem = null;
        try {
            $payment = $this->payments->settle($payment)?->load('invoice.enrollment.course') ?? $payment;
        } catch (\RuntimeException $e) {
            Log::error('Website payment check failed', ['reference' => $reference, 'error' => $e->getMessage()]);
            $problem = true;
        }

        return view('site.paid', ['payment' => $payment, 'problem' => $problem]);
    }
}
