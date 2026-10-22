@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">

    @php $open = $invoices->filter->isOpen(); @endphp
    <x-cb.hero title="My bills" icon="ri-wallet-3-line" subtitle="Your invoices and receipts." />

    @include('partials.flash')

    <x-cb.card title="Invoices" icon="ri-file-list-3-line" :count="$open->count() ?: null" :flush="true">
        @if($invoices->isEmpty())
            <div class="empty-state"><i class="ri-file-list-3-line"></i><h6>No invoices</h6><p>You don't owe anything.</p></div>
        @else
            <div class="table-responsive"><table class="cb-table stack">
                <thead><tr><th>Invoice</th><th>Due</th><th>Status</th><th class="num">Total</th><th class="num">Balance</th><th></th></tr></thead>
                <tbody>
                @foreach($invoices as $i)
                    <tr>
                        <td data-label="Invoice"><a href="{{ route('billing.invoice', $i) }}" class="fw-semibold">{{ $i->title }}</a><div class="small text-muted">{{ $i->number }}</div></td>
                        <td data-label="Due">{{ $i->due_date?->format('j M Y') ?? '—' }}</td>
                        <td data-label="Status">@include('modules.finance.pill', ['status' => $i->isOverdue() ? 'overdue' : $i->status, 'label' => $i->status_label])</td>
                        <td data-label="Total" class="num">{{ \App\Support\Money::format($i->total, $i->currency) }}</td>
                        <td data-label="Balance" class="num fw-semibold">{{ \App\Support\Money::format($i->balance(), $i->currency) }}</td>
                        <td class="text-end">@if($i->isOpen())<a href="{{ route('billing.invoice', $i) }}" class="btn btn-sm btn-primary">Pay</a>@endif</td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
        @endif
    </x-cb.card>

    <x-cb.card title="Receipts" icon="ri-receipt-line" :flush="true">
        @if($receipts->isEmpty())
            <div class="empty-state"><i class="ri-receipt-line"></i><h6>No receipts yet</h6></div>
        @else
            <div class="table-responsive"><table class="cb-table stack">
                <tbody>
                @foreach($receipts as $p)
                    <tr>
                        <td data-label="Receipt"><a href="{{ route('billing.receipt', $p) }}" class="fw-semibold">{{ $p->number }}</a> <a href="{{ route('billing.receipt.pdf', $p) }}" class="small ms-1" title="Download PDF"><i class="ri-file-pdf-2-line"></i> PDF</a><div class="small text-muted">{{ $p->method_label }} · {{ ($p->paid_at ?? $p->created_at)->format('j M Y') }}</div></td>
                        <td data-label="Status">@include('modules.finance.pill', ['status' => $p->status, 'label' => $p->status_label])</td>
                        <td data-label="Amount" class="num">{{ \App\Support\Money::format($p->amount, $p->currency) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
        @endif
    </x-cb.card>

</div></div></div>
@endsection
