@extends('layouts.master')

@section('content')
@php $user = $application->student->user; @endphp
<div class="main-content"><div class="page-content"><div class="container-fluid">

    <x-cb.hero :title="$user->name" icon="ri-user-search-line" :subtitle="'Application ' . $application->reference . ' · ' . $application->course->title" :back="route('academy.applications.index')" back-label="Applications">
        <x-slot:pills>
            <span class="cb-meta-pill">{{ $application->status_label }}</span>
            <span class="cb-meta-pill">Received {{ $application->created_at->format('j M Y, g:ia') }}</span>
        </x-slot:pills>
    </x-cb.hero>

    @include('partials.flash')

    <div class="row g-3">
        <div class="col-xl-7">
            <x-cb.card title="Application" icon="ri-file-text-line">
                <dl class="row mb-0">
                    <dt class="col-sm-4">Course</dt><dd class="col-sm-8"><a href="{{ route('academy.courses.show', $application->course) }}">{{ $application->course->title }}</a></dd>
                    <dt class="col-sm-4">Cohort</dt><dd class="col-sm-8"><a href="{{ route('academy.cohorts.show', $application->cohort) }}">{{ $application->cohort->name }}</a>
                        <span class="small text-muted">@if($application->cohort->capacity) · {{ $application->cohort->seatsLeft() }} of {{ $application->cohort->capacity }} seats left @endif · {{ $application->cohort->status_label }}</span></dd>
                    <dt class="col-sm-4">Fee</dt><dd class="col-sm-8">{{ \App\Support\Money::format($application->cohort->effectiveFee(), $application->course->currency) }}</dd>
                    <dt class="col-sm-4">Coding experience</dt><dd class="col-sm-8">{{ \App\Models\Academy\Application::EXPERIENCE[$application->experience_level] ?? '—' }}</dd>
                    <dt class="col-sm-4">Has a laptop</dt><dd class="col-sm-8">{{ $application->has_laptop === null ? '—' : ($application->has_laptop ? 'Yes' : 'No') }}</dd>
                    <dt class="col-sm-4">Why they want to join</dt><dd class="col-sm-8" style="white-space: pre-line">{{ $application->motivation ?? '—' }}</dd>
                </dl>
            </x-cb.card>

            @if($application->status === 'submitted')
                <x-cb.card title="Decision" icon="ri-scales-3-line">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <form method="POST" action="{{ route('academy.applications.admit', $application) }}">@csrf
                                <label class="form-label">Note for the record <span class="text-muted">(optional)</span></label>
                                <textarea name="decision_note" class="form-control mb-2" rows="2" maxlength="500"></textarea>
                                <button class="btn btn-success w-100"><i class="ri-check-line me-1"></i>Admit and enroll</button>
                                <div class="form-text">Admits {{ $user->name }} and enrolls them on {{ $application->cohort->name }}. The fee invoice is issued and course access opens once payment is confirmed.</div>
                            </form>
                        </div>
                        <div class="col-md-6">
                            <form method="POST" action="{{ route('academy.applications.decline', $application) }}">@csrf
                                <label class="form-label">Reason <span class="text-muted">(the applicant sees this)</span></label>
                                <textarea name="decision_note" class="form-control mb-2" rows="2" maxlength="500" required></textarea>
                                <button class="btn btn-soft-danger w-100"><i class="ri-close-line me-1"></i>Decline</button>
                            </form>
                        </div>
                    </div>
                </x-cb.card>
            @else
                <x-cb.card title="Decision" icon="ri-scales-3-line">
                    <p class="mb-1">@include('modules.academy.partials.pill', ['status' => $application->status, 'label' => $application->status_label])
                        @if($application->reviewer) by {{ $application->reviewer->name }} on {{ $application->reviewed_at->format('j M Y') }}@endif</p>
                    @if($application->decision_note)<p class="mb-1">{{ $application->decision_note }}</p>@endif
                    @if($application->enrollment)<p class="mb-0 text-success"><i class="ri-checkbox-circle-line"></i> Enrolled on {{ $application->cohort->name }} ({{ $application->enrollment->status_label }}).</p>
                    @elseif($application->status === 'admitted')<p class="mb-0 text-warning"><i class="ri-error-warning-line"></i> Not enrolled yet. <a href="{{ route('academy.students.show', $application->student) }}">Enroll from the student record</a>.</p>@endif
                </x-cb.card>
            @endif
        </div>

        <div class="col-xl-5">
            <x-cb.card title="Applicant" icon="ri-user-3-line">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <img src="{{ $user->avatar_url }}" class="rounded-circle" width="48" height="48" alt="">
                    <div><a href="{{ route('academy.students.show', $application->student) }}" class="fw-semibold">{{ $user->name }}</a>
                        <div class="small text-muted">{{ $application->student->student_no }} · {{ $application->student->status_label }}</div></div>
                </div>
                <dl class="row mb-0 small">
                    <dt class="col-5">E-mail</dt><dd class="col-7"><a href="mailto:{{ $user->email }}">{{ $user->email }}</a></dd>
                    <dt class="col-5">Phone</dt><dd class="col-7">{{ $user->phone_number ?? '—' }}</dd>
                    <dt class="col-5">Education</dt><dd class="col-7">{{ $application->student->education_level ?? '—' }}</dd>
                    <dt class="col-5">Occupation</dt><dd class="col-7">{{ $application->student->occupation ?? '—' }}</dd>
                    <dt class="col-5">Heard about us</dt><dd class="col-7">{{ $application->student->referral_source ?? '—' }}</dd>
                </dl>
            </x-cb.card>

            @if($others->isNotEmpty() || $application->student->enrollments->isNotEmpty())
                <x-cb.card title="History" icon="ri-history-line" :flush="true">
                    <div class="list-group list-group-flush">
                        @foreach($application->student->enrollments as $e)
                            <div class="list-group-item d-flex justify-content-between"><span>{{ $e->course->title }}</span>@include('modules.academy.partials.pill', ['status' => $e->status, 'label' => $e->status_label])</div>
                        @endforeach
                        @foreach($others as $o)
                            <a href="{{ route('academy.applications.show', $o) }}" class="list-group-item list-group-item-action d-flex justify-content-between"><span>{{ $o->reference }} · {{ $o->course->title }}</span>@include('modules.academy.partials.pill', ['status' => $o->status, 'label' => $o->status_label])</a>
                        @endforeach
                    </div>
                </x-cb.card>
            @endif
        </div>
    </div>

</div></div></div>
@endsection
