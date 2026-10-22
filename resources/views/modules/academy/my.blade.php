@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">

    <x-cb.hero title="My academy" icon="ri-graduation-cap-line" :subtitle="$student ? 'Student number ' . $student->student_no : 'Your courses and the cohorts you teach.'">
        @if($student)
            <x-slot:pills><span class="cb-meta-pill"><i class="ri-user-3-line me-1"></i>{{ $student->status_label }}</span></x-slot:pills>
        @endif
        <x-slot:actions>
            @if($student)<a href="{{ route('apply.index') }}" class="cb-hero-btn"><i class="ri-file-add-line"></i>Apply for a course</a>@endif
            <a href="{{ route('academy.courses.index') }}" class="cb-hero-btn"><i class="ri-book-2-line"></i>Browse courses</a>
        </x-slot:actions>
    </x-cb.hero>

    @include('partials.flash')

    @if($student)
        <x-cb.card title="My courses" icon="ri-book-open-line" :count="$enrollments->count()" :flush="true">
            @if($enrollments->isEmpty())
                <div class="empty-state"><i class="ri-book-open-line"></i><h6>No courses yet</h6><p>Once you are enrolled on a course it will appear here.</p></div>
            @else
                <div class="table-responsive"><table class="cb-table stack">
                    <thead><tr><th>Course</th><th>Cohort</th><th>Status</th><th>Access</th><th class="num">Fee</th></tr></thead>
                    <tbody>
                    @foreach($enrollments as $e)
                        <tr>
                            <td data-label="Course"><a href="{{ route('academy.courses.show', $e->course) }}" class="fw-semibold">{{ $e->course->title }}</a>
                                @if($e->canLearn())
                                    <div class="d-flex align-items-center gap-2 mt-1" style="max-width: 260px">
                                        <div class="progress flex-grow-1" style="height:6px"><div class="progress-bar bg-success" style="width: {{ $e->progress_percent }}%"></div></div>
                                        <small class="text-muted">{{ $e->progress_percent }}%</small>
                                    </div>
                                    <a href="{{ route('academy.learn.course', $e->course) }}" class="btn btn-sm btn-soft-success mt-1"><i class="ri-play-line"></i> {{ $e->status === 'completed' ? 'Revise' : ($e->progress_percent ? 'Continue' : 'Start') }}</a>
                                    @if($e->grade_percent !== null)<div class="small mt-1">Grade: <strong>{{ rtrim(rtrim(number_format((float) $e->grade_percent, 1), '0'), '.') }}%</strong></div>@endif
                                    @if($e->attendance_percent !== null)<div class="small">Attendance: <strong>{{ rtrim(rtrim(number_format((float) $e->attendance_percent, 1), '0'), '.') }}%</strong></div>@endif
                                @endif
                                @if($e->certificate && $e->certificate->status === 'issued')
                                    <a href="{{ route('certificates.show', $e->certificate) }}" class="btn btn-sm btn-warning mt-1"><i class="ri-award-line"></i> Certificate</a>
                                @endif
                            </td>
                            <td data-label="Cohort">{{ $e->cohort->name }}<div class="small text-muted">{{ $e->cohort->starts_on?->format('j M Y') }}@if($e->cohort->instructor) · {{ $e->cohort->instructor->name }}@endif</div></td>
                            <td data-label="Status">@include('modules.academy.partials.pill', ['status' => $e->status, 'label' => $e->status_label])</td>
                            <td data-label="Access">@if($e->access_granted)<span class="text-success"><i class="ri-lock-unlock-line"></i> Open</span>@elseif($e->isOpen())<span class="text-muted"><i class="ri-lock-line"></i> After payment</span>@else — @endif</td>
                            <td data-label="Fee" class="num">{{ \App\Support\Money::format($e->netFee(), $e->currency) }}
                                @php $inv = $e->invoices->firstWhere('status', '!=', 'void'); @endphp
                                @if($inv && $inv->isOpen())<div><a href="{{ route('billing.invoice', $inv) }}" class="btn btn-sm btn-primary mt-1">Pay {{ \App\Support\Money::format($inv->balance(), $inv->currency) }}</a></div>
                                @elseif($inv)<div class="small"><a href="{{ route('billing.invoice', $inv) }}">Invoice {{ $inv->status_label }}</a></div>@endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table></div>
            @endif
        </x-cb.card>
    @endif

    @if($applications->isNotEmpty())
        <x-cb.card title="My applications" icon="ri-file-list-3-line" :count="$applications->count()" :flush="true">
            <div class="table-responsive"><table class="cb-table stack">
                <thead><tr><th>Reference</th><th>Course</th><th>Status</th><th></th></tr></thead>
                <tbody>
                @foreach($applications as $a)
                    <tr>
                        <td data-label="Reference">{{ $a->reference }}<div class="small text-muted">{{ $a->created_at->format('j M Y') }}</div></td>
                        <td data-label="Course">{{ $a->course->title }}<div class="small text-muted">{{ $a->cohort->name }}</div></td>
                        <td data-label="Status">@include('modules.academy.partials.pill', ['status' => $a->status, 'label' => $a->status_label])
                            @if($a->status === 'declined' && $a->decision_note)<div class="small text-muted">{{ $a->decision_note }}</div>@endif</td>
                        <td class="text-end">@if($a->status === 'submitted')
                            <form method="POST" action="{{ route('academy.applications.withdraw', $a) }}" onsubmit="return confirm('Withdraw this application?')">@csrf<button class="btn btn-sm btn-soft-danger">Withdraw</button></form>
                        @endif</td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
        </x-cb.card>
    @endif

    @if($teaching->isNotEmpty())
        <x-cb.card title="Cohorts I teach" icon="ri-presentation-line" :count="$teaching->count()" :flush="true">
            <div class="table-responsive"><table class="cb-table stack">
                <thead><tr><th>Cohort</th><th>Dates</th><th>Status</th><th class="num">Students</th></tr></thead>
                <tbody>
                @foreach($teaching as $c)
                    <tr>
                        <td data-label="Cohort"><span class="fw-semibold">{{ $c->name }}</span><div class="small text-muted">{{ $c->course->title }}</div></td>
                        <td data-label="Dates">{{ $c->starts_on?->format('j M Y') ?? '—' }} – {{ $c->ends_on?->format('j M Y') ?? '—' }}</td>
                        <td data-label="Status">@include('modules.academy.partials.pill', ['status' => $c->status, 'label' => $c->status_label])</td>
                        <td data-label="Students" class="num">{{ $c->seats }}@can('View attendance')<div><a href="{{ route('attendance.cohort', $c) }}" class="small">Attendance</a></div>@endcan</td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
        </x-cb.card>
    @endif

    @if(!$student && $teaching->isEmpty())
        <x-cb.card><div class="empty-state"><i class="ri-book-open-line"></i><h6>Nothing here yet</h6><p>You are not enrolled on a course and are not teaching a cohort.</p></div></x-cb.card>
    @endif

</div></div></div>
@endsection
