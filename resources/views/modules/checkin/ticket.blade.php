@extends('layouts.guest')
@section('title', 'Ticket · ' . $event->title)

@section('content')
<div class="apply-course p-4 p-md-5 text-center mx-auto" style="max-width:460px">
    @if(session('success'))<div class="alert alert-success small">{{ session('success') }}</div>@endif
    @if($guest->cancelled_at)
        <div class="alert alert-danger">This ticket was cancelled.</div>
    @endif
    <h4 class="fw-semibold mb-1">{{ $event->title }}</h4>
    <p class="text-muted small mb-1">{{ $event->whenLabel() }}</p>
    @if($event->location)<p class="text-muted small">{{ $event->location }}</p>@endif
    <div class="d-inline-block p-2 bg-white border rounded my-2">{!! $qr !!}</div>
    <h5 class="mb-0">{{ $guest->name }}</h5>
    @if($guest->organisation)<div class="text-muted small">{{ $guest->organisation }}</div>@endif
    <p class="mt-2 mb-0 small text-muted">Ticket code</p>
    <p class="fs-4 fw-bold" style="letter-spacing:3px">{{ $guest->code }}</p>
    <p class="small text-muted mb-0">Show this QR at the door. Save this page or keep the e-mail we sent you.</p>
</div>
<style>.apply-course svg { max-width: 100%; height: auto; }</style>
@endsection
