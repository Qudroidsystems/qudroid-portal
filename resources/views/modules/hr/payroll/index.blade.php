@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">

    <x-cb.hero title="Payroll" icon="ri-money-dollar-circle-line" subtitle="One run a month: prepare, have someone else approve, then pay. Paying posts it to the ledger and releases the payslips." :back="route('hr.index')" back-label="HR" />

    @include('partials.flash')

    @php $m = fn ($v) => \App\Support\Money::format($v); @endphp
    <div class="row g-3">
        <div class="col-xl-8">
            <x-cb.card title="Payroll runs" icon="ri-money-dollar-circle-line" :count="$runs->total()" :flush="true">
                @if($runs->isEmpty())
                    <div class="empty-state"><i class="ri-money-dollar-circle-line"></i><h6>No payroll yet</h6></div>
                @else
                    <div class="table-responsive"><table class="cb-table stack">
                        <thead><tr><th>Month</th><th>Status</th><th class="num">People</th><th class="num">Gross</th><th class="num">Deductions</th><th class="num">Net pay</th></tr></thead>
                        <tbody>
                        @foreach($runs as $r)
                            <tr>
                                <td data-label="Month"><a href="{{ route('hr.payroll.show', $r) }}" class="fw-semibold">{{ $r->label }}</a><div class="small text-muted">prepared by {{ $r->preparer?->name ?? '—' }}</div></td>
                                <td data-label="Status">@include('modules.hr._pill', ['value' => $r->status, 'kind' => 'run'])</td>
                                <td data-label="People" class="num">{{ $r->payslips_count }}</td>
                                <td data-label="Gross" class="num">{{ $m($r->gross) }}</td>
                                <td data-label="Deductions" class="num">{{ $m($r->deductions) }}</td>
                                <td data-label="Net pay" class="num fw-semibold">{{ $m($r->net) }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table></div>
                    <div class="p-3">{{ $runs->links() }}</div>
                @endif
            </x-cb.card>
        </div>
        <div class="col-xl-4">
            @can('Manage payroll')
                <x-cb.card title="Prepare payroll" icon="ri-add-line">
                    <form method="POST" action="{{ route('hr.payroll.store') }}">
                        @csrf
                        <label class="form-label">Month</label>
                        <input type="month" name="period" class="form-control mb-2" value="{{ $suggested->format('Y-m') }}" required>
                        <button class="btn btn-primary w-100">Prepare</button>
                        <div class="form-text">Creates a payslip for every active employee with pay set, including unpaid leave approved for that month.</div>
                    </form>
                </x-cb.card>
            @endcan
            <x-cb.card title="How tax is worked out" icon="ri-information-line">
                <div class="small text-muted">
                    PAYE follows the Nigeria Tax Act 2025 bands: the first ₦800,000 a year is tax free, then 15%, 18%, 21%, 23% and 25%.
                    Pension (8%) and NHF (2.5% of basic) come off before tax, and so does rent relief (20% of rent, up to ₦500,000).
                    Each payslip keeps its workings. Rates live in <code>config/qudroid.php</code> under <code>payroll</code>.
                </div>
            </x-cb.card>
        </div>
    </div>

</div></div></div>
@endsection
