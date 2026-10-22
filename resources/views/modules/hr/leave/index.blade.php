@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">

    <x-cb.hero title="Leave" icon="ri-suitcase-line" subtitle="Everyone's leave requests. Managers decide their own team's; HR can decide any." :back="route('hr.index')" back-label="HR">
        <x-slot:actions>
            @can('Manage employees')<a href="{{ route('hr.leave.settings') }}" class="cb-hero-btn"><i class="ri-settings-3-line"></i>Leave types & holidays</a>@endcan
        </x-slot:actions>
    </x-cb.hero>

    @include('partials.flash')

    @php $d = fn ($n) => rtrim(rtrim(number_format((float) $n, 1), '0'), '.'); @endphp
    <x-cb.card title="Requests" icon="ri-suitcase-line" :count="$requests->total()" :flush="true">
        <form class="cb-toolbar" method="GET">
            <div class="cb-search"><i class="ri-search-line"></i><input name="q" value="{{ request('q') }}" placeholder="Employee name"></div>
            <select name="status" class="cb-select" onchange="this.form.submit()">
                @foreach(\App\Models\Hr\LeaveRequest::STATUSES as $k => $v)<option value="{{ $k }}" @selected($status === $k)>{{ $v }} ({{ $counts[$k] ?? 0 }})</option>@endforeach
                <option value="all" @selected($status === 'all')>All</option>
            </select>
            <select name="type" class="cb-select" onchange="this.form.submit()">
                <option value="">Any type</option>
                @foreach($types as $t)<option value="{{ $t->id }}" @selected((int) request('type') === $t->id)>{{ $t->name }}</option>@endforeach
            </select>
        </form>
        @if($requests->isEmpty())
            <div class="empty-state"><i class="ri-suitcase-line"></i><h6>No leave requests here</h6></div>
        @else
            <div class="table-responsive"><table class="cb-table stack">
                <thead><tr><th>Employee</th><th>Type</th><th>Dates</th><th class="num">Days</th><th>Status</th></tr></thead>
                <tbody>
                @foreach($requests as $l)
                    <tr>
                        <td data-label="Employee"><a href="{{ route('hr.leave.show', $l) }}" class="fw-semibold">{{ $l->employee->user->name }}</a><div class="small text-muted">{{ $l->employee->employee_no }}</div></td>
                        <td data-label="Type">{{ $l->type->name }}</td>
                        <td data-label="Dates">{{ $l->starts_on->format('j M') }} – {{ $l->ends_on->format('j M Y') }}</td>
                        <td data-label="Days" class="num">{{ $d($l->days) }}</td>
                        <td data-label="Status">@include('modules.hr._pill', ['value' => $l->status])@if($l->decider)<div class="small text-muted">by {{ $l->decider->name }}</div>@endif</td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
            <div class="p-3">{{ $requests->links() }}</div>
        @endif
    </x-cb.card>

</div></div></div>
@endsection
