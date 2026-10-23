@extends('layouts.master')
@section('title', 'Check-in')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">
    <x-cb.hero title="Check-in" icon="ri-qr-scan-2-line" subtitle="Scan the QR at the door, or show yours to be scanned.">
        <x-slot:actions>
            @can('Run check-in')<a href="{{ route('checkin.events.create') }}" class="cb-hero-btn"><i class="ri-add-line"></i>New event</a>@endcan
            @if($canPlaces)
                <a href="{{ route('checkin.places.index') }}" class="cb-hero-btn"><i class="ri-building-line"></i>Offices</a>
                <a href="{{ route('checkin.staff') }}" class="cb-hero-btn"><i class="ri-file-list-3-line"></i>Staff attendance</a>
            @endif
        </x-slot:actions>
    </x-cb.hero>
    @include('partials.flash')

    <div class="row g-3">
        <div class="col-lg-4">
            <x-cb.card title="My QR" icon="ri-qr-code-line">
                <div class="text-center">
                    <div class="d-inline-block p-2 bg-white rounded border">{!! $badge !!}</div>
                    <div class="fw-semibold mt-2">{{ auth()->user()->name }}</div>
                    <p class="small text-muted mb-2">Show this at the door when someone is scanning badges.</p>
                    <a href="{{ route('checkin.badge') }}" class="btn btn-sm btn-primary"><i class="ri-fullscreen-line me-1"></i>Show full screen</a>
                </div>
            </x-cb.card>
            <x-cb.card title="How to check in" icon="ri-information-line">
                <ol class="small mb-0 ps-3">
                    <li>Open your phone's camera (or the Qudroid app) and point it at the QR on the screen by the door.</li>
                    <li>Tap the link. Sign in if asked.</li>
                    <li>You're in. At the office, scan again when you leave to clock out.</li>
                </ol>
            </x-cb.card>
        </div>
        <div class="col-lg-8">
            <x-cb.card title="Today" icon="ri-calendar-check-line" :count="$today->count()" :flush="true">
                @forelse($today as $e)
                    @php $runs = app(\App\Services\Checkin\CheckinService::class)->canRun(auth()->user(), $e); @endphp
                    <div class="d-flex align-items-center gap-3 px-3 py-2 border-bottom">
                        <i class="{{ $e->icon() }} fs-4 text-primary"></i>
                        <div class="flex-grow-1 min-w-0">
                            <div class="fw-semibold text-truncate">{{ $e->title }}</div>
                            <small class="text-muted">{{ $e->kindLabel() }} · {{ $e->starts_at->format('g:i A') }}@if($e->ends_at)–{{ $e->ends_at->format('g:i A') }}@endif @if($e->location) · {{ $e->location }}@endif</small>
                        </div>
                        @if($e->status === 'closed')<span class="badge bg-secondary">Closed</span>@endif
                        @if($runs)
                            <a href="{{ route('checkin.events.show', $e) }}" class="btn btn-sm btn-soft-primary">Manage</a>
                        @endif
                    </div>
                @empty
                    <div class="p-4 text-center text-muted">Nothing to check in to right now.</div>
                @endforelse
                @if($places->isNotEmpty() && $canPlaces)
                    <div class="px-3 py-2 small bg-light">
                        Office door screens:
                        @foreach($places as $p)<a href="{{ route('checkin.places.screen', $p) }}" class="ms-2" target="_blank"><i class="ri-tv-2-line"></i> {{ $p->name }}</a>@endforeach
                    </div>
                @endif
            </x-cb.card>

            @if($running->isNotEmpty())
                <x-cb.card title="Events I run" icon="ri-calendar-event-line" :flush="true">
                    <div class="table-responsive"><table class="table table-sm align-middle mb-0">
                        <thead><tr><th>Event</th><th>When</th><th>Sign-up</th><th></th></tr></thead>
                        <tbody>
                        @foreach($running as $e)
                            <tr>
                                <td><a href="{{ route('checkin.events.show', $e) }}" class="fw-semibold">{{ $e->title }}</a>
                                    @if($e->status !== 'open')<span class="badge bg-secondary ms-1">{{ ucfirst($e->status) }}</span>@endif</td>
                                <td class="small">{{ $e->starts_at->format('D j M, g:i A') }}</td>
                                <td class="small">@if($e->public_registration)<a href="{{ route('checkin.public.register', $e->slug) }}" target="_blank">Public page</a>@else — @endif</td>
                                <td class="text-end"><a href="{{ route('checkin.events.show', $e) }}" class="btn btn-sm btn-light">Open</a></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table></div>
                </x-cb.card>
            @endif

            <x-cb.card title="My recent check-ins" icon="ri-history-line" :flush="true">
                @forelse($mine as $r)
                    <div class="d-flex justify-content-between px-3 py-2 border-bottom small">
                        <span><i class="{{ $r->event->icon() }} me-1 text-muted"></i>{{ $r->event->kind === 'staff' ? ($r->event->place?->name ?? $r->event->title) : $r->event->title }}</span>
                        <span>
                            {{ $r->checked_in_at->format('D j M, g:i A') }}@if($r->checked_out_at) – {{ $r->checked_out_at->format('g:i A') }}@endif
                            @if($r->status === 'late')<span class="badge bg-warning-subtle text-warning ms-1">Late</span>@endif
                        </span>
                    </div>
                @empty
                    <div class="p-3 text-center text-muted small">No check-ins yet.</div>
                @endforelse
            </x-cb.card>
        </div>
    </div>
</div></div></div>
@endsection
