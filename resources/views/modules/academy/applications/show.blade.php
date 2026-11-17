@extends('layouts.master')

@section('content')
@php $user = $application->student->user; @endphp
<div class="main-content"><div class="page-content"><div class="container-fluid">

    <x-cb.hero :title="$user->name" icon="ri-user-search-line" :subtitle="'Admission request ' . $application->reference . ' · ' . $application->courses_label" :back="route('academy.applications.index')" back-label="Applications">
        <x-slot:pills>
            <span class="cb-meta-pill">{{ $application->status_label }}</span>
            <span class="cb-meta-pill">Received {{ $application->created_at->format('j M Y, g:ia') }}</span>
        </x-slot:pills>
    </x-cb.hero>

    @include('partials.flash')

    <div class="row g-3">
        <div class="col-xl-7">
            <x-cb.card title="Courses chosen" icon="ri-book-open-line" :count="$application->choices->count()" :flush="true">
                @php $money = fn ($v, $c) => \App\Support\Money::format($v, $c); $pending = $application->status === 'submitted'; @endphp
                <div class="table-responsive"><table class="cb-table stack">
                    <thead><tr>@if($pending)<th style="width:36px"></th>@endif<th>Course</th><th>Class</th><th class="num">Fee</th>@unless($pending)<th>Outcome</th>@endunless</tr></thead>
                    <tbody>
                    @foreach($application->choices as $ch)
                        <tr>
                            @if($pending)<td><input class="form-check-input" type="checkbox" name="choices[]" value="{{ $ch->id }}" form="admit-form" checked aria-label="Admit to {{ $ch->course->title }}"></td>@endif
                            <td data-label="Course"><a href="{{ route('academy.courses.show', $ch->course) }}" class="fw-semibold">{{ $ch->course->title }}</a><div class="small text-muted">{{ $ch->course->code }}</div></td>
                            <td data-label="Class"><a href="{{ route('academy.cohorts.show', $ch->cohort) }}">{{ $ch->cohort->name }}</a>
                                <div class="small text-muted">{{ $ch->cohort->starts_on ? 'Starts ' . $ch->cohort->starts_on->format('j M Y') : 'Start to be announced' }}@if($ch->cohort->capacity) · {{ $ch->cohort->seatsLeft() }} of {{ $ch->cohort->capacity }} seats left @endif · {{ $ch->cohort->status_label }}</div></td>
                            <td data-label="Fee" class="num">{{ $money($ch->cohort->effectiveFee(), $ch->course->currency) }}</td>
                            @unless($pending)
                                <td data-label="Outcome">
                                    @include('modules.academy.partials.pill', ['status' => $ch->status === 'admitted' ? ($ch->enrollment_id ? 'active' : 'pending') : 'cancelled', 'label' => $ch->status === 'admitted' ? ($ch->enrollment ? 'Enrolled · ' . $ch->enrollment->status_label : 'Admitted, not enrolled') : $ch->status_label])
                                    @if($ch->problem)<div class="small text-warning">{{ $ch->problem }}</div>@endif
                                </td>
                            @endunless
                        </tr>
                    @endforeach
                    </tbody>
                    @if($application->choices->count() > 1)
                        <tfoot><tr>@if($pending)<td></td>@endif<td colspan="2" class="fw-semibold">Total</td><td class="num fw-semibold">{{ $application->choices->groupBy(fn ($c) => $c->course->currency)->map(fn ($g, $cur) => $money(\App\Support\Money::major($g->sum(fn ($c) => \App\Support\Money::minor($c->cohort->effectiveFee()))), $cur))->implode(' + ') }}</td>@unless($pending)<td></td>@endunless</tr></tfoot>
                    @endif
                </table></div>
            </x-cb.card>

            <x-cb.card title="About the applicant's goals" icon="ri-file-text-line">
                <dl class="row mb-0">
                    <dt class="col-sm-4">Coding experience</dt><dd class="col-sm-8">{{ \App\Models\Academy\Application::EXPERIENCE[$application->experience_level] ?? '—' }}</dd>
                    <dt class="col-sm-4">Has a laptop</dt><dd class="col-sm-8">{{ $application->has_laptop === null ? '—' : ($application->has_laptop ? 'Yes' : 'No') }}</dd>
                    <dt class="col-sm-4">Why they want to join</dt><dd class="col-sm-8" style="white-space: pre-line">{{ $application->motivation ?? '—' }}</dd>
                </dl>
            </x-cb.card>

            @if($application->status === 'submitted')
                <x-cb.card title="Decision" icon="ri-scales-3-line">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <form method="POST" action="{{ route('academy.applications.admit', $application) }}" id="admit-form">@csrf
                                <label class="form-label">Note for the record <span class="text-muted">(optional, the student sees it)</span></label>
                                <textarea name="decision_note" class="form-control mb-2" rows="2" maxlength="500"></textarea>
                                <button class="btn btn-success w-100"><i class="ri-check-line me-1"></i>Accept and admit</button>
                                <div class="form-text">{{ $user->name }} gets an admission number{{ $application->student->student_no ? ' (they already have ' . $application->student->student_no . ')' : '' }} and is enrolled on the ticked courses. One invoice is issued and e-mailed with a link to pay; course access opens once payment is confirmed. Unticked courses are marked "not offered".</div>
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
                    @if($application->status === 'admitted')
                        <p class="mb-1">Admission number <b>{{ $application->student->student_no }}</b></p>
                        @if($application->choices->where('status', 'admitted')->whereNull('enrollment_id')->isNotEmpty())<p class="mb-0 text-warning"><i class="ri-error-warning-line"></i> Some courses are not booked on a class yet. <a href="{{ route('academy.students.show', $application->student) }}">Enroll from the student record</a>.</p>@endif
                    @endif
                </x-cb.card>
                @if($application->invoice)
                    @include('modules.academy.partials.fees', ['invoices' => collect([$application->invoice->load('items', 'user', 'allocations.payment')]), 'gateways' => $gateways])
                @endif
            @endif
        </div>

        <div class="col-xl-5">
            <x-cb.card title="Applicant" icon="ri-user-3-line">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <img src="{{ $user->avatar_url }}" class="rounded-circle" width="48" height="48" alt="">
                    <div><a href="{{ route('academy.students.show', $application->student) }}" class="fw-semibold">{{ $user->name }}</a>
                        <div class="small text-muted">{{ $application->student->student_no ?: 'No admission number yet' }} · {{ $application->student->status_label }}</div></div>
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
                            <a href="{{ route('academy.applications.show', $o) }}" class="list-group-item list-group-item-action d-flex justify-content-between"><span>{{ $o->reference }} · {{ $o->courses_label }}</span>@include('modules.academy.partials.pill', ['status' => $o->status, 'label' => $o->status_label])</a>
                        @endforeach
                    </div>
                </x-cb.card>
            @endif
        </div>
    </div>

</div></div></div>
@endsection
