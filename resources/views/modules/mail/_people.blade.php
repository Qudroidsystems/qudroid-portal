{{-- Pick people: a filterable multi-select. $selected: user ids. --}}
@php $selected = collect(old('users', $selected ?? []))->map(fn ($i) => (int) $i)->all(); @endphp
<input type="search" class="form-control form-control-sm mb-1" placeholder="Filter people" data-people-filter aria-label="Filter people">
<select name="users[]" multiple class="form-select form-select-sm" size="6" required aria-label="People">
    @foreach($users as $u)<option value="{{ $u->id }}" @selected(in_array($u->id, $selected, true))>{{ $u->name }} ({{ $u->email }})</option>@endforeach
</select>
<div class="form-text">Hold Ctrl (or ⌘) to choose several.</div>
@once
@push('scripts')
<script>
document.addEventListener('input', function (e) {
    if (!e.target.matches('[data-people-filter]')) return;
    var t = e.target.value.toLowerCase(), sel = e.target.nextElementSibling;
    Array.prototype.forEach.call(sel.options, function (o) { o.hidden = t && !o.selected && o.text.toLowerCase().indexOf(t) === -1; });
});
</script>
@endpush
@endonce
