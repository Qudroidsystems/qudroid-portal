@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">

    @php $m = fn ($minor) => \App\Support\Money::format(\App\Support\Money::major($minor)); @endphp
    <x-cb.hero title="Trial balance" icon="ri-scales-3-line" :subtitle="'As at ' . $asAt->format('j M Y') . '. The two columns must agree.'" :back="route('finance.index')" back-label="Finance">
        <x-slot:actions><button type="button" onclick="window.print()" class="cb-hero-btn"><i class="ri-printer-line"></i>Print</button></x-slot:actions>
    </x-cb.hero>

    @include('modules.finance.ledger._tabs')

    <x-cb.card :flush="true">
        <form class="cb-toolbar" method="GET">
            <label class="small text-muted">As at</label>
            <input type="date" name="as_at" value="{{ $asAt->toDateString() }}" class="cb-select" onchange="this.form.submit()">
        </form>
        @if(empty($tb['rows']))
            <div class="empty-state"><i class="ri-scales-3-line"></i><h6>Nothing posted yet</h6></div>
        @else
            <div class="table-responsive"><table class="cb-table">
                <thead><tr><th style="width:6rem">Code</th><th>Account</th><th class="num">Debit</th><th class="num">Credit</th></tr></thead>
                <tbody>
                @foreach($tb['rows'] as $r)
                    <tr>
                        <td>{{ $r['account']->code }}</td>
                        <td><a href="{{ route('finance.ledger.account', [$r['account'], 'to' => $asAt->toDateString()]) }}">{{ $r['account']->name }}</a></td>
                        <td class="num">{{ $r['debit'] ? $m($r['debit']) : '' }}</td>
                        <td class="num">{{ $r['credit'] ? $m($r['credit']) : '' }}</td>
                    </tr>
                @endforeach
                <tr class="fw-bold"><td colspan="2">Total</td><td class="num">{{ $m($tb['debit']) }}</td><td class="num">{{ $m($tb['credit']) }}</td></tr>
                </tbody>
            </table></div>
            @if($tb['debit'] !== $tb['credit'])
                <div class="cb-banner danger m-3"><i class="ri-error-warning-line"></i><div>The totals do not agree. This should never happen; please report it.</div></div>
            @endif
        @endif
    </x-cb.card>

</div></div></div>
@endsection
