@extends('layouts.master')

@section('content')
@php $editing = $t->exists; @endphp
<div class="main-content"><div class="page-content"><div class="container-fluid">
    <x-cb.hero :title="$editing ? $t->name : 'New testimonial'" icon="ri-chat-quote-line" :subtitle="$editing ? (\App\Models\Website\Testimonial::KINDS[$t->kind] . ' · ' . \App\Models\Website\Testimonial::STATUSES[$t->status]) : 'Add words a student or client shared with you.'" :back="route('website.testimonials.index')" back-label="Testimonials" />
    @include('partials.flash')
    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

    <form method="POST" action="{{ $editing ? route('website.testimonials.update', $t) : route('website.testimonials.store') }}" enctype="multipart/form-data">
        @csrf @if($editing) @method('PUT') @endif
        <div class="row g-3">
            <div class="col-xl-8">
                <x-cb.card title="What they said" icon="ri-chat-quote-line">
                    <div class="row g-3">
                        <div class="col-md-4"><label class="form-label">From</label>
                            <select name="kind" class="form-select" id="tKind">@foreach(\App\Models\Website\Testimonial::KINDS as $k => $label)<option value="{{ $k }}" @selected(old('kind', $t->kind) === $k)>{{ $label }}</option>@endforeach</select></div>
                        <div class="col-md-8"><label class="form-label">Name</label><input name="name" class="form-control" required maxlength="120" value="{{ old('name', $t->name) }}"></div>
                        <div class="col-md-6"><label class="form-label">Role</label><input name="role" class="form-control" maxlength="120" value="{{ old('role', $t->role) }}" placeholder="e.g. CEO, or Junior developer"></div>
                        <div class="col-md-6"><label class="form-label">Company</label><input name="organisation" class="form-control" maxlength="150" value="{{ old('organisation', $t->organisation) }}"></div>
                        <div class="col-12"><label class="form-label">Quote</label><textarea name="quote" class="form-control" rows="6" required maxlength="1500">{{ old('quote', $t->quote) }}</textarea></div>
                        <div class="col-md-4"><label class="form-label">Rating</label><select name="rating" class="form-select"><option value="">No rating</option>@foreach([5, 4, 3, 2, 1] as $r)<option value="{{ $r }}" @selected(old('rating', $t->rating) == $r)>{{ str_repeat('★', $r) }}</option>@endforeach</select></div>
                        <div class="col-md-8"><label class="form-label">Website</label><input name="website_url" class="form-control" maxlength="500" value="{{ old('website_url', $t->website_url) }}" placeholder="https://"></div>
                        <div class="col-12"><label class="form-label">Video (optional)</label><input name="video_url" class="form-control" maxlength="500" value="{{ old('video_url', $t->video_url) }}" placeholder="A YouTube or Vimeo link"></div>
                        <div class="col-md-6" data-student-only><label class="form-label">Course</label><select name="course_id" class="form-select"><option value="">—</option>@foreach($courses as $c)<option value="{{ $c->id }}" @selected(old('course_id', $t->course_id) == $c->id)>{{ $c->title }}</option>@endforeach</select></div>
                        <div class="col-md-6" data-student-only><label class="form-label">Student (links to their profile)</label><select name="student_id" class="form-select"><option value="">—</option>@foreach($students as $s)<option value="{{ $s->id }}" @selected(old('student_id', $t->student_id) == $s->id)>{{ $s->user?->name }}</option>@endforeach</select></div>
                    </div>
                </x-cb.card>
            </div>
            <div class="col-xl-4">
                <x-cb.card title="Publishing" icon="ri-eye-line">
                    <select name="status" class="form-select mb-2">@foreach(\App\Models\Website\Testimonial::STATUSES as $k => $label)<option value="{{ $k }}" @selected(old('status', $t->status) === $k)>{{ $label }}</option>@endforeach</select>
                    <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" name="featured" value="1" id="feat" @checked(old('featured', $t->featured))><label class="form-check-label" for="feat">Featured (shown large at the top)</label></div>
                    <label class="form-label small">Order</label><input name="position" type="number" min="0" max="999" class="form-control mb-3" value="{{ old('position', $t->position ?? 0) }}">
                    <button class="btn btn-primary w-100">{{ $editing ? 'Save' : 'Add testimonial' }}</button>
                    @if($editing)<div class="text-end mt-1"><button form="deleteItem" class="btn btn-link text-danger btn-sm">Remove</button></div>@endif
                </x-cb.card>
                <x-cb.card title="Photo or logo" icon="ri-image-line" class="mt-3">
                    @include('modules.website._image', ['value' => $t->photo, 'label' => 'Photo or company logo'])
                </x-cb.card>
            </div>
        </div>
    </form>
    @if($editing)<form method="POST" action="{{ route('website.testimonials.destroy', $t) }}" id="deleteItem" onsubmit="return confirm('Remove this testimonial?')">@csrf @method('DELETE')</form>@endif
</div></div></div>
@include('modules.website._picker')
@push('scripts')
<script>
(() => { const k = document.getElementById('tKind'); const f = () => document.querySelectorAll('[data-student-only]').forEach(el => el.classList.toggle('d-none', k.value === 'client')); k.addEventListener('change', f); f(); })();
</script>
@endpush
@endsection
