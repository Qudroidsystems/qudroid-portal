@extends('layouts.master')

@section('content')
@php $editing = $p->exists; $features = old('features', $p->features ?? []); @endphp
<div class="main-content"><div class="page-content"><div class="container-fluid">
    <x-cb.hero :title="$editing ? $p->name : 'New product'" icon="ri-stack-line" subtitle="Each product gets its own page on the website." :back="route('website.products.index')" back-label="Products">
        @if($editing)
        <x-slot:actions><a class="cb-hero-btn" href="{{ route('site.products.show', $p) }}" target="_blank"><i class="ri-external-link-line me-1"></i>{{ $p->isPublished() ? 'View' : 'Preview' }}</a></x-slot:actions>
        @endif
    </x-cb.hero>
    @include('partials.flash')
    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

    <form method="POST" action="{{ $editing ? route('website.products.update', $p) : route('website.products.store') }}" enctype="multipart/form-data">
        @csrf @if($editing) @method('PUT') @endif
        <div class="row g-3">
            <div class="col-xl-8">
                <x-cb.card title="Product" icon="ri-file-text-line">
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">Name</label><input name="name" class="form-control" required maxlength="120" value="{{ old('name', $p->name) }}"></div>
                        <div class="col-md-6"><label class="form-label">Category</label><input name="category" class="form-control" maxlength="60" value="{{ old('category', $p->category) }}" placeholder="e.g. Cloud, Education"></div>
                        <div class="col-12"><label class="form-label">Tagline</label><input name="tagline" class="form-control" maxlength="200" value="{{ old('tagline', $p->tagline) }}" placeholder="One short line, like a headline"></div>
                        <div class="col-12"><label class="form-label">Summary</label><textarea name="summary" class="form-control" rows="2" maxlength="500">{{ old('summary', $p->summary) }}</textarea></div>
                        <div class="col-12"><label class="form-label">Full description</label><textarea name="body" class="form-control" rows="8" maxlength="20000">{{ old('body', $p->body) }}</textarea><div class="form-text">Markdown: **bold**, - lists, ## headings, [links](/contact).</div></div>
                    </div>
                </x-cb.card>
                <x-cb.card title="Features" icon="ri-apps-line" class="mt-3">
                    <div data-repeater>
                        <div data-rows>
                            @foreach($features as $i => $f)
                                @include('modules.website.products._feature', ['i' => $i, 'f' => $f])
                            @endforeach
                        </div>
                        <template>@include('modules.website.products._feature', ['i' => '__i__', 'f' => []])</template>
                        <button type="button" class="btn btn-sm btn-soft-secondary" data-add-row><i class="ri-add-line"></i> Add a feature</button>
                    </div>
                </x-cb.card>
            </div>
            <div class="col-xl-4">
                <x-cb.card title="Publishing" icon="ri-eye-line">
                    <select name="status" class="form-select mb-2"><option value="draft" @selected(old('status', $p->status) === 'draft')>Draft</option><option value="published" @selected(old('status', $p->status) === 'published')>Published</option></select>
                    <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" name="featured" value="1" id="feat" @checked(old('featured', $p->featured))><label class="form-check-label" for="feat">Featured (shown in home page tiles)</label></div>
                    <div class="row g-2 mb-3">
                        <div class="col-6"><label class="form-label small">Order</label><input name="position" type="number" min="0" max="999" class="form-control" value="{{ old('position', $p->position ?? 0) }}"></div>
                        <div class="col-6"><label class="form-label small">Address</label><input name="slug" class="form-control" maxlength="80" value="{{ old('slug', $p->slug) }}" placeholder="from the name"></div>
                    </div>
                    <button class="btn btn-primary w-100">{{ $editing ? 'Save product' : 'Add product' }}</button>
                    @if($editing)<div class="text-end mt-1"><button form="deleteItem" class="btn btn-link text-danger btn-sm">Remove product</button></div>@endif
                </x-cb.card>
                <x-cb.card title="Look" icon="ri-palette-line" class="mt-3">
                    @include('modules.website._image', ['value' => $p->image, 'help' => 'Without a picture, the icon is shown on a colour background.'])
                    <div class="row g-2 mt-2">
                        <div class="col-7"><label class="form-label small">Icon</label><input name="icon" class="form-control" maxlength="60" value="{{ old('icon', $p->icon) }}" placeholder="ri-cloud-line"></div>
                        <div class="col-5"><label class="form-label small">Colour</label><input name="color" type="color" class="form-control form-control-color w-100" value="{{ old('color', $p->color ?: '#0071e3') }}"></div>
                    </div>
                </x-cb.card>
                <x-cb.card title="Button and price" icon="ri-links-line" class="mt-3">
                    <div class="mb-2"><label class="form-label small">Button text</label><input name="link_label" class="form-control" maxlength="40" value="{{ old('link_label', $p->link_label) }}" placeholder="Talk to us"></div>
                    <div class="mb-2"><label class="form-label small">Button link</label><input name="link_url" class="form-control" maxlength="500" value="{{ old('link_url', $p->link_url) }}" placeholder="/contact or https://…"></div>
                    <div><label class="form-label small">Price line (optional)</label><input name="price_label" class="form-control" maxlength="80" value="{{ old('price_label', $p->price_label) }}" placeholder="From ₦50,000 a month"></div>
                </x-cb.card>
            </div>
        </div>
    </form>
    @if($editing)<form method="POST" action="{{ route('website.products.destroy', $p) }}" id="deleteItem" onsubmit="return confirm('Remove this product from the website?')">@csrf @method('DELETE')</form>@endif
</div></div></div>
@include('modules.website._picker')
@include('modules.website._repeater')
@endsection
