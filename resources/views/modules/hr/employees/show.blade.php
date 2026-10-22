@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">

    @php
        $m = fn ($v) => \App\Support\Money::format($v);
        $mm = fn ($minor) => \App\Support\Money::format(\App\Support\Money::major($minor));
        $d = fn ($n) => rtrim(rtrim(number_format((float) $n, 1), '0'), '.');
    @endphp
    <x-cb.hero :title="$employee->user->name" icon="ri-user-line" :subtitle="$employee->employee_no . ($employee->job_title ? ' · ' . $employee->job_title : '') . ($employee->department ? ' · ' . $employee->department->name : '')" :back="route('hr.employees.index')" back-label="Employees">
        <x-slot:actions>
            @can('Manage employees')<a href="{{ route('hr.employees.edit', $employee) }}" class="cb-hero-btn"><i class="ri-pencil-line"></i>Edit</a>@endcan
            @if(Route::has('connect.direct') && $employee->user_id !== auth()->id() && auth()->user()->can('Use connect'))
                <a href="{{ route('connect.direct', $employee->user) }}" class="cb-hero-btn"><i class="ri-chat-3-line"></i>Message</a>
            @endif
        </x-slot:actions>
    </x-cb.hero>

    @include('partials.flash')

    <div class="row g-3">
        <div class="col-xl-4">
            <x-cb.card>
                <div class="d-flex align-items-center gap-3 mb-3">
                    <img src="{{ $employee->user->avatar_url }}" class="rounded-circle" width="56" height="56" alt="">
                    <div>
                        <div class="fw-semibold">{{ $employee->user->name }}</div>
                        <div class="small text-muted">{{ $employee->user->email }}</div>
                        <div class="mt-1">@include('modules.hr._pill', ['value' => $employee->status, 'kind' => 'employee'])</div>
                    </div>
                </div>
                <dl class="row mb-0 small">
                    <dt class="col-5 text-muted fw-normal">Type</dt><dd class="col-7">{{ $employee->type_label }}</dd>
                    <dt class="col-5 text-muted fw-normal">Started</dt><dd class="col-7">{{ $employee->start_date?->format('j M Y') ?? '—' }}@if($employee->start_date) <span class="text-muted">({{ $employee->start_date->diffForHumans(null, true) }})</span>@endif</dd>
                    <dt class="col-5 text-muted fw-normal">Reports to</dt><dd class="col-7">{{ $employee->manager?->name ?? '—' }}</dd>
                    <dt class="col-5 text-muted fw-normal">Phone</dt><dd class="col-7">{{ $employee->user->phone_number ?? '—' }}</dd>
                    <dt class="col-5 text-muted fw-normal">Next of kin</dt><dd class="col-7">{{ $employee->next_of_kin_name ?? '—' }}@if($employee->next_of_kin_phone)<div class="text-muted">{{ $employee->next_of_kin_phone }}</div>@endif</dd>
                    <dt class="col-5 text-muted fw-normal">Roles</dt><dd class="col-7">{{ $employee->user->roles->pluck('name')->implode(', ') ?: '—' }}</dd>
                    @if($employee->status === 'exited')
                        <dt class="col-5 text-muted fw-normal">Left</dt><dd class="col-7">{{ $employee->exit_date?->format('j M Y') }}@if($employee->exit_reason)<div class="text-muted">{{ $employee->exit_reason }}</div>@endif</dd>
                    @endif
                </dl>
                @if($employee->notes)<div class="small mt-3 text-muted" style="white-space: pre-line">{{ $employee->notes }}</div>@endif
            </x-cb.card>

            @if($reports->isNotEmpty())
                <x-cb.card title="Team" icon="ri-team-line" :count="$reports->count()" :flush="true">
                    <ul class="list-group list-group-flush">
                        @foreach($reports as $r)<li class="list-group-item small"><a href="{{ route('hr.employees.show', $r) }}">{{ $r->user->name }}</a> <span class="text-muted">· {{ $r->job_title }}</span></li>@endforeach
                    </ul>
                </x-cb.card>
            @endif
        </div>

        <div class="col-xl-8">
            <x-cb.card title="Leave this year" icon="ri-suitcase-line" :flush="true">
                <div class="table-responsive"><table class="cb-table stack">
                    <thead><tr><th>Type</th><th class="num">Allowed</th><th class="num">Taken</th><th class="num">Waiting</th><th class="num">Left</th></tr></thead>
                    <tbody>
                    @foreach($balances as $b)
                        <tr>
                            <td data-label="Type">{{ $b->type->name }}@unless($b->type->is_paid) <span class="badge bg-secondary-subtle text-secondary">unpaid</span>@endunless</td>
                            <td data-label="Allowed" class="num">{{ $b->allowed ?? 'No limit' }}</td>
                            <td data-label="Taken" class="num">{{ $d($b->used) }}</td>
                            <td data-label="Waiting" class="num">{{ $d($b->pending) }}</td>
                            <td data-label="Left" class="num fw-semibold">{{ $b->left === null ? '—' : $d($b->left) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table></div>
                @if($requests->isNotEmpty())
                    <ul class="list-group list-group-flush border-top">
                        @foreach($requests as $l)
                            <li class="list-group-item small d-flex justify-content-between gap-2">
                                <a href="{{ route('hr.leave.show', $l) }}">{{ $l->type->name }}, {{ $l->starts_on->format('j M') }} – {{ $l->ends_on->format('j M Y') }} ({{ $d($l->days) }} days)</a>
                                @include('modules.hr._pill', ['value' => $l->status])
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-cb.card>

            @if($seePay)
                <x-cb.card title="Pay" icon="ri-money-dollar-circle-line">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <dl class="row mb-0 small">
                                <dt class="col-6 text-muted fw-normal">Basic</dt><dd class="col-6 text-end">{{ $m($employee->basic_salary) }}</dd>
                                <dt class="col-6 text-muted fw-normal">Housing</dt><dd class="col-6 text-end">{{ $m($employee->housing_allowance) }}</dd>
                                <dt class="col-6 text-muted fw-normal">Transport</dt><dd class="col-6 text-end">{{ $m($employee->transport_allowance) }}</dd>
                                <dt class="col-6 text-muted fw-normal">Other allowances</dt><dd class="col-6 text-end">{{ $m($employee->other_allowance) }}</dd>
                                <dt class="col-6 fw-semibold">Monthly gross</dt><dd class="col-6 text-end fw-semibold">{{ $mm($employee->monthlyGrossMinor()) }}</dd>
                            </dl>
                            <div class="small text-muted mt-2">
                                Pension {{ $employee->pension_enrolled ? 'yes' : 'no' }}@if($employee->pension_pfa) ({{ $employee->pension_pfa }}{{ $employee->pension_pin ? ', ' . $employee->pension_pin : '' }})@endif
                                · NHF {{ $employee->nhf_enrolled ? 'yes' : 'no' }}
                                @if($employee->annual_rent) · rent {{ $m($employee->annual_rent) }} a year @endif
                                <br>{{ $employee->bank_name ?? 'No bank' }}{{ $employee->account_number ? ' · ' . $employee->account_number : '' }}{{ $employee->account_name ? ' · ' . $employee->account_name : '' }}
                                @if($employee->tax_id)<br>Tax ID {{ $employee->tax_id }}@endif
                            </div>
                        </div>
                        <div class="col-md-6">
                            @if($estimate)
                                <div class="small text-muted mb-1">A normal month</div>
                                <dl class="row mb-0 small">
                                    <dt class="col-6 text-muted fw-normal">Pension</dt><dd class="col-6 text-end">−{{ $mm($estimate['pension']) }}</dd>
                                    <dt class="col-6 text-muted fw-normal">NHF</dt><dd class="col-6 text-end">−{{ $mm($estimate['nhf']) }}</dd>
                                    <dt class="col-6 text-muted fw-normal">PAYE</dt><dd class="col-6 text-end">−{{ $mm($estimate['paye']) }}</dd>
                                    <dt class="col-6 fw-semibold">Take-home</dt><dd class="col-6 text-end fw-semibold text-success">{{ $mm($estimate['net']) }}</dd>
                                    <dt class="col-6 text-muted fw-normal">Employer pension</dt><dd class="col-6 text-end">{{ $mm($estimate['employer_pension']) }}</dd>
                                </dl>
                            @else
                                <div class="text-muted small">Add pay to see take-home.</div>
                            @endif
                        </div>
                    </div>
                    @if($payslips->isNotEmpty())
                        <div class="table-responsive mt-3"><table class="cb-table">
                            <thead><tr><th>Month</th><th>Status</th><th class="num">Gross</th><th class="num">Net</th><th></th></tr></thead>
                            <tbody>
                            @foreach($payslips as $p)
                                <tr>
                                    <td>{{ $p->run->label }}</td>
                                    <td>@include('modules.hr._pill', ['value' => $p->run->status, 'kind' => 'run'])</td>
                                    <td class="num">{{ $m($p->gross) }}</td>
                                    <td class="num">{{ $m($p->net) }}</td>
                                    <td class="text-end">@canany(['Manage payroll', 'Approve finance'])<a href="{{ route('hr.payroll.payslip', [$p->run, $p, 'view' => 1]) }}" target="_blank" class="btn btn-sm btn-soft-secondary"><i class="ri-file-pdf-2-line"></i></a>@endcanany</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table></div>
                    @endif
                </x-cb.card>
            @endif
        </div>
    </div>

</div></div></div>
@endsection
