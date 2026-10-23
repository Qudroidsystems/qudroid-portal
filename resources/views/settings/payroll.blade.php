@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">
    <x-cb.hero title="Settings" icon="ri-settings-4-line" subtitle="Portal-wide settings. Changes here take effect straight away." />
    @include('settings._tabs')
    @include('partials.flash')

    @if($review)
        <div class="cb-banner success"><i class="ri-shield-check-line"></i><div>Checked by <strong>{{ $review['name'] }}</strong> on {{ \Illuminate\Support\Carbon::parse($review['at'])->format('j M Y') }}@if(!empty($review['note'])): “{{ $review['note'] }}”@endif</div></div>
    @else
        <div class="cb-banner warning"><i class="ri-error-warning-line"></i><div>Nobody has confirmed these rates yet. Ask your accountant to check them before the first live payroll, then record it at the bottom of this page.</div></div>
    @endif

    @php $bands = old('bands', array_map(fn ($b) => ['width' => $b[0], 'rate' => $b[1]], $c['tax_bands'] ?? [])); @endphp
    <form method="POST" action="{{ route('portal-settings.payroll.save') }}">
        @csrf
        <div class="row g-3">
            <div class="col-xl-7">
                <x-cb.card title="PAYE tax bands (a year)" icon="ri-percent-line" :flush="true">
                    <x-slot:tools><span class="badge {{ $custom ? 'bg-info' : 'bg-light text-muted' }}">{{ $custom ? 'Edited here' : 'Built-in defaults' }}</span></x-slot:tools>
                    <div class="p-3 small text-muted">Each band is taxed at its rate, in order, on the year's taxable income (after pension, NHF and rent relief). The last band covers everything above.</div>
                    <table class="table align-middle mb-0" id="bandTable">
                        <thead class="table-light"><tr><th>Band</th><th>Width (₦ a year)</th><th style="width:130px">Rate %</th><th></th></tr></thead>
                        <tbody>
                            @foreach($bands as $i => $b)
                                <tr>
                                    <td class="text-muted small band-label"></td>
                                    <td><input type="number" step="1" min="1" name="bands[{{ $i }}][width]" value="{{ $b['width'] }}" class="form-control band-width" placeholder="Everything above"></td>
                                    <td><input type="number" step="0.01" min="0" max="100" name="bands[{{ $i }}][rate]" value="{{ $b['rate'] }}" class="form-control" required></td>
                                    <td class="text-end"><button type="button" class="btn btn-sm btn-ghost-danger band-del" title="Remove band"><i class="ri-delete-bin-line"></i></button></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <div class="p-3 border-top"><button type="button" class="btn btn-sm btn-soft-primary" id="addBand"><i class="ri-add-line me-1"></i>Add a band</button></div>
                </x-cb.card>
            </div>
            <div class="col-xl-5">
                <x-cb.card title="Deductions and reliefs" icon="ri-scales-3-line">
                    @foreach([
                        'pension_employee' => ['Employee pension', '% of basic + housing + transport', '0.01'],
                        'pension_employer' => ['Employer pension', '% of basic + housing + transport', '0.01'],
                        'nhf_rate' => ['National Housing Fund', '% of basic', '0.01'],
                        'rent_relief_rate' => ['Rent relief', '% of annual rent', '0.01'],
                        'rent_relief_cap' => ['Rent relief cap', '₦ a year', '1'],
                        'working_days_month' => ['Working days in a month', 'for unpaid-leave deductions', '1'],
                    ] as $field => [$label, $hint, $step])
                        <div class="row g-2 align-items-center mb-2">
                            <label class="col-7 col-form-label" for="{{ $field }}">{{ $label }}<div class="small text-muted">{{ $hint }}</div></label>
                            <div class="col-5"><input type="number" step="{{ $step }}" min="0" id="{{ $field }}" name="{{ $field }}" value="{{ old($field, $c[$field] ?? '') }}" class="form-control" required></div>
                        </div>
                    @endforeach
                </x-cb.card>
                <x-cb.card title="What these rates give" icon="ri-calculator-line" :flush="true">
                    <table class="table table-sm mb-0">
                        <thead class="table-light"><tr><th>Taxable a year</th><th class="text-end">Tax a year</th><th class="text-end">Effective</th></tr></thead>
                        <tbody>
                            @foreach($examples as $e)
                                <tr><td>₦{{ number_format($e['income']) }}</td><td class="text-end">₦{{ number_format($e['tax'], 2) }}</td><td class="text-end">{{ number_format($e['rate'], 1) }}%</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                    <div class="small text-muted p-2">Worked out with the saved rates. Save first to see a change here.</div>
                </x-cb.card>
            </div>
        </div>
        <div class="d-flex gap-2 mb-3">
            <button class="btn btn-primary"><i class="ri-save-line me-1"></i>Save rates</button>
            <span class="small text-muted align-self-center">New rates apply the next time a payroll is generated. Payslips already worked out keep their figures.</span>
        </div>
    </form>

    <div class="row g-3">
        <div class="col-xl-7">
            <x-cb.card title="Accountant's check" icon="ri-shield-check-line">
                <form method="POST" action="{{ route('portal-settings.payroll.review') }}" class="row g-2">
                    @csrf
                    <div class="col-md-5"><input name="reviewer" class="form-control" placeholder="Accountant's name" maxlength="120" required></div>
                    <div class="col-md-7"><input name="note" class="form-control" placeholder="Note (optional), e.g. firm or reference" maxlength="500"></div>
                    <div class="col-12"><button class="btn btn-soft-success"><i class="ri-check-line me-1"></i>Record that these rates were checked</button></div>
                </form>
                <div class="small text-muted mt-2">If the rates change afterwards, the check is cleared and needs doing again.</div>
            </x-cb.card>
        </div>
        @if($custom)
            <div class="col-xl-5">
                <x-cb.card title="Start again" icon="ri-restart-line">
                    <form method="POST" action="{{ route('portal-settings.payroll.reset') }}" onsubmit="return confirm('Go back to the built-in rates?')">@csrf
                        <p class="small text-muted">Go back to the built-in Nigeria Tax Act 2025 rates.</p>
                        <button class="btn btn-soft-danger">Use the built-in rates</button>
                    </form>
                    @if($last)<div class="small text-muted mt-2">Last changed {{ $last->updated_at->diffForHumans() }}{{ $last->editor ? ' by ' . $last->editor->name : '' }}.</div>@endif
                </x-cb.card>
            </div>
        @endif
    </div>
</div></div></div>
@endsection

@push('scripts')
<script>
(function () {
    const body = document.querySelector('#bandTable tbody');
    function renumber() {
        const rows = [...body.querySelectorAll('tr')];
        rows.forEach((tr, i) => {
            tr.querySelector('.band-label').textContent = i === rows.length - 1 ? 'Above' : (i === 0 ? 'First' : 'Next');
            tr.querySelectorAll('input').forEach(inp => inp.name = inp.name.replace(/bands\[\d+\]/, 'bands[' + i + ']'));
            const w = tr.querySelector('.band-width');
            w.disabled = i === rows.length - 1;
            if (w.disabled) w.value = '';
        });
    }
    document.getElementById('addBand').addEventListener('click', () => {
        const rows = body.querySelectorAll('tr');
        const copy = rows[rows.length - 1].cloneNode(true);
        copy.querySelectorAll('input').forEach(i => i.value = '');
        body.insertBefore(copy, rows[rows.length - 1]);
        renumber();
    });
    body.addEventListener('click', e => {
        const b = e.target.closest('.band-del');
        if (b && body.querySelectorAll('tr').length > 1) { b.closest('tr').remove(); renumber(); }
    });
    renumber();
})();
</script>
@endpush
