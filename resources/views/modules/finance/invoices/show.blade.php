@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">

    <x-cb.hero :title="$invoice->number" icon="ri-file-list-3-line" :subtitle="$invoice->title" :back="route('finance.invoices.index')" back-label="Invoices">
        <x-slot:pills>
            <span class="cb-meta-pill">{{ $invoice->status_label }}</span>
            @if($invoice->enrollment)<a href="{{ route('academy.cohorts.show', $invoice->enrollment->cohort_id) }}" class="cb-meta-pill text-reset"><i class="ri-book-open-line me-1"></i>{{ $invoice->enrollment->course->title }}</a>@endif
            @if($invoice->creator)<span class="cb-meta-pill">Issued by {{ $invoice->creator->name }}</span>@endif
        </x-slot:pills>
        <x-slot:actions><button type="button" onclick="window.print()" class="cb-hero-btn"><i class="ri-printer-line"></i>Print</button></x-slot:actions>
    </x-cb.hero>

    @include('partials.flash')

    <div class="row g-3">
        <div class="col-xl-8">
            <x-cb.card>@include('modules.finance.invoices._document')</x-cb.card>

            <x-cb.card title="Payments" icon="ri-bank-line" :count="$payments->count()" :flush="true">
                @if($payments->isEmpty())
                    <div class="empty-state"><i class="ri-bank-line"></i><h6>No payments yet</h6></div>
                @else
                    <div class="table-responsive"><table class="cb-table stack">
                        <thead><tr><th>Receipt</th><th>Method</th><th>Date</th><th>Status</th><th class="num">Amount</th></tr></thead>
                        <tbody>
                        @foreach($payments as $p)
                            <tr>
                                <td data-label="Receipt"><a href="{{ route('finance.payments.show', $p) }}" class="fw-semibold">{{ $p->number ?? $p->reference }}</a>@if($p->recorder)<div class="small text-muted">recorded by {{ $p->recorder->name }}</div>@endif</td>
                                <td data-label="Method">{{ $p->method_label }}@if($p->reference && $p->number)<div class="small text-muted">{{ $p->reference }}</div>@endif</td>
                                <td data-label="Date">{{ ($p->paid_at ?? $p->created_at)->format('j M Y') }}</td>
                                <td data-label="Status">@include('modules.finance.pill', ['status' => $p->status, 'label' => $p->status_label])</td>
                                <td data-label="Amount" class="num">{{ \App\Support\Money::format($p->amount, $p->currency) }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table></div>
                @endif
            </x-cb.card>
        </div>
        <div class="col-xl-4">
            @if($invoice->isOpen())
                @can('Manage finance')
                    <x-cb.card title="Record a payment" icon="ri-hand-coin-line">
                        <form method="POST" action="{{ route('finance.payments.store', $invoice) }}">
                            @csrf
                            <div class="mb-2"><label class="form-label">Amount</label><input name="amount" type="number" step="0.01" min="0.01" max="{{ $invoice->balance() }}" class="form-control" required value="{{ old('amount', $invoice->balance()) }}"></div>
                            <div class="mb-2"><label class="form-label">Method</label>
                                <select name="method" class="form-select" required>@foreach(\App\Models\Finance\Payment::MANUAL_METHODS as $k)<option value="{{ $k }}" @selected(old('method') === $k)>{{ \App\Models\Finance\Payment::METHODS[$k] }}</option>@endforeach</select>
                            </div>
                            <div class="mb-2"><label class="form-label">Bank / teller reference</label><input name="reference" class="form-control" maxlength="100" value="{{ old('reference') }}"></div>
                            <div class="mb-2"><label class="form-label">Date received</label><input name="paid_at" type="date" class="form-control" required max="{{ today()->format('Y-m-d') }}" value="{{ old('paid_at', today()->format('Y-m-d')) }}"></div>
                            <div class="mb-3"><label class="form-label">Note</label><input name="notes" class="form-control" maxlength="2000" value="{{ old('notes') }}"></div>
                            <button class="btn btn-primary w-100">Record payment</button>
                            <div class="form-text">Only record money you have seen in the bank account or till. A receipt is issued at once.</div>
                        </form>
                    </x-cb.card>
                @endcan
            @endif
            @can('Approve finance')
                @if($invoice->status !== 'void')
                    <x-cb.card title="Corrections" icon="ri-error-warning-line">
                        @if((float) $invoice->amount_paid > 0)
                            <p class="small text-muted mb-0">Payments are applied to this invoice. To cancel it, reverse those payments first (open each receipt).</p>
                        @else
                            <form method="POST" action="{{ route('finance.invoices.void', $invoice) }}" onsubmit="return confirm('Void this invoice?')">
                                @csrf
                                <label class="form-label">Void this invoice</label>
                                <input name="reason" class="form-control mb-2" required maxlength="255" placeholder="Reason">
                                <button class="btn btn-soft-danger w-100">Void invoice</button>
                            </form>
                        @endif
                    </x-cb.card>
                @endif
            @endcan
        </div>
    </div>

</div></div></div>
@endsection
