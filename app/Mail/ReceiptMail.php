<?php

namespace App\Mail;

use App\Models\Finance\Payment;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** "We received your payment": the receipt, as a PDF and a download link. */
class ReceiptMail extends Mailable
{
    public function __construct(public Payment $payment, public string $downloadUrl)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Receipt ' . $this->payment->number . ' from ' . config('qudroid.company', config('app.name')));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.receipt', with: ['payment' => $this->payment, 'downloadUrl' => $this->downloadUrl]);
    }

    public function attachments(): array
    {
        $payment = $this->payment;

        return [
            Attachment::fromData(fn () => Pdf::loadView('pdf.receipt', ['payment' => $payment])->setPaper('a4')->output(), 'Receipt-' . $payment->number . '.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
