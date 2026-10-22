@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">

    <x-cb.hero title="Journal" icon="ri-file-list-3-line" subtitle="Every posting to the ledger, newest first. Entries are never edited; mistakes are reversed." :back="route('finance.index')" back-label="Finance">
        <x-slot:actions>
            @can('Approve finance')<a href="{{ route('finance.ledger.journals.create') }}" class="cb-hero-btn"><i class="ri-add-line"></i>Manual entry</a>@endcan
        </x-slot:actions>
    </x-cb.hero>

    @include('partials.flash')
    @include('modules.finance.ledger._tabs')

    <x-cb.card title="Entries" icon="ri-file-list-3-line" :count="$entries->total()" :flush="true">
        <form class="cb-toolbar" method="GET">
            <div class="cb-search"><i class="ri-search-line"></i><input name="q" value="{{ request('q') }}" placeholder="Number or description"></div>
            <select name="kind" class="cb-select" onchange="this.form.submit()">
                <option value="">Any kind</option>
                @foreach(\App\Models\Finance\JournalEntry::KINDS as $k => $v)<option value="{{ $k }}" @selected(request('kind') === $k)>{{ $v }}</option>@endforeach
            </select>
        </form>
        @if($entries->isEmpty())
            <div class="empty-state"><i class="ri-file-list-3-line"></i><h6>No entries yet</h6></div>
        @else
            <div class="table-responsive"><table class="cb-table stack">
                <thead><tr><th>Entry</th><th>Date</th><th>Kind</th><th>Lines</th><th class="num">Amount</th></tr></thead>
                <tbody>
                @foreach($entries as $e)
                    <tr>
                        <td data-label="Entry"><a href="{{ route('finance.ledger.journal', $e) }}" class="fw-semibold">{{ $e->number }}</a><div class="small text-muted">{{ \Illuminate\Support\Str::limit($e->description, 70) }}</div></td>
                        <td data-label="Date">{{ $e->date->format('j M Y') }}</td>
                        <td data-label="Kind">{{ $e->kind_label }}@if($e->reversed_by_id) <span class="badge bg-dark-subtle text-dark">reversed</span>@endif</td>
                        <td data-label="Lines" class="small">
                            @foreach($e->lines as $l)<div>{{ (float) $l->debit ? 'Dr' : 'Cr' }} {{ $l->account->code }} {{ $l->account->name }}</div>@endforeach
                        </td>
                        <td data-label="Amount" class="num">{{ \App\Support\Money::format($e->total()) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
            <div class="p-3">{{ $entries->links() }}</div>
        @endif
    </x-cb.card>

</div></div></div>
@endsection
