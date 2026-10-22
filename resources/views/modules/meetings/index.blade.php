@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">

    <x-cb.hero title="Meetings" icon="ri-video-chat-line" :subtitle="$all ? 'Everyone\'s meetings.' : 'Meetings you organise or are invited to.'">
        <x-slot:actions>
            <a href="{{ route('meetings.calendar') }}" class="cb-hero-btn"><i class="ri-calendar-2-line"></i>Calendar</a>
            <a href="{{ route('meetings.create') }}" class="cb-hero-btn"><i class="ri-add-line"></i>Schedule</a>
            @can('Manage meetings')<a href="{{ route('meetings.index', $all ? [] : ['show' => 'all']) }}" class="cb-hero-btn"><i class="ri-team-line"></i>{{ $all ? 'Just mine' : 'Everyone\'s' }}</a>@endcan
        </x-slot:actions>
    </x-cb.hero>

    @include('partials.flash')

    @if($toAnswer->isNotEmpty())
        <x-cb.card title="Waiting for your answer" icon="ri-question-answer-line" :count="$toAnswer->count()" :flush="true">
            <ul class="list-group list-group-flush">
                @foreach($toAnswer as $a)
                    <li class="list-group-item d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <span><a href="{{ route('meetings.show', $a->meeting) }}" class="fw-semibold">{{ $a->meeting->title }}</a> <span class="small text-muted">· {{ $a->meeting->whenLabel() }} · from {{ $a->meeting->organizer->name }}</span></span>
                        <form method="POST" action="{{ route('meetings.respond', $a->meeting) }}" class="d-flex gap-1">
                            @csrf
                            <button name="response" value="accepted" class="btn btn-sm btn-soft-success">Going</button>
                            <button name="response" value="tentative" class="btn btn-sm btn-soft-info">Maybe</button>
                            <button name="response" value="declined" class="btn btn-sm btn-soft-secondary">Can't</button>
                        </form>
                    </li>
                @endforeach
            </ul>
        </x-cb.card>
    @endif

    <div class="row g-3">
        <div class="col-xl-7">
            <x-cb.card title="Coming up" icon="ri-calendar-event-line" :count="$upcoming->count()" :flush="true">
                @if($upcoming->isEmpty())
                    <div class="empty-state"><i class="ri-calendar-event-line"></i><h6>No meetings coming up</h6></div>
                @else
                    <ul class="list-group list-group-flush">@foreach($upcoming as $m)@include('modules.meetings._row', ['m' => $m])@endforeach</ul>
                @endif
            </x-cb.card>
        </div>
        <div class="col-xl-5">
            <x-cb.card title="Recent" icon="ri-history-line" :flush="true">
                @if($past->isEmpty())
                    <div class="empty-state"><i class="ri-history-line"></i><h6>No past meetings</h6></div>
                @else
                    <ul class="list-group list-group-flush">
                        @foreach($past as $m)
                            <li class="list-group-item small d-flex justify-content-between gap-2">
                                <a href="{{ route('meetings.show', $m) }}" class="{{ $m->isCancelled() ? 'text-decoration-line-through text-muted' : '' }}">{{ $m->title }}</a>
                                <span class="text-muted text-nowrap">{{ $m->starts_at->format('j M') }}@if($m->minutes) · <i class="ri-file-text-line" title="Has minutes"></i>@endif</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-cb.card>
        </div>
    </div>

</div></div></div>
@endsection
