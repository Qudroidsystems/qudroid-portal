@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">

    @php $m = fn ($minor) => \App\Support\Money::format(\App\Support\Money::major($minor)); @endphp
    <x-cb.hero :title="$account->code . ' ' . $account->name" icon="ri-book-2-line" :subtitle="$account->type_label . ' account'" :back="route('finance.ledger.accounts')" back-label="Chart of accounts" />

    @include('partials.flash')

    <div class="row g-3">
        <div class="col-xl-8">
            <x-cb.card title="Ledger" icon="ri-file-list-3-line" :count="$rows->count()" :flush="true">
                <form class="cb-toolbar" method="GET">
                    <label class="small text-muted">From</label><input type="date" name="from" value="{{ $from->toDateString() }}" class="cb-select">
                    <label class="small text-muted">To</label><input type="date" name="to" value="{{ $to->toDateString() }}" class="cb-select">
                    <button class="btn btn-sm btn-soft-primary">Show</button>
                </form>
                <div class="table-responsive"><table class="cb-table stack">
                    <thead><tr><th>Date</th><th>Entry</th><th class="num">Debit</th><th class="num">Credit</th><th class="num">Balance</th></tr></thead>
                    <tbody>
                        <tr class="text-muted"><td colspan="4">Opening balance</td><td class="num">{{ $m($opening) }}</td></tr>
                        @foreach($rows as $r)
                            @php $l = $r['line']; @endphp
                            <tr>
                                <td data-label="Date">{{ $l->entry->date->format('j M Y') }}</td>
                                <td data-label="Entry"><a href="{{ route('finance.ledger.journal', $l->entry) }}">{{ $l->entry->number }}</a> {{ $l->memo ?: $l->entry->description }}</td>
                                <td data-label="Debit" class="num">{{ (float) $l->debit ? \App\Support\Money::format($l->debit) : '' }}</td>
                                <td data-label="Credit" class="num">{{ (float) $l->credit ? \App\Support\Money::format($l->credit) : '' }}</td>
                                <td data-label="Balance" class="num">{{ $m($r['balance']) }}</td>
                            </tr>
                        @endforeach
                        <tr class="fw-semibold"><td colspan="4">Closing balance</td><td class="num">{{ $m($rows->last()['balance'] ?? $opening) }}</td></tr>
                    </tbody>
                </table></div>
            </x-cb.card>
        </div>
        <div class="col-xl-4">
            @can('Approve finance')
                <x-cb.card title="Edit account" icon="ri-edit-line">
                    <form method="POST" action="{{ route('finance.ledger.accounts.update', $account) }}">
                        @csrf @method('PUT')
                        <label class="form-label">Name</label>
                        <input name="name" class="form-control mb-2" value="{{ $account->name }}" maxlength="150" required>
                        <input name="description" class="form-control mb-2" value="{{ $account->description }}" maxlength="500" placeholder="Description">
                        @unless($account->is_system)
                            <div class="form-check mb-2"><input type="checkbox" name="is_active" value="1" class="form-check-input" id="active" @checked($account->is_active)><label class="form-check-label" for="active">Active</label></div>
                        @endunless
                        <button class="btn btn-soft-primary w-100">Save</button>
                        @if($account->is_system)<div class="form-text">The portal posts to this account automatically, so it stays active.</div>@endif
                    </form>
                </x-cb.card>
            @endcan
        </div>
    </div>

</div></div></div>
@endsection
