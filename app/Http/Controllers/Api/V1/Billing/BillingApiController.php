<?php

namespace App\Http\Controllers\Api\V1\Billing;

use App\Http\Controllers\Api\V1\ApiController;
use App\Models\Finance\Invoice;
use App\Models\Finance\Payment;
use App\Services\Finance\DocumentPdf;
use App\Services\Finance\PaymentService;
use App\Services\Finance\PaystackGateway;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * "My bills" for the app: a person's own invoices and receipts, and paying
 * online with Paystack. Payments are only ever confirmed by checking with
 * Paystack (verify here, the callback page or the webhook), never on the
 * app's word.
 */
class BillingApiController extends ApiController
{
    public function __construct(protected PaymentService $payments, protected PaystackGateway $paystack) {}

    /** ?status=open|paid|all (default all, void left out) */
    public function invoices(Request $request)
    {
        $user = $request->user();
        $q = Invoice::where('user_id', $user->id)->where('status', '!=', 'void')->with('enrollment.course');
        match ($request->get('status', 'all')) {
            'open' => $q->open(),
            'paid' => $q->where('status', 'paid'),
            default => null,
        };
        $page = $q->orderByRaw("case when status in ('issued','partially_paid') then 0 else 1 end")->latest('issue_date')->latest('id')
            ->paginate(min(50, max(5, (int) $request->get('per_page', 20))));

        $owed = Invoice::where('user_id', $user->id)->open()->get()->sum(fn ($i) => $i->balanceMinor());
        $currency = $page->first()?->currency;
        $res = $this->paged($page, fn ($i) => $this->invoice($i));
        $body = $res->getData(true);
        $body['meta'] += ['balance_total' => $owed, 'balance_total_label' => Money::format(Money::major($owed), $currency), 'online' => (bool) $this->payments->onlineGateways(), 'gateways' => array_keys($this->payments->onlineGateways())];

        return response()->json($body);
    }

    public function show(Request $request, Invoice $invoice)
    {
        $this->own($request, $invoice->user_id);
        abort_if($invoice->status === 'void', 404);
        $invoice->load('items', 'enrollment.course');

        $payments = Payment::whereHas('allocations', fn ($q) => $q->where('invoice_id', $invoice->id))
            ->orWhere(fn ($q) => $q->where('invoice_id', $invoice->id)->where('user_id', $invoice->user_id)->where('status', 'pending'))
            ->latest('id')->get();

        return $this->ok($this->invoice($invoice) + [
            'items' => $invoice->items->map(fn ($it) => ['description' => $it->description, 'quantity' => (float) $it->quantity]
                + $this->money('unit_price', $it->unit_price, $invoice->currency) + $this->money('discount', $it->discount, $invoice->currency) + $this->money('amount', $it->amount, $invoice->currency))->values(),
            'subtotal_label' => Money::format($invoice->subtotal, $invoice->currency),
            'discount_label' => (float) $invoice->discount_total > 0 ? Money::format($invoice->discount_total, $invoice->currency) : null,
            'notes' => $invoice->notes,
            'payments' => $payments->map(fn ($p) => $this->payment($p))->values(),
        ]);
    }

    /** Start an online payment. {amount?} in kobo (defaults to the whole balance), {gateway?} paystack (default) or opay. */
    public function pay(Request $request, Invoice $invoice)
    {
        $this->own($request, $invoice->user_id);
        $data = $request->validate(['amount' => ['nullable', 'integer', 'min:1'], 'gateway' => ['nullable', 'in:paystack,opay']]);
        $gateway = $data['gateway'] ?? 'paystack';
        $amount = (int) ($data['amount'] ?? $invoice->balanceMinor());
        $callback = route('billing.paystack.app-return');

        try {
            $url = $this->payments->startOnline($gateway, $invoice, $amount, $request->user(), fn () => $callback);
        } catch (ValidationException $e) {
            return $this->fail(collect($e->errors())->flatten()->first(), 422, array_key_exists($gateway, $this->payments->onlineGateways()) ? 'invalid_amount' : 'online_payment_off');
        } catch (\RuntimeException $e) {
            Log::error('Online payment start failed', ['gateway' => $gateway, 'invoice' => $invoice->id, 'error' => $e->getMessage()]);

            return $this->fail('We could not reach the payment provider. Please try again shortly.', 502, 'gateway_unavailable');
        }
        $payment = Payment::where('invoice_id', $invoice->id)->where('method', $gateway)->where('status', 'pending')->latest('id')->first();

        return $this->ok(['authorization_url' => $url, 'reference' => $payment?->reference, 'callback_url' => $callback, 'gateway' => $gateway]
            + $this->money('amount', Money::major($amount), $invoice->currency), null, 201);
    }

