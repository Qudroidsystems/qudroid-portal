@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">

    <x-cb.hero :title="$supplier->name" icon="ri-store-2-line" :subtitle="$supplier->is_active ? null : 'Archived'" :back="route('inventory.suppliers.index')" back-label="Suppliers">
        <x-slot:actions>
            @can('Manage inventory')
                <a href="{{ route('inventory.orders.create', ['supplier' => $supplier->id]) }}" class="cb-hero-btn"><i class="ri-shopping-cart-2-line"></i>New order</a>
                <a href="{{ route('inventory.suppliers.edit', $supplier) }}" class="cb-hero-btn"><i class="ri-pencil-line"></i>Edit</a>
            @endcan
        </x-slot:actions>
    </x-cb.hero>

    @include('partials.flash')

    <div class="row g-3">
        <div class="col-xl-4">
            <x-cb.card>
                <dl class="row mb-0 small">
                    <dt class="col-4 text-muted fw-normal">Contact</dt><dd class="col-8">{{ $supplier->contact_name ?? '—' }}</dd>
                    <dt class="col-4 text-muted fw-normal">Email</dt><dd class="col-8">@if($supplier->email)<a href="mailto:{{ $supplier->email }}">{{ $supplier->email }}</a>@else — @endif</dd>
                    <dt class="col-4 text-muted fw-normal">Phone</dt><dd class="col-8">{{ $supplier->phone ?? '—' }}</dd>
                    <dt class="col-4 text-muted fw-normal">Address</dt><dd class="col-8">{{ $supplier->address ?? '—' }}</dd>
                    <dt class="col-4 text-muted fw-normal">Bank</dt><dd class="col-8">{{ $supplier->bank_details ?? '—' }}</dd>
                    <dt class="col-4 text-muted fw-normal">Bought</dt><dd class="col-8">{{ \App\Support\Money::format(\App\Support\Money::major($spent)) }}</dd>
                </dl>
                @if($supplier->notes)<div class="small text-muted mt-3" style="white-space: pre-line">{{ $supplier->notes }}</div>@endif
            </x-cb.card>
            @if($items->isNotEmpty())
                <x-cb.card title="Usually supplies" icon="ri-archive-line" :flush="true">
                    <ul class="list-group list-group-flush">@foreach($items as $i)<li class="list-group-item small"><a href="{{ route('inventory.items.show', $i) }}">{{ $i->name }}</a></li>@endforeach</ul>
                </x-cb.card>
            @endif
        </div>
        <div class="col-xl-8">
            <x-cb.card title="Purchase orders" icon="ri-shopping-cart-2-line" :count="$orders->count()" :flush="true">
                @if($orders->isEmpty())
                    <div class="empty-state"><i class="ri-shopping-cart-2-line"></i><h6>No orders yet</h6></div>
                @else
                    <div class="table-responsive"><table class="cb-table stack">
                        <thead><tr><th>Order</th><th>Date</th><th>Status</th><th class="num">Total</th></tr></thead>
                        <tbody>
                        @foreach($orders as $o)
                            <tr>
                                <td data-label="Order"><a href="{{ route('inventory.orders.show', $o) }}" class="fw-semibold">{{ $o->number }}</a></td>
                                <td data-label="Date">{{ ($o->order_date ?? $o->created_at)->format('j M Y') }}</td>
                                <td data-label="Status">@include('modules.inventory._pill', ['value' => $o->status])</td>
                                <td data-label="Total" class="num">{{ \App\Support\Money::format($o->total, $o->currency) }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table></div>
                @endif
            </x-cb.card>
            @if($assets->isNotEmpty())
                <x-cb.card title="Assets bought here" icon="ri-macbook-line" :flush="true">
                    <ul class="list-group list-group-flush">@foreach($assets as $a)<li class="list-group-item small"><a href="{{ route('inventory.assets.show', $a) }}">{{ $a->tag }} · {{ $a->name }}</a></li>@endforeach</ul>
                </x-cb.card>
            @endif
        </div>
    </div>

</div></div></div>
@endsection
