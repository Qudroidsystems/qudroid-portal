@extends('layouts.guest')
@section('title', 'Certificate check')

@section('content')
    <div class="apply-course p-4 p-md-5 text-center mx-auto" style="max-width: 620px">
        @if(!$certificate)
            <i class="ri-error-warning-line text-danger" style="font-size:56px"></i>
            <h3 class="fw-semibold mt-2">Certificate not found</h3>
            <p class="text-muted mb-0">This link does not match any certificate issued by {{ $brand['company'] ?? 'Qudroid Systems' }}. Check you scanned the right code, or contact us.</p>
        @elseif($certificate->isValid())
            <i class="ri-shield-check-line text-success" style="font-size:56px"></i>
            <h3 class="fw-semibold mt-2">Valid certificate</h3>
            <p class="text-muted">Issued by {{ $brand['company'] ?? 'Qudroid Systems' }}</p>
        @else
            <i class="ri-close-circle-line text-danger" style="font-size:56px"></i>
            <h3 class="fw-semibold mt-2">This certificate was revoked</h3>
            <p class="text-muted">on {{ $certificate->revoked_at?->format('j F Y') }}@if($certificate->revoke_reason): {{ $certificate->revoke_reason }}@endif</p>
        @endif

        @if($certificate)
            <dl class="row text-start mt-4 mb-0">
                <dt class="col-5">Name</dt><dd class="col-7">{{ $certificate->get('name') }}</dd>
                <dt class="col-5">Course</dt><dd class="col-7">{{ $certificate->get('course') }}</dd>
                <dt class="col-5">Cohort</dt><dd class="col-7">{{ $certificate->get('cohort') }}</dd>
                <dt class="col-5">Completed</dt><dd class="col-7">{{ \Illuminate\Support\Carbon::parse($certificate->get('completed_on'))->format('j F Y') }}</dd>
                <dt class="col-5">Serial number</dt><dd class="col-7">{{ $certificate->serial }}</dd>
                <dt class="col-5">Issued</dt><dd class="col-7">{{ $certificate->issued_at->format('j F Y') }}</dd>
            </dl>
        @endif
    </div>
@endsection
