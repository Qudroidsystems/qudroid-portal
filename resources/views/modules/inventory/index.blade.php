@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">

    @php $qty = fn ($n) => \App\Models\Inventory\Item::qty($n); @endphp
    <x-cb.hero title="Inventory & Assets" icon="ri-archive-line" subtitle="Stock in the store, what's on order, and who has which equipment.">
        <x-slot:actions>
            @can('Manage inventory')
                <a href="{{ route('inventory.orders.create') }}" class="cb-hero-btn"><i class="ri-shopping-cart-2-line"></i>New order</a>
                <a href="{{ route('inventory.assets.create') }}" class="cb-hero-btn"><i class="ri-macbook-line"></i>Add asset</a>
            @endcan
        </x-slot:actions>
    </x-cb.hero>

    @include('partials.flash')

    <div class="row g-3 mb-3">
        <div class="col-xl-3 col-md-6"><x-cb.stat label="Stock value" :value="\App\Support\Money::format(\App\Support\Money::major($stockValue))" icon="ri-archive-line" accent="teal" :hint="$itemCount . ' items, at average cost'" /></div>
        <div class="col-xl-3 col-md-6"><x-cb.stat label="Low stock" :value="$low->count()" icon="ri-alarm-warning-line" :accent="$low->isEmpty() ? 'green' : 'rose'" hint="at or below reorder level" /></div>
        <div class="col-xl-3 col-md-6"><x-cb.stat label="Open orders" :value="$openOrders->count()" icon="ri-truck-line" accent="amber" /></div>
        <div class="col-xl-3 col-md-6"><x-cb.stat label="Assets in use" :value="$assetCounts['assigned'] ?? 0" icon="ri-macbook-line" accent="sky" :hint="($assetCounts['in_store'] ?? 0) . ' in store, ' . ($assetCounts['repair'] ?? 0) . ' in repair'" /></div>
    </div>

    <div class="row g-3">
        <div class="col-xl-6">
            <x-cb.card title="Running low" icon="ri-alarm-warning-line" :count="$low->count()" :flush="true">
                <x-slot:tools><a href="{{ route('inventory.items.index', ['show' => 'low']) }}" class="btn btn-sm btn-soft-primary">All</a></x-slot:tools>
                @if($low->isEmpty())
                    <div class="empty-state"><i class="ri-checkbox-circle-line"></i><h6>Everything is above its reorder level</h6></div>
                @else
                    <ul class="list-group list-group-flush">
                        @foreach($low->take(8) as $i)
                            <li class="list-group-item d-flex justify-content-between align-items-center gap-2 small">
                                <span><a href="{{ route('inventory.items.show', $i) }}" class="fw-semibold">{{ $i->name }}</a> <span class="text-muted">· {{ $qty($i->onHand()) }} {{ $i->unit }} left, reorder at {{ $qty($i->reorder_level) }}</span></span>
                                @can('Manage inventory')<a href="{{ route('inventory.orders.create', ['item' => $i->sku]) }}" class="btn btn-sm btn-soft-primary text-nowrap">Order</a>@endcan
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-cb.card>

            <x-cb.card title="On order" icon="ri-truck-line" :flush="true">
                <x-slot:tools><a href="{{ route('inventory.orders.index') }}" class="btn btn-sm btn-soft-primary">All</a></x-slot:tools>
                @if($openOrders->isEmpty())
                    <div class="empty-state"><i class="ri-truck-line"></i><h6>Nothing on order</h6></div>
                @else
                    <ul class="list-group list-group-flush">
                        @foreach($openOrders as $o)
                            <li class="list-group-item d-flex justify-content-between small">
                                <span><a href="{{ route('inventory.orders.show', $o) }}" class="fw-semibold">{{ $o->number }}</a> · {{ $o->supplier->name }}</span>
                                <span class="{{ $o->isLate() ? 'text-danger fw-semibold' : 'text-muted' }}">{{ $o->expected_on ? 'due ' . $o->expected_on->format('j M') : '' }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-cb.card>
        </div>
        <div class="col-xl-6">
            <x-cb.card title="Latest stock movements" icon="ri-arrow-left-right-line" :flush="true">
                <x-slot:tools><a href="{{ route('inventory.movements') }}" class="btn btn-sm btn-soft-primary">All</a></x-slot:tools>
                @if($recent->isEmpty())
                    <div class="empty-state"><i class="ri-arrow-left-right-line"></i><h6>No stock movements yet</h6></div>
                @else
                    <ul class="list-group list-group-flush">
                        @foreach($recent as $m)
                            <li class="list-group-item small d-flex justify-content-between gap-2">
                                <span>@include('modules.inventory._pill', ['value' => $m->type, 'kind' => 'move']) <a href="{{ route('inventory.items.show', $m->item) }}">{{ $m->item->name }}</a>
                                    <span class="{{ $m->quantity < 0 ? 'text-danger' : 'text-success' }}">{{ $m->quantity < 0 ? '−' : '+' }}{{ $qty(abs($m->quantity)) }}</span>
                                    @if($m->recipient)<span class="text-muted">to {{ $m->recipient->name }}</span>@endif</span>
                                <span class="text-muted text-nowrap">{{ $m->created_at->diffForHumans() }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-cb.card>

            @if($warranty->isNotEmpty())
                <x-cb.card title="Warranties ending soon" icon="ri-shield-check-line" :flush="true">
                    <ul class="list-group list-group-flush">
                        @foreach($warranty as $a)
                            <li class="list-group-item small d-flex justify-content-between"><a href="{{ route('inventory.assets.show', $a) }}">{{ $a->tag }} · {{ $a->name }}</a><span class="text-muted">{{ $a->warranty_until->format('j M Y') }}</span></li>
                        @endforeach
                    </ul>
                </x-cb.card>
            @endif
        </div>
    </div>

</div></div></div>
@endsection
