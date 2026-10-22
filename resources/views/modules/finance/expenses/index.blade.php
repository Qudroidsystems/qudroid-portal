@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">

    <x-cb.hero title="Expenses" icon="ri-shopping-bag-3-line" subtitle="Money out. Someone other than the person who recorded an expense approves it before it is paid." :back="route('finance.index')" back-label="Finance">
        <x-slot:actions>
            @can('Manage finance')<a href="{{ route('finance.expenses.create') }}" class="cb-hero-btn"><i class="ri-add-line"></i>Record expense</a>@endcan
            <a href="{{ route('finance.budgets.index') }}" class="cb-hero-btn"><i class="ri-pie-chart-line"></i>Budgets</a>
        </x-slot:actions>
    </x-cb.hero>

    @include('partials.flash')

    <x-cb.card title="Expenses" icon="ri-shopping-bag-3-line" :count="$expenses->total()" :flush="true">
        <form class="cb-toolbar" method="GET">
            <div class="cb-search"><i class="ri-search-line"></i><input name="q" value="{{ request('q') }}" placeholder="Number, payee or description"></div>
            <select name="status" class="cb-select" onchange="this.form.submit()">
                <option value="">Any status</option>
                @foreach(\App\Models\Finance\Expense::STATUSES as $k => $v)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $v }} ({{ $counts[$k] ?? 0 }})</option>@endforeach
            </select>
            <select name="account_id" class="cb-select" onchange="this.form.submit()">
                <option value="">Any category</option>
                @foreach($accounts as $a)<option value="{{ $a->id }}" @selected((int) request('account_id') === $a->id)>{{ $a->label }}</option>@endforeach
            </select>
        </form>
        @if($expenses->isEmpty())
            <div class="empty-state"><i class="ri-shopping-bag-3-line"></i><h6>No expenses found</h6></div>
        @else
            <div class="table-responsive"><table class="cb-table stack">
                <thead><tr><th>Expense</th><th>Payee</th><th>Category</th><th>Date</th><th>Status</th><th class="num">Amount</th></tr></thead>
                <tbody>
                @foreach($expenses as $e)
                    <tr>
                        <td data-label="Expense"><a href="{{ route('finance.expenses.show', $e) }}" class="fw-semibold">{{ $e->number }}</a><div class="small text-muted">{{ \Illuminate\Support\Str::limit($e->description, 60) }}</div></td>
                        <td data-label="Payee">{{ $e->payee }}</td>
                        <td data-label="Category">{{ $e->account->name }}</td>
                        <td data-label="Date">{{ $e->date->format('j M Y') }}</td>
                        <td data-label="Status">@include('modules.finance.pill', ['status' => $e->status, 'label' => $e->status_label])</td>
                        <td data-label="Amount" class="num">{{ \App\Support\Money::format($e->amount, $e->currency) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
            <div class="p-3">{{ $expenses->links() }}</div>
        @endif
    </x-cb.card>

</div></div></div>
@endsection
