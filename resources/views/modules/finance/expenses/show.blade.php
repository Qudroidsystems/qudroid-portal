@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">

    @php $m = fn ($v) => \App\Support\Money::format($v, $expense->currency); @endphp
    <x-cb.hero :title="'Expense ' . $expense->number" icon="ri-shopping-bag-3-line" :subtitle="$m($expense->amount) . ' to ' . $expense->payee" :back="route('finance.expenses.index')" back-label="Expenses" />

    @include('partials.flash')

    <div class="row g-3">
        <div class="col-xl-8">
            <x-cb.card>
                <div class="d-flex justify-content-between flex-wrap gap-3 mb-3">
                    <div>
                        <div class="text-muted small">Paid to</div>
                        <div class="fw-semibold">{{ $expense->payee }}</div>
                        <div class="small text-muted">{{ $expense->account->label }}</div>
                    </div>
                    <div class="text-md-end">
                        <div><span class="text-muted">Date</span> {{ $expense->date->format('j M Y') }}</div>
                        <div class="mt-1">@include('modules.finance.pill', ['status' => $expense->status, 'label' => $expense->status_label])</div>
                    </div>
                </div>
                <div class="fs-2 fw-bold mb-1">{{ $m($expense->amount) }}</div>
                <p class="mb-3">{{ $expense->description }}</p>
                @if($expense->attachment_path)
                    <a href="{{ route('finance.expenses.attachment', $expense) }}" class="btn btn-soft-secondary btn-sm"><i class="ri-attachment-2"></i> {{ $expense->attachment_name }}</a>
                @endif
                @if($expense->status === 'rejected')
                    <div class="cb-banner danger mt-3 mb-0"><i class="ri-close-circle-line"></i><div>Rejected by {{ $expense->approver?->name }}: {{ $expense->rejection_reason }}</div></div>
                @endif
                @if($entries->isNotEmpty())
                    <div class="mt-3 small">Ledger:
                        @foreach($entries as $j)<a href="{{ route('finance.ledger.journal', $j) }}">{{ $j->number }}</a>@if(!$loop->last), @endif
                        @endforeach
                    </div>
                @endif
            </x-cb.card>
        </div>
        <div class="col-xl-4">
            <x-cb.card title="Record" icon="ri-history-line">
                <dl class="row mb-0 small">
                    <dt class="col-5 text-muted fw-normal">Recorded by</dt><dd class="col-7">{{ $expense->submitter?->name ?? '—' }}</dd>
                    <dt class="col-5 text-muted fw-normal">Decided</dt><dd class="col-7">{{ $expense->approved_at?->format('j M Y H:i') ?? '—' }}@if($expense->approver) by {{ $expense->approver->name }}@endif</dd>
                    @if($expense->status === 'paid')
                        <dt class="col-5 text-muted fw-normal">Paid</dt><dd class="col-7">{{ $expense->paid_at?->format('j M Y') }} from {{ $expense->paidFrom?->name }}@if($expense->payer) by {{ $expense->payer->name }}@endif</dd>
                        @if($expense->payment_reference)<dt class="col-5 text-muted fw-normal">Reference</dt><dd class="col-7">{{ $expense->payment_reference }}</dd>@endif
                    @endif
                </dl>
            </x-cb.card>

            @if($expense->status === 'submitted')
                @can('Approve finance')
                    @if($expense->submitted_by !== auth()->id())
                        <x-cb.card title="Decide" icon="ri-check-double-line">
                            <form method="POST" action="{{ route('finance.expenses.approve', $expense) }}" class="mb-3">
                                @csrf
                                <button class="btn btn-success w-100">Approve</button>
                            </form>
                            <form method="POST" action="{{ route('finance.expenses.reject', $expense) }}">
                                @csrf
                                <input name="reason" class="form-control mb-2" required maxlength="300" placeholder="Reason for rejecting">
                                <button class="btn btn-soft-danger w-100">Reject</button>
                            </form>
                        </x-cb.card>
                    @else
                        <div class="cb-banner info"><i class="ri-information-line"></i><div>You recorded this expense, so another approver must decide it.</div></div>
                    @endif
                @endcan
            @endif

            @if($expense->status === 'approved')
                @can('Manage finance')
                    <x-cb.card title="Mark paid" icon="ri-bank-card-line">
                        <form method="POST" action="{{ route('finance.expenses.pay', $expense) }}">
                            @csrf
                            <label class="form-label">Paid from</label>
                            <select name="paid_from_account_id" class="form-select mb-2" required>
                                @foreach($payFrom as $a)<option value="{{ $a->id }}" @selected($a->code === \App\Models\Finance\Account::BANK)>{{ $a->label }}</option>@endforeach
                            </select>
                            <label class="form-label">Date paid</label>
                            <input type="date" name="paid_on" class="form-control mb-2" value="{{ today()->toDateString() }}" required>
                            <input name="payment_reference" class="form-control mb-2" maxlength="100" placeholder="Transfer reference (optional)">
                            <button class="btn btn-primary w-100">Mark paid</button>
                            <div class="form-text">Posts the expense to the ledger.</div>
                        </form>
                    </x-cb.card>
                @endcan
            @endif
        </div>
    </div>

</div></div></div>
@endsection
