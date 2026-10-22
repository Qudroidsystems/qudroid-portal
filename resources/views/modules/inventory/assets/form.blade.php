@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">

    @php $editing = $asset->exists; $v = fn ($f) => old($f, $asset->{$f}); $d = fn ($f) => old($f, $asset->{$f}?->toDateString()); @endphp
    <x-cb.hero :title="$editing ? 'Edit ' . $asset->tag : 'Add asset'" icon="ri-macbook-line" :back="$editing ? route('inventory.assets.show', $asset) : route('inventory.assets.index')" :back-label="$editing ? $asset->tag : 'Assets'" />

    @include('partials.flash')

    <div class="row"><div class="col-xl-8">
        <x-cb.card>
            <form method="POST" action="{{ $editing ? route('inventory.assets.update', $asset) : route('inventory.assets.store') }}">
                @csrf
                @if($editing) @method('PUT') @endif
                <div class="row g-3">
                    <div class="col-md-8"><label class="form-label">Name</label><input name="name" class="form-control" value="{{ $v('name') }}" maxlength="150" required placeholder="e.g. MacBook Air 13&quot;"></div>
                    <div class="col-md-4"><label class="form-label">Category</label>
                        <select name="category_id" class="form-select"><option value="">None</option>@foreach($categories as $c)<option value="{{ $c->id }}" @selected((int) $v('category_id') === $c->id)>{{ $c->name }}</option>@endforeach</select></div>
                    <div class="col-md-6"><label class="form-label">Make and model</label><input name="make_model" class="form-control" value="{{ $v('make_model') }}" maxlength="150"></div>
                    <div class="col-md-6"><label class="form-label">Serial number</label><input name="serial_number" class="form-control" value="{{ $v('serial_number') }}" maxlength="100"></div>
                    <div class="col-md-4"><label class="form-label">Bought from</label>
                        <select name="supplier_id" class="form-select"><option value="">—</option>@foreach($suppliers as $s)<option value="{{ $s->id }}" @selected((int) $v('supplier_id') === $s->id)>{{ $s->name }}</option>@endforeach</select></div>
                    <div class="col-md-4"><label class="form-label">Bought on</label><input type="date" name="purchase_date" class="form-control" value="{{ $d('purchase_date') }}"></div>
                    <div class="col-md-4"><label class="form-label">Cost</label><input type="number" step="0.01" min="0" name="purchase_cost" class="form-control" value="{{ $v('purchase_cost') }}"></div>
                    <div class="col-md-4"><label class="form-label">Warranty until</label><input type="date" name="warranty_until" class="form-control" value="{{ $d('warranty_until') }}"></div>
                    <div class="col-md-4"><label class="form-label">Condition</label>
                        <select name="condition" class="form-select">@foreach(\App\Models\Inventory\Asset::CONDITIONS as $k => $c)<option value="{{ $k }}" @selected($v('condition') === $k)>{{ $c }}</option>@endforeach</select></div>
                    <div class="col-md-4"><label class="form-label">Kept at</label>
                        <select name="location_id" class="form-select"><option value="">—</option>@foreach($locations as $l)<option value="{{ $l->id }}" @selected((int) $v('location_id') === $l->id)>{{ $l->name }}</option>@endforeach</select></div>
                    <div class="col-12"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="2" maxlength="5000">{{ $v('notes') }}</textarea></div>
                </div>
                <div class="mt-4"><button class="btn btn-primary">{{ $editing ? 'Save' : 'Add asset' }}</button></div>
            </form>
        </x-cb.card>
    </div></div>

</div></div></div>
@endsection
