@extends('layouts.guest')
@section('title', 'Apply to the Academy')

@section('content')
    <div class="mb-4">
        <h2 class="fw-semibold mb-1">Apply to {{ $brand['company'] ?? 'Qudroid Systems' }} Academy</h2>
        <p class="text-muted mb-0">Choose a course and the intake you want to join. It takes about five minutes.</p>
    </div>

    @if($courses->isEmpty())
        <div class="apply-course empty-state py-5"><i class="ri-calendar-close-line"></i><h6>No intakes are open right now</h6><p>Please check back soon.</p></div>
    @endif

    @foreach($courses as $course)
        <div class="apply-course mb-3">
            <div class="p-3 p-md-4">
                <div class="d-flex flex-wrap justify-content-between gap-2">
                    <div>
                        <h5 class="mb-1">{{ $course->title }}</h5>
                        <div class="small text-muted">{{ ucfirst($course->level) }} · {{ ucfirst($course->delivery_mode) }}@if($course->duration_weeks) · {{ $course->duration_weeks }} weeks @endif</div>
                    </div>
                    <div class="text-md-end">
                        <div class="fw-semibold">{{ \App\Support\Money::format($course->fee, $course->currency) }}</div>
                        @if($course->installments_allowed)<div class="small text-muted">Instalments available</div>@endif
                    </div>
                </div>
                @if($course->summary)<p class="mt-2 mb-0">{{ $course->summary }}</p>@endif
            </div>
            @foreach($course->cohorts as $cohort)
                <div class="apply-cohort p-3 px-md-4 d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <div>
                        <div class="fw-medium">{{ $cohort->name }}</div>
                        <div class="small text-muted">
                            @if($cohort->starts_on)Starts {{ $cohort->starts_on->format('j M Y') }}@endif
                            @if($cohort->schedule) · {{ $cohort->schedule }}@endif
                            @if($cohort->registration_closes_on) · Apply by {{ $cohort->registration_closes_on->format('j M Y') }}@endif
                            @if($cohort->fee_override !== null) · {{ \App\Support\Money::format($cohort->fee_override, $course->currency) }}@endif
                        </div>
                    </div>
                    @if($cohort->capacity && $cohort->seatsLeft() <= 0)
                        <span class="badge bg-dark-subtle text-dark">Full</span>
                    @else
                        <a href="{{ route('apply.create', $cohort) }}" class="btn btn-primary btn-sm">Apply</a>
                    @endif
                </div>
            @endforeach
        </div>
    @endforeach
@endsection
