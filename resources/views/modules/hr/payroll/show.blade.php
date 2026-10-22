@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">

    @php
        $m = fn ($v) => \App\Support\Money::format($v);
        $draft = $run->status === 'draft';
        $canEdit = $draft && auth()->user()->can('Manage payroll');
    @endphp
    <x-cb.hero :title="'Payroll ' . $run->label" icon="ri-money-dollar-circle-line" :subtitle="$slips->count() . ' payslips · net pay ' . $m($run->net)" :back="route('hr.payroll.index')" back-label="Payroll">
        <x-slot:actions>
            @if($canEdit)
                <form method="POST" action="{{ route('hr.payroll.generate', $run) }}" class="d-inline">@csrf<button class="cb-hero-btn"><i class="ri-refresh-line"></i>Recalculate</button></form>
            @endif
        </x-slot:actions>
    </x-cb.hero>

    @include('partials.flash')

    <div class="row g-3 mb-3">
        <div class="col-xl-3 col-md-6"><x-cb.stat label="Gross pay" :value="$m($run->gross)" icon="ri-wallet-3-line" accent="teal" /></div>
        <div class="col-xl-3 col-md-6"><x-cb.stat label="Deductions" :value="$m($run->deductions)" icon="ri-subtract-line" accent="amber" hint="PAYE, pension, NHF and other" /></div>
        <div class="col-xl-3 col-md-6"><x-cb.stat label="Net pay" :value="$m($run->net)" icon="ri-bank-line" accent="green" hint="to employees' banks" /></div>
        <div class="col-xl-3 col-md-6"><x-cb.stat label="Employer pension" :value="$m($run->employer_pension)" icon="ri-shield-check-line" accent="sky" hint="on top of gross" /></div>
    </div>

    <div class="row g-3">
        <div class="col-xl-9">
            <x-cb.card title="Payslips" icon="ri-file-list-3-line" :count="$slips->count()" :flush="true">
                @if($slips->isEmpty())
                    <div class="empty-state"><i class="ri-file-list-3-line"></i><h6>No payslips</h6><p class="small text-muted">Set pay on employee records, then recalculate.</p></div>
                @else
                    <div class="table-responsive"><table class="cb-table stack">
                        <thead><tr><th>Employee</th><th class="num">Gross</th><th class="num">PAYE</th><th class="num">Pension</th><th class="num">NHF</th><th class="num">Other</th><th class="num">Net</th><th></th></tr></thead>
                        <tbody>
                        @foreach($slips as $s)
                            <tr>
                                <td data-label="Employee">
                                    <span class="fw-semibold">{{ $s->employee->user->name }}</span>
                                    <div class="small text-muted">{{ $s->employee->employee_no }}@if((float) $s->bonus > 0) · bonus {{ $m($s->bonus) }}@endif @if($s->unpaid_days > 0) · {{ rtrim(rtrim(number_format($s->unpaid_days, 1), '0'), '.') }} unpaid days @endif</div>
                                    @if(empty($s->bank['account_number']))<div class="small text-danger">No bank account on file</div>@endif
                                </td>
                                <td data-label="Gross" class="num">{{ $m($s->gross) }}</td>
                                <td data-label="PAYE" class="num">{{ $m($s->paye) }}</td>
                                <td data-label="Pension" class="num">{{ $m($s->pension) }}</td>
                                <td data-label="NHF" class="num">{{ $m($s->nhf) }}</td>
                                <td data-label="Other" class="num" title="{{ $s->other_deductions_note }}">{{ $m($s->other_deductions) }}</td>
                                <td data-label="Net" class="num fw-semibold">{{ $m($s->net) }}</td>
                                <td class="text-end text-nowrap">
                                    @if($canEdit)<button type="button" class="btn btn-sm btn-soft-secondary" data-bs-toggle="collapse" data-bs-target="#adj-{{ $s->id }}" title="Bonus or deduction"><i class="ri-pencil-line"></i></button>@endif
                                    <a href="{{ route('hr.payroll.payslip', [$run, $s, 'view' => 1]) }}" target="_blank" class="btn btn-sm btn-soft-secondary" title="Payslip PDF"><i class="ri-file-pdf-2-line"></i></a>
                                </td>
                            </tr>
                            @if($canEdit)
                                <tr class="collapse" id="adj-{{ $s->id }}"><td colspan="8">
                                    <form method="POST" action="{{ route('hr.payroll.adjust', [$run, $s]) }}" class="row g-2 align-items-end">
                                        @csrf @method('PUT')
                                        <div class="col-md-3"><label class="form-label small">Bonus this month</label><input type="number" step="0.01" min="0" name="bonus" class="form-control" value="{{ (float) $s->bonus ?: '' }}"></div>
                                        <div class="col-md-3"><label class="form-label small">Other deduction</label><input type="number" step="0.01" min="0" name="other_deductions" class="form-control" value="{{ (float) $s->other_deductions ?: '' }}"></div>
                                        <div class="col-md-4"><label class="form-label small">What for</label><input name="other_deductions_note" class="form-control" value="{{ $s->other_deductions_note }}" maxlength="255" placeholder="e.g. Salary advance"></div>
                                        <div class="col-md-2"><button class="btn btn-primary w-100">Apply</button></div>
                                    </form>
                                </td></tr>
                            @endif
                        @endforeach
                        </tbody>
                    </table></div>
                @endif
            </x-cb.card>
        </div>

        <div class="col-xl-3">
            <x-cb.card title="Status" icon="ri-history-line">
                <div class="mb-2">@include('modules.hr._pill', ['value' => $run->status, 'kind' => 'run'])</div>
                <dl class="row mb-0 small">
                    <dt class="col-5 text-muted fw-normal">Prepared</dt><dd class="col-7">{{ $run->preparer?->name ?? '—' }}</dd>
                    <dt class="col-5 text-muted fw-normal">Approved</dt><dd class="col-7">{{ $run->approver ? $run->approver->name . ', ' . $run->approved_at->format('j M') : '—' }}</dd>
                    <dt class="col-5 text-muted fw-normal">Paid</dt><dd class="col-7">{{ $run->paid_at ? $run->paid_at->format('j M Y') . ' from ' . $run->paidFrom?->name : '—' }}</dd>
                </dl>
                @if($entries->isNotEmpty() && Route::has('finance.ledger.journal'))
                    <div class="small mt-2">Ledger: @foreach($entries as $j)<a href="{{ route('finance.ledger.journal', $j) }}">{{ $j->number }}</a>@if(!$loop->last), @endif @endforeach</div>
                @endif
            </x-cb.card>

            @if($draft && $slips->isNotEmpty())
                @if($run->prepared_by !== auth()->id())
                    <x-cb.card title="Approve" icon="ri-check-double-line">
                        <form method="POST" action="{{ route('hr.payroll.approve', $run) }}" onsubmit="return confirm('Approve payroll for {{ $run->label }}: {{ $m($run->net) }} net?')">
                            @csrf<button class="btn btn-success w-100">Approve payroll</button>
                        </form>
                    </x-cb.card>
                @else
                    <div class="cb-banner info"><i class="ri-information-line"></i><div>You prepared this payroll, so someone else with payroll or finance approval must approve it.</div></div>
                @endif
            @endif

            @if($run->status === 'approved')
                @can('Manage payroll')
                    <x-cb.card title="Mark paid" icon="ri-bank-card-line">
                        <form method="POST" action="{{ route('hr.payroll.pay', $run) }}" onsubmit="return confirm('Mark {{ $run->label }} paid? This posts it to the ledger and can\'t be undone here.')">
                            @csrf
                            <label class="form-label">Paid from</label>
                            <select name="paid_from_account_id" class="form-select mb-2" required>
                                @foreach($payFrom as $a)<option value="{{ $a->id }}" @selected($a->code === \App\Models\Finance\Account::BANK)>{{ $a->label }}</option>@endforeach
                            </select>
                            <label class="form-label">Date paid</label>
                            <input type="date" name="paid_on" class="form-control mb-2" value="{{ today()->toDateString() }}" required>
                            <button class="btn btn-primary w-100">Mark paid</button>
                            <div class="form-text">Posts salaries, PAYE, pension and NHF to the ledger and sends everyone their payslip.</div>
                        </form>
                    </x-cb.card>
                    <form method="POST" action="{{ route('hr.payroll.reopen', $run) }}">@csrf<button class="btn btn-soft-secondary w-100 mb-3">Back to draft</button></form>
                @endcan
            @endif

            @if($canEdit)
                <form method="POST" action="{{ route('hr.payroll.destroy', $run) }}" onsubmit="return confirm('Discard this draft payroll?')">@csrf @method('DELETE')<button class="btn btn-soft-danger w-100">Discard draft</button></form>
            @endif
        </div>
    </div>

</div></div></div>
@endsection
