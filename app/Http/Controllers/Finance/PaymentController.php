<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Finance\Invoice;
use App\Models\Finance\Payment;
use App\Services\Finance\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    public function __construct(protected PaymentService $service) {}

    public function index(Request $request)
    {
        $payments = Payment::with('user', 'invoice')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status), fn ($q) => $q->where('status', '!=', 'failed'))
            ->when($request->filled('method'), fn ($q) => $q->where('method', $request->method))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%' . $request->q . '%';
                $q->where(fn ($w) => $w->where('number', 'like', $term)->orWhere('reference', 'like', $term)
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', $term)));
            })
            ->latest('id')->paginate(25)->withQueryString();

        return view('modules.finance.payments.index', compact('payments'));
    }

    public function show(Payment $payment)
    {
        $payment->load('user', 'allocations.invoice', 'recorder', 'confirmer', 'reverser', 'invoice');

        return view('modules.finance.payments.show', ['payment' => $payment, 'staffView' => true]);
    }

    /** Record a bank transfer, cash, POS or cheque payment against an invoice. */
    public function store(Request $request, Invoice $invoice)
    {
        $data = $request->validate([
            'amount'    => ['required', 'numeric', 'min:0.01'],
            'method'    => ['required', Rule::in(Payment::MANUAL_METHODS)],
            'reference' => ['nullable', 'string', 'max:100'],
            'paid_at'   => ['required', 'date', 'before_or_equal:now'],
            'notes'     => ['nullable', 'string', 'max:2000'],
        ]);
        $payment = $this->service->recordManual($invoice, $data, $request->user());

        return back()->with('success', "Payment recorded. Receipt {$payment->number}.");
    }

    public function reverse(Request $request, Payment $payment)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);
        $this->service->reverse($payment, $request->user(), $data['reason']);

        return back()->with('success', 'Payment reversed. The invoice balance has been updated.');
    }
}
