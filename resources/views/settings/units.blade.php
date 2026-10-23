@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">
    <x-cb.hero title="Settings" icon="ri-settings-4-line" subtitle="Portal-wide settings. Changes here take effect straight away." />
    @include('settings._tabs')
    @include('partials.flash')

    <div class="row g-3">
        <div class="col-xl-8">
            <x-cb.card title="Business units" icon="ri-building-2-line" :count="$units->count()" :flush="true">
                <div class="p-3 small text-muted">Invoices, expenses, deals, projects, staff and courses each belong to a unit, so the Command Center can report on each one and on all of them together. Payments count towards their invoice's unit, and payroll towards each employee's unit.</div>
                @foreach($units as $u)
                    <form method="POST" action="{{ route('portal-settings.units.update', $u) }}" class="border-top p-3">
                        @csrf @method('PUT')
                        <div class="row g-2 align-items-end">
                            <div class="col-md-4"><label class="form-label small">Name</label><input name="name" class="form-control" value="{{ $u->name }}" maxlength="100" required></div>
                            <div class="col-4 col-md-2"><label class="form-label small">Code</label><input name="code" class="form-control text-uppercase" value="{{ $u->code }}" maxlength="10" required></div>
                            <div class="col-4 col-md-2"><label class="form-label small">Colour</label><input type="color" name="color" class="form-control form-control-color w-100" value="{{ $u->color }}"></div>
                            <div class="col-4 col-md-1"><label class="form-label small">Order</label><input type="number" name="position" class="form-control" value="{{ $u->position }}" min="0"></div>
                            <div class="col-md-3"><div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="act{{ $u->id }}" @checked($u->is_active)><label class="form-check-label" for="act{{ $u->id }}">In use</label></div></div>
                            <div class="col-md-9"><input name="description" class="form-control form-control-sm" value="{{ $u->description }}" maxlength="255" placeholder="What this unit does"></div>
                            <div class="col-md-3 text-md-end"><button class="btn btn-sm btn-soft-primary">Save</button></div>
                        </div>
                        <div class="small text-muted mt-2">
                            @php $c = $counts[$u->id] ?? []; @endphp
                            {{ $c ? collect($c)->map(fn ($n, $k) => $n . ' ' . $k)->implode(' · ') : 'Nothing assigned yet.' }}
                            @if($u->code === config('qudroid.units.default')) · <strong>default for new records</strong>@endif
                            @if($u->code === config('qudroid.units.academy')) · <strong>academy courses and fees</strong>@endif
                        </div>
                    </form>
                @endforeach
            </x-cb.card>
        </div>
        <div class="col-xl-4">
            <x-cb.card title="Add a unit" icon="ri-add-line">
                <form method="POST" action="{{ route('portal-settings.units.store') }}">
                    @csrf
                    <div class="mb-2"><label class="form-label">Name</label><input name="name" class="form-control" maxlength="100" required value="{{ old('name') }}"></div>
                    <div class="row g-2 mb-2">
                        <div class="col-7"><label class="form-label">Code</label><input name="code" class="form-control text-uppercase" maxlength="10" required placeholder="e.g. VS" value="{{ old('code') }}"></div>
                        <div class="col-5"><label class="form-label">Colour</label><input type="color" name="color" class="form-control form-control-color w-100" value="{{ old('color', '#6250d6') }}"></div>
                    </div>
                    <div class="mb-3"><label class="form-label">Description</label><input name="description" class="form-control" maxlength="255" value="{{ old('description') }}"></div>
                    <button class="btn btn-primary w-100">Add unit</button>
                </form>
                <div class="small text-muted mt-3">A unit that's no longer used can be switched off; its records keep it. Units can't be deleted, so past reports stay right.</div>
            </x-cb.card>
        </div>
    </div>
</div></div></div>
@endsection
