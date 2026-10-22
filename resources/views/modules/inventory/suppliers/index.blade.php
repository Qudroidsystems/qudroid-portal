@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">

    <x-cb.hero title="Suppliers" icon="ri-store-2-line" subtitle="Who Qudroid buys from." :back="route('inventory.index')" back-label="Inventory">
        <x-slot:actions>
            @can('Manage inventory')<a href="{{ route('inventory.suppliers.create') }}" class="cb-hero-btn"><i class="ri-add-line"></i>New supplier</a>@endcan
        </x-slot:actions>
    </x-cb.hero>

    @include('partials.flash')

    <x-cb.card title="Suppliers" icon="ri-store-2-line" :count="$suppliers->count()" :flush="true">
        <form class="cb-toolbar" method="GET">
            <div class="cb-search"><i class="ri-search-line"></i><input name="q" value="{{ request('q') }}" placeholder="Name or contact"></div>
            <select name="show" class="cb-select" onchange="this.form.submit()"><option value="">Active</option><option value="archived" @selected(request('show') === 'archived')>Archived</option></select>
        </form>
        @if($suppliers->isEmpty())
            <div class="empty-state"><i class="ri-store-2-line"></i><h6>No suppliers yet</h6></div>
        @else
            <div class="table-responsive"><table class="cb-table stack">
                <thead><tr><th>Supplier</th><th>Contact</th><th>Phone</th><th class="num">Open orders</th></tr></thead>
                <tbody>
                @foreach($suppliers as $s)
                    <tr>
                        <td data-label="Supplier"><a href="{{ route('inventory.suppliers.show', $s) }}" class="fw-semibold">{{ $s->name }}</a>@if($s->email)<div class="small text-muted">{{ $s->email }}</div>@endif</td>
                        <td data-label="Contact">{{ $s->contact_name ?? '—' }}</td>
                        <td data-label="Phone">{{ $s->phone ?? '—' }}</td>
                        <td data-label="Open orders" class="num">{{ $s->open_orders_count }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
        @endif
    </x-cb.card>

</div></div></div>
@endsection
