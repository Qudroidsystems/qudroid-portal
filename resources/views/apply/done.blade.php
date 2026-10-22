@extends('layouts.guest')
@section('title', 'Application received')

@section('content')
    <div class="apply-course p-4 p-md-5 text-center">
        <i class="ri-checkbox-circle-line text-success" style="font-size:56px"></i>
        <h3 class="fw-semibold mt-2">Application received</h3>
        <p class="text-muted mb-1">{{ $application->course->title }} · {{ $application->cohort->name }}</p>
        <p class="fs-5 mb-4">Your reference is <strong>{{ $application->reference }}</strong></p>
        @auth
            <p>We will let you know in the portal once it has been reviewed.</p>
            <a href="{{ route('academy.my') }}" class="btn btn-primary">Go to My academy</a>
        @else
            <p>We have sent an e-mail to <strong>{{ $application->student->user->email }}</strong> with a link to set your password.<br>
               Sign in afterwards to follow your application and, once admitted, pay your fees and start learning.</p>
            <a href="{{ route('login') }}" class="btn btn-primary">Sign in</a>
        @endauth
    </div>
@endsection
