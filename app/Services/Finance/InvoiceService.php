<?php

namespace App\Services\Finance;

use App\Models\Academy\Enrollment;
use App\Models\Finance\Invoice;
use App\Models\Finance\PaymentAllocation;
use App\Models\User;
use App\Services\Messaging\PortalNotifier;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InvoiceService
{
    /**
     * Issue an invoice.
     *
     * @param  array<int, array{description:string, quantity?:mixed, unit_price:mixed, discount?:mixed}>  $lines
     */
    public function issue(User $billTo, string $title, array $lines, User $by, array $options = []): Invoice
    {
        $lines = array_values(array_filter($lines, fn ($l) => trim((string) ($l['description'] ?? '')) !== ''));
        if (!$lines) {
            throw ValidationException::withMessages(['lines' => 'Add at least one line.']);
        }

        $invoice = DB::transaction(function () use ($billTo, $title, $lines, $by, $options) {
            $subtotal = 0;
            $discounts = 0;
            $rows = [];
            foreach ($lines as $i => $l) {
                $qtyHundredths = Money::minor($l['quantity'] ?? 1);      // quantity with two decimals
                $gross = intdiv(Money::minor($l['unit_price']) * $qtyHundredths + 50, 100);
                $discount = Money::minor($l['discount'] ?? 0);
                if ($qtyHundredths <= 0 || $gross < 0 || $discount < 0 || $discount > $gross) {
                    throw ValidationException::withMessages(['lines' => 'Line ' . ($i + 1) . ': check the quantity, price and discount.']);
                }
                $subtotal += $gross;
                $discounts += $discount;
                $rows[] = [
                    'description' => mb_substr(trim($l['description']), 0, 255),
                    'quantity'    => Money::major($qtyHundredths),
                    'unit_price'  => Money::major(Money::minor($l['unit_price'])),
                    'discount'    => Money::major($discount),
                    'amount'      => Money::major($gross - $discount),
                    'position'    => $i,
                ];
            }

            $invoice = Invoice::create([
                'number'         => DocumentNumber::next('invoice'),
                'user_id'        => $billTo->id,
                'enrollment_id'  => $options['enrollment_id'] ?? null,
                'title'          => mb_substr($title, 0, 200),
                'issue_date'     => today(),
                'due_date'       => $options['due_date'] ?? null,
                'currency'       => strtoupper($options['currency'] ?? config('qudroid.academy.currency', 'NGN')),
                'subtotal'       => Money::major($subtotal),
                'discount_total' => Money::major($discounts),
                'total'          => Money::major($subtotal - $discounts),
                'amount_paid'    => '0.00',
                'status'         => $subtotal - $discounts > 0 ? 'issued' : 'paid',
                'notes'          => $options['notes'] ?? null,
                'created_by'     => $by->id,
            ]);
            $invoice->items()->createMany($rows);

            return $invoice;
        });

        PortalNotifier::toUsers([$billTo->id], 'New invoice ' . $invoice->number,
            $invoice->title . ': ' . Money::format($invoice->total, $invoice->currency) . ($invoice->due_date ? ', due ' . $invoice->due_date->format('j M Y') : '') . '.',
            route('billing.invoice', $invoice), 'invoice');

        return $invoice;
    }

    /** The course-fee invoice for an enrollment. Returns the existing one if there is one; null when nothing is owed. */
    public function forEnrollment(Enrollment $enrollment, User $by): ?Invoice
    {
        if ($existing = $enrollment->invoices()->where('status', '!=', 'void')->first()) {
            return $existing;
        }
        if (Money::minor($enrollment->netFee()) <= 0) {
            return null;
        }

        $cohort = $enrollment->cohort;
        $course = $enrollment->course;
        $due = $cohort->registration_closes_on ?? $cohort->starts_on;
        if (!$due || $due->lt(today())) {
            $due = today()->addDays((int) config('qudroid.finance.default_due_days', 14));
        }

        return $this->issue($enrollment->student->user, 'Course fee: ' . $course->title, [[
            'description' => $course->title . ' (' . $course->code . ') — ' . $cohort->name,
            'quantity'    => 1,
            'unit_price'  => $enrollment->fee_amount,
            'discount'    => $enrollment->discount_amount,
        ]], $by, ['enrollment_id' => $enrollment->id, 'due_date' => $due, 'currency' => $enrollment->currency]);
    }

    /** Cancel an unpaid invoice. A paid or part-paid one needs its payments reversed first. */
    public function void(Invoice $invoice, User $by, string $reason): Invoice
    {
        $reason = trim($reason);
        if ($reason === '') throw ValidationException::withMessages(['reason' => 'Give a reason.']);
        if ($invoice->status === 'void') throw ValidationException::withMessages(['reason' => 'This invoice is already void.']);
        if (Money::minor($invoice->amount_paid) > 0) {
            throw ValidationException::withMessages(['reason' => 'Payments have been applied to this invoice. Reverse them before voiding it.']);
        }

        $invoice->update(['status' => 'void', 'voided_at' => now(), 'voided_by' => $by->id, 'void_reason' => mb_substr($reason, 0, 255)]);

        return $invoice;
    }

    /** Recalculate what has been paid from confirmed allocations, and the status that follows. */
    public function refresh(Invoice $invoice): Invoice
    {
        $paid = PaymentAllocation::where('invoice_id', $invoice->id)
            ->whereHas('payment', fn ($q) => $q->where('status', 'confirmed'))
            ->pluck('amount')->sum(fn ($a) => Money::minor($a));

        $status = $invoice->status;
        if ($status !== 'void') {
            $total = Money::minor($invoice->total);
            $status = $paid >= $total ? 'paid' : ($paid > 0 ? 'partially_paid' : 'issued');
        }

        $invoice->update(['amount_paid' => Money::major($paid), 'status' => $status]);

        return $invoice;
    }
}
