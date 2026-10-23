@php $days = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun']; $wd = $p->workdays ?: [1, 2, 3, 4, 5]; @endphp
<div class="row g-2">
    <div class="col-md-6"><label class="form-label small">Name</label><input name="name" class="form-control form-control-sm" value="{{ $p->name }}" required placeholder="Lagos HQ"></div>
    <div class="col-md-6"><label class="form-label small">Address</label><input name="address" class="form-control form-control-sm" value="{{ $p->address }}"></div>
    <div class="col-4"><label class="form-label small">Work starts</label><input type="time" name="work_starts" class="form-control form-control-sm" value="{{ substr((string) ($p->work_starts ?? '09:00'), 0, 5) }}" required></div>
    <div class="col-4"><label class="form-label small">Work ends</label><input type="time" name="work_ends" class="form-control form-control-sm" value="{{ substr((string) ($p->work_ends ?? '17:00'), 0, 5) }}" required></div>
    <div class="col-4"><label class="form-label small">Grace (min)</label><input type="number" name="grace_minutes" min="0" class="form-control form-control-sm" value="{{ $p->grace_minutes ?? 15 }}" required></div>
    <div class="col-12 small">@foreach($days as $n => $d)<label class="me-2"><input type="checkbox" name="workdays[]" value="{{ $n }}" @checked(in_array($n, $wd, true))> {{ $d }}</label>@endforeach</div>
    <div class="col-12">@include('modules.checkin._location', ['m' => $p])</div>
    <div class="col-12"><label class="small"><input type="checkbox" name="is_active" value="1" @checked($p->is_active ?? true)> In use</label></div>
</div>
