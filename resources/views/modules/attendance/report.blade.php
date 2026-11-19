@extends('layouts.master')

@section('content')
@php
    $pct = fn ($v) => $v === null ? '—' : rtrim(rtrim(number_format((float) $v, 1), '0'), '.') . '%';
    $f = $filters;
@endphp
<div class="main-content"><div class="page-content"><div class="container-fluid">

    <x-cb.hero title="Attendance report" icon="ri-bar-chart-grouped-line" subtitle="Every student's attendance, by course and class." :back="route('attendance.index')" back-label="Attendance">
        <x-slot:pills>
            <span class="cb-meta-pill">{{ $summary['students'] }} {{ \Illuminate\Support\Str::plural('student', $summary['students']) }}</span>
            <span class="cb-meta-pill">Average {{ $pct($summary['average']) }}</span>
            @if($summary['low'])<span class="cb-meta-pill text-danger">{{ $summary['low'] }} below 75%</span>@endif
        </x-slot:pills>
        <x-slot:actions>
            <a href="{{ route('attendance.report', array_merge($f, ['export' => 1])) }}" class="cb-hero-btn"><i class="ri-download-2-line"></i>Download CSV</a>
        </x-slot:actions>
    </x-cb.hero>

    @include('partials.flash')

    <x-cb.card>
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3"><label class="form-label small">Course</label>
                <select name="course_id" class="form-select form-select-sm"><option value="">All courses</option>
                    @foreach($courses as $c)<option value="{{ $c->id }}" @selected(($f['course_id'] ?? null) == $c->id)>{{ $c->title }}</option>@endforeach
                </select></div>
            <div class="col-md-3"><label class="form-label small">Class</label>
                <select name="cohort_id" class="form-select form-select-sm"><option value="">All classes</option>
                    @foreach($cohorts as $c)<option value="{{ $c->id }}" @selected(($f['cohort_id'] ?? null) == $c->id)>{{ $c->course?->title }} · {{ $c->name }}</option>@endforeach
                </select></div>
            <div class="col-md-2"><label class="form-label small">From</label><input type="date" name="from" value="{{ $f['from'] ?? '' }}" class="form-control form-control-sm"></div>
            <div class="col-md-2"><label class="form-label small">To</label><input type="date" name="to" value="{{ $f['to'] ?? '' }}" class="form-control form-control-sm"></div>
            <div class="col-md-1"><label class="form-label small">Below %</label><input type="number" name="below" min="1" max="100" value="{{ $f['below'] ?? '' }}" placeholder="75" class="form-control form-control-sm"></div>
            <div class="col-md-1 d-grid"><button class="btn btn-sm btn-primary">Show</button></div>
        </form>
    </x-cb.card>

    <form method="POST" action="{{ route('attendance.nudge') }}">
        @csrf
        <x-cb.card title="Students" icon="ri-team-line" :count="$rows->count()" :flush="true">
            @if($rows->isNotEmpty())
                <x-slot:tools>
                    <button type="button" class="btn btn-sm btn-soft-secondary" onclick="document.querySelectorAll('[data-low]').forEach(function (c) { c.checked = true; })">Tick everyone below 75%</button>
                </x-slot:tools>
            @endif
            @if($rows->isEmpty())
                <div class="empty-state"><i class="ri-bar-chart-grouped-line"></i><h6>No students match</h6><p>Change the filters above.</p></div>
            @else
                <div class="table-responsive"><table class="cb-table stack">
                    <thead><tr><th style="width:32px"></th><th>Student</th><th>Course · class</th><th class="num">Present</th><th class="num">Late</th><th class="num">Absent</th><th class="num">Excused</th><th class="num">Rate</th></tr></thead>
                    <tbody>
                    @foreach($rows as $r)
                        @php $low = $r['rate'] !== null && $r['rate'] < 75; @endphp
                        <tr>
                            <td><input type="checkbox" class="form-check-input" name="enrollments[]" value="{{ $r['enrollment']->id }}" @if($low) data-low @endif aria-label="Select {{ $r['name'] }}"></td>
                            <td data-label="Student"><span class="fw-semibold">{{ $r['name'] }}</span><div class="small text-muted">{{ $r['student_no'] }}</div></td>
                            <td data-label="Course"><a href="{{ route('attendance.cohort', $r['enrollment']->cohort_id) }}">{{ $r['course'] }} · {{ $r['cohort'] }}</a></td>
                            <td data-label="Present" class="num">{{ $r['present'] }}</td>
                            <td data-label="Late" class="num">{{ $r['late'] }}</td>
                            <td data-label="Absent" class="num">{{ $r['absent'] }}</td>
                            <td data-label="Excused" class="num">{{ $r['excused'] }}</td>
                            <td data-label="Rate" class="num fw-semibold {{ $r['rate'] === null ? 'text-muted' : ($low ? 'text-danger' : 'text-success') }}">{{ $pct($r['rate']) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table></div>
                <div class="p-3 border-top row g-2 align-items-center">
                    <div class="col-md-8"><input name="message" maxlength="500" class="form-control form-control-sm" placeholder="Message to the ticked students (optional): e.g. Please see me after class on Friday."></div>
                    <div class="col-md-4 d-grid"><button class="btn btn-sm btn-warning"><i class="ri-notification-3-line me-1"></i>Notify ticked students</button></div>
                    <div class="col-12 form-text">They get a notification on their phone with their attendance rate and your message.</div>
                </div>
            @endif
        </x-cb.card>
    </form>

</div></div></div>
@endsection
