{{-- The invoice itself (lines, totals, bank details). Used by staff and by the customer. --}}
@php $m = fn ($v) => \App\Support\Money::format($v, $invoice->currency); @endphp
<div class="d-flex justify-content-between flex-wrap gap-3 mb-3">
    <div>
        <div class="text-muted small">Bill to</div>
        <div class="fw-semibold">{{ $invoice->user->name }}</div>
        <div class="small text-muted">{{ $invoice->user->email }}@if($invoice->user->phone_number) · {{ $invoice->user->phone_number }}@endif</div>
    </div>
    <div class="text-md-end">
        <div><span class="text-muted">Invoice</span> <strong>{{ $invoice->number }}</strong></div>
        <div><span class="text-muted">Issued</span> {{ $invoice->issue_date->format('j M Y') }}</div>
        @if($invoice->due_date)<div><span class="text-muted">Due</span> {{ $invoice->due_date->format('j M Y') }}</div>@endif
        <div class="mt-1">@include('modules.finance.pill', ['status' => $invoice->isOverdue() ? 'overdue' : $invoice->status, 'label' => $invoice->status_label])</div>
    </div>
</div>
<div class="table-responsive"><table class="cb-table">
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
</table></div>
<div class="d-flex justify-content-end mt-3">
    <table class="table table-sm w-auto mb-0">
        <tr><td class="text-muted">Subtotal</td><td class="text-end">{{ $m($invoice->subtotal) }}</td></tr>
        @if((float) $invoice->discount_total)<tr><td class="text-muted">Discount</td><td class="text-end">− {{ $m($invoice->discount_total) }}</td></tr>@endif
        <tr><td class="fw-semibold">Total</td><td class="text-end fw-semibold">{{ $m($invoice->total) }}</td></tr>
        <tr><td class="text-muted">Paid</td><td class="text-end">{{ $m($invoice->amount_paid) }}</td></tr>
        <tr><td class="fw-bold">Balance</td><td class="text-end fw-bold fs-5">{{ $m($invoice->balance()) }}</td></tr>
    </table>
</div>
@if($invoice->notes)<hr><div class="small" style="white-space: pre-line">{{ $invoice->notes }}</div>@endif
@if($invoice->isOpen() && config('qudroid.finance.bank_details'))
    <hr><div class="small"><strong>Pay by bank transfer:</strong> {{ config('qudroid.finance.bank_details') }}. Use <strong>{{ $invoice->number }}</strong> as the reference.</div>
@endif
@if($invoice->status === 'void')
    <div class="cb-banner danger mt-3 mb-0"><i class="ri-close-circle-line"></i><div>Voided {{ $invoice->voided_at?->format('j M Y') }}@if($invoice->voider) by {{ $invoice->voider->name }}@endif: {{ $invoice->void_reason }}</div></div>
@endif
