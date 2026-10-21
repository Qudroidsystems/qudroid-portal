@extends('layouts.master')

@php $F = fn ($p) => \App\Services\Website\WebsiteContent::fileUrl($p); @endphp

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">
    <x-cb.hero title="Gallery" icon="ri-image-2-line" subtitle="Create albums and upload photos for the website gallery. Photos are resized automatically, with thumbnails for fast loading." />
    @include('website.admin._nav')

    <div class="row g-3">
        <div class="col-xl-4">
            <x-cb.card title="New album" icon="ri-folder-add-line">
                <form method="POST" action="{{ route('website.admin.albums.store') }}">@csrf
                    <label class="form-label">Title *</label><input name="title" class="form-control mb-2" required placeholder="e.g. Inter-house sports 2026">
                    <label class="form-label">Date</label><input type="date" name="taken_on" class="form-control mb-2">
                    <label class="form-label">Description</label><textarea name="description" class="form-control mb-3" rows="2" maxlength="500"></textarea>
                    <button class="btn btn-primary w-100"><i class="ri-add-line me-1"></i>Create album &amp; add photos</button>
                </form>
            </x-cb.card>
        </div>
        <div class="col-xl-8">
            <x-cb.card title="Albums" icon="ri-gallery-line" :count="$albums->count()">
                @if($albums->isEmpty())<p class="text-muted mb-0">No albums yet. Create one on the left.</p>@endif
                <div class="row g-3">
                    @foreach($albums as $a)
                        @php $c = $a->coverPhoto(); @endphp
                        <div class="col-sm-6 col-lg-4">
                            <a href="{{ route('website.admin.album', $a->id) }}" class="d-block border rounded-3 overflow-hidden text-reset h-100">
                                <div style="aspect-ratio:4/3;background:#f1f5f9">@if($c)<img src="{{ $F($c->thumb ?: $c->path) }}" style="width:100%;height:100%;object-fit:cover">@endif</div>
                                <div class="p-2">
                                    <div class="fw-semibold">{{ $a->title }}</div>
                                    <div class="small text-muted">{{ $a->photos_count }} photos{{ $a->taken_on ? ' · ' . $a->taken_on->format('d M Y') : '' }} @unless($a->published)<span class="badge bg-secondary-subtle text-secondary">Hidden</span>@endunless</div>
                                </div>
                            </a>
                        </div>
                    @endforeach
                </div>
            </x-cb.card>
        </div>
    </div>
</div></div></div>
@endsection
