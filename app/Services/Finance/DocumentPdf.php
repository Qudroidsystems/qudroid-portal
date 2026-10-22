<?php

namespace App\Services\Finance;

use App\Models\Finance\Invoice;
use App\Models\Finance\Payment;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

/** Invoice and receipt PDFs, the same for staff and for the customer. */
class DocumentPdf
{
    public function invoice(Invoice $invoice, bool $inline = false): Response
    {
        $invoice->loadMissing('items', 'user');

        return $this->send('pdf.invoice', ['invoice' => $invoice], 'Invoice-' . $invoice->number . '.pdf', $inline);
    }

    public function receipt(Payment $payment, bool $inline = false): Response
    {
        abort_unless($payment->number && in_array($payment->status, ['confirmed', 'reversed'], true), 404);
        $payment->loadMissing('user', 'allocations.invoice');

        return $this->send('pdf.receipt', ['payment' => $payment], 'Receipt-' . $payment->number . '.pdf', $inline);
    }

    protected function send(string $view, array $data, string $name, bool $inline): Response
    {
        $pdf = Pdf::loadView($view, $data)->setPaper('a4');

        return $inline ? $pdf->stream($name) : $pdf->download($name);
    }
}
