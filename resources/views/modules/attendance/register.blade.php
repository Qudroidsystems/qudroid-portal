@extends('layouts.master')

@section('content')
@php $editable = $canTake && $session->status !== 'cancelled' && !$session->date->isFuture(); @endphp
<div class="main-content"><div class="page-content"><div class="container-fluid">

    <x-cb.hero :title="'Register: ' . $session->date->format('D j M Y')" icon="ri-file-list-3-line" :subtitle="$session->cohort->name . ' · ' . $session->cohort->course->title" :back="route('attendance.cohort', $session->cohort)" back-label="Sessions">
        <x-slot:pills>
            @if($session->timeRange())<span class="cb-meta-pill"><i class="ri-time-line me-1"></i>{{ $session->timeRange() }}</span>@endif
            <span class="cb-meta-pill">{{ $session->status_label }}</span>
            @if($session->takenBy)<span class="cb-meta-pill">by {{ $session->takenBy->name }}, {{ $session->register_taken_at->format('j M, g:ia') }}</span>@endif
        </x-slot:pills>
        @if($session->status !== 'cancelled' && \Illuminate\Support\Facades\Route::has('checkin.session'))
            <x-slot:actions>
                <form method="POST" action="{{ route('checkin.session', $session) }}">@csrf<button class="cb-hero-btn"><i class="ri-qr-scan-2-line"></i>QR check-in</button></form>
            </x-slot:actions>
        @endif
    </x-cb.hero>

    @include('partials.flash')

    @if($session->status === 'cancelled')
        <div class="cb-banner info"><i class="ri-close-circle-line"></i><div>This session was cancelled: {{ $session->cancel_reason }}</div></div>
    @elseif($session->date->isFuture())
        <div class="cb-banner info"><i class="ri-calendar-line"></i><div>The register opens on the day of the session.</div></div>
    @endif

    <form method="POST" action="{{ route('attendance.register.save', $session) }}">
        @csrf
        <x-cb.card title="Students" icon="ri-team-line" :count="$roster->count()" :flush="true">
            @if($editable && $roster->isNotEmpty())
                <x-slot:tools><button type="button" class="btn btn-sm btn-soft-success" onclick="document.querySelectorAll('[data-present]').forEach(function (r) { r.checked = true; })">Everyone present</button></x-slot:tools>
            @endif
            @if($editable)<div class="p-3 border-bottom"><input name="topic" class="form-control" maxlength="200" value="{{ $session->topic }}" placeholder="What was covered (optional)"></div>@endif
            @if($roster->isEmpty())
                <div class="empty-state"><i class="ri-team-line"></i><h6>No students on this cohort</h6></div>
            @else
                <div class="table-responsive"><table class="cb-table stack">
                    <thead><tr><th>Student</th><th>Mark</th><th>Note</th></tr></thead>
                    <tbody>
                    @foreach($roster as $e)
                        @php $m = $marks->get($e->id); $current = old("marks.{$e->id}.status", $m?->status); @endphp
                        <tr>
                            <td data-label="Student"><span class="fw-semibold">{{ $e->student->user->name }}</span><div class="small text-muted">{{ $e->student->student_no }}@if($e->status === 'pending') · awaiting payment @endif</div></td>
                            <td data-label="Mark">
                                <div class="btn-group btn-group-sm flex-wrap" role="group">
                                    @foreach(\App\Models\Academy\Attendance::STATUSES as $k => $v)
                                        @php $tone = ['present' => 'success', 'late' => 'warning', 'absent' => 'danger', 'excused' => 'secondary'][$k]; @endphp
                                        <input type="radio" class="btn-check" name="marks[{{ $e->id }}][status]" value="{{ $k }}" id="m{{ $e->id }}{{ $k }}" @checked($current === $k) @disabled(!$editable) @if($k === 'present') data-present @endif>
                                        <label class="btn btn-outline-{{ $tone }}" for="m{{ $e->id }}{{ $k }}">{{ $v }}</label>
                                    @endforeach
                                </div>
                            </td>
                            <td data-label="Note"><input name="marks[{{ $e->id }}][note]" class="form-control form-control-sm" maxlength="300" value="{{ old("marks.{$e->id}.note", $m?->note) }}" @disabled(!$editable)></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table></div>
            @endif
        </x-cb.card>
        @if($editable && $roster->isNotEmpty())<button class="btn btn-primary">Save register</button>@endif
    </form>

</div></div></div>
@endsection
