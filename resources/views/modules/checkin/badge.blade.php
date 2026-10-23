@extends('layouts.guest')
@section('title', 'My QR')

@section('content')
<div class="apply-course p-4 text-center mx-auto" style="max-width:420px">
    <img src="{{ $user->avatar_url }}" alt="" class="rounded-circle mb-2" width="72" height="72" style="object-fit:cover">
    <h4 class="mb-0">{{ $user->name }}</h4>
    <p class="text-muted small mb-3">{{ $user->user_type_label }}</p>
    <div class="d-inline-block p-3 bg-white rounded border" style="max-width:100%">{!! $qr !!}</div>
    <p class="text-muted small mt-3 mb-3">Turn your screen brightness up and hold this to the scanner.</p>
    <a href="{{ route('checkin.index') }}" class="btn btn-light btn-sm">Back</a>
</div>
<style>.apply-course svg { max-width: 100%; height: auto; }</style>
@endsection
