@extends('layouts.master')

@section('content')
@php $editing = $w->exists; $results = old('results', $w->results ?? []); @endphp
<div class="main-content"><div class="page-content"><div class="container-fluid">
    <x-cb.hero :title="$editing ? $w->title : 'New work'" icon="ri-briefcase-4-line" subtitle="A client project, told as a short case study." :back="route('website.works.index')" back-label="Our work">
        @if($editing)
        <x-slot:actions><a class="cb-hero-btn" href="{{ route('site.works.show', $w) }}" target="_blank"><i class="ri-external-link-line me-1"></i>{{ $w->isPublished() ? 'View' : 'Preview' }}</a></x-slot:actions>
        @endif
    </x-cb.hero>
    @include('partials.flash')
    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

    <form method="POST" action="{{ $editing ? route('website.works.update', $w) : route('website.works.store') }}" enctype="multipart/form-data">
        @csrf @if($editing) @method('PUT') @endif
        <div class="row g-3">
            <div class="col-xl-8">
                <x-cb.card title="The project" icon="ri-file-text-line">
                    <div class="row g-3">
                        <div class="col-12"><label class="form-label">Title</label><input name="title" class="form-control" required maxlength="150" value="{{ old('title', $w->title) }}" placeholder="e.g. A school portal for 3,000 students"></div>
                        <div class="col-md-5"><label class="form-label">Client</label><input name="client" class="form-control" maxlength="120" value="{{ old('client', $w->client) }}"></div>
                        <div class="col-md-4"><label class="form-label">Category</label><input name="category" class="form-control" maxlength="60" value="{{ old('category', $w->category) }}" placeholder="Web app, Mobile…"></div>
                        <div class="col-md-3"><label class="form-label">Year</label><input name="year" type="number" min="1990" max="2100" class="form-control" value="{{ old('year', $w->year) }}"></div>
                        <div class="col-12"><label class="form-label">Summary</label><textarea name="summary" class="form-control" rows="2" maxlength="500">{{ old('summary', $w->summary) }}</textarea></div>
                        <div class="col-12"><label class="form-label">The story</label><textarea name="body" class="form-control" rows="10" maxlength="20000" placeholder="## The challenge&#10;…&#10;## What we built&#10;…">{{ old('body', $w->body) }}</textarea><div class="form-text">Markdown: ## headings, **bold**, - lists.</div></div>
                        <div class="col-12"><label class="form-label">Tags</label><input name="tags" class="form-control" maxlength="300" value="{{ old('tags', implode(', ', $w->tags ?? [])) }}" placeholder="Laravel, Flutter, Paystack"><div class="form-text">Separate with commas.</div></div>
                    </div>
                </x-cb.card>
                <x-cb.card title="Results in numbers" icon="ri-bar-chart-2-line" class="mt-3">
                    <div data-repeater>
                        <div data-rows>
                            @foreach($results as $i => $r)@include('modules.website.works._result', ['i' => $i, 'r' => $r])@endforeach
                        </div>
                        <template>@include('modules.website.works._result', ['i' => '__i__', 'r' => []])</template>
                        <button type="button" class="btn btn-sm btn-soft-secondary" data-add-row><i class="ri-add-line"></i> Add a number</button>
                        <div class="form-text">e.g. "3,000" students on the platform, "40%" faster payments.</div>
                    </div>
                </x-cb.card>
            </div>
            <div class="col-xl-4">
                <x-cb.card title="Publishing" icon="ri-eye-line">
                    <select name="status" class="form-select mb-2"><option value="draft" @selected(old('status', $w->status) === 'draft')>Draft</option><option value="published" @selected(old('status', $w->status) === 'published')>Published</option></select>
                    <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" name="featured" value="1" id="feat" @checked(old('featured', $w->featured))><label class="form-check-label" for="feat">Featured (shown on the home page)</label></div>
                    <div class="row g-2 mb-3">
                        <div class="col-6"><label class="form-label small">Order</label><input name="position" type="number" min="0" max="999" class="form-control" value="{{ old('position', $w->position ?? 0) }}"></div>
                        <div class="col-6"><label class="form-label small">Address</label><input name="slug" class="form-control" maxlength="80" value="{{ old('slug', $w->slug) }}" placeholder="from the title"></div>
                    </div>
                    <button class="btn btn-primary w-100">{{ $editing ? 'Save' : 'Add work' }}</button>
                    @if($editing)<div class="text-end mt-1"><button form="deleteItem" class="btn btn-link text-danger btn-sm">Remove</button></div>@endif
                </x-cb.card>
                <x-cb.card title="Look" icon="ri-palette-line" class="mt-3">
                    @include('modules.website._image', ['value' => $w->image, 'help' => 'A screenshot or photo of the project.'])
                    <div class="mt-2"><label class="form-label small">Colour (when there is no picture)</label><input name="color" type="color" class="form-control form-control-color w-100" value="{{ old('color', $w->color ?: '#1d1d1f') }}"></div>
                    <div class="mt-2"><label class="form-label small">Live project link (optional)</label><input name="link_url" class="form-control" maxlength="500" value="{{ old('link_url', $w->link_url) }}" placeholder="https://"></div>
                </x-cb.card>
            </div>
        </div>
    </form>
    @if($editing)<form method="POST" action="{{ route('website.works.destroy', $w) }}" id="deleteItem" onsubmit="return confirm('Remove this work from the website?')">@csrf @method('DELETE')</form>@endif
</div></div></div>
@include('modules.website._picker')
@include('modules.website._repeater')
@endsection
