@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">

    @php $m = fn ($v) => \App\Support\Money::format($v, $payment->currency); @endphp
    <x-cb.hero :title="$payment->number ? 'Receipt ' . $payment->number : 'Payment ' . $payment->reference" icon="ri-receipt-line"
               :subtitle="$m($payment->amount) . ' · ' . $payment->method_label"
               :back="$staffView ? route('finance.payments.index') : route('billing.index')" :back-label="$staffView ? 'Payments' : 'My bills'">
        <x-slot:actions>@if($payment->number)<a href="{{ $staffView ? route('finance.payments.pdf', $payment) : route('billing.receipt.pdf', $payment) }}" class="cb-hero-btn"><i class="ri-file-pdf-2-line"></i>Download PDF</a>@endif<button type="button" onclick="window.print()" class="cb-hero-btn"><i class="ri-printer-line"></i>Print</button></x-slot:actions>
    </x-cb.hero>

    @include('partials.flash')

    <div class="row g-3">
        <div class="col-xl-8">
            <x-cb.card>
                <div class="d-flex justify-content-between flex-wrap gap-3 mb-3">
                    <div>
                        <div class="text-muted small">Received from</div>
                        <div class="fw-semibold">{{ $payment->user->name }}</div>
                        <div class="small text-muted">{{ $payment->user->email }}</div>
                    </div>
                    <div class="text-md-end">
                        <div class="text-muted small">{{ config('qudroid.company') }}</div>
                        @if($payment->number)<div><span class="text-muted">Receipt</span> <strong>{{ $payment->number }}</strong></div>@endif
                        <div><span class="text-muted">Date</span> {{ ($payment->paid_at ?? $payment->created_at)->format('j M Y') }}</div>
                        <div class="mt-1">@include('modules.finance.pill', ['status' => $payment->status, 'label' => $payment->status_label])</div>
                    </div>
                </div>
                <div class="fs-2 fw-bold mb-1">{{ $m($payment->amount) }}</div>
                <div class="text-muted mb-3">{{ $payment->method_label }}@if($payment->reference) · ref {{ $payment->reference }}@endif</div>
                @if($payment->allocations->isNotEmpty())
                    <div class="table-responsive"><table class="cb-table">
                        <thead><tr><th>Applied to invoice</th><th class="num">Amount</th></tr></thead>
                        <tbody>
                        @foreach($payment->allocations as $a)
                            <tr>
                                <td><a href="{{ $staffView ? route('finance.invoices.show', $a->invoice) : route('billing.invoice', $a->invoice) }}">{{ $a->invoice->number }}</a> — {{ $a->invoice->title }}</td>
                                <td class="num">{{ $m($a->amount) }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table></div>
                @endif
                @if($payment->status === 'reversed')
                    <div class="cb-banner danger mt-3 mb-0"><i class="ri-arrow-go-back-line"></i><div>Reversed {{ $payment->reversed_at?->format('j M Y') }}@if($staffView && $payment->reverser) by {{ $payment->reverser->name }}@endif: {{ $payment->reversal_reason }}</div></div>
                @endif
            </x-cb.card>
        </div>
        @if($staffView)
            <div class="col-xl-4">
                <x-cb.card title="Record" icon="ri-history-line">
                    <dl class="row mb-0 small">
                        <dt class="col-5 text-muted fw-normal">Recorded by</dt><dd class="col-7">{{ $payment->recorder?->name ?? '—' }}</dd>
                        <dt class="col-5 text-muted fw-normal">Confirmed</dt><dd class="col-7">{{ $payment->confirmed_at?->format('j M Y H:i') ?? '—' }}@if($payment->confirmer) by {{ $payment->confirmer->name }}@elseif($payment->confirmed_at) by Paystack @endif</dd>
                        @if($payment->gateway_response)
                            <dt class="col-5 text-muted fw-normal">Paystack</dt><dd class="col-7">{{ $payment->gateway_response['channel'] ?? '' }} {{ $payment->gateway_response['gateway_response'] ?? '' }}</dd>
                        @endif
                        @if($payment->notes)<dt class="col-5 text-muted fw-normal">Note</dt><dd class="col-7">{{ $payment->notes }}</dd>@endif
                    </dl>
                </x-cb.card>
                @can('Approve finance')
                    @if($payment->status === 'confirmed')
                        <x-cb.card title="Reverse payment" icon="ri-arrow-go-back-line">
                            <form method="POST" action="{{ route('finance.payments.reverse', $payment) }}" onsubmit="return confirm('Reverse this payment? The invoice balance goes back up.')">
                                @csrf
                                <input name="reason" class="form-control mb-2" required maxlength="255" placeholder="Reason, e.g. transfer bounced">
                                <button class="btn btn-soft-danger w-100">Reverse</button>
                                <div class="form-text">The payment stays on record as reversed. Course access opened by it is locked again.</div>
                            </form>
                        </x-cb.card>
                    @endif
                @endcan
            </div>
        @endif
    </div>

</div></div></div>
@endsection
