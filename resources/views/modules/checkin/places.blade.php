@extends('layouts.master')
@section('title', 'Offices')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">
    <x-cb.hero title="Offices" icon="ri-building-line" subtitle="Where staff clock in. Put each office's door QR on a screen at the entrance." :back="route('checkin.index')" back-label="Check-in">
        <x-slot:actions><a href="{{ route('checkin.staff') }}" class="cb-hero-btn"><i class="ri-file-list-3-line"></i>Staff attendance</a></x-slot:actions>
    </x-cb.hero>
    @include('partials.flash')
    <div class="row g-3">
        <div class="col-xl-5">
            <x-cb.card title="Add an office" icon="ri-add-line">
                <form method="POST" action="{{ route('checkin.places.store') }}">@csrf
                    @include('modules.checkin._place_form', ['p' => new \App\Models\Checkin\Place(['radius_m' => 150, 'is_active' => true])])
                    <button class="btn btn-primary btn-sm mt-2">Add office</button>
                </form>
            </x-cb.card>
        </div>
        <div class="col-xl-7">
            @forelse($places as $p)
                <x-cb.card :title="$p->name" icon="ri-building-2-line">
                    <x-slot:tools>
                        @if(!$p->is_active)<span class="badge bg-secondary">Not in use</span>@endif
                        @if($p->is_active)
                            <a href="{{ route('checkin.places.screen', $p) }}" target="_blank" class="btn btn-sm btn-primary"><i class="ri-tv-2-line me-1"></i>Door screen</a>
                            <a href="{{ route('checkin.places.today', $p) }}" class="btn btn-sm btn-light">Today</a>
                        @endif
                    </x-slot:tools>
                    <p class="small text-muted mb-2">{{ $p->hoursLabel() }}, late after {{ $p->grace_minutes }} min · {{ collect($p->workdays ?: [1,2,3,4,5])->map(fn ($d) => ['', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'][$d])->implode(', ') }}
                        @if($p->require_location) · within {{ $p->radius_m }} m @endif</p>
                    <details><summary class="small">Edit</summary>
                        <form method="POST" action="{{ route('checkin.places.update', $p) }}" class="mt-2">@csrf @method('PUT')
                            @include('modules.checkin._place_form', ['p' => $p])
                            <button class="btn btn-primary btn-sm mt-2">Save</button>
                        </form>
                    </details>
                </x-cb.card>
            @empty
                <div class="cb-card p-4 text-center text-muted">No offices yet. Add one to start staff clock-in.</div>
            @endforelse
        </div>
    </div>
</div></div></div>
@endsection
