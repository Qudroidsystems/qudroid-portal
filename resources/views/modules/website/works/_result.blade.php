<div class="row g-2 align-items-center mb-2" data-row>
    <div class="col-4"><input name="results[{{ $i }}][value]" class="form-control form-control-sm" placeholder="3,000" value="{{ $r['value'] ?? '' }}" aria-label="Number"></div>
    <div class="col-6"><input name="results[{{ $i }}][label]" class="form-control form-control-sm" placeholder="students on the platform" value="{{ $r['label'] ?? '' }}" aria-label="What it counts"></div>
    <div class="col-2 text-end"><button type="button" class="btn btn-sm btn-ghost-secondary px-1" data-up-row title="Move up"><i class="ri-arrow-up-line"></i></button><button type="button" class="btn btn-sm btn-ghost-danger px-1" data-remove-row title="Remove"><i class="ri-delete-bin-line"></i></button></div>
</div>
