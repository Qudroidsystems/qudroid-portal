@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">

    <x-cb.hero title="Programmes" icon="ri-stack-line" subtitle="Group courses into tracks such as Web Development, Mobile Development or Data Science." :back="route('academy.index')" back-label="Academy" />

    @include('modules.academy.partials.flash')

    <div class="row g-3">
        <div class="col-xl-8">
            <x-cb.card title="All programmes" icon="ri-stack-line" :count="$programmes->count()" :flush="true">
                @if($programmes->isEmpty())
                    <div class="empty-state"><i class="ri-stack-line"></i><h6>No programmes yet</h6><p>Add your first one on the right.</p></div>
                @else
                    <div class="list-group list-group-flush">
                        @foreach($programmes as $p)
                            <div class="list-group-item py-3">
                                <form method="POST" action="{{ route('academy.programmes.update', $p) }}" class="row g-2 align-items-center">
                                    @csrf @method('PUT')
                                    <div class="col-md-4"><input name="name" value="{{ $p->name }}" class="form-control form-control-sm" required maxlength="150"></div>
                                    <div class="col-md-4"><input name="description" value="{{ $p->description }}" class="form-control form-control-sm" placeholder="Description"></div>
                                    <div class="col-md-1"><input name="position" value="{{ $p->position }}" type="number" min="0" class="form-control form-control-sm" title="Order"></div>
                                    <div class="col-md-1 form-check form-switch ms-2"><input type="hidden" name="is_active" value="0"><input class="form-check-input" type="checkbox" name="is_active" value="1" @checked($p->is_active) title="Active"></div>
                                    <div class="col-md-auto ms-auto d-flex gap-1 align-items-center">
                                        <span class="badge bg-light text-body me-1">{{ $p->courses_count }} courses</span>
                                        <button class="btn btn-sm btn-soft-primary">Save</button>
                                        <button form="del-programme-{{ $p->id }}" class="btn btn-sm btn-soft-danger" @disabled($p->courses_count) title="Delete"><i class="ri-delete-bin-line"></i></button>
                                    </div>
                                </form>
                                <form id="del-programme-{{ $p->id }}" method="POST" action="{{ route('academy.programmes.destroy', $p) }}" onsubmit="return confirm('Delete this programme?')">@csrf @method('DELETE')</form>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-cb.card>
        </div>
        <div class="col-xl-4">
            <x-cb.card title="Add a programme" icon="ri-add-line">
                <form method="POST" action="{{ route('academy.programmes.store') }}">
                    @csrf
                    <div class="mb-2"><label class="form-label">Name</label><input name="name" class="form-control" required maxlength="150" value="{{ old('name') }}"></div>
                    <div class="mb-2"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="3">{{ old('description') }}</textarea></div>
                    <div class="mb-3"><label class="form-label">Order</label><input name="position" type="number" min="0" class="form-control" value="{{ old('position', 0) }}"></div>
                    <input type="hidden" name="is_active" value="1">
                    <button class="btn btn-primary w-100">Add programme</button>
                </form>
            </x-cb.card>
        </div>
    </div>

</div></div></div>
@endsection
