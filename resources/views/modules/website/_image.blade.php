{{-- A picture field: upload, pick from Pictures, or remove. Needs @include('modules.website._picker') once on the page. --}}
@php $url = \App\Models\Website\Product::url($value ?? null); @endphp
<div class="ws-image" data-ws-image>
    <label class="form-label">{{ $label ?? 'Picture' }}</label>
    <div class="ws-image-preview mb-2 {{ $url ? '' : 'd-none' }}"><img src="{{ $url }}" alt=""></div>
    <input type="hidden" name="image" value="{{ old('image', $value ?? '') }}" data-ws-image-value>
    <div class="d-flex gap-2 flex-wrap">
        <button type="button" class="btn btn-sm btn-soft-primary" data-ws-pick><i class="ri-image-add-line me-1"></i>Choose from pictures</button>
        <label class="btn btn-sm btn-soft-secondary mb-0"><i class="ri-upload-2-line me-1"></i>Upload<input type="file" name="image_file" accept="image/*" class="d-none" data-ws-image-file></label>
        <button type="button" class="btn btn-sm btn-soft-danger {{ $url ? '' : 'd-none' }}" data-ws-image-clear><i class="ri-close-line"></i> Remove</button>
    </div>
    <input type="hidden" name="remove_image" value="0" data-ws-image-remove>
    @if(!empty($help))<div class="form-text">{{ $help }}</div>@endif
</div>
