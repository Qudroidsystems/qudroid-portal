@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">

    <x-cb.hero :title="'Journal ' . $entry->number" icon="ri-file-list-3-line" :subtitle="$entry->date->format('j M Y') . ' · ' . $entry->kind_label" :back="route('finance.ledger.journals')" back-label="Journal" />

    @include('partials.flash')

    <div class="row g-3">
        <div class="col-xl-8">
            <x-cb.card :title="$entry->description" icon="ri-file-list-3-line" :flush="true">
                <div class="table-responsive"><table class="cb-table">
                    <thead><tr><th>Account</th><th>Memo</th><th class="num">Debit</th><th class="num">Credit</th></tr></thead>
                    <tbody>
                    @foreach($entry->lines as $l)
                        <tr>
                            <td><a href="{{ route('finance.ledger.account', $l->account) }}">{{ $l->account->code }} {{ $l->account->name }}</a></td>
                            <td class="small text-muted">{{ $l->memo }}</td>
                            <td class="num">{{ (float) $l->debit ? \App\Support\Money::format($l->debit) : '' }}</td>
                            <td class="num">{{ (float) $l->credit ? \App\Support\Money::format($l->credit) : '' }}</td>
                        </tr>
                    @endforeach
                    <tr class="fw-semibold"><td colspan="2">Total</td><td class="num">{{ \App\Support\Money::format($entry->total()) }}</td><td class="num">{{ \App\Support\Money::format($entry->total()) }}</td></tr>
                    </tbody>
                </table></div>
            </x-cb.card>
        </div>
        <div class="col-xl-4">
            <x-cb.card title="Record" icon="ri-history-line">
                <dl class="row mb-0 small">
                    <dt class="col-5 text-muted fw-normal">Posted by</dt><dd class="col-7">{{ $entry->creator?->name ?? 'The portal' }}</dd>
                    <dt class="col-5 text-muted fw-normal">Posted at</dt><dd class="col-7">{{ $entry->created_at->format('j M Y H:i') }}</dd>
                    @if($entry->source)
                        @php
                            $src = $entry->source;
                            $link = match (true) {
                                $src instanceof \App\Models\Finance\Invoice => [route('finance.invoices.show', $src), 'Invoice ' . $src->number],
                                $src instanceof \App\Models\Finance\Payment => [route('finance.payments.show', $src), 'Receipt ' . ($src->number ?? $src->reference)],
                                $src instanceof \App\Models\Finance\Expense => [route('finance.expenses.show', $src), 'Expense ' . $src->number],
                                default => null,
                            };
                        @endphp
                        @if($link)<dt class="col-5 text-muted fw-normal">From</dt><dd class="col-7"><a href="{{ $link[0] }}">{{ $link[1] }}</a></dd>@endif
                    @endif
                    @if($entry->reverses)<dt class="col-5 text-muted fw-normal">Reverses</dt><dd class="col-7"><a href="{{ route('finance.ledger.journal', $entry->reverses) }}">{{ $entry->reverses->number }}</a></dd>@endif
                    @if($entry->reversedBy)<dt class="col-5 text-muted fw-normal">Reversed by</dt><dd class="col-7"><a href="{{ route('finance.ledger.journal', $entry->reversedBy) }}">{{ $entry->reversedBy->number }}</a></dd>@endif
                </dl>
            </x-cb.card>
            @can('Approve finance')
                @if($entry->kind === 'manual' && !$entry->reversed_by_id)
                    <x-cb.card title="Reverse entry" icon="ri-arrow-go-back-line">
                        <form method="POST" action="{{ route('finance.ledger.journals.reverse', $entry) }}" onsubmit="return confirm('Post a reversing entry?')">
                            @csrf
                            <input name="reason" class="form-control mb-2" required maxlength="200" placeholder="Reason">
                            <button class="btn btn-soft-danger w-100">Reverse</button>
                            <div class="form-text">Posts the mirror entry today. Invoice, payment and expense entries are reversed by voiding or reversing those records instead.</div>
                        </form>
                    </x-cb.card>
                @endif
            @endcan
        </div>
    </div>

</div></div></div>
@endsection
