@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">

    <x-cb.hero title="Manual journal entry" icon="ri-file-list-3-line" subtitle="For opening balances, owner's capital, bank charges and corrections. Debits must equal credits." :back="route('finance.ledger.journals')" back-label="Journal" />

    @include('partials.flash')

    <x-cb.card>
        <form method="POST" action="{{ route('finance.ledger.journals.store') }}" id="jform">
            @csrf
            <div class="row g-3 mb-3">
                <div class="col-md-3"><label class="form-label">Date</label><input type="date" name="date" class="form-control" value="{{ old('date', today()->toDateString()) }}" max="{{ today()->toDateString() }}" required></div>
                <div class="col-md-9"><label class="form-label">Description</label><input name="description" class="form-control" value="{{ old('description') }}" maxlength="255" required placeholder="e.g. Opening bank balance"></div>
            </div>
            <div class="table-responsive"><table class="cb-table">
                <thead><tr><th>Account</th><th>Memo</th><th style="width:10rem" class="num">Debit</th><th style="width:10rem" class="num">Credit</th></tr></thead>
                <tbody>
                @for($i = 0; $i < max(4, count(old('lines', []))); $i++)
                    <tr>
                        <td><select name="lines[{{ $i }}][account]" class="form-select form-select-sm">
                            <option value="">—</option>
                            @foreach($accounts as $a)<option value="{{ $a->id }}" @selected((int) old("lines.$i.account") === $a->id)>{{ $a->label }}</option>@endforeach
                        </select></td>
                        <td><input name="lines[{{ $i }}][memo]" class="form-control form-control-sm" value="{{ old("lines.$i.memo") }}" maxlength="255"></td>
                        <td><input type="number" step="0.01" min="0" name="lines[{{ $i }}][debit]" class="form-control form-control-sm text-end jd" value="{{ old("lines.$i.debit") }}"></td>
                        <td><input type="number" step="0.01" min="0" name="lines[{{ $i }}][credit]" class="form-control form-control-sm text-end jc" value="{{ old("lines.$i.credit") }}"></td>
                    </tr>
                @endfor
                <tr class="fw-semibold"><td colspan="2">Total <span id="jdiff" class="small text-danger ms-2"></span></td><td class="num" id="jdt">0.00</td><td class="num" id="jct">0.00</td></tr>
                </tbody>
            </table></div>
            <div class="mt-3"><button class="btn btn-primary">Post entry</button></div>
        </form>
    </x-cb.card>

</div></div></div>
@endsection

@push('scripts')
<script>
(function () {
    const f = document.getElementById('jform');
    const sum = (sel) => [...f.querySelectorAll(sel)].reduce((s, el) => s + Math.round((parseFloat(el.value) || 0) * 100), 0);
    const upd = () => {
        const d = sum('.jd'), c = sum('.jc');
        document.getElementById('jdt').textContent = (d / 100).toFixed(2);
        document.getElementById('jct').textContent = (c / 100).toFixed(2);
        document.getElementById('jdiff').textContent = d === c ? '' : 'Out by ' + (Math.abs(d - c) / 100).toFixed(2);
    };
    f.addEventListener('input', upd); upd();
})();
</script>
@endpush
