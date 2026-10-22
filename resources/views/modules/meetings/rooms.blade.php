@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">

    <x-cb.hero title="Meeting rooms" icon="ri-door-open-line" subtitle="Rooms can't be booked by two meetings at once." :back="route('meetings.index')" back-label="Meetings" />

    @include('partials.flash')

    <div class="row g-3">
        <div class="col-xl-8">
            <x-cb.card title="Rooms" icon="ri-door-open-line" :count="$rooms->count()" :flush="true">
                <ul class="list-group list-group-flush">
                    @foreach($rooms as $r)
                        <li class="list-group-item">
                            <form method="POST" action="{{ route('meetings.rooms.update', $r) }}" class="row g-2 align-items-center">
                                @csrf @method('PUT')
                                <div class="col-md-4"><input name="name" class="form-control form-control-sm" value="{{ $r->name }}" maxlength="80" required></div>
                                <div class="col-md-2"><input type="number" name="capacity" class="form-control form-control-sm" value="{{ $r->capacity }}" min="1" placeholder="Seats"></div>
                                <div class="col-md-3"><input name="description" class="form-control form-control-sm" value="{{ $r->description }}" maxlength="255" placeholder="Description"></div>
                                <div class="col-md-2"><div class="form-check"><input type="checkbox" class="form-check-input" name="is_active" value="1" id="rm{{ $r->id }}" @checked($r->is_active)><label class="form-check-label small" for="rm{{ $r->id }}">Bookable</label></div></div>
                                <div class="col-md-1 text-end"><button class="btn btn-sm btn-soft-primary">Save</button></div>
                            </form>
                        </li>
                    @endforeach
                </ul>
            </x-cb.card>
        </div>
        <div class="col-xl-4">
            <x-cb.card title="Add a room" icon="ri-add-line">
                <form method="POST" action="{{ route('meetings.rooms.store') }}">
                    @csrf
                    <input name="name" class="form-control mb-2" maxlength="80" required placeholder="e.g. Training hall">
                    <input type="number" name="capacity" class="form-control mb-2" min="1" placeholder="Seats (optional)">
                    <input name="description" class="form-control mb-2" maxlength="255" placeholder="Description (optional)">
                    <button class="btn btn-primary w-100">Add room</button>
                </form>
            </x-cb.card>
        </div>
    </div>

</div></div></div>
@endsection
