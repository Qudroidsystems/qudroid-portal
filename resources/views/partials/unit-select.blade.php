{{-- Business unit picker. $value: the record's current unit id (or null for the default). Optional $class for the wrapper. --}}
@php $units = \App\Models\BusinessUnit::options($value ?? null); @endphp
@if($units->count() > 1)
    <div class="{{ $class ?? 'mb-3' }}">
        <label class="form-label" for="business_unit_id">Business unit</label>
        <select name="business_unit_id" id="business_unit_id" class="form-select">
            @foreach($units as $u)
                <option value="{{ $u->id }}" @selected((int) old('business_unit_id', $value ?? $default ?? \App\Models\BusinessUnit::defaultId()) === $u->id)>{{ $u->name }}{{ $u->is_active ? '' : ' (inactive)' }}</option>
            @endforeach
        </select>
    </div>
@endif
