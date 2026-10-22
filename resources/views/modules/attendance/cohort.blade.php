@extends('layouts.master')

@section('content')
@php $pct = fn ($v) => $v === null ? '—' : rtrim(rtrim(number_format((float) $v, 1), '0'), '.') . '%'; @endphp
<div class="main-content"><div class="page-content"><div class="container-fluid">

    <x-cb.hero :title="'Attendance: ' . $cohort->name" icon="ri-calendar-check-line" :subtitle="$cohort->course->title" :back="route('attendance.index')" back-label="Attendance">
        <x-slot:pills>
            @if($cohort->schedule)<span class="cb-meta-pill"><i class="ri-time-line me-1"></i>{{ $cohort->schedule }}</span>@endif
            <span class="cb-meta-pill">{{ $sessions->where('status', 'held')->count() }} of {{ $sessions->where('status', '!=', 'cancelled')->count() }} registers taken</span>
            @if($cohort->instructor)<span class="cb-meta-pill"><i class="ri-user-star-line me-1"></i>{{ $cohort->instructor->name }}</span>@endif
        </x-slot:pills>
        @can('Manage academy')
            <x-slot:actions><a href="{{ route('academy.cohorts.show', $cohort) }}" class="cb-hero-btn"><i class="ri-team-line"></i>Cohort</a></x-slot:actions>
        @endcan
    </x-cb.hero>

    @include('partials.flash')

    <div class="row g-3">
        <div class="col-xl-7">
            <x-cb.card title="Sessions" icon="ri-calendar-line" :count="$sessions->count()" :flush="true">
                @if($sessions->isEmpty())
                    <div class="empty-state"><i class="ri-calendar-line"></i><h6>No sessions yet</h6>@if($canTake)<p>Add them with the forms on the right.</p>@endif</div>
                @else
                    <div class="table-responsive"><table class="cb-table stack">
                        <thead><tr><th>Date</th><th>Topic</th><th>Register</th><th></th></tr></thead>
                        <tbody>
                        @foreach($sessions as $s)
                            <tr class="{{ $s->date->isToday() ? 'table-active' : '' }}">
                                <td data-label="Date"><span class="fw-semibold">{{ $s->date->format('D j M Y') }}</span>@if($s->timeRange())<div class="small text-muted">{{ $s->timeRange() }}</div>@endif</td>
                                <td data-label="Topic">{{ $s->topic ?? '—' }}@if($s->status === 'cancelled')<div class="small text-danger">Cancelled: {{ $s->cancel_reason }}</div>@endif</td>
                                <td data-label="Register">
                                    @if($s->status === 'held')<span class="text-success"><i class="ri-checkbox-circle-line"></i> {{ $s->present_count }} present</span>
                                    @elseif($s->status === 'cancelled')<span class="text-muted">—</span>
                                    @elseif($s->date->isFuture())<span class="text-muted small">Not yet</span>
                                    @else<span class="text-warning small">Not taken</span>@endif
                                </td>
                                <td class="text-end">
                                    <div class="d-flex gap-1 justify-content-end">
                                        @if($s->status !== 'cancelled')
                                            <a href="{{ route('attendance.register', $s) }}" class="btn btn-sm {{ $s->status === 'scheduled' && !$s->date->isFuture() && $canTake ? 'btn-primary' : 'btn-light' }}">{{ $s->status === 'held' ? 'View' : ($canTake && !$s->date->isFuture() ? 'Take register' : 'Open') }}</a>
                                        @endif
                                        @if($canTake && $s->status === 'scheduled')
                                            <button type="button" class="btn btn-sm btn-light" data-bs-toggle="collapse" data-bs-target="#cancel-{{ $s->id }}" title="Cancel or remove"><i class="ri-more-2-line"></i></button>
                                        @endif
                                    </div>
                                    @if($canTake && $s->status === 'scheduled')
                                        <div class="collapse mt-2" id="cancel-{{ $s->id }}">
                                            <form method="POST" action="{{ route('attendance.sessions.cancel', $s) }}" class="input-group input-group-sm mb-1">@csrf<input name="reason" class="form-control" required maxlength="300" placeholder="Why cancelled?"><button class="btn btn-soft-warning">Cancel</button></form>
                                            <form method="POST" action="{{ route('attendance.sessions.destroy', $s) }}" onsubmit="return confirm('Remove this session? Use this for sessions added by mistake.')">@csrf @method('DELETE')<button class="btn btn-sm btn-link text-danger p-0">Remove (added by mistake)</button></form>
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table></div>
                @endif
            </x-cb.card>
        </div>
        <div class="col-xl-5">
            <x-cb.card title="Attendance rate" icon="ri-percent-line" :flush="true">
                @if($enrollments->isEmpty())
                    <div class="p-3 small text-muted">No students on this cohort.</div>
                @else
                    <div class="list-group list-group-flush">
                        @foreach($enrollments as $e)
                            <div class="list-group-item d-flex justify-content-between">
                                <span>{{ $e->student->user->name }}<span class="d-block small text-muted">{{ $e->status_label }}</span></span>
                                <strong class="{{ $e->attendance_percent === null ? 'text-muted' : ($e->attendance_percent >= 75 ? 'text-success' : 'text-danger') }}">{{ $pct($e->attendance_percent) }}</strong>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-cb.card>

            @if($canTake)
                <x-cb.card title="Add sessions for a period" icon="ri-calendar-2-line">
                    <form method="POST" action="{{ route('attendance.sessions.schedule', $cohort) }}">@csrf
                        <div class="row g-2 mb-2">
                            <div class="col-6"><label class="form-label small">From</label><input type="date" name="from" class="form-control form-control-sm" required value="{{ old('from', ($cohort->starts_on ?? today())->toDateString()) }}"></div>
                            <div class="col-6"><label class="form-label small">To</label><input type="date" name="to" class="form-control form-control-sm" required value="{{ old('to', $cohort->ends_on?->toDateString()) }}"></div>
                        </div>
                        <div class="mb-2 d-flex flex-wrap gap-2">
                            @foreach(['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $i => $day)
                                <div class="form-check form-check-inline me-0"><input class="form-check-input" type="checkbox" name="weekdays[]" value="{{ $i }}" id="wd{{ $i }}" @checked(in_array($i, old('weekdays', [])))><label class="form-check-label small" for="wd{{ $i }}">{{ $day }}</label></div>
                            @endforeach
                        </div>
                        <div class="row g-2 mb-2">
                            <div class="col-6"><label class="form-label small">Starts</label><input type="time" name="starts_at" class="form-control form-control-sm" value="{{ old('starts_at') }}"></div>
                            <div class="col-6"><label class="form-label small">Ends</label><input type="time" name="ends_at" class="form-control form-control-sm" value="{{ old('ends_at') }}"></div>
                        </div>
                        <button class="btn btn-sm btn-primary w-100">Add sessions</button>
                    </form>
                </x-cb.card>
                <x-cb.card title="Add one session" icon="ri-add-line">
                    <form method="POST" action="{{ route('attendance.sessions.store', $cohort) }}">@csrf
                        <div class="row g-2 mb-2">
                            <div class="col-12"><input type="date" name="date" class="form-control form-control-sm" required value="{{ today()->toDateString() }}"></div>
                            <div class="col-6"><input type="time" name="starts_at" class="form-control form-control-sm"></div>
                            <div class="col-6"><input type="time" name="ends_at" class="form-control form-control-sm"></div>
                            <div class="col-12"><input name="topic" class="form-control form-control-sm" maxlength="200" placeholder="Topic (optional)"></div>
                        </div>
                        <button class="btn btn-sm btn-light w-100">Add session</button>
                    </form>
                </x-cb.card>
            @endif
        </div>
    </div>

</div></div></div>
@endsection
