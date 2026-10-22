@extends('pdf._layout')
@php
    $m = fn ($v) => \App\Support\Money::format($v);
    $mm = fn ($minor) => \App\Support\Money::format(\App\Support\Money::major($minor));
    $e = $slip->employee;
    $w = $slip->workings ?? [];
@endphp
@section('title', 'Payslip ' . $slip->run->label)
@section('doc', 'PAYSLIP')
@section('meta')
    <table class="meta" style="margin-top: 8px">
        <tr><td class="muted">Month</td><td class="num"><strong>{{ $slip->run->label }}</strong></td></tr>
        <tr><td class="muted">Employee no.</td><td class="num">{{ $e->employee_no }}</td></tr>
        @if($slip->run->paid_at)<tr><td class="muted">Paid</td><td class="num">{{ $slip->run->paid_at->format('j M Y') }}</td></tr>@endif
    </table>
@endsection
@if($slip->run->status !== 'paid')@section('stamp', 'DRAFT')@endif

@section('body')
    <table style="margin-top: 18px">
        <tr>
            <td style="width: 50%; vertical-align: top">
                <div class="muted" style="font-size: 10px; text-transform: uppercase">Employee</div>
                <div style="font-size: 13px; font-weight: bold">{{ $e->user->name }}</div>
                <div class="muted">{{ $e->job_title }}@if($e->department) · {{ $e->department->name }}@endif</div>
            </td>
            <td style="width: 50%; vertical-align: top">
                <div class="muted" style="font-size: 10px; text-transform: uppercase">Paid to</div>
                <div>{{ $slip->bank['bank'] ?? '—' }}</div>
                <div class="muted">{{ $slip->bank['account_number'] ?? '' }} {{ $slip->bank['account_name'] ?? '' }}</div>
                @if($e->pension_pin)<div class="muted">Pension: {{ $e->pension_pfa }} {{ $e->pension_pin }}</div>@endif
                @if($e->tax_id)<div class="muted">Tax ID: {{ $e->tax_id }}</div>@endif
            </td>
        </tr>
    </table>

    <table style="margin-top: 6px">
        <tr>
            <td style="width: 50%; vertical-align: top; padding-right: 8px">
                <table class="lines">
                    <thead><tr><th>Earnings</th><th class="num">Amount</th></tr></thead>
                    <tbody>
                        <tr><td>Basic salary</td><td class="num">{{ $m($slip->basic) }}</td></tr>
                        <tr><td>Housing allowance</td><td class="num">{{ $m($slip->housing) }}</td></tr>
                        <tr><td>Transport allowance</td><td class="num">{{ $m($slip->transport) }}</td></tr>
                        @if((float) $slip->other_allowance > 0)<tr><td>Other allowances</td><td class="num">{{ $m($slip->other_allowance) }}</td></tr>@endif
                        @if((float) $slip->bonus > 0)<tr><td>Bonus</td><td class="num">{{ $m($slip->bonus) }}</td></tr>@endif
                        @if((float) $slip->unpaid_deduction > 0)<tr><td>Unpaid leave ({{ rtrim(rtrim(number_format($slip->unpaid_days, 1), '0'), '.') }} days)</td><td class="num">−{{ $m($slip->unpaid_deduction) }}</td></tr>@endif
                        <tr><td><strong>Gross pay</strong></td><td class="num"><strong>{{ $m($slip->gross) }}</strong></td></tr>
                    </tbody>
                </table>
            </td>
            <td style="width: 50%; vertical-align: top; padding-left: 8px">
                <table class="lines">
                    <thead><tr><th>Deductions</th><th class="num">Amount</th></tr></thead>
                    <tbody>
                        <tr><td>PAYE tax</td><td class="num">{{ $m($slip->paye) }}</td></tr>
                        <tr><td>Pension (employee)</td><td class="num">{{ $m($slip->pension) }}</td></tr>
                        @if((float) $slip->nhf > 0)<tr><td>National Housing Fund</td><td class="num">{{ $m($slip->nhf) }}</td></tr>@endif
                        @if((float) $slip->other_deductions > 0)<tr><td>{{ $slip->other_deductions_note ?: 'Other' }}</td><td class="num">{{ $m($slip->other_deductions) }}</td></tr>@endif
                        <tr><td><strong>Total deductions</strong></td><td class="num"><strong>{{ $m($slip->totalDeductions()) }}</strong></td></tr>
                    </tbody>
                </table>
            </td>
        </tr>
    </table>

    <div class="box" style="text-align: center">
        <div class="muted">Net pay</div>
        <div style="font-size: 24px; font-weight: bold; margin-top: 4px">{{ $m($slip->net) }}</div>
    </div>

    @if($w)
        <table class="lines" style="margin-top: 14px">
            <thead><tr><th colspan="2">How your tax was worked out (for the year)</th></tr></thead>
            <tbody>
                <tr><td>Regular gross × 12</td><td class="num">{{ $mm(($w['regular_gross'] ?? 0) * 12) }}</td></tr>
                <tr><td>Less pension and NHF × 12</td><td class="num">−{{ $mm(((\App\Support\Money::minor($slip->pension) + \App\Support\Money::minor($slip->nhf)) * 12)) }}</td></tr>
                @if(($w['rent_relief'] ?? 0) > 0)<tr><td>Less rent relief</td><td class="num">−{{ $mm($w['rent_relief']) }}</td></tr>@endif
                <tr><td>Taxable income for the year</td><td class="num">{{ $mm($w['taxable_year'] ?? 0) }}</td></tr>
                @foreach($w['bands'] ?? [] as $b)
                    <tr><td class="muted">{{ $b['rate'] + 0 }}% on {{ $mm($b['amount']) }}</td><td class="num muted">{{ $mm($b['tax']) }}</td></tr>
                @endforeach
                <tr><td>Tax for the year ÷ 12</td><td class="num">{{ $mm(intdiv(($w['tax_year'] ?? 0) + 6, 12)) }}</td></tr>
                @if(($w['bonus_tax'] ?? 0) > 0)<tr><td>Extra tax on this month's bonus</td><td class="num">{{ $mm($w['bonus_tax']) }}</td></tr>@endif
            </tbody>
        </table>
    @endif

    <p class="muted" style="margin-top: 14px">Your employer also paid {{ $m($slip->employer_pension) }} into your pension this month. This payslip is confidential.</p>
@endsection
