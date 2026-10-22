@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">

    <x-cb.hero title="Stock movements" icon="ri-arrow-left-right-line" subtitle="Every change to stock, newest first." :back="route('inventory.items.index')" back-label="Stock" />

    <x-cb.card title="Movements" icon="ri-arrow-left-right-line" :count="$movements->total()" :flush="true">
        <form class="cb-toolbar" method="GET">
            <select name="type" class="cb-select" onchange="this.form.submit()">
                <option value="">Any kind</option>
                @foreach(\App\Models\Inventory\Movement::TYPES as $k => $v)<option value="{{ $k }}" @selected(request('type') === $k)>{{ $v }}</option>@endforeach
            </select>
            <select name="location" class="cb-select" onchange="this.form.submit()">
                <option value="">Any location</option>
                @foreach($locations as $l)<option value="{{ $l->id }}" @selected((int) request('location') === $l->id)>{{ $l->name }}</option>@endforeach
            </select>
            <input type="date" name="from" value="{{ request('from') }}" class="cb-select" onchange="this.form.submit()" title="From">
            <input type="date" name="to" value="{{ request('to') }}" class="cb-select" onchange="this.form.submit()" title="To">
        </form>
        @if($movements->isEmpty())
            <div class="empty-state"><i class="ri-arrow-left-right-line"></i><h6>No movements found</h6></div>
        @else
            <div class="table-responsive"><table class="cb-table stack">
                <thead><tr><th>When</th><th>Item</th><th>What</th><th class="num">Qty</th><th>Location</th><th class="num">Balance</th><th>Details</th><th>By</th></tr></thead>
                <tbody>@foreach($movements as $mv)@include('modules.inventory._movement', ['mv' => $mv, 'showItem' => true])@endforeach</tbody>
            </table></div>
            <div class="p-3">{{ $movements->links() }}</div>
        @endif
    </x-cb.card>

</div></div></div>
@endsection
