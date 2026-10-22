@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">

    <x-cb.hero title="Payments" icon="ri-bank-line" subtitle="Money received, with receipts. Online payments appear once Paystack confirms them." :back="route('finance.index')" back-label="Finance" />

    @include('partials.flash')

    <x-cb.card title="Payments" icon="ri-bank-line" :count="$payments->total()" :flush="true">
        <form class="cb-toolbar" method="GET">
            <div class="cb-search"><i class="ri-search-line"></i><input name="q" value="{{ request('q') }}" placeholder="Receipt, reference or payer"></div>
            <select name="method" class="cb-select" onchange="this.form.submit()">
                <option value="">Any method</option>
                @foreach(\App\Models\Finance\Payment::METHODS as $k => $v)<option value="{{ $k }}" @selected(request('method') === $k)>{{ $v }}</option>@endforeach
            </select>
            <select name="status" class="cb-select" onchange="this.form.submit()">
                <option value="">Any status (not failed)</option>
                @foreach(\App\Models\Finance\Payment::STATUSES as $k => $v)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $v }}</option>@endforeach
            </select>
        </form>
        @if($payments->isEmpty())
            <div class="empty-state"><i class="ri-bank-line"></i><h6>No payments found</h6></div>
        @else
            <div class="table-responsive"><table class="cb-table stack">
                <thead><tr><th>Receipt</th><th>Payer</th><th>Invoice</th><th>Method</th><th>Date</th><th>Status</th><th class="num">Amount</th></tr></thead>
                <tbody>
                @foreach($payments as $p)
                    <tr>
                        <td data-label="Receipt"><a href="{{ route('finance.payments.show', $p) }}" class="fw-semibold">{{ $p->number ?? '—' }}</a><div class="small text-muted">{{ $p->reference }}</div></td>
                        <td data-label="Payer">{{ $p->user->name }}</td>
                        <td data-label="Invoice">@if($p->invoice)<a href="{{ route('finance.invoices.show', $p->invoice) }}">{{ $p->invoice->number }}</a>@else — @endif</td>
                        <td data-label="Method">{{ $p->method_label }}</td>
                        <td data-label="Date">{{ ($p->paid_at ?? $p->created_at)->format('j M Y') }}</td>
                        <td data-label="Status">@include('modules.finance.pill', ['status' => $p->status, 'label' => $p->status_label])</td>
                        <td data-label="Amount" class="num">{{ \App\Support\Money::format($p->amount, $p->currency) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
            <div class="p-3">{{ $payments->links() }}</div>
        @endif
    </x-cb.card>

</div></div></div>
@endsection
