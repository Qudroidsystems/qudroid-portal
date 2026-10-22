@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">

    @php $editing = $lesson->exists; $type = old('type', $lesson->type); @endphp
    <x-cb.hero :title="$editing ? 'Edit lesson' : 'New lesson'" icon="ri-file-edit-line" :subtitle="$course->title" :back="route('academy.content.index', $course)" back-label="Content" />

    @include('partials.flash')

    <form method="POST" enctype="multipart/form-data" action="{{ $editing ? route('academy.content.lessons.update', [$course, $lesson]) : route('academy.content.lessons.store', $course) }}">
        @csrf @if($editing) @method('PUT') @endif
        <div class="row g-3">
            <div class="col-xl-8">
                <x-cb.card title="Lesson" icon="ri-file-text-line">
                    <div class="mb-3"><label class="form-label">Title</label><input name="title" class="form-control" required maxlength="200" value="{{ old('title', $lesson->title) }}"></div>
                    <div class="mb-3">
                        <label class="form-label d-block">Type</label>
                        @foreach(\App\Models\Academy\Lesson::TYPES as $k => $v)
                            <div class="form-check form-check-inline"><input class="form-check-input lesson-type" type="radio" name="type" id="t-{{ $k }}" value="{{ $k }}" @checked($type === $k)><label class="form-check-label" for="t-{{ $k }}"><i class="{{ \App\Models\Academy\Lesson::ICONS[$k] }}"></i> {{ $v }}</label></div>
                        @endforeach
                    </div>
                    <div class="mb-3 type-field" data-types="video"><label class="form-label">YouTube or Vimeo link</label><input name="video_url" type="url" class="form-control" maxlength="500" value="{{ old('video_url', $lesson->video_url) }}" placeholder="https://www.youtube.com/watch?v=…"></div>
                    <div class="mb-3 type-field" data-types="link"><label class="form-label">Link</label><input name="external_url" type="url" class="form-control" maxlength="500" value="{{ old('external_url', $lesson->external_url) }}" placeholder="https://…"></div>
                    <div class="mb-3 type-field" data-types="file">
                        <label class="form-label">File</label>
                        <input name="file" type="file" class="form-control">
                        <div class="form-text">PDF, Office, images, zip, MP3/MP4. Up to {{ round(config('qudroid.academy.max_upload_kb', 51200) / 1024) }} MB.@if($lesson->file_name) Current: <strong>{{ $lesson->file_name }}</strong> — choose a file only to replace it.@endif</div>
                    </div>
                    <div class="mb-0"><label class="form-label">Text <span class="text-muted">(Markdown; optional for videos, files and links)</span></label><textarea name="body" class="form-control font-monospace" rows="16">{{ old('body', $lesson->body) }}</textarea></div>
                </x-cb.card>
            </div>
            <div class="col-xl-4">
                <x-cb.card title="Settings" icon="ri-settings-3-line">
                    <div class="mb-2"><label class="form-label">Section</label>
                        <select name="section_id" class="form-select"><option value="">Other lessons (no section)</option>
                            @foreach($sections as $id => $t)<option value="{{ $id }}" @selected(old('section_id', $lesson->section_id) == $id)>{{ $t }}</option>@endforeach
                        </select>
                    </div>
                    <div class="mb-3"><label class="form-label">Length (minutes)</label><input name="duration_minutes" type="number" min="1" class="form-control" value="{{ old('duration_minutes', $lesson->duration_minutes) }}"></div>
                    <div class="form-check form-switch mb-2"><input type="hidden" name="is_published" value="0"><input class="form-check-input" type="checkbox" id="pub" name="is_published" value="1" @checked(old('is_published', $lesson->is_published))><label class="form-check-label" for="pub">Published (students can see it)</label></div>
                    <div class="form-check form-switch mb-3"><input type="hidden" name="is_preview" value="0"><input class="form-check-input" type="checkbox" id="prev" name="is_preview" value="1" @checked(old('is_preview', $lesson->is_preview))><label class="form-check-label" for="prev">Free preview (open before payment)</label></div>
                    <button class="btn btn-primary w-100">{{ $editing ? 'Save lesson' : 'Add lesson' }}</button>
                </x-cb.card>
            </div>
        </div>
    </form>

</div></div></div>
@endsection

@push('scripts')
<script>
(function () {
    function sync() {
        var t = (document.querySelector('.lesson-type:checked') || {}).value;
        document.querySelectorAll('.type-field').forEach(function (el) { el.style.display = el.dataset.types.split(' ').indexOf(t) >= 0 ? '' : 'none'; });
    }
    document.querySelectorAll('.lesson-type').forEach(function (r) { r.addEventListener('change', sync); });
    sync();
})();
</script>
@endpush
