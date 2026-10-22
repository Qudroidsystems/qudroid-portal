@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">

    @php $qty = fn ($n) => \App\Models\Inventory\Item::qty($n); $onHand = $stock->sum('quantity'); @endphp
    <x-cb.hero :title="$item->name" icon="ri-archive-line" :subtitle="$item->sku . ($item->category ? ' · ' . $item->category->name : '') . ($item->is_active ? '' : ' · archived')" :back="route('inventory.items.index')" back-label="Stock">
        <x-slot:actions>
            @can('Manage inventory')
                <a href="{{ route('inventory.orders.create', ['item' => $item->sku]) }}" class="cb-hero-btn"><i class="ri-shopping-cart-2-line"></i>Order more</a>
                <a href="{{ route('inventory.items.edit', $item) }}" class="cb-hero-btn"><i class="ri-pencil-line"></i>Edit</a>
            @endcan
        </x-slot:actions>
    </x-cb.hero>

    @include('partials.flash')

    <div class="row g-3 mb-3">
        <div class="col-xl-3 col-md-6"><x-cb.stat label="On hand" :value="$qty($onHand) . ' ' . $item->unit" icon="ri-archive-line" :accent="$item->reorder_level > 0 && $onHand <= $item->reorder_level ? 'rose' : 'teal'" :hint="$item->reorder_level > 0 ? 'reorder at ' . $qty($item->reorder_level) : null" /></div>
        <div class="col-xl-3 col-md-6"><x-cb.stat label="Average cost" :value="\App\Support\Money::format($item->avg_cost)" icon="ri-price-tag-3-line" accent="sky" :hint="'per ' . $item->unit" /></div>
        <div class="col-xl-3 col-md-6"><x-cb.stat label="Value" :value="\App\Support\Money::format(\App\Support\Money::major((int) round($onHand * \App\Support\Money::minor($item->avg_cost))))" icon="ri-wallet-3-line" accent="green" /></div>
        <div class="col-xl-3 col-md-6"><x-cb.stat label="Usual supplier" :value="$item->supplier?->name ?? '—'" icon="ri-store-2-line" accent="amber" /></div>
    </div>

    <div class="row g-3">
        <div class="col-xl-8">
            <x-cb.card title="By location" icon="ri-map-pin-line" :flush="true">
                @if($stock->isEmpty())
                    <div class="p-3 small text-muted">None in stock yet.</div>
                @else
                    <ul class="list-group list-group-flush">
                        @foreach($stock as $s)<li class="list-group-item d-flex justify-content-between small"><span>{{ $s->location->name }}</span><span class="fw-semibold">{{ $qty($s->quantity) }} {{ $item->unit }}</span></li>@endforeach
                    </ul>
                @endif
            </x-cb.card>

            <x-cb.card title="History" icon="ri-history-line" :count="$movements->total()" :flush="true">
                @if($movements->isEmpty())
                    <div class="empty-state"><i class="ri-history-line"></i><h6>No movements yet</h6></div>
                @else
                    <div class="table-responsive"><table class="cb-table stack">
                        <thead><tr><th>When</th><th>What</th><th class="num">Qty</th><th>Location</th><th class="num">Balance</th><th>Details</th><th>By</th></tr></thead>
                        <tbody>@foreach($movements as $mv)@include('modules.inventory._movement', ['mv' => $mv])@endforeach</tbody>
                    </table></div>
                    <div class="p-3">{{ $movements->links() }}</div>
                @endif
            </x-cb.card>
        </div>

        <div class="col-xl-4">
            @can('Manage inventory')
                @php $locOpts = $locations->map(fn ($l) => '<option value="' . $l->id . '">' . e($l->name) . '</option>')->implode(''); @endphp
                <x-cb.card title="Update stock" icon="ri-exchange-box-line">
                    <ul class="nav nav-pills nav-justified mb-3 small" role="tablist">
                        <li class="nav-item"><a class="nav-link active py-1" data-bs-toggle="tab" href="#t-issue">Issue</a></li>
                        <li class="nav-item"><a class="nav-link py-1" data-bs-toggle="tab" href="#t-receive">Receive</a></li>
                        <li class="nav-item"><a class="nav-link py-1" data-bs-toggle="tab" href="#t-count">Count</a></li>
                        <li class="nav-item"><a class="nav-link py-1" data-bs-toggle="tab" href="#t-move">Move</a></li>
                    </ul>
                    <div class="tab-content">
                        <div class="tab-pane active" id="t-issue">
                            <form method="POST" action="{{ route('inventory.items.issue', $item) }}">
                                @csrf
                                <div class="row g-2 mb-2">
                                    <div class="col-5"><input type="number" step="0.01" min="0.01" name="quantity" class="form-control" placeholder="Qty" required></div>
                                    <div class="col-7"><select name="location_id" class="form-select">{!! $locOpts !!}</select></div>
                                </div>
                                <select name="issued_to" class="form-select mb-2"><option value="">To (optional)</option>@foreach($staff as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach</select>
                                @if($projects->isNotEmpty())
                                    <select name="project_id" class="form-select mb-2"><option value="">For a project (optional)</option>@foreach($projects as $p)<option value="{{ $p->id }}">{{ $p->code }} · {{ $p->name }}</option>@endforeach</select>
                                @endif
                                <input name="note" class="form-control mb-2" maxlength="255" placeholder="Note (optional)">
                                <button class="btn btn-primary w-100">Issue</button>
                            </form>
                        </div>
                        <div class="tab-pane" id="t-receive">
                            <form method="POST" action="{{ route('inventory.items.receive', $item) }}">
                                @csrf
                                <div class="row g-2 mb-2">
                                    <div class="col-5"><input type="number" step="0.01" min="0.01" name="quantity" class="form-control" placeholder="Qty" required></div>
                                    <div class="col-7"><select name="location_id" class="form-select">{!! $locOpts !!}</select></div>
                                </div>
                                <input type="number" step="0.01" min="0" name="unit_cost" class="form-control mb-2" value="{{ (float) $item->avg_cost ?: '' }}" placeholder="Cost each">
                                <input name="reference" class="form-control mb-2" maxlength="100" placeholder="Delivery note or receipt no. (optional)">
                                <button class="btn btn-success w-100">Receive</button>
                                <div class="form-text">Goods bought on a purchase order are received on the order instead.</div>
                            </form>
                        </div>
                        <div class="tab-pane" id="t-count">
                            <form method="POST" action="{{ route('inventory.items.adjust', $item) }}">
                                @csrf
                                <div class="row g-2 mb-2">
                                    <div class="col-5"><input type="number" step="0.01" min="0" name="quantity" class="form-control" placeholder="Counted" required></div>
                                    <div class="col-7"><select name="location_id" class="form-select">{!! $locOpts !!}</select></div>
                                </div>
                                <input name="note" class="form-control mb-2" maxlength="255" required placeholder="Why it differs, e.g. stock count, damaged">
                                <button class="btn btn-warning w-100">Set count</button>
                            </form>
                        </div>
                        <div class="tab-pane" id="t-move">
                            <form method="POST" action="{{ route('inventory.items.transfer', $item) }}">
                                @csrf
                                <input type="number" step="0.01" min="0.01" name="quantity" class="form-control mb-2" placeholder="Qty" required>
                                <div class="row g-2 mb-2">
                                    <div class="col-6"><label class="form-label small">From</label><select name="location_id" class="form-select">{!! $locOpts !!}</select></div>
                                    <div class="col-6"><label class="form-label small">To</label><select name="to_location_id" class="form-select">{!! $locOpts !!}</select></div>
                                </div>
                                <input name="note" class="form-control mb-2" maxlength="255" placeholder="Note (optional)">
                                <button class="btn btn-info w-100">Move</button>
                            </form>
                        </div>
                    </div>
                </x-cb.card>
            @endcan
            @if($item->description)
                <x-cb.card title="About" icon="ri-information-line"><div class="small" style="white-space: pre-line">{{ $item->description }}</div></x-cb.card>
            @endif
        </div>
    </div>

</div></div></div>
@endsection
