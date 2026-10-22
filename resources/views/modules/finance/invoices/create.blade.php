@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">

    <x-cb.hero title="New invoice" icon="ri-file-add-line" subtitle="For clients, services and anything else. Course fees are invoiced automatically when a student is enrolled." :back="route('finance.invoices.index')" back-label="Invoices" />

    @include('partials.flash')

    <form method="POST" action="{{ route('finance.invoices.store') }}">
        @csrf
        <div class="row g-3">
            <div class="col-xl-8">
                <x-cb.card title="Lines" icon="ri-list-check">
                    <div class="table-responsive">
                        <table class="table align-middle mb-2" id="lines">
                            <thead><tr><th style="width:50%">Description</th><th>Qty</th><th>Unit price</th><th>Discount</th></tr></thead>
                            <tbody>
                            @php $lines = old('lines', array_fill(0, 3, [])); @endphp
                            @foreach($lines as $n => $l)
                                <tr>
                                    <td><input name="lines[{{ $n }}][description]" class="form-control" maxlength="255" value="{{ $l['description'] ?? '' }}"></td>
                                    <td><input name="lines[{{ $n }}][quantity]" type="number" step="0.01" min="0.01" class="form-control" value="{{ $l['quantity'] ?? 1 }}"></td>
                                    <td><input name="lines[{{ $n }}][unit_price]" type="number" step="0.01" min="0" class="form-control" value="{{ $l['unit_price'] ?? '' }}"></td>
                                    <td><input name="lines[{{ $n }}][discount]" type="number" step="0.01" min="0" class="form-control" value="{{ $l['discount'] ?? 0 }}"></td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                    <button type="button" class="btn btn-sm btn-soft-primary" id="add-line"><i class="ri-add-line"></i> Add line</button>
                    <div class="form-text">Empty lines are ignored.</div>
                </x-cb.card>
            </div>
            <div class="col-xl-4">
                <x-cb.card title="Invoice" icon="ri-file-list-3-line">
                    <div class="mb-2"><label class="form-label">Bill to</label>
                        <select name="user_id" class="form-select" required><option value="">Choose…</option>
                            @foreach($people as $p)<option value="{{ $p->id }}" @selected(old('user_id', $selected) == $p->id)>{{ $p->name }} — {{ $p->email }} ({{ $p->user_type_label }})</option>@endforeach
                        </select>
                        <div class="form-text">Customers need an account. Add clients in User Management first.</div>
                    </div>
                    <div class="mb-2"><label class="form-label">Title</label><input name="title" class="form-control" required maxlength="200" value="{{ old('title') }}" placeholder="e.g. Website development — milestone 1"></div>
                    <div class="row g-2 mb-2">
                        <div class="col-7"><label class="form-label">Due date</label><input name="due_date" type="date" class="form-control" value="{{ old('due_date', today()->addDays(config('qudroid.finance.default_due_days', 14))->format('Y-m-d')) }}"></div>
                        <div class="col-5"><label class="form-label">Currency</label><input name="currency" class="form-control text-uppercase" maxlength="3" required value="{{ old('currency', config('qudroid.academy.currency', 'NGN')) }}"></div>
                    </div>
                    <div class="mb-3"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="3">{{ old('notes') }}</textarea></div>
                    <button class="btn btn-primary w-100">Issue invoice</button>
                    <div class="form-text">An issued invoice can't be edited. Mistakes are fixed by voiding it and issuing a new one.</div>
                </x-cb.card>
            </div>
        </div>
    </form>

</div></div></div>
@endsection

@push('scripts')
<script>
document.getElementById('add-line').addEventListener('click', function () {
    const body = document.querySelector('#lines tbody');
    const n = body.rows.length;
    const row = body.rows[0].cloneNode(true);
    row.querySelectorAll('input').forEach(function (input) {
        input.name = input.name.replace(/lines\[\d+\]/, 'lines[' + n + ']');
        input.value = input.name.endsWith('[quantity]') ? 1 : (input.name.endsWith('[discount]') ? 0 : '');
    });
    body.appendChild(row);
});
</script>
@endpush
