@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">

    @php $u = $student->user; @endphp
    <x-cb.hero :title="$u->name" icon="ri-graduation-cap-line" :subtitle="$student->student_no . ' · ' . $u->email" :back="route('academy.students.index')" back-label="Students">
        <x-slot:pills>
            <span class="cb-meta-pill">{{ $student->status_label }}</span>
            @if($u->phone_number)<span class="cb-meta-pill"><i class="ri-phone-line me-1"></i>{{ $u->phone_number }}</span>@endif
            @if($student->admitted_at)<span class="cb-meta-pill">Admitted {{ $student->admitted_at->format('j M Y') }}@if($student->admittedBy) by {{ $student->admittedBy->name }}@endif</span>@endif
            @if($u->is_disabled)<span class="cb-meta-pill"><i class="ri-forbid-line me-1"></i>Sign-in disabled</span>@endif
        </x-slot:pills>
        <x-slot:actions><a href="{{ route('academy.students.edit', $student) }}" class="cb-hero-btn"><i class="ri-edit-line"></i>Edit</a></x-slot:actions>
    </x-cb.hero>

    @include('modules.academy.partials.flash')

    <div class="row g-3">
        <div class="col-xl-8">
            <x-cb.card title="Enrollments" icon="ri-book-open-line" :count="$enrollments->count()" :flush="true">
                @if($enrollments->isEmpty())
                    <div class="empty-state"><i class="ri-book-open-line"></i><h6>Not enrolled yet</h6></div>
                @else
                    <div class="table-responsive"><table class="cb-table stack">
                        <thead><tr><th>Course / cohort</th><th>Status</th><th>Access</th><th class="num">Fee</th><th></th></tr></thead>
                        <tbody>
                        @foreach($enrollments as $e)
                            <tr>
                                <td data-label="Course"><a href="{{ route('academy.cohorts.show', $e->cohort_id) }}" class="fw-semibold">{{ $e->course->title }}</a><div class="small text-muted">{{ $e->cohort->name }} · enrolled {{ $e->enrolled_at?->format('j M Y') }}@if($e->enrolledBy) by {{ $e->enrolledBy->name }}@endif</div>@if($e->notes)<div class="small text-muted" style="white-space: pre-line">{{ $e->notes }}</div>@endif</td>
                                <td data-label="Status">@include('modules.academy.partials.pill', ['status' => $e->status, 'label' => $e->status_label])@if($e->prerequisites_waived)<div class="small text-warning">Prerequisites waived</div>@endif</td>
                                <td data-label="Access">@if($e->access_granted)<span class="text-success"><i class="ri-lock-unlock-line"></i> Open</span><div class="small text-muted">{{ $e->access_reason }}@if($e->accessGrantedBy) · {{ $e->accessGrantedBy->name }}@endif</div>@else<span class="text-muted"><i class="ri-lock-line"></i> Locked</span>@endif</td>
                                <td data-label="Fee" class="num">{{ \App\Support\Money::format($e->netFee(), $e->currency) }}@if((float) $e->discount_amount > 0)<div class="small text-muted">less {{ \App\Support\Money::format($e->discount_amount, $e->currency) }}</div>@endif</td>
                                <td>@include('modules.academy.partials.enrollment-actions', ['e' => $e])</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table></div>
                @endif
            </x-cb.card>

            <x-cb.card title="Profile" icon="ri-user-3-line">
                <dl class="row mb-0">
                    @foreach([
                        'Gender' => ucfirst((string) $u->gender), 'Date of birth' => $u->date_of_birth?->format('j M Y'), 'Address' => $u->address,
                        'Education' => $student->education_level, 'Institution' => $student->institution, 'Occupation' => $student->occupation,
                        'Emergency contact' => trim($student->emergency_contact_name . ' ' . $student->emergency_contact_phone),
                        'Guardian' => trim($student->guardian_name . ' ' . $student->guardian_phone),
                        'Heard of us via' => $student->referral_source,
                    ] as $label => $value)
                        <dt class="col-sm-4 text-muted fw-normal">{{ $label }}</dt><dd class="col-sm-8">{{ $value ?: '—' }}</dd>
                    @endforeach
                </dl>
                @if($student->notes)<hr><div style="white-space: pre-line">{{ $student->notes }}</div>@endif
            </x-cb.card>
        </div>
        <div class="col-xl-4">
            <x-cb.card title="Enroll on a cohort" icon="ri-user-add-line">
                @if(!$student->canEnroll())
                    <p class="text-muted mb-0">{{ $student->status_label }} students can't be enrolled. Admit or reinstate them first.</p>
                @elseif($cohorts->isEmpty())
                    <p class="text-muted mb-0">No cohort is open for registration.</p>
                @else
                    <form method="POST" action="{{ route('academy.enrollments.store') }}">
                        @csrf
                        <input type="hidden" name="student_id" value="{{ $student->id }}">
                        <div class="mb-2"><select name="cohort_id" class="form-select" required><option value="">Choose a cohort…</option>
                            @foreach($cohorts as $c)<option value="{{ $c->id }}">{{ $c->course->title }} — {{ $c->name }} ({{ \App\Support\Money::format($c->effectiveFee(), $c->course->currency) }})</option>@endforeach
                        </select></div>
                        <div class="mb-2"><label class="form-label">Discount</label><input name="discount_amount" type="number" step="0.01" min="0" class="form-control" value="0"></div>
                        @can('Grant course access')
                            <div class="form-check mb-2"><input type="hidden" name="waive_prerequisites" value="0"><input class="form-check-input" type="checkbox" id="waive" name="waive_prerequisites" value="1"><label class="form-check-label" for="waive">Waive prerequisites</label></div>
                        @endcan
                        <button class="btn btn-primary w-100">Enroll</button>
                    </form>
                @endif
            </x-cb.card>
            <x-cb.card title="Status" icon="ri-flag-line">
                <form method="POST" action="{{ route('academy.students.status', $student) }}" class="d-flex gap-2">
                    @csrf
                    <select name="status" class="form-select">@foreach(\App\Models\Academy\Student::STATUSES as $k => $v)<option value="{{ $k }}" @selected($student->status === $k)>{{ $v }}</option>@endforeach</select>
                    <button class="btn btn-soft-primary">Change</button>
                </form>
                <div class="form-text">Suspending or withdrawing a student stops new enrollments. It does not end current ones.</div>
            </x-cb.card>
        </div>
    </div>

</div></div></div>
@endsection
