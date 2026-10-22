@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">

    @php $m = fn ($v) => \App\Support\Money::format($v); @endphp
    <x-cb.hero title="Finance" icon="ri-money-dollar-circle-line" subtitle="Invoices, payments, expenses and the books.">
        @can('Manage finance')
            <x-slot:actions><a href="{{ route('finance.invoices.create') }}" class="cb-hero-btn"><i class="ri-add-line"></i>New invoice</a></x-slot:actions>
        @endcan
    </x-cb.hero>

    @include('partials.flash')

    @if($awaiting || $toPay)
        <div class="cb-banner info"><i class="ri-shopping-bag-3-line"></i><div>
            @if($awaiting)<a href="{{ route('finance.expenses.index', ['status' => 'submitted']) }}">{{ $awaiting }} {{ \Illuminate\Support\Str::plural('expense', $awaiting) }} awaiting approval</a>@endif
            @if($awaiting && $toPay) · @endif
            @if($toPay)<a href="{{ route('finance.expenses.index', ['status' => 'approved']) }}">{{ $toPay }} approved and waiting to be paid</a>@endif
        </div></div>
    @endif

    <div class="row g-3 mb-3">
        <div class="col-xl-3 col-md-6"><x-cb.stat label="Outstanding" :value="$m($outstanding)" icon="ri-file-list-3-line" accent="amber" :hint="$openCount . ' open invoices'" /></div>
        <div class="col-xl-3 col-md-6"><x-cb.stat label="Overdue" :value="$m($overdue)" icon="ri-alarm-warning-line" accent="rose" :hint="$overdueCount . ' invoices'" /></div>
        <div class="col-xl-3 col-md-6"><x-cb.stat label="Received this month" :value="$m($thisMonth)" icon="ri-bank-line" accent="green" /></div>
        <div class="col-xl-3 col-md-6"><x-cb.stat label="Received today" :value="$m($today)" icon="ri-calendar-check-line" accent="teal" /></div>
    </div>

    <div class="row g-3">
        <div class="col-xl-6">
            <x-cb.card title="Latest payments" icon="ri-bank-line" :flush="true">
                <x-slot:tools><a href="{{ route('finance.payments.index') }}" class="btn btn-sm btn-soft-primary">All payments</a></x-slot:tools>
                @if($recent->isEmpty())
                    <div class="empty-state"><i class="ri-bank-line"></i><h6>No payments yet</h6></div>
                @else
                    <div class="table-responsive"><table class="cb-table stack"><tbody>
                        @foreach($recent as $p)
                            <tr>
                                <td data-label="Payer"><a href="{{ route('finance.payments.show', $p) }}" class="fw-semibold">{{ $p->user->name }}</a><div class="small text-muted">{{ $p->number }} · {{ $p->method_label }}</div></td>
                                <td data-label="Amount" class="num">{{ \App\Support\Money::format($p->amount, $p->currency) }}<div class="small text-muted">{{ $p->confirmed_at?->diffForHumans() }}</div></td>
                            </tr>
                        @endforeach
                    </tbody></table></div>
                @endif
            </x-cb.card>
        </div>
        <div class="col-xl-6">
            <x-cb.card title="Overdue invoices" icon="ri-alarm-warning-line" :count="$overdueCount" :flush="true">
                <x-slot:tools><a href="{{ route('finance.invoices.index', ['status' => 'overdue']) }}" class="btn btn-sm btn-soft-primary">All overdue</a></x-slot:tools>
                @if($overdueList->isEmpty())
                    <div class="empty-state"><i class="ri-checkbox-circle-line"></i><h6>Nothing overdue</h6></div>
                @else
                    <div class="table-responsive"><table class="cb-table stack"><tbody>
                        @foreach($overdueList as $i)
                            <tr>
                                <td data-label="Invoice"><a href="{{ route('finance.invoices.show', $i) }}" class="fw-semibold">{{ $i->number }}</a><div class="small text-muted">{{ $i->user->name }} · due {{ $i->due_date->format('j M Y') }}</div></td>
                                <td data-label="Balance" class="num">{{ \App\Support\Money::format($i->balance(), $i->currency) }}</td>
                            </tr>
                        @endforeach
                    </tbody></table></div>
                @endif
            </x-cb.card>
        </div>
    </div>

</div></div></div>
@endsection
