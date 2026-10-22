@extends('pdf._layout')
@php $m = fn ($v) => \App\Support\Money::format($v, $invoice->currency); @endphp
@section('title', 'Invoice ' . $invoice->number)
@section('doc', 'INVOICE')
@section('meta')
    <table class="meta" style="margin-top: 8px">
        <tr><td class="muted">Number</td><td class="num"><strong>{{ $invoice->number }}</strong></td></tr>
        <tr><td class="muted">Issued</td><td class="num">{{ $invoice->issue_date->format('j M Y') }}</td></tr>
        @if($invoice->due_date)<tr><td class="muted">Due</td><td class="num">{{ $invoice->due_date->format('j M Y') }}</td></tr>@endif
        <tr><td class="muted">Status</td><td class="num">{{ $invoice->status_label }}</td></tr>
    </table>
@endsection
@if($invoice->status === 'void')@section('stamp', 'VOID')@elseif($invoice->status === 'paid')@section('stamp', 'PAID')@endif

@section('body')
    <div style="margin-top: 18px">
        <div class="muted" style="font-size: 10px; text-transform: uppercase">Bill to</div>
        <div style="font-size: 13px; font-weight: bold">{{ $invoice->user->name }}</div>
        <div class="muted">{{ $invoice->user->email }}@if($invoice->user->phone_number) · {{ $invoice->user->phone_number }}@endif</div>
        @if($invoice->title)<div style="margin-top: 6px">{{ $invoice->title }}</div>@endif
    </div>

    <table class="lines">
        <thead><tr><th>Description</th><th class="num">Qty</th><th class="num">Unit price</th><th class="num">Discount</th><th class="num">Amount</th></tr></thead>
        <tbody>
        @foreach($invoice->items as $item)
            <tr>
                <td>{{ $item->description }}</td>
                <td class="num">{{ rtrim(rtrim($item->quantity, '0'), '.') }}</td>
                <td class="num">{{ $m($item->unit_price) }}</td>
                <td class="num">{{ (float) $item->discount ? $m($item->discount) : '—' }}</td>
                <td class="num">{{ $m($item->amount) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr><td class="muted">Subtotal</td><td class="num">{{ $m($invoice->subtotal) }}</td></tr>
        @if((float) $invoice->discount_total)<tr><td class="muted">Discount</td><td class="num">− {{ $m($invoice->discount_total) }}</td></tr>@endif
        <tr><td>Total</td><td class="num">{{ $m($invoice->total) }}</td></tr>
        <tr><td class="muted">Paid</td><td class="num">{{ $m($invoice->amount_paid) }}</td></tr>
        <tr class="grand"><td>Balance due</td><td class="num">{{ $m($invoice->balance()) }}</td></tr>
    </table>

    @if($invoice->notes)<div class="box" style="white-space: pre-line">{{ $invoice->notes }}</div>@endif
    @if($invoice->isOpen())
        <div class="box">
            <strong>How to pay</strong><br>
            Online: sign in to {{ $brand['name'] ?? 'the portal' }} and open My bills.<br>
            @if(config('qudroid.finance.bank_details'))Bank transfer: {{ config('qudroid.finance.bank_details') }}. Use <strong>{{ $invoice->number }}</strong> as the reference.@endif
        </div>
    @endif
    @if($invoice->status === 'void')
        <div class="box" style="border-color: #fecaca; background: #fef2f2">This invoice was voided on {{ $invoice->voided_at?->format('j M Y') }}: {{ $invoice->void_reason }}. Nothing is owed on it.</div>
    @endif
@endsection
