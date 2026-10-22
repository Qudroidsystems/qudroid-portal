@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">

    <x-cb.hero title="Assets" icon="ri-macbook-line" subtitle="Equipment Qudroid owns and who has it." :back="route('inventory.index')" back-label="Inventory">
        <x-slot:actions>
            @can('Manage inventory')<a href="{{ route('inventory.assets.create') }}" class="cb-hero-btn"><i class="ri-add-line"></i>Add asset</a>@endcan
        </x-slot:actions>
    </x-cb.hero>

    @include('partials.flash')

    <x-cb.card title="Asset register" icon="ri-macbook-line" :count="$assets->total()" :flush="true">
        <form class="cb-toolbar" method="GET">
            <div class="cb-search"><i class="ri-search-line"></i><input name="q" value="{{ request('q') }}" placeholder="Tag, name, serial or holder"></div>
            <select name="status" class="cb-select" onchange="this.form.submit()">
                <option value="current" @selected($status === 'current')>In use or in store</option>
                @foreach(\App\Models\Inventory\Asset::STATUSES as $k => $v)<option value="{{ $k }}" @selected($status === $k)>{{ $v }} ({{ $counts[$k] ?? 0 }})</option>@endforeach
                <option value="all" @selected($status === 'all')>All</option>
            </select>
            <select name="category" class="cb-select" onchange="this.form.submit()">
                <option value="">Any category</option>
                @foreach($categories as $c)<option value="{{ $c->id }}" @selected((int) request('category') === $c->id)>{{ $c->name }}</option>@endforeach
            </select>
        </form>
        @if($assets->isEmpty())
            <div class="empty-state"><i class="ri-macbook-line"></i><h6>No assets found</h6></div>
        @else
            <div class="table-responsive"><table class="cb-table stack">
                <thead><tr><th>Asset</th><th>Category</th><th>Serial</th><th>With</th><th>Status</th></tr></thead>
                <tbody>
                @foreach($assets as $a)
                    <tr>
                        <td data-label="Asset"><a href="{{ route('inventory.assets.show', $a) }}" class="fw-semibold">{{ $a->name }}</a><div class="small text-muted">{{ $a->tag }}@if($a->make_model) · {{ $a->make_model }}@endif</div></td>
                        <td data-label="Category">{{ $a->category?->name ?? '—' }}</td>
                        <td data-label="Serial" class="small">{{ $a->serial_number ?? '—' }}</td>
                        <td data-label="With">{{ $a->holder?->name ?? ($a->location?->name ?? '—') }}</td>
                        <td data-label="Status">@include('modules.inventory._pill', ['value' => $a->status, 'kind' => 'asset'])</td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
            <div class="p-3">{{ $assets->links() }}</div>
        @endif
    </x-cb.card>

</div></div></div>
@endsection
