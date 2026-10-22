<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Finance\Invoice;
use App\Models\Finance\Payment;
use App\Models\User;
use App\Services\Finance\InvoiceService;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function __construct(protected InvoiceService $service) {}

    public function index(Request $request)
    {
        $invoices = Invoice::with('user')
            ->when($request->status === 'overdue', fn ($q) => $q->overdue())
            ->when($request->filled('status') && $request->status !== 'overdue', fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%' . $request->q . '%';
                $q->where(fn ($w) => $w->where('number', 'like', $term)->orWhere('title', 'like', $term)
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', $term)->orWhere('email', 'like', $term)));
            })
            ->latest('id')->paginate(25)->withQueryString();

        return view('modules.finance.invoices.index', compact('invoices'));
    }

    public function create(Request $request)
    {
        return view('modules.finance.invoices.create', [
            'people'   => User::where('is_disabled', false)->orderBy('name')->get(['id', 'name', 'email', 'user_type']),
            'selected' => $request->integer('user') ?: null,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'user_id'              => ['required', 'exists:users,id'],
            'title'                => ['required', 'string', 'max:200'],
            'due_date'             => ['nullable', 'date', 'after_or_equal:today'],
            'currency'             => ['required', 'string', 'size:3'],
            'notes'                => ['nullable', 'string', 'max:5000'],
            'lines'                => ['required', 'array', 'min:1', 'max:50'],
            'lines.*.description'  => ['nullable', 'string', 'max:255'],
            'lines.*.quantity'     => ['nullable', 'numeric', 'min:0.01', 'max:100000'],
            'lines.*.unit_price'   => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'lines.*.discount'     => ['nullable', 'numeric', 'min:0'],
        ]);

        $invoice = $this->service->issue(User::findOrFail($data['user_id']), $data['title'], $data['lines'], $request->user(), [
            'due_date' => $data['due_date'] ?? null, 'currency' => $data['currency'], 'notes' => $data['notes'] ?? null,
        ]);

        return redirect()->route('finance.invoices.show', $invoice)->with('success', "Invoice {$invoice->number} issued.");
    }

    public function show(Invoice $invoice)
    {
        $invoice->load('user', 'items', 'creator', 'voider', 'enrollment.course', 'enrollment.cohort');
        $payments = Payment::with('recorder', 'confirmer', 'reverser')
            ->where(fn ($q) => $q->where('invoice_id', $invoice->id)->orWhereHas('allocations', fn ($a) => $a->where('invoice_id', $invoice->id)))
            ->latest('id')->get();

        return view('modules.finance.invoices.show', compact('invoice', 'payments'));
    }

    public function void(Request $request, Invoice $invoice)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);
        $this->service->void($invoice, $request->user(), $data['reason']);

        return back()->with('success', 'Invoice voided.');
    }
}
