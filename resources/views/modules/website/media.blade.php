@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">
    <x-cb.hero title="Pictures" icon="ri-image-line" subtitle="Images for the website. Upload here, then choose them in pages, products and work." :back="route('website.index')" back-label="Website" />
    @include('partials.flash')
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

    <x-cb.card title="Upload" icon="ri-upload-2-line">
        <form method="POST" action="{{ route('website.media.store') }}" enctype="multipart/form-data" class="d-flex gap-2 flex-wrap align-items-center">
            @csrf
            <input type="file" name="files[]" accept="image/*" multiple required class="form-control" style="max-width:420px">
            <button class="btn btn-primary">Upload</button>
            <span class="small text-muted">JPG, PNG, WebP or GIF, up to 5 MB each. Wide pictures (at least 1600 px) look best in heroes and tiles.</span>
        </form>
    </x-cb.card>

    <div class="ws-media mt-3">
        @forelse($media as $m)
            <div class="card mb-0">
                <a href="{{ $m->url() }}" target="_blank" class="ws-media-img"><img src="{{ $m->url() }}" alt="{{ $m->alt }}" loading="lazy"></a>
                <div class="card-body p-2">
                    <div class="small text-truncate" title="{{ $m->name }}">{{ $m->name }}</div>
                    <div class="small text-muted">{{ $m->width && $m->height ? $m->width . '×' . $m->height . ' · ' : '' }}{{ number_format($m->size / 1024) }} KB</div>
                    <div class="d-flex gap-1 mt-1">
                        <button type="button" class="btn btn-sm btn-soft-secondary flex-grow-1" onclick="navigator.clipboard.writeText(@js($m->url())); this.textContent='Copied'">Copy link</button>
                        <form method="POST" action="{{ route('website.media.destroy', $m) }}" onsubmit="return confirm('Delete this picture? Pages using it will show no picture.')">@csrf @method('DELETE')<button class="btn btn-sm btn-ghost-danger" title="Delete"><i class="ri-delete-bin-line"></i></button></form>
                    </div>
                </div>
            </div>
        @empty
            <div class="text-muted">No pictures yet.</div>
        @endforelse
    </div>
    <div class="mt-3">{{ $media->links() }}</div>
</div></div></div>
@endsection
@push('styles')
<style>
    .ws-media { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 12px; }
    .ws-media-img { display: block; aspect-ratio: 4/3; background: var(--vz-light); overflow: hidden; border-radius: .25rem .25rem 0 0; }
    .ws-media-img img { width: 100%; height: 100%; object-fit: cover; }
</style>
@endpush
