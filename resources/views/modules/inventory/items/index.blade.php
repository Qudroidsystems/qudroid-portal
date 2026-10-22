@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">

    @php $qty = fn ($n) => \App\Models\Inventory\Item::qty($n); @endphp
    <x-cb.hero title="Stock" icon="ri-archive-line" subtitle="Consumables and supplies held in the store." :back="route('inventory.index')" back-label="Inventory">
        <x-slot:actions>
            <a href="{{ route('inventory.movements') }}" class="cb-hero-btn"><i class="ri-arrow-left-right-line"></i>Movements</a>
            @can('Manage inventory')<a href="{{ route('inventory.items.create') }}" class="cb-hero-btn"><i class="ri-add-line"></i>New item</a>@endcan
        </x-slot:actions>
    </x-cb.hero>

    @include('partials.flash')

    <x-cb.card title="Items" icon="ri-archive-line" :count="$items->count()" :flush="true">
        <form class="cb-toolbar" method="GET">
            <div class="cb-search"><i class="ri-search-line"></i><input name="q" value="{{ request('q') }}" placeholder="Name or SKU"></div>
            <select name="category" class="cb-select" onchange="this.form.submit()">
                <option value="">Any category</option>
                @foreach($categories as $c)<option value="{{ $c->id }}" @selected((int) request('category') === $c->id)>{{ $c->name }}</option>@endforeach
            </select>
            <select name="show" class="cb-select" onchange="this.form.submit()">
                <option value="">In use</option>
                <option value="low" @selected(request('show') === 'low')>Running low</option>
                <option value="archived" @selected(request('show') === 'archived')>Archived</option>
            </select>
        </form>
        @if($items->isEmpty())
            <div class="empty-state"><i class="ri-archive-line"></i><h6>No items found</h6></div>
        @else
            <div class="table-responsive"><table class="cb-table stack">
                <thead><tr><th>Item</th><th>Category</th><th class="num">On hand</th><th class="num">Reorder at</th><th class="num">Avg cost</th><th class="num">Value</th></tr></thead>
                <tbody>
                @foreach($items as $i)
                    <tr>
                        <td data-label="Item"><a href="{{ route('inventory.items.show', $i) }}" class="fw-semibold">{{ $i->name }}</a><div class="small text-muted">{{ $i->sku }}</div></td>
                        <td data-label="Category">{{ $i->category?->name ?? '—' }}</td>
                        <td data-label="On hand" class="num {{ $i->isLow() ? 'text-danger fw-semibold' : '' }}">{{ $qty($i->onHand()) }} <span class="text-muted small">{{ $i->unit }}</span></td>
                        <td data-label="Reorder at" class="num">{{ $i->reorder_level > 0 ? $qty($i->reorder_level) : '—' }}</td>
                        <td data-label="Avg cost" class="num">{{ \App\Support\Money::format($i->avg_cost) }}</td>
                        <td data-label="Value" class="num">{{ \App\Support\Money::format(\App\Support\Money::major($i->valueMinor())) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
        @endif
    </x-cb.card>

</div></div></div>
@endsection
