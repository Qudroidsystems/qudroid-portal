@extends('layouts.master')

@section('content')
@php
    $e = $enrollment;
    $pct = fn ($v) => $v === null ? '—' : rtrim(rtrim(number_format((float) $v, 1), '0'), '.') . '%';
    $tone = ['present' => 'success', 'late' => 'warning', 'absent' => 'danger', 'excused' => 'secondary', 'cancelled' => 'secondary'];
@endphp
<div class="main-content"><div class="page-content"><div class="container-fluid">

    <x-cb.hero :title="'My attendance: ' . $e->course->title" icon="ri-calendar-check-line" :subtitle="$e->cohort->name . ($e->cohort->place ? ' · ' . $e->cohort->place->name : '')" :back="route('academy.my')" back-label="My academy">
        <x-slot:pills>
            <span class="cb-meta-pill">Rate {{ $pct($rate) }}</span>
            @foreach(\App\Models\Academy\Attendance::STATUSES as $k => $v)<span class="cb-meta-pill">{{ $v }} {{ $counts[$k] ?? 0 }}</span>@endforeach
        </x-slot:pills>
    </x-cb.hero>

    <div class="cb-banner info"><i class="ri-smartphone-line"></i><div>Check in to each class from the Qlouds Workspace app: tap "I'm here" when you are on campus, or scan the class QR. You get a reminder when check-in opens.</div></div>

    <x-cb.card title="Sessions" icon="ri-calendar-line" :count="$sessions->count()" :flush="true">
        @if($sessions->isEmpty())
            <div class="empty-state"><i class="ri-calendar-line"></i><h6>No sessions scheduled yet</h6></div>
        @else
            <div class="table-responsive"><table class="cb-table stack">
                <thead><tr><th>Date</th><th>Topic</th><th>Your attendance</th></tr></thead>
                <tbody>
                @foreach($sessions as $s)
                    <tr>
                        <td data-label="Date"><span class="fw-semibold">{{ $s['date']->format('D j M Y') }}</span>@if($s['time'])<div class="small text-muted">{{ $s['time'] }}</div>@endif</td>
                        <td data-label="Topic">{{ $s['topic'] ?? '—' }}</td>
                        <td data-label="Attendance">
                            @if($s['status'])
                                <span class="badge bg-{{ $tone[$s['status']] ?? 'secondary' }}-subtle text-{{ $tone[$s['status']] ?? 'secondary' }}">{{ \App\Models\Academy\Attendance::STATUSES[$s['status']] ?? ucfirst($s['status']) }}</span>
                                @if($s['checked_in_at'])<div class="small text-muted">{{ $s['checked_in_at']->format('g:ia') }} · {{ \App\Models\Checkin\Record::METHODS[$s['method']] ?? '' }}</div>@endif
                                @if($s['note'] && !$s['checked_in_at'])<div class="small text-muted">{{ $s['note'] }}</div>@endif
                            @elseif($s['date']->isFuture() || $s['date']->isToday())
                                <span class="text-muted small">Coming up</span>
                            @else
                                <span class="text-muted small">Not marked</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
        @endif
    </x-cb.card>

</div></div></div>
@endsection
