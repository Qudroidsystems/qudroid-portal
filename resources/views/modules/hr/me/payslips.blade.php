@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">

    @php $m = fn ($v) => \App\Support\Money::format($v); @endphp
    <x-cb.hero title="My payslips" icon="ri-file-list-3-line" subtitle="A payslip appears here once that month's salary is paid." :back="route('me.leave')" back-label="My leave" />

    @include('partials.flash')

    <x-cb.card title="Payslips" icon="ri-file-list-3-line" :count="$slips->count()" :flush="true">
        @if($slips->isEmpty())
            <div class="empty-state"><i class="ri-file-list-3-line"></i><h6>No payslips yet</h6></div>
        @else
            <div class="table-responsive"><table class="cb-table stack">
                <thead><tr><th>Month</th><th class="num">Gross</th><th class="num">Tax</th><th class="num">Pension</th><th class="num">Take-home</th><th></th></tr></thead>
                <tbody>
                @foreach($slips as $s)
                    <tr>
                        <td data-label="Month" class="fw-semibold">{{ $s->run->label }}</td>
                        <td data-label="Gross" class="num">{{ $m($s->gross) }}</td>
                        <td data-label="Tax" class="num">{{ $m($s->paye) }}</td>
                        <td data-label="Pension" class="num">{{ $m($s->pension) }}</td>
                        <td data-label="Take-home" class="num fw-semibold">{{ $m($s->net) }}</td>
                        <td class="text-end text-nowrap">
                            <a href="{{ route('me.payslips.show', [$s, 'view' => 1]) }}" target="_blank" class="btn btn-sm btn-soft-secondary"><i class="ri-eye-line"></i> View</a>
                            <a href="{{ route('me.payslips.show', $s) }}" class="btn btn-sm btn-soft-primary"><i class="ri-download-2-line"></i> PDF</a>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
        @endif
    </x-cb.card>

</div></div></div>
@endsection