    /** {reference} — ask the gateway (Paystack or OPay). Safe to repeat; the webhook may have settled it already. */
    public function verify(Request $request)
    {
        $data = $request->validate(['reference' => ['required', 'string', 'max:100']]);
        $payment = Payment::where('reference', $data['reference'])->whereIn('method', ['paystack', 'opay'])->first();
        if (!$payment || $payment->user_id !== $request->user()->id) return $this->fail('Payment not found.', 404, 'not_found');

        try {
            $payment = $this->payments->settle($payment) ?? $payment;
        } catch (\RuntimeException $e) {
            Log::error('Online payment check failed', ['reference' => $payment->reference, 'error' => $e->getMessage()]);

            return $this->ok(['status' => 'pending', 'message' => 'We could not confirm your payment yet. If you were charged it will show here shortly; there is no need to pay again.',
                'payment' => $this->payment($payment), 'invoice' => $payment->invoice ? $this->invoice($payment->invoice) : null]);
        }

        [$status, $message] = match ($payment->status) {
            'confirmed' => ['success', 'Payment received. Thank you!'],
            'pending' => ['pending', 'Your payment has not completed yet. If you were charged it will show here shortly.'],
            default => ['failed', 'The payment did not go through. You have not been charged for it.'],
        };

        return $this->ok(['status' => $status, 'message' => $message, 'payment' => $this->payment($payment),
            'invoice' => $payment->invoice ? $this->invoice($payment->invoice->fresh()) : null]);
    }

    /** Receipts: confirmed (and reversed) payments. */
    public function payments(Request $request)
    {
        $page = Payment::where('user_id', $request->user()->id)->whereIn('status', ['confirmed', 'reversed'])
            ->latest('confirmed_at')->latest('id')->paginate(min(50, max(5, (int) $request->get('per_page', 20))));

        return $this->paged($page, fn ($p) => $this->payment($p));
    }

    public function invoicePdf(Request $request, Invoice $invoice, DocumentPdf $pdf)
    {
        $this->own($request, $invoice->user_id);
        abort_if($invoice->status === 'void', 404);

        return $pdf->invoice($invoice, $request->boolean('view'));
    }

    public function receipt(Request $request, Payment $payment, DocumentPdf $pdf)
    {
        $this->own($request, $payment->user_id);
        abort_unless(in_array($payment->status, ['confirmed', 'reversed'], true), 404);

        return $pdf->receipt($payment, $request->boolean('view'));
    }

    protected function invoice(Invoice $i): array
    {
        $min = $i->isOpen() ? $this->payments->minimumPayment($i) : 0;

        return [
            'id' => $i->id, 'number' => $i->number, 'title' => $i->title,
            'issue_date' => $i->issue_date?->toDateString(), 'due_date' => $i->due_date?->toDateString(),
            'currency' => $i->currency,
        ] + $this->money('total', $i->total, $i->currency) + $this->money('amount_paid', $i->amount_paid, $i->currency)
          + $this->money('balance', $i->balance(), $i->currency) + [
            'status' => $i->status, 'status_label' => $i->status_label, 'overdue' => $i->isOverdue(),
            'can_pay' => $i->isOpen() && $i->balanceMinor() > 0 && (bool) $this->payments->onlineGateways(),
            'part_payment' => $i->isOpen() && $min < $i->balanceMinor(),
            'min_payment' => $min, 'min_payment_label' => Money::format(Money::major($min), $i->currency),
            'course' => $i->enrollment?->course ? ['id' => $i->enrollment->course->id, 'title' => $i->enrollment->course->title] : null,
            'pdf_url' => route('api.v1.billing.invoices.pdf', $i),
            'web_url' => route('billing.invoice', $i),
        ];
    }

    protected function payment(Payment $p): array
    {
        $receipt = in_array($p->status, ['confirmed', 'reversed'], true);

        return ['id' => $p->id, 'number' => $p->number] + $this->money('amount', $p->amount, $p->currency) + [
            'method' => $p->method, 'method_label' => $p->method_label, 'reference' => $p->reference,
            'status' => $p->status, 'status_label' => $p->status_label,
            'paid_at' => ($p->paid_at ?? $p->confirmed_at)?->toIso8601String(),
            'invoice' => $p->invoice_id ? ['id' => $p->invoice_id, 'number' => $p->invoice?->number] : null,
            'receipt_url' => $receipt ? route('api.v1.billing.payments.receipt', $p) : null,
            'web_url' => $receipt ? route('billing.receipt', $p) : ($p->invoice_id ? route('billing.invoice', $p->invoice_id) : route('billing.index')),
        ];
    }

    /** Only your own bills; finance staff use the Finance module. */
    protected function own(Request $request, ?int $userId): void
    {
        abort_unless($request->user()->id === $userId, 404);
    }
}
