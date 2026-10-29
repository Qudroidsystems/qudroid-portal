<div class="row g-2 align-items-start border-bottom pb-2 mb-2" data-row>
    <div class="col-md-3"><input name="features[{{ $i }}][icon]" class="form-control form-control-sm" placeholder="ri-check-line" value="{{ $f['icon'] ?? '' }}" aria-label="Icon"></div>
    <div class="col-md-8">
        <input name="features[{{ $i }}][title]" class="form-control form-control-sm mb-1" placeholder="Title" value="{{ $f['title'] ?? '' }}" aria-label="Title">
        <textarea name="features[{{ $i }}][text]" class="form-control form-control-sm" rows="2" placeholder="One or two sentences" aria-label="Text">{{ $f['text'] ?? '' }}</textarea>
    </div>
    <div class="col-md-1 text-end"><button type="button" class="btn btn-sm btn-ghost-secondary px-1" data-up-row title="Move up"><i class="ri-arrow-up-line"></i></button><button type="button" class="btn btn-sm btn-ghost-danger px-1" data-remove-row title="Remove"><i class="ri-delete-bin-line"></i></button></div>
</div>
