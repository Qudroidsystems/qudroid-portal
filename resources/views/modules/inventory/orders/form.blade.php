@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">

    @php $editing = $po->exists; $rows = old('lines', $lines); @endphp
    <x-cb.hero :title="$editing ? 'Edit ' . $po->number : 'New purchase order'" icon="ri-shopping-cart-2-line" :back="$editing ? route('inventory.orders.show', $po) : route('inventory.orders.index')" :back-label="$editing ? $po->number : 'Purchase orders'" />

    @include('partials.flash')

    @if($suppliers->isEmpty())
        <div class="cb-banner warning"><i class="ri-information-line"></i><div>Add a supplier first. <a href="{{ route('inventory.suppliers.create') }}">New supplier</a></div></div>
    @endif

    <form method="POST" action="{{ $editing ? route('inventory.orders.update', $po) : route('inventory.orders.store') }}">
        @csrf
        @if($editing) @method('PUT') @endif
        <x-cb.card>
            <div class="row g-3">
                <div class="col-md-5"><label class="form-label">Supplier</label>
                    <select name="supplier_id" class="form-select" required><option value="">Choose…</option>@foreach($suppliers as $s)<option value="{{ $s->id }}" @selected((int) old('supplier_id', $po->supplier_id) === $s->id)>{{ $s->name }}</option>@endforeach</select></div>
                <div class="col-md-3"><label class="form-label">Expected</label><input type="date" name="expected_on" class="form-control" value="{{ old('expected_on', $po->expected_on?->toDateString()) }}"></div>
                <div class="col-md-4"><label class="form-label">Deliver to</label>
                    <select name="location_id" class="form-select">@foreach($locations as $l)<option value="{{ $l->id }}" @selected((int) old('location_id', $po->location_id) === $l->id)>{{ $l->name }}</option>@endforeach</select></div>
            </div>
        </x-cb.card>

        <x-cb.card title="Lines" icon="ri-list-check-2" :flush="true">
            <div class="table-responsive"><table class="cb-table" id="po-lines">
                <thead><tr><th style="width: 34%">Stock item</th><th>Description</th><th style="width: 110px">Qty</th><th style="width: 150px">Price each</th><th class="num" style="width: 130px">Amount</th><th style="width: 40px"></th></tr></thead>
                <tbody>
                @foreach($rows as $i => $l)
                    <tr>
                        <td><select name="lines[{{ $i }}][item_id]" class="form-select form-select-sm po-item"><option value="">Not a stock item</option>@foreach($items as $it)<option value="{{ $it->id }}" data-name="{{ $it->name }}" data-cost="{{ (float) $it->avg_cost }}" @selected((int) ($l['item_id'] ?? 0) === $it->id)>{{ $it->name }} ({{ $it->sku }})</option>@endforeach</select></td>
                        <td><input name="lines[{{ $i }}][description]" class="form-control form-control-sm po-desc" value="{{ $l['description'] ?? '' }}" maxlength="255"></td>
                        <td><input type="number" step="0.01" min="0" name="lines[{{ $i }}][quantity]" class="form-control form-control-sm po-qty" value="{{ $l['quantity'] ?? '' }}"></td>
                        <td><input type="number" step="0.01" min="0" name="lines[{{ $i }}][unit_cost]" class="form-control form-control-sm po-cost" value="{{ $l['unit_cost'] ?? '' }}"></td>
                        <td class="num po-amt">0.00</td>
                        <td><button type="button" class="btn btn-sm btn-link text-danger po-del"><i class="ri-close-line"></i></button></td>
                    </tr>
                @endforeach
                </tbody>
                <tfoot><tr><td colspan="4"><button type="button" class="btn btn-sm btn-soft-primary" id="po-add"><i class="ri-add-line"></i> Add line</button></td><td class="num fw-bold" id="po-total">0.00</td><td></td></tr></tfoot>
            </table></div>
        </x-cb.card>

        <x-cb.card>
            <label class="form-label">Notes for the supplier</label>
            <textarea name="notes" class="form-control" rows="2" maxlength="5000">{{ old('notes', $po->notes) }}</textarea>
            <div class="mt-3"><button class="btn btn-primary">{{ $editing ? 'Save' : 'Save draft' }}</button></div>
        </x-cb.card>
    </form>

</div></div></div>
@endsection

@push('scripts')
<script>
(function () {
    var body = document.querySelector('#po-lines tbody');
    var n = body.rows.length;
    function fmt(v) { return v.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
    function total() {
        var t = 0;
        body.querySelectorAll('tr').forEach(function (tr) {
            var a = (parseFloat(tr.querySelector('.po-qty').value) || 0) * (parseFloat(tr.querySelector('.po-cost').value) || 0);
            tr.querySelector('.po-amt').textContent = fmt(a);
            t += a;
        });
        document.getElementById('po-total').textContent = fmt(t);
    }
    body.addEventListener('input', total);
    body.addEventListener('change', function (e) {
        if (!e.target.classList.contains('po-item')) return;
        var opt = e.target.selectedOptions[0], tr = e.target.closest('tr');
        if (opt && opt.value) {
            var d = tr.querySelector('.po-desc'), c = tr.querySelector('.po-cost');
            if (!d.value) d.value = opt.dataset.name;
            if (!c.value && parseFloat(opt.dataset.cost) > 0) c.value = opt.dataset.cost;
        }
        total();
    });
    body.addEventListener('click', function (e) {
        var b = e.target.closest('.po-del');
        if (b && body.rows.length > 1) { b.closest('tr').remove(); total(); }
    });
    document.getElementById('po-add').addEventListener('click', function () {
        var tr = body.rows[0].cloneNode(true);
        tr.querySelectorAll('[name]').forEach(function (el) { el.name = el.name.replace(/lines\[\d+\]/, 'lines[' + n + ']'); el.value = el.tagName === 'SELECT' ? '' : ''; });
        n++;
        body.appendChild(tr);
        total();
    });
    total();
})();
</script>
@endpush
