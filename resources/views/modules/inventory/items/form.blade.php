@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">

    @php $editing = $item->exists; $v = fn ($f) => old($f, $item->{$f}); @endphp
    <x-cb.hero :title="$editing ? 'Edit ' . $item->name : 'New stock item'" icon="ri-archive-line" :back="$editing ? route('inventory.items.show', $item) : route('inventory.items.index')" :back-label="$editing ? $item->name : 'Stock'" />

    @include('partials.flash')

    <div class="row"><div class="col-xl-8">
        <x-cb.card>
            <form method="POST" action="{{ $editing ? route('inventory.items.update', $item) : route('inventory.items.store') }}">
                @csrf
                @if($editing) @method('PUT') @endif
                <div class="row g-3">
                    <div class="col-md-8"><label class="form-label">Name</label><input name="name" class="form-control" value="{{ $v('name') }}" maxlength="150" required placeholder="e.g. A4 paper (ream)"></div>
                    <div class="col-md-4"><label class="form-label">SKU</label><input name="sku" class="form-control text-uppercase" value="{{ $v('sku') }}" maxlength="40" @if($editing) required @else placeholder="Made from the name" @endif></div>
                    <div class="col-md-4"><label class="form-label">Category</label>
                        <select name="category_id" class="form-select"><option value="">None</option>@foreach($categories as $c)<option value="{{ $c->id }}" @selected((int) $v('category_id') === $c->id)>{{ $c->name }}</option>@endforeach</select></div>
                    <div class="col-md-4"><label class="form-label">Unit</label><input name="unit" class="form-control" value="{{ $v('unit') }}" maxlength="20" required list="units">
                        <datalist id="units"><option>pcs</option><option>box</option><option>ream</option><option>pack</option><option>carton</option><option>litre</option><option>metre</option><option>roll</option></datalist></div>
                    <div class="col-md-4"><label class="form-label">Reorder at</label><input type="number" step="0.01" min="0" name="reorder_level" class="form-control" value="{{ $v('reorder_level') }}" placeholder="0 = don't warn">
                        <div class="form-text">Warns the team when stock falls to this.</div></div>
                    <div class="col-md-6"><label class="form-label">Usual supplier</label>
                        <select name="supplier_id" class="form-select"><option value="">None</option>@foreach($suppliers as $s)<option value="{{ $s->id }}" @selected((int) $v('supplier_id') === $s->id)>{{ $s->name }}</option>@endforeach</select></div>
                    @if($editing)
                        <div class="col-md-6 d-flex align-items-end"><div class="form-check form-switch mb-2"><input type="checkbox" class="form-check-input" name="is_active" value="1" id="act" @checked($v('is_active'))><label class="form-check-label" for="act">In use (untick to archive)</label></div></div>
                    @endif
                    <div class="col-12"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="2" maxlength="500">{{ $v('description') }}</textarea></div>
                    @unless($editing)
                        <div class="col-12"><hr class="my-1"><div class="small text-muted mb-2">Opening stock (optional)</div></div>
                        <div class="col-md-4"><label class="form-label">Quantity on hand</label><input type="number" step="0.01" min="0" name="opening_quantity" class="form-control" value="{{ old('opening_quantity') }}"></div>
                        <div class="col-md-4"><label class="form-label">Cost each</label><input type="number" step="0.01" min="0" name="opening_cost" class="form-control" value="{{ old('opening_cost') }}"></div>
                        <div class="col-md-4"><label class="form-label">At</label>
                            <select name="opening_location_id" class="form-select">@foreach($locations as $l)<option value="{{ $l->id }}">{{ $l->name }}</option>@endforeach</select></div>
                    @endunless
                </div>
                <div class="mt-4"><button class="btn btn-primary">{{ $editing ? 'Save' : 'Add item' }}</button></div>
            </form>
        </x-cb.card>
    </div></div>

</div></div></div>
@endsection
