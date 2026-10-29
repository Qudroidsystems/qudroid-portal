@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">
    <x-cb.hero title="Our work" icon="ri-briefcase-4-line" subtitle="Client projects as case studies. Published ones appear on /work and in Our work and Tiles blocks." :back="route('website.index')" back-label="Website">
        <x-slot:actions><a class="cb-hero-btn" href="{{ route('website.works.create') }}"><i class="ri-add-line me-1"></i>New work</a></x-slot:actions>
    </x-cb.hero>
    @include('partials.flash')
    <x-cb.card title="All work" icon="ri-briefcase-4-line" :count="$works->count()" :flush="true">
        @forelse($works as $p)
            <div class="d-flex gap-3 align-items-center border-top p-3">
                <div class="ws-swatch" style="background:{{ $p->color ?: '#0071e3' }}">@if($p->imageUrl())<img src="{{ $p->imageUrl() }}" alt="">@else<i class="{{ 'ri-briefcase-4-line' }}"></i>@endif</div>
                <div class="flex-grow-1 min-w-0">
                    <a href="{{ route('website.works.edit', $p) }}" class="fw-semibold">{{ $p->title }}</a>
                    @if($p->featured)<span class="badge bg-warning-subtle text-warning ms-1"><i class="ri-star-fill"></i> Featured</span>@endif
                    <div class="small text-muted text-truncate">{{ collect([$p->client, $p->category, $p->year])->filter()->implode(' · ') }}</div>
                </div>
                <span class="badge bg-{{ $p->isPublished() ? 'success' : 'secondary' }}-subtle text-{{ $p->isPublished() ? 'success' : 'secondary' }}">{{ ucfirst($p->status) }}</span>
                <a href="{{ route('site.works.show', $p) }}" target="_blank" class="btn btn-sm btn-ghost-secondary" title="View"><i class="ri-external-link-line"></i></a>
                <a href="{{ route('website.works.edit', $p) }}" class="btn btn-sm btn-soft-primary">Edit</a>
            </div>
        @empty
            <div class="p-4 text-center text-muted">No work yet. Add a client project you are proud of.</div>
        @endforelse
    </x-cb.card>
</div></div></div>
@endsection
@push('styles')
<style>
    .ws-swatch { width: 56px; height: 40px; border-radius: 8px; display: grid; place-items: center; color: #fff; font-size: 20px; overflow: hidden; flex: none; }
    .ws-swatch img { width: 100%; height: 100%; object-fit: cover; }
    .min-w-0 { min-width: 0; }
</style>
@endpush
