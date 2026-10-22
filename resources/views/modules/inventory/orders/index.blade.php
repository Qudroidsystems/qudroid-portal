@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">

    <x-cb.hero title="Purchase orders" icon="ri-shopping-cart-2-line" subtitle="Draft, place with the supplier, receive what arrives, then send the bill to Finance." :back="route('inventory.index')" back-label="Inventory">
        <x-slot:actions>
            @can('Manage inventory')<a href="{{ route('inventory.orders.create') }}" class="cb-hero-btn"><i class="ri-add-line"></i>New order</a>@endcan
        </x-slot:actions>
    </x-cb.hero>

    @include('partials.flash')

    <x-cb.card title="Orders" icon="ri-shopping-cart-2-line" :count="$orders->total()" :flush="true">
        <form class="cb-toolbar" method="GET">
            <select name="status" class="cb-select" onchange="this.form.submit()">
                <option value="open" @selected($status === 'open')>Open</option>
                @foreach(\App\Models\Inventory\PurchaseOrder::STATUSES as $k => $v)<option value="{{ $k }}" @selected($status === $k)>{{ $v }}</option>@endforeach
                <option value="all" @selected($status === 'all')>All</option>
            </select>
            <select name="supplier" class="cb-select" onchange="this.form.submit()">
                <option value="">Any supplier</option>
                @foreach($suppliers as $s)<option value="{{ $s->id }}" @selected((int) request('supplier') === $s->id)>{{ $s->name }}</option>@endforeach
            </select>
        </form>
        @if($orders->isEmpty())
            <div class="empty-state"><i class="ri-shopping-cart-2-line"></i><h6>No orders here</h6></div>
        @else
            <div class="table-responsive"><table class="cb-table stack">
                <thead><tr><th>Order</th><th>Supplier</th><th>Ordered</th><th>Expected</th><th>Status</th><th class="num">Total</th></tr></thead>
                <tbody>
                @foreach($orders as $o)
                    <tr>
                        <td data-label="Order"><a href="{{ route('inventory.orders.show', $o) }}" class="fw-semibold">{{ $o->number }}</a></td>
                        <td data-label="Supplier">{{ $o->supplier->name }}</td>
                        <td data-label="Ordered">{{ $o->order_date?->format('j M Y') ?? '—' }}</td>
                        <td data-label="Expected" class="{{ $o->isLate() ? 'text-danger fw-semibold' : '' }}">{{ $o->expected_on?->format('j M Y') ?? '—' }}</td>
                        <td data-label="Status">@include('modules.inventory._pill', ['value' => $o->status])@if($o->expense_id) <span class="badge bg-success-subtle text-success">billed</span>@endif</td>
                        <td data-label="Total" class="num">{{ \App\Support\Money::format($o->total, $o->currency) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
            <div class="p-3">{{ $orders->links() }}</div>
        @endif
    </x-cb.card>

</div></div></div>
@endsection
