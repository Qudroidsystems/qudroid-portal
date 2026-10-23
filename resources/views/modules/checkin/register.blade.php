@extends('layouts.guest')
@section('title', $event->title)
@section('description', \Illuminate\Support\Str::limit((string) $event->description, 150))

@section('content')
<div class="apply-course p-4 p-md-5 mx-auto" style="max-width:640px">
    <h2 class="fw-semibold mb-1">{{ $event->title }}</h2>
    <p class="text-muted mb-1"><i class="ri-calendar-line me-1"></i>{{ $event->whenLabel() }}</p>
    @if($event->location)<p class="text-muted"><i class="ri-map-pin-line me-1"></i>{{ $event->location }}</p>@endif
    @if($event->description)<div class="mb-4" style="white-space:pre-line">{{ $event->description }}</div>@endif

    @if($closed)
        <div class="alert alert-secondary mb-0">Registration is closed.</div>
    @elseif($full)
        <div class="alert alert-warning mb-0">Sorry, this event is full.</div>
    @else
        <h5 class="mb-3">Register</h5>
        @if($errors->any())<div class="alert alert-danger small">{{ $errors->first() }}</div>@endif
        <form method="POST" action="{{ route('checkin.public.store', $event->slug) }}">
            @csrf
            <div class="hp-field" aria-hidden="true"><label>Website <input name="website" tabindex="-1" autocomplete="off"></label></div>
            <div class="row g-2">
                <div class="col-md-6"><label class="form-label">Full name</label><input name="name" class="form-control" value="{{ old('name', auth()->user()?->name) }}" required maxlength="150"></div>
                <div class="col-md-6"><label class="form-label">E-mail</label><input name="email" type="email" class="form-control" value="{{ old('email', auth()->user()?->email) }}" required></div>
                <div class="col-md-6"><label class="form-label">Phone <span class="text-muted small">(optional)</span></label><input name="phone" class="form-control" value="{{ old('phone') }}"></div>
                <div class="col-md-6"><label class="form-label">Organisation <span class="text-muted small">(optional)</span></label><input name="organisation" class="form-control" value="{{ old('organisation') }}"></div>
            </div>
            <button class="btn btn-primary mt-3">Register and get my ticket</button>
            <p class="small text-muted mt-2 mb-0">We'll e-mail you a ticket with a QR code to show at the door.</p>
        </form>
    @endif
</div>
@endsection
