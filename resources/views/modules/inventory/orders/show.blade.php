@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">

    @php
        $m = fn ($v) => \App\Support\Money::format($v, $po->currency);
        $qty = fn ($n) => \App\Models\Inventory\Item::qty($n);
        $manage = auth()->user()->can('Manage inventory');
        $receivedValue = $po->lines->sum(fn ($l) => (int) round($l->received * \App\Support\Money::minor($l->unit_cost)));
    @endphp
    <x-cb.hero :title="'Purchase order ' . $po->number" icon="ri-shopping-cart-2-line" :subtitle="$po->supplier->name . ' · ' . $m($po->total)" :back="route('inventory.orders.index')" back-label="Purchase orders">
        <x-slot:actions>
            @if($manage && $po->status === 'draft')
                <a href="{{ route('inventory.orders.edit', $po) }}" class="cb-hero-btn"><i class="ri-pencil-line"></i>Edit</a>
            @endif
        </x-slot:actions>
    </x-cb.hero>

    @include('partials.flash')

    <div class="row g-3">
        <div class="col-xl-8">
            <x-cb.card>
                <div class="d-flex justify-content-between flex-wrap gap-3 mb-3">
                    <div>
                        <div class="text-muted small">Supplier</div>
                        <a href="{{ route('inventory.suppliers.show', $po->supplier) }}" class="fw-semibold">{{ $po->supplier->name }}</a>
                        <div class="small text-muted">{{ $po->supplier->contact_name }}@if($po->supplier->phone) · {{ $po->supplier->phone }}@endif</div>
                    </div>
                    <div class="text-md-end small">
                        <div>@include('modules.inventory._pill', ['value' => $po->status])</div>
                        <div class="mt-1"><span class="text-muted">Ordered</span> {{ $po->order_date?->format('j M Y') ?? '—' }}</div>
                        <div class="{{ $po->isLate() ? 'text-danger fw-semibold' : '' }}"><span class="text-muted">Expected</span> {{ $po->expected_on?->format('j M Y') ?? '—' }}</div>
                        <div><span class="text-muted">Deliver to</span> {{ $po->location?->name ?? '—' }}</div>
                    </div>
                </div>

                @php $receiving = $manage && in_array($po->status, ['ordered', 'partial'], true); @endphp
                @if($receiving)<form method="POST" action="{{ route('inventory.orders.receive', $po) }}" id="receive-form">@csrf @endif
                <div class="table-responsive"><table class="cb-table">
                    <thead><tr><th>Item</th><th class="num">Ordered</th><th class="num">Price</th><th class="num">Amount</th><th class="num">Received</th>@if($receiving)<th style="width: 120px">Arrived now</th>@endif</tr></thead>
                    <tbody>
                    @foreach($po->lines as $l)
                        <tr>
                            <td>{{ $l->description }}@if($l->item)<div class="small"><a href="{{ route('inventory.items.show', $l->item) }}">{{ $l->item->sku }}</a></div>@else<div class="small text-muted">not stocked</div>@endif</td>
                            <td class="num">{{ $qty($l->quantity) }}</td>
                            <td class="num">{{ $m($l->unit_cost) }}</td>
                            <td class="num">{{ \App\Support\Money::format(\App\Support\Money::major($l->amountMinor()), $po->currency) }}</td>
                            <td class="num {{ $l->outstanding() <= 0 ? 'text-success' : '' }}">{{ $qty($l->received) }}</td>
                            @if($receiving)<td>@if($l->outstanding() > 0)<input type="number" step="0.01" min="0" max="{{ $l->outstanding() }}" name="received[{{ $l->id }}]" class="form-control form-control-sm" placeholder="{{ $qty($l->outstanding()) }}">@endif</td>@endif
                        </tr>
                    @endforeach
                    </tbody>
                    <tfoot><tr><td colspan="3" class="text-end fw-semibold">Total</td><td class="num fw-bold">{{ $m($po->total) }}</td><td colspan="{{ $receiving ? 2 : 1 }}"></td></tr></tfoot>
                </table></div>
                @if($receiving)
                    <div class="row g-2 mt-2 align-items-end">
                        <div class="col-md-4"><label class="form-label small">Into</label><select name="location_id" class="form-select">@foreach($locations as $loc)<option value="{{ $loc->id }}" @selected($po->location_id === $loc->id)>{{ $loc->name }}</option>@endforeach</select></div>
                        <div class="col-md-4"><label class="form-label small">Delivery note no.</label><input name="reference" class="form-control" maxlength="100"></div>
                        <div class="col-md-4"><button class="btn btn-success w-100"><i class="ri-truck-line"></i> Record delivery</button></div>
                    </div>
                    </form>
                @endif
                @if($po->notes)<div class="small text-muted mt-3" style="white-space: pre-line">{{ $po->notes }}</div>@endif
            </x-cb.card>
        </div>

        <div class="col-xl-4">
            <x-cb.card title="Record" icon="ri-history-line">
                <dl class="row mb-0 small">
                    <dt class="col-5 text-muted fw-normal">Created by</dt><dd class="col-7">{{ $po->creator?->name ?? '—' }}</dd>
                    <dt class="col-5 text-muted fw-normal">Created</dt><dd class="col-7">{{ $po->created_at->format('j M Y') }}</dd>
                    <dt class="col-5 text-muted fw-normal">Received value</dt><dd class="col-7">{{ \App\Support\Money::format(\App\Support\Money::major($receivedValue), $po->currency) }}</dd>
                    @if($po->expense)
                        <dt class="col-5 text-muted fw-normal">Finance</dt>
                        <dd class="col-7">@if(Route::has('finance.expenses.show') && auth()->user()->can('View finance'))<a href="{{ route('finance.expenses.show', $po->expense) }}">{{ $po->expense->number }}</a>@else{{ $po->expense->number }}@endif · {{ strtolower($po->expense->status_label) }}</dd>
                    @endif
                </dl>
            </x-cb.card>

            @if($manage)
                @if($po->status === 'draft')
                    <x-cb.card title="Place the order" icon="ri-send-plane-line">
                        <form method="POST" action="{{ route('inventory.orders.place', $po) }}">@csrf<button class="btn btn-primary w-100">Mark as ordered</button></form>
                        <div class="form-text">Do this once you've sent the order to the supplier.</div>
                    </x-cb.card>
                @endif
                @if(in_array($po->status, ['partial', 'received'], true) && !$po->expense_id)
                    <x-cb.card title="Send the bill to Finance" icon="ri-bill-line">
                        <form method="POST" action="{{ route('inventory.orders.bill', $po) }}" enctype="multipart/form-data">
                            @csrf
                            <label class="form-label">Expense category</label>
                            <select name="account_id" class="form-select mb-2" required>@foreach($expenseAccounts as $a)<option value="{{ $a->id }}">{{ $a->label }}</option>@endforeach</select>
                            <label class="form-label">Supplier's invoice (optional)</label>
                            <input type="file" name="invoice" class="form-control mb-2" accept=".pdf,.jpg,.jpeg,.png">
                            <button class="btn btn-primary w-100">Send {{ \App\Support\Money::format(\App\Support\Money::major($receivedValue), $po->currency) }} to Finance</button>
                            <div class="form-text">Creates an expense for Finance to approve and pay.</div>
                        </form>
                    </x-cb.card>
                @endif
                @if(in_array($po->status, ['draft', 'ordered'], true))
                    <form method="POST" action="{{ route('inventory.orders.cancel', $po) }}" onsubmit="return confirm('Cancel {{ $po->number }}?')">@csrf<button class="btn btn-soft-danger w-100">Cancel order</button></form>
                @endif
            @endif
        </div>
    </div>

</div></div></div>
@endsection
