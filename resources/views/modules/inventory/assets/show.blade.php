@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">

    <x-cb.hero :title="$asset->name" icon="ri-macbook-line" :subtitle="$asset->tag . ($asset->serial_number ? ' · S/N ' . $asset->serial_number : '')" :back="route('inventory.assets.index')" back-label="Assets">
        <x-slot:actions>
            @can('Manage inventory')<a href="{{ route('inventory.assets.edit', $asset) }}" class="cb-hero-btn"><i class="ri-pencil-line"></i>Edit</a>@endcan
        </x-slot:actions>
    </x-cb.hero>

    @include('partials.flash')

    <div class="row g-3">
        <div class="col-xl-8">
            <x-cb.card>
                <div class="d-flex justify-content-between flex-wrap gap-2 mb-3">
                    <div>
                        @if($asset->holder)
                            <div class="text-muted small">With</div>
                            <div class="fw-semibold">{{ $asset->holder->name }}</div>
                            <div class="small text-muted">since {{ $asset->assigned_at?->format('j M Y') }}</div>
                        @else
                            <div class="text-muted small">Kept at</div>
                            <div class="fw-semibold">{{ $asset->location?->name ?? '—' }}</div>
                        @endif
                    </div>
                    <div>@include('modules.inventory._pill', ['value' => $asset->status, 'kind' => 'asset'])</div>
                </div>
                <dl class="row mb-0 small">
                    <dt class="col-sm-3 text-muted fw-normal">Category</dt><dd class="col-sm-9">{{ $asset->category?->name ?? '—' }}</dd>
                    <dt class="col-sm-3 text-muted fw-normal">Make and model</dt><dd class="col-sm-9">{{ $asset->make_model ?? '—' }}</dd>
                    <dt class="col-sm-3 text-muted fw-normal">Condition</dt><dd class="col-sm-9">{{ \App\Models\Inventory\Asset::CONDITIONS[$asset->condition] ?? $asset->condition }}</dd>
                    <dt class="col-sm-3 text-muted fw-normal">Bought</dt><dd class="col-sm-9">{{ $asset->purchase_date?->format('j M Y') ?? '—' }}@if($asset->supplier) from <a href="{{ route('inventory.suppliers.show', $asset->supplier) }}">{{ $asset->supplier->name }}</a>@endif @if($asset->purchase_cost) for {{ \App\Support\Money::format($asset->purchase_cost) }}@endif</dd>
                    <dt class="col-sm-3 text-muted fw-normal">Warranty</dt><dd class="col-sm-9 {{ $asset->warrantyEndingSoon() ? 'text-warning fw-semibold' : '' }}">{{ $asset->warranty_until ? 'until ' . $asset->warranty_until->format('j M Y') . ($asset->warranty_until->isPast() ? ' (ended)' : '') : '—' }}</dd>
                </dl>
                @if($asset->notes)<div class="small text-muted mt-3" style="white-space: pre-line">{{ $asset->notes }}</div>@endif
            </x-cb.card>

            <x-cb.card title="History" icon="ri-history-line" :flush="true">
                <ul class="list-group list-group-flush">
                    @foreach($asset->events as $e)
                        <li class="list-group-item small">
                            <span class="fw-semibold">{{ $e->label }}</span>@if($e->user) {{ $e->user->name }}@endif
                            @if($e->note)<span class="text-muted">: {{ $e->note }}</span>@endif
                            <div class="text-muted">{{ $e->created_at->format('j M Y H:i') }} · {{ $e->creator?->name }}</div>
                        </li>
                    @endforeach
                </ul>
            </x-cb.card>
        </div>

        <div class="col-xl-4">
            @can('Manage inventory')
                @php $act = route('inventory.assets.action', $asset); @endphp
                @if($asset->status === 'in_store')
                    <x-cb.card title="Hand over" icon="ri-user-shared-line">
                        <form method="POST" action="{{ $act }}">
                            @csrf<input type="hidden" name="do" value="assign">
                            <select name="user_id" class="form-select mb-2" required><option value="">To…</option>@foreach($staff as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach</select>
                            <input name="note" class="form-control mb-2" maxlength="255" placeholder="Note (optional), e.g. with charger">
                            <button class="btn btn-primary w-100">Hand over</button>
                        </form>
                    </x-cb.card>
                @endif
                @if($asset->status === 'assigned')
                    <x-cb.card title="Return to store" icon="ri-arrow-go-back-line">
                        <form method="POST" action="{{ $act }}">
                            @csrf<input type="hidden" name="do" value="return">
                            <select name="condition" class="form-select mb-2">@foreach(\App\Models\Inventory\Asset::CONDITIONS as $k => $c)<option value="{{ $k }}" @selected($asset->condition === $k)>Condition: {{ $c }}</option>@endforeach</select>
                            <input name="note" class="form-control mb-2" maxlength="255" placeholder="Note (optional)">
                            <button class="btn btn-primary w-100">Returned</button>
                        </form>
                    </x-cb.card>
                @endif
                @if(!in_array($asset->status, ['retired', 'lost'], true))
                    <x-cb.card title="Other" icon="ri-tools-line">
                        <form method="POST" action="{{ $act }}">
                            @csrf
                            <input name="note" class="form-control mb-2" maxlength="255" placeholder="What happened">
                            <div class="d-grid gap-2">
                                @if($asset->status === 'repair')
                                    <button name="do" value="repaired" class="btn btn-soft-success">Back from repair</button>
                                @else
                                    <button name="do" value="repair" class="btn btn-soft-warning">Send for repair</button>
                                @endif
                                <button name="do" value="note" class="btn btn-soft-secondary">Add note</button>
                                <button name="do" value="retire" class="btn btn-soft-dark" onclick="return confirm('Retire this asset? It leaves the register.')">Retire (sold, scrapped)</button>
                                <button name="do" value="lost" class="btn btn-soft-danger" onclick="return confirm('Record this asset as lost?')">Report lost</button>
                            </div>
                        </form>
                    </x-cb.card>
                @endif
            @endcan
        </div>
    </div>

</div></div></div>
@endsection
