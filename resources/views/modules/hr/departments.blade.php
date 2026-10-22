@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">

    <x-cb.hero title="Departments" icon="ri-building-2-line" :back="route('hr.index')" back-label="HR" />

    @include('partials.flash')

    <div class="row g-3">
        <div class="col-xl-8">
            <x-cb.card title="Departments" icon="ri-building-2-line" :count="$departments->count()" :flush="true">
                @if($departments->isEmpty())
                    <div class="empty-state"><i class="ri-building-2-line"></i><h6>No departments yet</h6></div>
                @else
                    <div class="table-responsive"><table class="cb-table stack">
                        <thead><tr><th>Department</th><th>Head</th><th class="num">Employees</th>@can('Manage employees')<th></th>@endcan</tr></thead>
                        <tbody>
                        @foreach($departments as $d)
                            <tr>
                                <td data-label="Department"><a href="{{ route('hr.employees.index', ['department' => $d->id]) }}" class="fw-semibold">{{ $d->name }}</a>@if($d->description)<div class="small text-muted">{{ $d->description }}</div>@endif</td>
                                <td data-label="Head">{{ $d->head?->name ?? '—' }}</td>
                                <td data-label="Employees" class="num">{{ $d->employees_count }}</td>
                                @can('Manage employees')
                                <td class="text-end text-nowrap">
                                    <button class="btn btn-sm btn-soft-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#dep-{{ $d->id }}"><i class="ri-pencil-line"></i></button>
                                    <form method="POST" action="{{ route('hr.departments.destroy', $d) }}" class="d-inline" onsubmit="return confirm('Remove {{ e($d->name) }}? Its employees keep their records.')">@csrf @method('DELETE')<button class="btn btn-sm btn-soft-danger"><i class="ri-delete-bin-line"></i></button></form>
                                </td>
                                @endcan
                            </tr>
                            @can('Manage employees')
                            <tr class="collapse" id="dep-{{ $d->id }}"><td colspan="4">
                                <form method="POST" action="{{ route('hr.departments.update', $d) }}" class="row g-2">
                                    @csrf @method('PUT')
                                    <div class="col-md-4"><input name="name" class="form-control" value="{{ $d->name }}" required maxlength="100"></div>
                                    <div class="col-md-3"><select name="head_id" class="form-select"><option value="">No head</option>@foreach($staff as $s)<option value="{{ $s->id }}" @selected($d->head_id === $s->id)>{{ $s->name }}</option>@endforeach</select></div>
                                    <div class="col-md-3"><input name="description" class="form-control" value="{{ $d->description }}" maxlength="255" placeholder="Description"></div>
                                    <div class="col-md-2"><button class="btn btn-primary w-100">Save</button></div>
                                </form>
                            </td></tr>
                            @endcan
                        @endforeach
                        </tbody>
                    </table></div>
                @endif
            </x-cb.card>
        </div>
        @can('Manage employees')
        <div class="col-xl-4">
            <x-cb.card title="Add a department" icon="ri-add-line">
                <form method="POST" action="{{ route('hr.departments.store') }}">
                    @csrf
                    <input name="name" class="form-control mb-2" required maxlength="100" placeholder="Name, e.g. Engineering">
                    <select name="head_id" class="form-select mb-2"><option value="">Head of department (optional)</option>@foreach($staff as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach</select>
                    <input name="description" class="form-control mb-2" maxlength="255" placeholder="Description (optional)">
                    <button class="btn btn-primary w-100">Add department</button>
                </form>
            </x-cb.card>
        </div>
        @endcan
    </div>

</div></div></div>
@endsection
