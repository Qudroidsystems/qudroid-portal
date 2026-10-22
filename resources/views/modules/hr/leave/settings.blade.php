@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">

    <x-cb.hero title="Leave types & holidays" icon="ri-settings-3-line" subtitle="Public holidays and weekends don't count as leave days." :back="route('hr.leave.index')" back-label="Leave" />

    @include('partials.flash')

    <div class="row g-3">
        <div class="col-xl-7">
            <x-cb.card title="Leave types" icon="ri-suitcase-line" :flush="true">
                <div class="table-responsive"><table class="cb-table">
                    <thead><tr><th>Name</th><th>Days a year</th><th>Paid</th><th>In use</th><th></th></tr></thead>
                    <tbody>
                    @foreach($types as $t)
                        <tr>
                            @php $f = 'lt-' . $t->id; @endphp
                            <td><input form="{{ $f }}" name="name" class="form-control form-control-sm" value="{{ $t->name }}" maxlength="60" required></td>
                            <td>
                                @if($t->is_annual)
                                    <span class="small text-muted">Each employee's own allowance</span>
                                @else
                                    <input form="{{ $f }}" type="number" name="days_per_year" class="form-control form-control-sm" value="{{ $t->days_per_year }}" min="1" max="365" placeholder="No limit" style="max-width: 110px">
                                @endif
                            </td>
                            <td><input form="{{ $f }}" type="hidden" name="is_paid" value="0"><input form="{{ $f }}" type="checkbox" class="form-check-input" name="is_paid" value="1" @checked($t->is_paid)></td>
                            <td><input form="{{ $f }}" type="hidden" name="is_active" value="0"><input form="{{ $f }}" type="checkbox" class="form-check-input" name="is_active" value="1" @checked($t->is_active)></td>
                            <td class="text-end">
                                <form method="POST" action="{{ route('hr.leave.types.update', $t) }}" id="{{ $f }}">@csrf @method('PUT')<button class="btn btn-sm btn-soft-primary">Save</button></form>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table></div>
                <form method="POST" action="{{ route('hr.leave.types.store') }}" class="row g-2 p-3 border-top">
                    @csrf
                    <div class="col-md-5"><input name="name" class="form-control" maxlength="60" required placeholder="New type, e.g. Study leave"></div>
                    <div class="col-md-3"><input type="number" name="days_per_year" class="form-control" min="1" max="365" placeholder="Days (blank = no limit)"></div>
                    <div class="col-md-2 d-flex align-items-center"><input type="hidden" name="is_paid" value="0"><div class="form-check"><input type="checkbox" class="form-check-input" name="is_paid" value="1" id="np" checked><label for="np" class="form-check-label">Paid</label></div></div>
                    <div class="col-md-2"><button class="btn btn-primary w-100">Add</button></div>
                </form>
            </x-cb.card>
        </div>
        <div class="col-xl-5">
            <x-cb.card title="Public holidays" icon="ri-calendar-2-line" :count="$holidays->count()" :flush="true">
                <form method="POST" action="{{ route('hr.leave.holidays.store') }}" class="row g-2 p-3">
                    @csrf
                    <div class="col-5"><input type="date" name="date" class="form-control" required></div>
                    <div class="col-5"><input name="name" class="form-control" maxlength="100" required placeholder="e.g. Independence Day"></div>
                    <div class="col-2"><button class="btn btn-primary w-100"><i class="ri-add-line"></i></button></div>
                </form>
                @if($holidays->isEmpty())
                    <div class="p-3 pt-0 small text-muted">No holidays added for this year yet. Add them as the government announces them.</div>
                @else
                    <ul class="list-group list-group-flush">
                        @foreach($holidays as $h)
                            <li class="list-group-item d-flex justify-content-between align-items-center small">
                                <span><span class="fw-semibold">{{ $h->date->format('D j M Y') }}</span> · {{ $h->name }}</span>
                                <form method="POST" action="{{ route('hr.leave.holidays.destroy', $h) }}">@csrf @method('DELETE')<button class="btn btn-sm btn-link text-danger p-0"><i class="ri-delete-bin-line"></i></button></form>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-cb.card>
        </div>
    </div>

</div></div></div>
@endsection
