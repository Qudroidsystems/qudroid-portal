@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">

    @php $m = fn ($minor) => \App\Support\Money::format(\App\Support\Money::major($minor)); @endphp
    <x-cb.hero title="Budgets" icon="ri-pie-chart-line" :subtitle="'Spending against budget for periods that include ' . $on->format('j M Y') . '. Committed means approved but not yet paid.'" :back="route('finance.expenses.index')" back-label="Expenses" />

    @include('partials.flash')

    <x-cb.card title="Budget versus actual" icon="ri-pie-chart-line" :count="$report->count()" :flush="true">
        <form class="cb-toolbar" method="GET">
            <label class="small text-muted">As at</label>
            <input type="date" name="on" value="{{ $on->toDateString() }}" class="cb-select" onchange="this.form.submit()">
        </form>
        @if($report->isEmpty())
            <div class="empty-state"><i class="ri-pie-chart-line"></i><h6>No budgets cover this date</h6></div>
        @else
            <div class="table-responsive"><table class="cb-table stack">
                <thead><tr><th>Category</th><th>Period</th><th class="num">Budget</th><th class="num">Spent</th><th class="num">Committed</th><th class="num">Left</th><th style="width:18%">Used</th></tr></thead>
                <tbody>
                @foreach($report as $r)
                    @php $tone = $r->percent > 100 ? 'danger' : ($r->percent >= 80 ? 'warning' : 'success'); @endphp
                    <tr>
                        <td data-label="Category"><a href="{{ route('finance.ledger.account', [$r->budget->account, 'from' => $r->budget->starts_on->toDateString(), 'to' => $r->budget->ends_on->toDateString()]) }}">{{ $r->budget->account->label }}</a></td>
                        <td data-label="Period">{{ $r->budget->starts_on->format('j M') }} – {{ $r->budget->ends_on->format('j M Y') }}</td>
                        <td data-label="Budget" class="num">{{ $m($r->amount) }}</td>
                        <td data-label="Spent" class="num">{{ $m($r->spent) }}</td>
                        <td data-label="Committed" class="num">{{ $m($r->committed) }}</td>
                        <td data-label="Left" class="num {{ $r->left < 0 ? 'text-danger fw-semibold' : '' }}">{{ $m($r->left) }}</td>
                        <td data-label="Used"><div class="progress" style="height:8px"><div class="progress-bar bg-{{ $tone }}" style="width: {{ min(100, $r->percent) }}%"></div></div><div class="small text-muted">{{ $r->percent }}%</div></td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
        @endif
    </x-cb.card>

    <div class="row g-3">
        <div class="col-xl-8">
            <x-cb.card title="All budgets" icon="ri-list-check" :count="$all->count()" :flush="true">
                @if($all->isEmpty())
                    <div class="empty-state"><i class="ri-list-check"></i><h6>No budgets yet</h6></div>
                @else
                    <div class="table-responsive"><table class="cb-table stack">
                        <thead><tr><th>Category</th><th>Period</th><th class="num">Amount</th><th></th></tr></thead>
                        <tbody>
                        @foreach($all as $b)
                            <tr>
                                <td data-label="Category">{{ $b->account->label }}@if($b->notes)<div class="small text-muted">{{ $b->notes }}</div>@endif</td>
                                <td data-label="Period">{{ $b->starts_on->format('j M Y') }} – {{ $b->ends_on->format('j M Y') }}</td>
                                <td data-label="Amount" class="num">
                                    @can('Approve finance')
                                        <form method="POST" action="{{ route('finance.budgets.update', $b) }}" class="d-flex gap-1 justify-content-end">
                                            @csrf @method('PUT')
                                            <input type="number" name="amount" step="0.01" min="0" value="{{ $b->amount }}" class="form-control form-control-sm" style="max-width:9rem">
                                            <input type="hidden" name="notes" value="{{ $b->notes }}">
                                            <button class="btn btn-sm btn-soft-primary">Save</button>
                                        </form>
                                    @else
                                        {{ \App\Support\Money::format($b->amount) }}
                                    @endcan
                                </td>
                                <td class="text-end">
                                    @can('Approve finance')
                                        <form method="POST" action="{{ route('finance.budgets.destroy', $b) }}" onsubmit="return confirm('Remove this budget?')">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-sm btn-ghost-danger"><i class="ri-delete-bin-line"></i></button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table></div>
                @endif
            </x-cb.card>
        </div>
        @can('Approve finance')
            <div class="col-xl-4">
                <x-cb.card title="Set a budget" icon="ri-add-line">
                    <form method="POST" action="{{ route('finance.budgets.store') }}">
                        @csrf
                        <label class="form-label">Category</label>
                        <select name="account_id" class="form-select mb-2" required>
                            @foreach($accounts as $a)<option value="{{ $a->id }}" @selected((int) old('account_id') === $a->id)>{{ $a->label }}</option>@endforeach
                        </select>
                        <div class="row g-2 mb-2">
                            <div class="col-6"><label class="form-label">From</label><input type="date" name="starts_on" class="form-control" value="{{ old('starts_on', today()->startOfMonth()->toDateString()) }}" required></div>
                            <div class="col-6"><label class="form-label">To</label><input type="date" name="ends_on" class="form-control" value="{{ old('ends_on', today()->endOfMonth()->toDateString()) }}" required></div>
                        </div>
                        <label class="form-label">Amount</label>
                        <input type="number" name="amount" step="0.01" min="0" class="form-control mb-2" value="{{ old('amount') }}" required>
                        <input name="notes" class="form-control mb-2" maxlength="300" value="{{ old('notes') }}" placeholder="Notes (optional)">
                        <button class="btn btn-primary w-100">Set budget</button>
                    </form>
                </x-cb.card>
            </div>
        @endcan
    </div>

</div></div></div>
@endsection
