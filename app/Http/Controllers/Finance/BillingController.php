<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Finance\Invoice;
use App\Models\Finance\Payment;
use App\Services\Finance\PaymentService;
use App\Services\Finance\PaystackGateway;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/** A person's own invoices, receipts and online payments. */
class BillingController extends Controller
{
    public function __construct(protected PaymentService $payments, protected PaystackGateway $paystack) {}

    public function index(Request $request)
    {
        $user = $request->user();

        return view('modules.finance.billing.index', [
            'invoices' => Invoice::where('user_id', $user->id)->where('status', '!=', 'void')->latest('id')->get(),
            'receipts' => Payment::where('user_id', $user->id)->whereIn('status', ['confirmed', 'reversed'])->latest('confirmed_at')->get(),
        ]);
    }

    public function invoice(Request $request, Invoice $invoice)
    {
        $this->own($request, $invoice->user_id);
        $invoice->load('items', 'enrollment.course');

        return view('modules.finance.billing.invoice', [
            'invoice'  => $invoice,
            'receipts' => Payment::whereHas('allocations', fn ($q) => $q->where('invoice_id', $invoice->id))->whereIn('status', ['confirmed', 'reversed'])->latest('id')->get(),
            'minimum'  => Money::major($this->payments->minimumPayment($invoice)),
            'online'   => $this->paystack->enabled(),
        ]);
    }

    public function pay(Request $request, Invoice $invoice)
    {
        $this->own($request, $invoice->user_id);
        $data = $request->validate(['amount' => ['required', 'numeric', 'min:0.01']]);

        try {
            $url = $this->payments->startPaystack($invoice, Money::minor($data['amount']), $request->user(), route('billing.paystack.callback'));
        } catch (\RuntimeException $e) {
            Log::error('Paystack initialize failed', ['invoice' => $invoice->id, 'error' => $e->getMessage()]);
            throw ValidationException::withMessages(['amount' => 'We could not reach the payment provider. Please try again shortly.']);
        }

        return redirect()->away($url);
    }

    /** Paystack sends the payer back here. We check with Paystack before believing anything. */
    public function callback(Request $request)
    {
        $reference = (string) $request->query('reference', $request->query('trxref', ''));
        $payment = Payment::where('reference', $reference)->where('method', 'paystack')->first();
        abort_unless($payment && $payment->user_id === $request->user()->id, 404);

        try {
            $payment = $this->payments->settlePaystack($reference);
        } catch (\RuntimeException $e) {
            Log::error('Paystack verify failed', ['reference' => $reference, 'error' => $e->getMessage()]);

            return redirect()->route('billing.invoice', $payment->invoice_id)
                ->with('error', 'We could not confirm your payment with Paystack yet. If you were charged it will show here shortly; there is no need to pay again.');
        }

        return match ($payment->status) {
            'confirmed' => redirect()->route('billing.receipt', $payment)->with('success', 'Payment received. Thank you!'),
            'pending'   => redirect()->route('billing.invoice', $payment->invoice_id)->with('error', 'Your payment has not completed yet. If you were charged it will show here shortly.'),
            default     => redirect()->route('billing.invoice', $payment->invoice_id)->with('error', 'The payment did not go through. You have not been charged for it.'),
        };
    }

    public function receipt(Request $request, Payment $payment)
    {
        $this->own($request, $payment->user_id);
        abort_unless(in_array($payment->status, ['confirmed', 'reversed'], true), 404);
        $payment->load('user', 'allocations.invoice');

        return view('modules.finance.payments.show', ['payment' => $payment, 'staffView' => false]);
    }

    public function invoicePdf(Request $request, Invoice $invoice, \App\Services\Finance\DocumentPdf $pdf)
    {
        $this->own($request, $invoice->user_id);
        abort_if($invoice->status === 'void', 404);

        return $pdf->invoice($invoice, $request->boolean('view'));
    }

    public function receiptPdf(Request $request, Payment $payment, \App\Services\Finance\DocumentPdf $pdf)
    {
        $this->own($request, $payment->user_id);

        return $pdf->receipt($payment, $request->boolean('view'));
    }

    /** People only see their own bills here; finance staff use the Finance module. */
    protected function own(Request $request, int $userId): void
    {
        abort_unless($request->user()->id === $userId, 404);
    }
}
