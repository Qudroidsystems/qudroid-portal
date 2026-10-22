@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">

    <x-cb.hero :title="$invoice->title" icon="ri-file-list-3-line" :subtitle="$invoice->number" :back="route('billing.index')" back-label="My bills">
        <x-slot:actions><button type="button" onclick="window.print()" class="cb-hero-btn"><i class="ri-printer-line"></i>Print</button></x-slot:actions>
    </x-cb.hero>

    @include('partials.flash')

    <div class="row g-3">
        <div class="col-xl-8">
            <x-cb.card>@include('modules.finance.invoices._document')</x-cb.card>
            @if($receipts->isNotEmpty())
                <x-cb.card title="Receipts" icon="ri-receipt-line" :flush="true">
                    <div class="list-group list-group-flush">
                        @foreach($receipts as $p)
                            <a href="{{ route('billing.receipt', $p) }}" class="list-group-item list-group-item-action d-flex justify-content-between">
                                <span>{{ $p->number }} <span class="small text-muted">· {{ $p->method_label }} · {{ ($p->paid_at ?? $p->created_at)->format('j M Y') }}</span></span>
                                <span>{{ \App\Support\Money::format($p->amount, $p->currency) }} @if($p->status === 'reversed')<span class="badge bg-dark-subtle text-dark ms-1">Reversed</span>@endif</span>
                            </a>
                        @endforeach
                    </div>
                </x-cb.card>
            @endif
        </div>
        <div class="col-xl-4">
            @if($invoice->isOpen())
                <x-cb.card title="Pay now" icon="ri-bank-card-line">
                    @if($online)
                        <form method="POST" action="{{ route('billing.pay', $invoice) }}">
                            @csrf
                            <label class="form-label">Amount</label>
                            <input name="amount" type="number" step="0.01" min="{{ $minimum }}" max="{{ $invoice->balance() }}" class="form-control mb-2" required value="{{ old('amount', $invoice->balance()) }}" @readonly($minimum === $invoice->balance())>
                            @if($minimum !== $invoice->balance())<div class="form-text mb-2">You can pay in instalments. The least you can pay now is {{ \App\Support\Money::format($minimum, $invoice->currency) }}.</div>@endif
                            <button class="btn btn-primary w-100"><i class="ri-secure-payment-line me-1"></i>Pay with card or bank (Paystack)</button>
                            <div class="form-text">You'll be taken to Paystack's secure page and brought back here.</div>
                        </form>
                    @else
                        <p class="mb-0">Online payment is not available yet. Please pay by bank transfer using <strong>{{ $invoice->number }}</strong> as the reference, then send your receipt to the finance office.</p>
                    @endif
                </x-cb.card>
            @endif
        </div>
    </div>

</div></div></div>
@endsection
