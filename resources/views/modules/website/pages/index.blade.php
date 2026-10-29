@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">
    <x-cb.hero title="Pages" icon="ri-pages-line" subtitle="Pages are built from blocks: heroes, tiles, features, pictures and text, products, courses and forms." :back="route('website.index')" back-label="Website">
        <x-slot:actions><a class="cb-hero-btn" href="{{ route('website.pages.create') }}"><i class="ri-add-line me-1"></i>New page</a></x-slot:actions>
    </x-cb.hero>
    @include('partials.flash')

    <x-cb.card title="All pages" icon="ri-pages-line" :count="$pages->count()" :flush="true">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light"><tr><th>Page</th><th>Address</th><th>Shown in</th><th>Status</th><th>Last edited</th><th></th></tr></thead>
                <tbody>
                @forelse($pages as $p)
                    <tr>
                        <td><a href="{{ route('website.pages.edit', $p) }}" class="fw-medium">{{ $p->title }}</a>@if($p->slug === 'home') <span class="badge bg-primary-subtle text-primary">Front page</span>@endif</td>
                        <td><a href="{{ $p->url() }}" target="_blank" class="small"><code>/{{ $p->slug === 'home' ? '' : $p->slug }}</code> <i class="ri-external-link-line"></i></a></td>
                        <td class="small text-muted">{{ collect([$p->in_nav ? 'Top menu' : null, $p->in_footer ? 'Footer' : null])->filter()->implode(', ') ?: '—' }}</td>
                        <td><span class="badge bg-{{ $p->isPublished() ? 'success' : 'secondary' }}-subtle text-{{ $p->isPublished() ? 'success' : 'secondary' }}">{{ \App\Models\Website\Page::STATUSES[$p->status] ?? $p->status }}</span></td>
                        <td class="small text-muted">{{ $p->updated_at?->diffForHumans() }}@if($p->editor) by {{ $p->editor->name }}@endif</td>
                        <td class="text-end"><a href="{{ route('website.pages.edit', $p) }}" class="btn btn-sm btn-soft-primary">Edit</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted p-4">No pages yet. <a href="{{ route('website.index') }}">Add the starter content</a> or create a page.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </x-cb.card>
</div></div></div>
@endsection
