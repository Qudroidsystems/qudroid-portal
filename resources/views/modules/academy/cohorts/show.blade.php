@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">

    @php $course = $cohort->course; $money = fn ($v) => \App\Support\Money::format($v, $course->currency); @endphp
    <x-cb.hero :title="$cohort->name" icon="ri-team-line" :subtitle="$course->title" :back="route('academy.courses.show', $course)" back-label="Course">
        <x-slot:pills>
            <span class="cb-meta-pill">{{ $cohort->code }}</span>
            <span class="cb-meta-pill">{{ $cohort->status_label }}</span>
            <span class="cb-meta-pill"><i class="ri-calendar-line me-1"></i>{{ $cohort->starts_on?->format('j M Y') ?? 'No start date' }} – {{ $cohort->ends_on?->format('j M Y') ?? '…' }}</span>
            @if($cohort->instructor)<span class="cb-meta-pill"><i class="ri-user-star-line me-1"></i>{{ $cohort->instructor->name }}</span>@endif
            <span class="cb-meta-pill">{{ \App\Models\Academy\Course::MODES[$cohort->effectiveMode()] ?? $cohort->effectiveMode() }}</span>
            <span class="cb-meta-pill"><i class="ri-money-dollar-circle-line me-1"></i>{{ $money($cohort->effectiveFee()) }}</span>
        </x-slot:pills>
        <x-slot:actions><a href="{{ route('attendance.cohort', $cohort) }}" class="cb-hero-btn"><i class="ri-calendar-check-line"></i>Attendance</a><a href="{{ route('academy.cohorts.gradebook', $cohort) }}" class="cb-hero-btn"><i class="ri-table-line"></i>Gradebook</a><a href="{{ route('academy.cohorts.edit', $cohort) }}" class="cb-hero-btn"><i class="ri-edit-line"></i>Edit</a></x-slot:actions>
    </x-cb.hero>

    @include('partials.flash')

    @php $holding = $enrollments->filter->isOpen(); @endphp
    <div class="row g-3 mb-3">
        <div class="col-md-3"><x-cb.stat label="Seats taken" :value="$holding->count() . ($cohort->capacity ? ' / ' . $cohort->capacity : '')" icon="ri-team-line" accent="teal" /></div>
        <div class="col-md-3"><x-cb.stat label="Access open" :value="$holding->where('access_granted', true)->count()" icon="ri-lock-unlock-line" accent="green" /></div>
        <div class="col-md-3"><x-cb.stat label="Awaiting payment" :value="$holding->where('access_granted', false)->count()" icon="ri-time-line" accent="amber" /></div>
        <div class="col-md-3"><x-cb.stat label="Fees billed (net)" :value="$money($holding->sum(fn ($e) => (float) $e->netFee()))" icon="ri-money-dollar-circle-line" accent="sky" /></div>
    </div>

    <div class="row g-3">
        <div class="col-xl-8">
            <x-cb.card title="Students" icon="ri-graduation-cap-line" :count="$enrollments->count()" :flush="true">
                @if($enrollments->isEmpty())
                    <div class="empty-state"><i class="ri-user-add-line"></i><h6>No students yet</h6><p>Enroll students using the form on the right.</p></div>
                @else
                    <div class="table-responsive"><table class="cb-table stack">
                        <thead><tr><th>Student</th><th>Status</th><th>Access</th><th>Progress</th><th class="num">Fee</th><th></th></tr></thead>
                        <tbody>
                        @foreach($enrollments as $e)
                            <tr>
                                <td data-label="Student"><a href="{{ route('academy.students.show', $e->student_id) }}" class="fw-semibold">{{ $e->student->user->name }}</a><div class="small text-muted">{{ $e->student->student_no }}</div></td>
                                <td data-label="Status">@include('modules.academy.partials.pill', ['status' => $e->status, 'label' => $e->status_label])</td>
                                <td data-label="Access">@if($e->access_granted)<span class="text-success" title="{{ $e->access_reason }}"><i class="ri-lock-unlock-line"></i> Open</span><div class="small text-muted">{{ $e->access_reason }}</div>@else<span class="text-muted"><i class="ri-lock-line"></i> Locked</span>@endif</td>
                                <td data-label="Progress">@if($e->access_granted)<div class="d-flex align-items-center gap-2" style="min-width: 110px"><div class="progress flex-grow-1" style="height:6px"><div class="progress-bar bg-success" style="width: {{ $e->progress_percent }}%"></div></div><small>{{ $e->progress_percent }}%</small></div>@if($e->grade_percent !== null)<div class="small">Grade {{ rtrim(rtrim(number_format((float) $e->grade_percent, 1), '0'), '.') }}%</div>@endif @if($e->attendance_percent !== null)<div class="small">Attendance {{ rtrim(rtrim(number_format((float) $e->attendance_percent, 1), '0'), '.') }}%</div>@endif @if($e->last_activity_at)<div class="small text-muted">{{ $e->last_activity_at->diffForHumans() }}</div>@endif @else — @endif</td>
                                <td data-label="Fee" class="num">{{ $money($e->netFee()) }}@if((float) $e->discount_amount > 0)<div class="small text-muted">less {{ $money($e->discount_amount) }}</div>@endif @can('View finance')@foreach($e->invoices as $inv)<div class="small"><a href="{{ route('finance.invoices.show', $inv) }}">{{ $inv->number }}</a> · {{ $inv->status_label }}</div>@endforeach @endcan</td>
                                <td>@include('modules.academy.partials.enrollment-actions', ['e' => $e])</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table></div>
                @endif
            </x-cb.card>
        </div>
        <div class="col-xl-4">
            <x-cb.card title="Enroll a student" icon="ri-user-add-line">
                @if(!in_array($cohort->status, \App\Models\Academy\Cohort::ENROLLABLE, true))
                    <p class="text-muted mb-0">This cohort is {{ strtolower($cohort->status_label) }}. Set it to open or running to enroll students.</p>
                @elseif($candidates->isEmpty())
                    <p class="text-muted mb-0">No admitted students left to add. <a href="{{ route('academy.students.create') }}">Add a student</a>.</p>
                @else
                    <form method="POST" action="{{ route('academy.enrollments.store') }}">
                        @csrf
                        <input type="hidden" name="cohort_id" value="{{ $cohort->id }}">
                        <div class="mb-2"><label class="form-label">Student</label>
                            <select name="student_id" class="form-select" required><option value="">Choose…</option>
                                @foreach($candidates as $s)<option value="{{ $s->id }}" @selected(old('student_id') == $s->id)>{{ $s->user->name }} ({{ $s->student_no }})</option>@endforeach
                            </select>
                        </div>
                        <div class="mb-2"><label class="form-label">Discount</label><input name="discount_amount" type="number" step="0.01" min="0" max="{{ $cohort->effectiveFee() }}" class="form-control" value="{{ old('discount_amount', 0) }}"><div class="form-text">Scholarships and discounts, taken off {{ $money($cohort->effectiveFee()) }}.</div></div>
                        @can('Grant course access')
                            @if($course->prerequisites()->exists())
                                <div class="form-check mb-2"><input type="hidden" name="waive_prerequisites" value="0"><input class="form-check-input" type="checkbox" id="waive" name="waive_prerequisites" value="1"><label class="form-check-label" for="waive">Waive prerequisites</label></div>
                            @endif
                        @endcan
                        <div class="mb-3"><label class="form-label">Note</label><input name="notes" class="form-control" maxlength="2000" value="{{ old('notes') }}"></div>
                        <button class="btn btn-primary w-100">Enroll</button>
                    </form>
                @endif
            </x-cb.card>
        </div>
    </div>

</div></div></div>
@endsection
