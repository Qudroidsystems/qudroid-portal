@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">

    @php $m = fn ($minor) => \App\Support\Money::format(\App\Support\Money::major($minor)); @endphp
    <x-cb.hero title="Chart of accounts" icon="ri-book-2-line" subtitle="Every account in the ledger and its balance today. Invoices, payments and paid expenses post here automatically." :back="route('finance.index')" back-label="Finance" />

    @include('partials.flash')
    @include('modules.finance.ledger._tabs')

    <div class="row g-3">
        <div class="col-xl-8">
            @foreach(\App\Models\Finance\Account::TYPES as $type => $typeLabel)
                @continue(!isset($accounts[$type]))
                <x-cb.card :title="$typeLabel" icon="ri-folder-2-line" :count="$accounts[$type]->count()" :flush="true">
                    <div class="table-responsive"><table class="cb-table stack">
                        <thead><tr><th style="width:6rem">Code</th><th>Account</th><th class="num">Balance</th></tr></thead>
                        <tbody>
                        @foreach($accounts[$type] as $a)
                            <tr class="{{ $a->is_active ? '' : 'text-muted' }}">
                                <td data-label="Code">{{ $a->code }}</td>
                                <td data-label="Account"><a href="{{ route('finance.ledger.account', $a) }}" class="fw-semibold">{{ $a->name }}</a>
                                    @if($a->is_system)<span class="badge bg-light text-muted ms-1">system</span>@endif
                                    @if(!$a->is_active)<span class="badge bg-light text-muted ms-1">inactive</span>@endif
                                    @if($a->description)<div class="small text-muted">{{ $a->description }}</div>@endif
                                </td>
                                <td data-label="Balance" class="num {{ $a->balance_minor < 0 ? 'text-danger' : '' }}">{{ $m($a->balance_minor) }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table></div>
                </x-cb.card>
            @endforeach
        </div>
        @can('Approve finance')
            <div class="col-xl-4">
                <x-cb.card title="Add an account" icon="ri-add-line">
                    <form method="POST" action="{{ route('finance.ledger.accounts.store') }}">
                        @csrf
                        <div class="row g-2 mb-2">
                            <div class="col-4"><label class="form-label">Code</label><input name="code" class="form-control" value="{{ old('code') }}" maxlength="20" required placeholder="5950"></div>
                            <div class="col-8"><label class="form-label">Type</label>
                                <select name="type" class="form-select" required>
                                    @foreach(\App\Models\Finance\Account::TYPES as $k => $v)<option value="{{ $k }}" @selected(old('type', 'expense') === $k)>{{ $v }}</option>@endforeach
                                </select>
                            </div>
                        </div>
                        <label class="form-label">Name</label>
                        <input name="name" class="form-control mb-2" value="{{ old('name') }}" maxlength="150" required>
                        <input name="description" class="form-control mb-2" value="{{ old('description') }}" maxlength="500" placeholder="Description (optional)">
                        <button class="btn btn-primary w-100">Add account</button>
                        <div class="form-text">Asset and expense accounts grow with debits; liability, equity and income accounts grow with credits.</div>
                    </form>
                </x-cb.card>
            </div>
        @endcan
    </div>

</div></div></div>
@endsection
