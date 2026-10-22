@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">

    <x-cb.hero title="My academy" icon="ri-graduation-cap-line" :subtitle="$student ? 'Student number ' . $student->student_no : 'Your courses and the cohorts you teach.'">
        @if($student)
            <x-slot:pills><span class="cb-meta-pill"><i class="ri-user-3-line me-1"></i>{{ $student->status_label }}</span></x-slot:pills>
        @endif
        <x-slot:actions><a href="{{ route('academy.courses.index') }}" class="cb-hero-btn"><i class="ri-book-2-line"></i>Browse courses</a></x-slot:actions>
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
                            <td data-label="Course"><a href="{{ route('academy.courses.show', $e->course) }}" class="fw-semibold">{{ $e->course->title }}</a></td>
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
                        <td data-label="Students" class="num">{{ $c->seats }}</td>
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
