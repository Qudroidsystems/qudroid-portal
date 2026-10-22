@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">

    <x-cb.hero title="Categories & locations" icon="ri-settings-3-line" :back="route('inventory.index')" back-label="Inventory" />

    @include('partials.flash')

    <div class="row g-3">
        @foreach(['stock' => 'Stock categories', 'asset' => 'Asset categories'] as $kind => $title)
            <div class="col-xl-4">
                <x-cb.card :title="$title" icon="ri-price-tag-3-line" :flush="true">
                    <ul class="list-group list-group-flush">
                        @foreach($categories->get($kind, collect()) as $c)
                            <li class="list-group-item">
                                <form method="POST" action="{{ route('inventory.categories.update', $c) }}" class="d-flex gap-2">
                                    @csrf @method('PUT')
                                    <input name="name" class="form-control form-control-sm" value="{{ $c->name }}" maxlength="80" required>
                                    <button class="btn btn-sm btn-soft-primary">Save</button>
                                </form>
                            </li>
                        @endforeach
                    </ul>
                    <form method="POST" action="{{ route('inventory.categories.store') }}" class="d-flex gap-2 p-3 border-top">
                        @csrf<input type="hidden" name="kind" value="{{ $kind }}">
                        <input name="name" class="form-control" maxlength="80" required placeholder="New category">
                        <button class="btn btn-primary"><i class="ri-add-line"></i></button>
                    </form>
                </x-cb.card>
            </div>
        @endforeach
        <div class="col-xl-4">
            <x-cb.card title="Locations" icon="ri-map-pin-line" :flush="true">
                <ul class="list-group list-group-flush">
                    @foreach($locations as $l)
                        <li class="list-group-item">
                            <form method="POST" action="{{ route('inventory.locations.update', $l) }}" class="row g-2 align-items-center">
                                @csrf @method('PUT')
                                <div class="col-12"><input name="name" class="form-control form-control-sm" value="{{ $l->name }}" maxlength="80" required></div>
                                <div class="col-12"><input name="description" class="form-control form-control-sm" value="{{ $l->description }}" maxlength="255" placeholder="Description"></div>
                                <div class="col-7"><div class="form-check"><input type="checkbox" class="form-check-input" name="is_active" value="1" id="loc{{ $l->id }}" @checked($l->is_active)><label class="form-check-label small" for="loc{{ $l->id }}">In use</label></div></div>
                                <div class="col-5 text-end"><button class="btn btn-sm btn-soft-primary">Save</button></div>
                            </form>
                        </li>
                    @endforeach
                </ul>
                <form method="POST" action="{{ route('inventory.locations.store') }}" class="p-3 border-top">
                    @csrf
                    <input name="name" class="form-control mb-2" maxlength="80" required placeholder="New location, e.g. Training centre">
                    <input name="description" class="form-control mb-2" maxlength="255" placeholder="Description (optional)">
                    <button class="btn btn-primary w-100">Add location</button>
                </form>
            </x-cb.card>
        </div>
    </div>

</div></div></div>
@endsection
