<?php

namespace App\Http\Controllers\Academy;

use App\Http\Controllers\Controller;
use App\Models\Academy\Student;
use App\Models\Finance\Invoice;
use App\Models\Finance\Payment;
use App\Services\Academy\ApplicationService;
use App\Services\Finance\PaymentService;
use App\Services\Finance\ReceiptMailer;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Academy staff helping a student pay their course fees, from the student's
 * record or their admission request:
 *  - record money received by transfer, cash or POS (a receipt is e-mailed),
 *  - open Paystack/OPay checkout for the student's invoice right now,
 *  - send the student a secure link to pay (by e-mail, or copy it to WhatsApp),
 *  - e-mail a receipt again.
 * Only course-fee invoices are handled here; everything else is Finance's.
 */
class StudentFeeController extends Controller
{
    public function __construct(
        protected PaymentService $payments,
        protected ApplicationService $applications,
        protected ReceiptMailer $receipts,
    ) {}

    public function record(Request $request, Invoice $invoice)
    {
        $this->courseFee($invoice);
        $data = $request->validate([
            'amount'    => ['required', 'numeric', 'min:0.01'],
            'method'    => ['required', Rule::in(Payment::MANUAL_METHODS)],
            'reference' => ['nullable', 'string', 'max:100'],
            'paid_at'   => ['nullable', 'date', 'before_or_equal:today'],
            'notes'     => ['nullable', 'string', 'max:500'],
        ]);
        $payment = $this->payments->recordManual($invoice, $data, $request->user());

        return $this->back($invoice)->with('success', 'Payment of ' . Money::format($payment->amount, $payment->currency) . ' recorded. Receipt ' . $payment->number . ' has been e-mailed to ' . $invoice->user->email . '.');
    }

    public function payOnline(Request $request, Invoice $invoice)
    {
        $this->courseFee($invoice);
        $data = $request->validate([
            'amount'  => ['required', 'numeric', 'min:0.01'],
            'gateway' => ['required', 'in:paystack,opay'],
        ]);

        try {
            $url = $this->payments->startOnline($data['gateway'], $invoice, Money::minor($data['amount']), $invoice->user,
                fn (Payment $p) => route('academy.fees.return', $p->reference), $request->user());
        } catch (\RuntimeException $e) {
            Log::error('Staff online payment start failed', ['invoice' => $invoice->id, 'error' => $e->getMessage()]);
            throw ValidationException::withMessages(['amount' => 'We could not reach the payment provider. Please try again shortly.']);
        }

        return redirect()->away($url);
    }

    /** Back from the gateway: check it with the gateway, then return to the student. */
    public function returned(string $reference)
    {
        $payment = Payment::with('invoice')->where('reference', $reference)->whereIn('method', ['paystack', 'opay'])->firstOrFail();
        try {
            $payment = $this->payments->settle($payment) ?? $payment;
        } catch (\RuntimeException $e) {
            Log::error('Staff online payment check failed', ['reference' => $reference, 'error' => $e->getMessage()]);
        }
        $back = redirect()->to($this->studentPage($payment->invoice));

        return match ($payment->status) {
            'confirmed' => $back->with('success', 'Payment confirmed. Receipt ' . $payment->number . ' has been e-mailed to the student.'),
            'pending'   => $back->with('error', 'The payment is not confirmed yet. Check again in a minute; there is no need to pay again.'),
            default     => $back->with('error', 'The payment did not go through.'),
        };
    }

    public function sendLink(Request $request, Invoice $invoice)
    {
        $this->courseFee($invoice);
        abort_unless($invoice->isOpen(), 422, 'This invoice is not open for payment.');
        $url = $this->applications->sendPayLink($invoice, $request->boolean('email', true));

        return $this->back($invoice)
            ->with('success', $request->boolean('email', true) ? 'A link to pay has been e-mailed to ' . $invoice->user->email . '.' : 'Here is the link to pay.')
            ->with('pay_link', ['invoice' => $invoice->id, 'url' => $url]);
    }

    public function emailReceipt(Payment $payment)
    {
        abort_unless($payment->invoice && $payment->invoice->isCourseFee(), 404);
        $sent = $this->receipts->send($payment);

        return $this->back($payment->invoice)->with($sent ? 'success' : 'error',
            $sent ? 'Receipt ' . $payment->number . ' e-mailed to ' . $payment->user->email . '.' : 'The receipt could not be e-mailed. Check the mail settings.');
    }

    protected function courseFee(Invoice $invoice): void
    {
        abort_if($invoice->status === 'void' || !$invoice->isCourseFee(), 404);
    }

    /** Back to the page the staff member came from (a student record or an admission request). */
    protected function back(Invoice $invoice)
    {
        return redirect()->back(fallback: $this->studentPage($invoice));
    }

    protected function studentPage(Invoice $invoice): string
    {
        $student = Student::where('user_id', $invoice->user_id)->first();

        return $student ? route('academy.students.show', $student) . '#fees' : route('academy.index');
    }
}
