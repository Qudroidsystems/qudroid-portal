<?php

namespace App\Services\Finance;

use App\Mail\ReceiptMail;
use App\Models\Finance\Payment;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

/**
 * E-mails the receipt for a confirmed payment, with the PDF attached and a
 * link to download it again. A mail problem never undoes the payment.
 */
class ReceiptMailer
{
    public function send(Payment $payment, ?string $to = null): bool
    {
        $payment->loadMissing('user', 'allocations.invoice');
        $to ??= $payment->user?->email;
        if (!$to || !$payment->number || $payment->status !== 'confirmed') {
            return false;
        }

        try {
            Mail::to($to)->send(new ReceiptMail($payment, self::downloadUrl($payment)));

            return true;
        } catch (\Throwable $e) {
            Log::warning('Receipt e-mail failed', ['payment' => $payment->number, 'error' => $e->getMessage()]);

            return false;
        }
    }

    /** A link anyone holding it can use to download the receipt PDF, for a year. */
    public static function downloadUrl(Payment $payment): string
    {
        return URL::temporarySignedRoute('site.receipt', now()->addYear(), ['payment' => $payment->id]);
    }
}
