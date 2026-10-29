<?php

namespace App\Services\Finance;

use App\Models\Finance\Invoice;
use App\Models\Finance\Payment;
use App\Models\User;
use App\Services\Academy\EnrollmentService;
use App\Services\Messaging\PortalNotifier;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Money in. Every confirmed payment gets a receipt number and is allocated
 * to invoices; invoice balances are recalculated from those allocations.
 * When a course-fee invoice reaches its threshold (full fee, or the minimum
 * first payment where instalments are allowed), course access opens.
 */
class PaymentService
{
    public function __construct(
        protected InvoiceService $invoices,
        protected EnrollmentService $enrollments,
        protected PaystackGateway $paystack,
        protected OpayGateway $opay,
    ) {}

    /** The online gateways that are switched on, key => label (Paystack first). */
    public function onlineGateways(): array
    {
        return array_filter([
            'paystack' => $this->paystack->enabled() ? 'Paystack' : null,
            'opay'     => $this->opay->enabled() ? 'OPay' : null,
        ]);
    }

    /** A bank transfer, cash or POS payment that Finance has checked. */
    public function recordManual(Invoice $invoice, array $data, User $by): Payment
    {
        $this->check(!in_array($data['method'] ?? '', Payment::MANUAL_METHODS, true), 'method', 'Choose how the money was paid.');
        $amount = Money::minor($data['amount'] ?? 0);
        $this->check(!$invoice->isOpen(), 'amount', 'This invoice is not open for payment.');
        $this->check($amount <= 0, 'amount', 'Enter the amount received.');
        $this->check($amount > $invoice->balanceMinor(), 'amount', 'That is more than the balance of ' . Money::format($invoice->balance(), $invoice->currency) . '.');
        $reference = trim((string) ($data['reference'] ?? '')) ?: null;
        $this->check($reference && Payment::where('reference', $reference)->exists(), 'reference', 'A payment with this reference is already recorded.');

        $payment = DB::transaction(function () use ($invoice, $data, $by, $amount, $reference) {
            $payment = Payment::create([
                'user_id'     => $invoice->user_id,
                'invoice_id'  => $invoice->id,
                'amount'      => Money::major($amount),
                'currency'    => $invoice->currency,
                'method'      => $data['method'],
                'reference'   => $reference,
                'status'      => 'pending',
                'paid_at'     => $data['paid_at'] ?? now(),
                'notes'       => $data['notes'] ?? null,
                'recorded_by' => $by->id,
            ]);

            return $this->confirm($payment, $by);
        });

        $this->afterConfirm($payment, $by);

        return $payment;
    }

    /** Create a pending online payment and return the Paystack page to send the payer to. */
    public function startPaystack(Invoice $invoice, int $amountMinor, User $payer, string $callbackUrl): string
    {
        return $this->startOnline('paystack', $invoice, $amountMinor, $payer, fn () => $callbackUrl);
    }

    /**
     * Create a pending online payment with a gateway and return the page to send the payer to.
     * $returnUrl gets the new Payment and gives the address the gateway sends the payer back to.
     */
    public function startOnline(string $gateway, Invoice $invoice, int $amountMinor, User $payer, callable $returnUrl): string
    {
        $this->check(!array_key_exists($gateway, $this->onlineGateways()), 'amount', 'Online payment is not set up yet. Please pay by bank transfer and send your receipt to the finance office.');
        $this->check(!$invoice->isOpen(), 'amount', 'This invoice is not open for payment.');
        $this->check($amountMinor < $this->minimumPayment($invoice), 'amount', 'The least you can pay now is ' . Money::format(Money::major($this->minimumPayment($invoice)), $invoice->currency) . '.');
        $this->check($amountMinor > $invoice->balanceMinor(), 'amount', 'That is more than the balance.');

        $payment = Payment::create([
            'user_id'     => $invoice->user_id,
            'invoice_id'  => $invoice->id,
            'amount'      => Money::major($amountMinor),
            'currency'    => $invoice->currency,
            'method'      => $gateway,
            'reference'   => 'QH-' . $invoice->id . '-' . Str::upper(Str::random(10)),
            'status'      => 'pending',
            'recorded_by' => $payer->id,
        ]);
        $back = $returnUrl($payment);

        if ($gateway === 'opay') {
            return $this->opay->initialize(
                ['email' => $payer->email, 'id' => $payer->id, 'name' => $payer->name, 'phone' => $payer->phone_number ?? null],
                $amountMinor, $invoice->currency, $payment->reference, $back, route('webhooks.opay'), $back,
                'Invoice ' . $invoice->number,
            );
        }

        return $this->paystack->initialize($payer->email, $amountMinor, $invoice->currency, $payment->reference, $back, [
            'invoice' => $invoice->number, 'payment_id' => $payment->id,
        ]);
    }

