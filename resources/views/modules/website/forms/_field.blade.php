<div class="border rounded-3 p-2 mb-2" data-row>
    <div class="row g-2 align-items-center">
        <div class="col-md-5"><input name="fields[{{ $i }}][label]" class="form-control form-control-sm" placeholder="Label, e.g. Company" value="{{ $f['label'] ?? '' }}" aria-label="Label"></div>
        <div class="col-md-3"><select name="fields[{{ $i }}][type]" class="form-select form-select-sm" aria-label="Type">@foreach(\App\Models\Website\Form::TYPES as $k => $v)<option value="{{ $k }}" @selected(($f['type'] ?? 'text') === $k)>{{ $v }}</option>@endforeach</select></div>
        <div class="col-md-2"><div class="form-check mb-0"><input type="hidden" name="fields[{{ $i }}][required]" value="0"><input class="form-check-input" type="checkbox" name="fields[{{ $i }}][required]" value="1" id="req{{ $i }}" @checked(!empty($f['required']))><label class="form-check-label small" for="req{{ $i }}">Required</label></div></div>
        <div class="col-md-2 text-end"><button type="button" class="btn btn-sm btn-ghost-secondary px-1" data-up-row title="Move up"><i class="ri-arrow-up-line"></i></button><button type="button" class="btn btn-sm btn-ghost-danger px-1" data-remove-row title="Remove"><i class="ri-delete-bin-line"></i></button></div>
        <div class="col-md-5"><input name="fields[{{ $i }}][help]" class="form-control form-control-sm" placeholder="Help text (optional)" value="{{ $f['help'] ?? '' }}" aria-label="Help text"></div>
        <div class="col-md-7"><input name="fields[{{ $i }}][options]" class="form-control form-control-sm" placeholder="Choices for Choose one, separated by commas" value="{{ is_array($f['options'] ?? null) ? implode(', ', $f['options']) : ($f['options'] ?? '') }}" aria-label="Choices"></div>
        <input type="hidden" name="fields[{{ $i }}][key]" value="{{ $f['key'] ?? '' }}">
    </div>
</div>
