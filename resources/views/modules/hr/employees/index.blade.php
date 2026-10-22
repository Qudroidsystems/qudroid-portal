@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">

    <x-cb.hero title="Employees" icon="ri-team-line" subtitle="Everyone on the staff list, with their department, manager and leave." :back="route('hr.index')" back-label="HR">
        <x-slot:actions>
            @can('Manage employees')<a href="{{ route('hr.employees.create') }}" class="cb-hero-btn"><i class="ri-user-add-line"></i>Add employee</a>@endcan
        </x-slot:actions>
    </x-cb.hero>

    @include('partials.flash')

    <x-cb.card title="Employees" icon="ri-team-line" :count="$employees->total()" :flush="true">
        <form class="cb-toolbar" method="GET">
            <div class="cb-search"><i class="ri-search-line"></i><input name="q" value="{{ request('q') }}" placeholder="Name, email, number or job title"></div>
            <select name="department" class="cb-select" onchange="this.form.submit()">
                <option value="">Any department</option>
                @foreach($departments as $d)<option value="{{ $d->id }}" @selected((int) request('department') === $d->id)>{{ $d->name }}</option>@endforeach
            </select>
            <select name="status" class="cb-select" onchange="this.form.submit()">
                @foreach(\App\Models\Hr\Employee::STATUSES as $k => $v)<option value="{{ $k }}" @selected(request('status', 'active') === $k)>{{ $v }} ({{ $counts[$k] ?? 0 }})</option>@endforeach
            </select>
        </form>
        @if($employees->isEmpty())
            <div class="empty-state"><i class="ri-team-line"></i><h6>No employees found</h6>@can('Manage employees')<p class="text-muted small">Add staff to start tracking leave and payroll.</p>@endcan</div>
        @else
            <div class="table-responsive"><table class="cb-table stack">
                <thead><tr><th>Employee</th><th>Job title</th><th>Department</th><th>Type</th><th>Started</th><th>Status</th></tr></thead>
                <tbody>
                @foreach($employees as $e)
                    <tr>
                        <td data-label="Employee">
                            <div class="d-flex align-items-center gap-2">
                                <img src="{{ $e->user->avatar_url }}" class="rounded-circle" width="32" height="32" alt="">
                                <div><a href="{{ route('hr.employees.show', $e) }}" class="fw-semibold">{{ $e->user->name }}</a><div class="small text-muted">{{ $e->employee_no }} · {{ $e->user->email }}</div></div>
                            </div>
                        </td>
                        <td data-label="Job title">{{ $e->job_title ?? '—' }}</td>
                        <td data-label="Department">{{ $e->department?->name ?? '—' }}</td>
                        <td data-label="Type">{{ $e->type_label }}</td>
                        <td data-label="Started">{{ $e->start_date?->format('j M Y') ?? '—' }}</td>
                        <td data-label="Status">@include('modules.hr._pill', ['value' => $e->status, 'kind' => 'employee'])</td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
            <div class="p-3">{{ $employees->links() }}</div>
        @endif
    </x-cb.card>

</div></div></div>
@endsection
