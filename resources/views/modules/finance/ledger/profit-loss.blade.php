@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">

    @php $m = fn ($minor) => \App\Support\Money::format(\App\Support\Money::major($minor)); @endphp
    <x-cb.hero title="Profit and loss" icon="ri-line-chart-line" :subtitle="$from->format('j M Y') . ' to ' . $to->format('j M Y') . '. Income is counted when invoiced, expenses when paid.'" :back="route('finance.index')" back-label="Finance">
        <x-slot:actions><button type="button" onclick="window.print()" class="cb-hero-btn"><i class="ri-printer-line"></i>Print</button></x-slot:actions>
    </x-cb.hero>

    @include('modules.finance.ledger._tabs')

    <div class="row g-3 mb-1">
        <div class="col-md-4"><x-cb.stat label="Income" :value="$m($pl['totalIncome'])" icon="ri-arrow-down-circle-line" accent="teal" /></div>
        <div class="col-md-4"><x-cb.stat label="Expenses" :value="$m($pl['totalExpenses'])" icon="ri-arrow-up-circle-line" accent="rose" /></div>
        <div class="col-md-4"><x-cb.stat :label="$pl['net'] >= 0 ? 'Profit' : 'Loss'" :value="$m(abs($pl['net']))" icon="ri-line-chart-line" :accent="$pl['net'] >= 0 ? 'teal' : 'rose'" /></div>
    </div>

    <x-cb.card :flush="true">
        <form class="cb-toolbar" method="GET">
            <label class="small text-muted">From</label><input type="date" name="from" value="{{ $from->toDateString() }}" class="cb-select">
            <label class="small text-muted">To</label><input type="date" name="to" value="{{ $to->toDateString() }}" class="cb-select">
            <button class="btn btn-sm btn-soft-primary">Show</button>
        </form>
        <div class="table-responsive"><table class="cb-table">
            <tbody>
                <tr class="fw-bold"><td colspan="2">Income</td></tr>
                @forelse($pl['income'] as $r)
                    <tr><td class="ps-4"><a href="{{ route('finance.ledger.account', [$r['account'], 'from' => $from->toDateString(), 'to' => $to->toDateString()]) }}">{{ $r['account']->label }}</a></td><td class="num">{{ $m($r['amount']) }}</td></tr>
                @empty
                    <tr><td class="ps-4 text-muted" colspan="2">None</td></tr>
                @endforelse
                <tr class="fw-semibold"><td>Total income</td><td class="num">{{ $m($pl['totalIncome']) }}</td></tr>
                <tr class="fw-bold"><td colspan="2">Expenses</td></tr>
                @forelse($pl['expenses'] as $r)
                    <tr><td class="ps-4"><a href="{{ route('finance.ledger.account', [$r['account'], 'from' => $from->toDateString(), 'to' => $to->toDateString()]) }}">{{ $r['account']->label }}</a></td><td class="num">{{ $m($r['amount']) }}</td></tr>
                @empty
                    <tr><td class="ps-4 text-muted" colspan="2">None</td></tr>
                @endforelse
                <tr class="fw-semibold"><td>Total expenses</td><td class="num">{{ $m($pl['totalExpenses']) }}</td></tr>
                <tr class="fw-bold fs-5"><td>Net {{ $pl['net'] >= 0 ? 'profit' : 'loss' }}</td><td class="num {{ $pl['net'] < 0 ? 'text-danger' : '' }}">{{ $m(abs($pl['net'])) }}</td></tr>
            </tbody>
        </table></div>
    </x-cb.card>

</div></div></div>
@endsection