    /** Check a pending online payment with whichever gateway took it. Safe to call repeatedly. */
    public function settle(Payment $payment): ?Payment
    {
        return match ($payment->method) {
            'paystack' => $this->settlePaystack($payment->reference),
            'opay'     => $this->settleOpay($payment->reference),
            default    => $payment,
        };
    }

    /**
     * Check an OPay reference with OPay and confirm the payment if it succeeded
     * for the exact amount and currency. Safe to call repeatedly.
     */
    public function settleOpay(string $reference): ?Payment
    {
        $payment = Payment::where('reference', $reference)->where('method', 'opay')->first();
        if (!$payment) return null;
        if ($payment->status !== 'pending') return $payment;

        $data = $this->opay->status($reference);
        $status = strtoupper((string) ($data['status'] ?? ''));

        $confirmed = DB::transaction(function () use ($payment, $data, $status) {
            $payment = Payment::whereKey($payment->id)->lockForUpdate()->first();
            if ($payment->status !== 'pending') return null;

            $ok = $status === 'SUCCESS'
                && (int) ($data['amount']['total'] ?? -1) === Money::minor($payment->amount)
                && strtoupper((string) ($data['amount']['currency'] ?? '')) === $payment->currency
                && ($data['reference'] ?? null) === $payment->reference;

            $payment->gateway_response = collect($data)->only(['orderNo', 'status', 'reference', 'amount', 'vat', 'failureReason', 'createTime'])->all();

            if (!$ok) {
                // Not paid yet: keep it pending. Closed, failed or a wrong amount: failed.
                $payment->status = in_array($status, ['INITIAL', 'PENDING', ''], true) ? 'pending' : 'failed';
                $payment->save();
                if ($status === 'SUCCESS') Log::warning('OPay amount/currency mismatch', ['reference' => $payment->reference]);

                return null;
            }

            $payment->paid_at = now();
            $payment->save();

            return $this->confirm($payment, null);
        });

        if ($confirmed) $this->afterConfirm($confirmed, null);

        return $payment->fresh();
    }

    /**
     * Check a Paystack reference with Paystack and confirm the payment if it
     * succeeded for the exact amount and currency. Safe to call repeatedly
     * (callback and webhook may both arrive).
     */
    public function settlePaystack(string $reference): ?Payment
    {
        $payment = Payment::where('reference', $reference)->where('method', 'paystack')->first();
        if (!$payment) return null;
        if ($payment->status !== 'pending') return $payment;

        $data = $this->paystack->verify($reference);

        $confirmed = DB::transaction(function () use ($payment, $data) {
            $payment = Payment::whereKey($payment->id)->lockForUpdate()->first();
            if ($payment->status !== 'pending') return null;

            $ok = ($data['status'] ?? null) === 'success'
                && (int) ($data['amount'] ?? -1) === Money::minor($payment->amount)
                && strtoupper((string) ($data['currency'] ?? '')) === $payment->currency
                && ($data['reference'] ?? null) === $payment->reference;

            $payment->gateway_response = collect($data)->only(['id', 'status', 'reference', 'amount', 'currency', 'channel', 'paid_at', 'gateway_response', 'fees'])->all();

            if (!$ok) {
                // Abandoned or still in progress: keep it pending. Anything else is a failure.
                $payment->status = in_array($data['status'] ?? null, ['abandoned', 'ongoing', 'pending', 'processing'], true) ? 'pending' : 'failed';
                $payment->save();
                if ($payment->status === 'failed' && ($data['status'] ?? null) === 'success') {
                    Log::warning('Paystack amount/currency mismatch', ['reference' => $payment->reference]);
                }

                return null;
            }

            $payment->paid_at = $data['paid_at'] ?? now();
            $payment->save();

            return $this->confirm($payment, null);
        });

        if ($confirmed) $this->afterConfirm($confirmed, null);

        return $payment->fresh();
    }

