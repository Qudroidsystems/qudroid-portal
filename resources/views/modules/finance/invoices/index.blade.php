@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">

    <x-cb.hero title="Invoices" icon="ri-file-list-3-line" subtitle="Issued invoices and what is still owed." :back="route('finance.index')" back-label="Finance">
        @can('Manage finance')
            <x-slot:actions><a href="{{ route('finance.invoices.create') }}" class="cb-hero-btn"><i class="ri-add-line"></i>New invoice</a></x-slot:actions>
        @endcan
    </x-cb.hero>

    @include('partials.flash')

    <x-cb.card title="Invoices" icon="ri-file-list-3-line" :count="$invoices->total()" :flush="true">
        <form class="cb-toolbar" method="GET">
            <div class="cb-search"><i class="ri-search-line"></i><input name="q" value="{{ request('q') }}" placeholder="Number, title or customer"></div>
            <select name="status" class="cb-select" onchange="this.form.submit()">
                <option value="">Any status</option>
                <option value="overdue" @selected(request('status') === 'overdue')>Overdue</option>
                @foreach(\App\Models\Finance\Invoice::STATUSES as $k => $v)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $v }}</option>@endforeach
            </select>
        </form>
        @if($invoices->isEmpty())
            <div class="empty-state"><i class="ri-file-list-3-line"></i><h6>No invoices found</h6></div>
        @else
            <div class="table-responsive"><table class="cb-table stack">
                <thead><tr><th>Invoice</th><th>Customer</th><th>Issued</th><th>Due</th><th>Status</th><th class="num">Total</th><th class="num">Balance</th></tr></thead>
                <tbody>
                @foreach($invoices as $i)
                    <tr>
                        <td data-label="Invoice"><a href="{{ route('finance.invoices.show', $i) }}" class="fw-semibold">{{ $i->number }}</a><div class="small text-muted">{{ $i->title }}</div></td>
                        <td data-label="Customer">{{ $i->user->name }}<div class="small text-muted">{{ $i->user->user_type_label }}</div></td>
                        <td data-label="Issued">{{ $i->issue_date->format('j M Y') }}</td>
                        <td data-label="Due">{{ $i->due_date?->format('j M Y') ?? '—' }}</td>
                        <td data-label="Status">@include('modules.finance.pill', ['status' => $i->isOverdue() ? 'overdue' : $i->status, 'label' => $i->status_label])</td>
                        <td data-label="Total" class="num">{{ \App\Support\Money::format($i->total, $i->currency) }}</td>
                        <td data-label="Balance" class="num fw-semibold">{{ \App\Support\Money::format($i->balance(), $i->currency) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
            <div class="p-3">{{ $invoices->links() }}</div>
        @endif
    </x-cb.card>

</div></div></div>
@endsection
