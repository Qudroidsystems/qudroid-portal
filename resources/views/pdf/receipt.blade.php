@extends('pdf._layout')
@php $m = fn ($v) => \App\Support\Money::format($v, $payment->currency); @endphp
@section('title', 'Receipt ' . $payment->number)
@section('doc', 'RECEIPT')
@section('meta')
    <table class="meta" style="margin-top: 8px">
        <tr><td class="muted">Number</td><td class="num"><strong>{{ $payment->number }}</strong></td></tr>
        <tr><td class="muted">Date</td><td class="num">{{ ($payment->paid_at ?? $payment->confirmed_at ?? $payment->created_at)->format('j M Y') }}</td></tr>
        <tr><td class="muted">Method</td><td class="num">{{ $payment->method_label }}</td></tr>
        @if($payment->reference)<tr><td class="muted">Reference</td><td class="num">{{ $payment->reference }}</td></tr>@endif
    </table>
@endsection
@if($payment->status === 'reversed')@section('stamp', 'REVERSED')@endif

@section('body')
    <div style="margin-top: 18px">
        <div class="muted" style="font-size: 10px; text-transform: uppercase">Received from</div>
        <div style="font-size: 13px; font-weight: bold">{{ $payment->user->name }}</div>
        <div class="muted">{{ $payment->user->email }}</div>
    </div>

    <div class="box" style="text-align: center">
        <div class="muted">Amount received</div>
        <div style="font-size: 24px; font-weight: bold; margin-top: 4px">{{ $m($payment->amount) }}</div>
    </div>

    @if($payment->allocations->isNotEmpty())
        <table class="lines">
            <thead><tr><th>Applied to invoice</th><th>For</th><th class="num">Amount</th><th class="num">Balance after</th></tr></thead>
            <tbody>
            @foreach($payment->allocations as $a)
                <tr>
                    <td>{{ $a->invoice->number }}</td>
                    <td>{{ $a->invoice->title }}</td>
                    <td class="num">{{ $m($a->amount) }}</td>
                    <td class="num">{{ \App\Support\Money::format($a->invoice->balance(), $a->invoice->currency) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif

    @if($payment->status === 'reversed')
        <div class="box" style="border-color: #fecaca; background: #fef2f2">This payment was reversed on {{ $payment->reversed_at?->format('j M Y') }}: {{ $payment->reversal_reason }}. This receipt is no longer valid.</div>
    @else
        <p class="muted" style="margin-top: 18px">Thank you for your payment.</p>
    @endif
@endsection