    /** Reverse a confirmed payment (bounced transfer, refund, mistake). It stays on record. */
    public function reverse(Payment $payment, User $by, string $reason): Payment
    {
        $this->check(trim($reason) === '', 'reason', 'Give a reason.');
        $this->check($payment->status !== 'confirmed', 'reason', 'Only a confirmed payment can be reversed.');

        DB::transaction(function () use ($payment, $by, $reason) {
            $payment->update(['status' => 'reversed', 'reversed_at' => now(), 'reversed_by' => $by->id, 'reversal_reason' => mb_substr(trim($reason), 0, 255)]);
            app(LedgerService::class)->paymentReversed($payment, $by);
            foreach ($payment->allocations()->with('invoice')->get() as $allocation) {
                $invoice = $this->invoices->refresh($allocation->invoice);
                if ($invoice->enrollment && !$this->thresholdMet($invoice)) {
                    $this->enrollments->paymentReversed($invoice->enrollment, 'Payment ' . $payment->number . ' reversed: ' . trim($reason));
                }
            }
        });

        return $payment->fresh();
    }

    /** The smallest online payment allowed on this invoice right now, in minor units. */
    public function minimumPayment(Invoice $invoice): int
    {
        $balance = $invoice->balanceMinor();
        $course = $invoice->enrollment?->course;
        if (!$course || !$course->installments_allowed) {
            return $balance;
        }
        if (Money::minor($invoice->amount_paid) > 0) {
            return min($balance, 100); // later instalments: any amount
        }

        return min($balance, max(100, Money::minor($course->min_deposit)));
    }

    /** Whether enough of a course-fee invoice is paid to open course access. */
    public function thresholdMet(Invoice $invoice): bool
    {
        $paid = Money::minor($invoice->amount_paid);
        $course = $invoice->enrollment?->course;
        if ($course && $course->installments_allowed) {
            return $paid >= max(1, min(Money::minor($invoice->total), Money::minor($course->min_deposit)));
        }

        return $paid >= Money::minor($invoice->total);
    }

    protected function confirm(Payment $payment, ?User $by): Payment
    {
        $payment->update([
            'status'       => 'confirmed',
            'number'       => DocumentNumber::next('receipt'),
            'confirmed_by' => $by?->id,
            'confirmed_at' => now(),
        ]);

        // Allocate to the invoice it was made for; anything over the balance stays unallocated (credit).
        $invoice = Invoice::whereKey($payment->invoice_id)->lockForUpdate()->first();
        if ($invoice && $invoice->isOpen()) {
            $amount = min(Money::minor($payment->amount), $invoice->balanceMinor());
            if ($amount > 0) {
                $payment->allocations()->create(['invoice_id' => $invoice->id, 'amount' => Money::major($amount)]);
            }
            $this->invoices->refresh($invoice);
        }
        app(LedgerService::class)->paymentConfirmed($payment, $by);

        return $payment;
    }

    protected function afterConfirm(Payment $payment, ?User $by): void
    {
        PortalNotifier::toUsers([$payment->user_id], 'Payment received',
            'We received ' . Money::format($payment->amount, $payment->currency) . '. Receipt ' . $payment->number . '.',
            route('billing.receipt', $payment), 'payment');

        foreach ($payment->allocations()->with('invoice.enrollment')->get() as $allocation) {
            $invoice = $allocation->invoice;
            if ($invoice->enrollment && $this->thresholdMet($invoice)) {
                $this->enrollments->paymentConfirmed($invoice->enrollment, $by, $payment->number);
            }
        }
    }

    protected function check(bool $failed, string $field, string $message): void
    {
        if ($failed) throw ValidationException::withMessages([$field => $message]);
    }
}
