@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">

    <x-cb.hero title="HR & Payroll" icon="ri-team-line" subtitle="Employees, leave and monthly payroll.">
        <x-slot:actions>
            @can('Manage employees')<a href="{{ route('hr.employees.create') }}" class="cb-hero-btn"><i class="ri-user-add-line"></i>Add employee</a>@endcan
            @canany(['Manage payroll', 'Approve finance'])<a href="{{ route('hr.payroll.index') }}" class="cb-hero-btn"><i class="ri-money-dollar-circle-line"></i>Payroll</a>@endcanany
        </x-slot:actions>
    </x-cb.hero>

    @include('partials.flash')

    <div class="row g-3 mb-3">
        <div class="col-xl-3 col-md-6"><x-cb.stat label="Employees" :value="$headcount" icon="ri-team-line" accent="teal" :hint="$newStarters->count() . ' joined in the last 30 days'" /></div>
        <div class="col-xl-3 col-md-6"><x-cb.stat label="On leave today" :value="$onLeave->count()" icon="ri-suitcase-line" accent="sky" /></div>
        <div class="col-xl-3 col-md-6"><x-cb.stat label="Leave to decide" :value="$pendingCount" icon="ri-time-line" accent="amber" /></div>
        <div class="col-xl-3 col-md-6">
            @if($lastRun)
                <x-cb.stat :label="'Payroll ' . $lastRun->label" :value="\App\Support\Money::format($lastRun->net)" icon="ri-money-dollar-circle-line" accent="green" :hint="'net pay · ' . strtolower($lastRun->status_label)" />
            @else
                <x-cb.stat label="Departments" :value="$byDepartment->count()" icon="ri-building-2-line" accent="green" />
            @endif
        </div>
    </div>

    <div class="row g-3">
        <div class="col-xl-6">
            <x-cb.card title="Leave waiting for a decision" icon="ri-time-line" :count="$pendingCount" :flush="true">
                <x-slot:tools><a href="{{ route('hr.leave.index') }}" class="btn btn-sm btn-soft-primary">All leave</a></x-slot:tools>
                @if($pending->isEmpty())
                    <div class="empty-state"><i class="ri-check-double-line"></i><h6>Nothing waiting</h6></div>
                @else
                    <ul class="list-group list-group-flush">
                        @foreach($pending as $l)
                            <li class="list-group-item d-flex justify-content-between gap-2 small">
                                <div><a href="{{ route('hr.leave.show', $l) }}" class="fw-semibold">{{ $l->employee->user->name }}</a> · {{ $l->type->name }}
                                    <div class="text-muted">{{ $l->starts_on->format('j M') }} – {{ $l->ends_on->format('j M Y') }}</div></div>
                                <span class="text-nowrap">{{ rtrim(rtrim(number_format($l->days, 1), '0'), '.') }} days</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-cb.card>

            <x-cb.card title="Away today" icon="ri-suitcase-line" :flush="true">
                @if($onLeave->isEmpty())
                    <div class="empty-state"><i class="ri-user-smile-line"></i><h6>Everyone is in</h6></div>
                @else
                    <ul class="list-group list-group-flush">
                        @foreach($onLeave as $l)
                            <li class="list-group-item small"><span class="fw-semibold">{{ $l->employee->user->name }}</span> · {{ $l->type->name }} <span class="text-muted">· back after {{ $l->ends_on->format('j M') }}</span></li>
                        @endforeach
                    </ul>
                @endif
            </x-cb.card>
        </div>
        <div class="col-xl-6">
            <x-cb.card title="Leave in the next 30 days" icon="ri-calendar-event-line" :flush="true">
                @if($upcoming->isEmpty())
                    <div class="empty-state"><i class="ri-calendar-event-line"></i><h6>No leave booked</h6></div>
                @else
                    <ul class="list-group list-group-flush">
                        @foreach($upcoming as $l)
                            <li class="list-group-item small d-flex justify-content-between"><span><span class="fw-semibold">{{ $l->employee->user->name }}</span> · {{ $l->type->name }}</span><span class="text-muted">{{ $l->starts_on->format('j M') }} – {{ $l->ends_on->format('j M') }}</span></li>
                        @endforeach
                    </ul>
                @endif
            </x-cb.card>

            <x-cb.card title="Departments" icon="ri-building-2-line" :flush="true">
                <x-slot:tools><a href="{{ route('hr.departments.index') }}" class="btn btn-sm btn-soft-primary">Manage</a></x-slot:tools>
                <ul class="list-group list-group-flush">
                    @foreach($byDepartment as $d)
                        <li class="list-group-item small d-flex justify-content-between"><a href="{{ route('hr.employees.index', ['department' => $d->id]) }}">{{ $d->name }}</a><span>{{ $d->employees_count }}</span></li>
                    @endforeach
                    @if($unassigned)<li class="list-group-item small d-flex justify-content-between text-muted"><span>No department</span><span>{{ $unassigned }}</span></li>@endif
                    @if($byDepartment->isEmpty() && !$unassigned)<li class="list-group-item small text-muted">No departments yet.</li>@endif
                </ul>
            </x-cb.card>
        </div>
    </div>

</div></div></div>
@endsection
