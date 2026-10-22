@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">

    <x-cb.hero title="Attendance" icon="ri-calendar-check-line" subtitle="Class sessions and registers." />

    @include('partials.flash')

    <div class="row g-3">
        <div class="col-xl-7">
            <x-cb.card title="Today" icon="ri-calendar-event-line" :count="$today->count()" :flush="true">
                @if($today->isEmpty())
                    <div class="empty-state"><i class="ri-cup-line"></i><h6>No classes today</h6></div>
                @else
                    <div class="list-group list-group-flush">@foreach($today as $s)@include('modules.attendance._session-row')@endforeach</div>
                @endif
            </x-cb.card>
            @if($overdue->isNotEmpty())
                <x-cb.card title="Registers not taken" icon="ri-error-warning-line" :count="$overdue->count()" :flush="true">
                    <div class="list-group list-group-flush">@foreach($overdue as $s)@include('modules.attendance._session-row')@endforeach</div>
                </x-cb.card>
            @endif
            <x-cb.card title="Coming up" icon="ri-calendar-line" :flush="true">
                @if($upcoming->isEmpty())
                    <div class="p-3 small text-muted">Nothing scheduled. Open a cohort to add its sessions.</div>
                @else
                    <div class="list-group list-group-flush">@foreach($upcoming as $s)@include('modules.attendance._session-row')@endforeach</div>
                @endif
            </x-cb.card>
        </div>
        <div class="col-xl-5">
            <x-cb.card title="Cohorts" icon="ri-team-line" :count="$cohorts->count()" :flush="true">
                @if($cohorts->isEmpty())
                    <div class="p-3 small text-muted">No open or running cohorts{{ auth()->user()->can('Manage academy') ? '' : ' that you teach' }}.</div>
                @else
                    <div class="list-group list-group-flush">
                        @foreach($cohorts as $c)
                            <a href="{{ route('attendance.cohort', $c) }}" class="list-group-item list-group-item-action d-flex justify-content-between">
                                <span><strong>{{ $c->name }}</strong><span class="d-block small text-muted">{{ $c->course->title }}</span></span>
                                <small class="text-muted">{{ $c->sessions_count }} sessions</small>
                            </a>
                        @endforeach
                    </div>
                @endif
            </x-cb.card>
        </div>
    </div>

</div></div></div>
@endsection
