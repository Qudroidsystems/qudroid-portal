@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">

    <x-cb.hero title="Companies" icon="ri-building-line" subtitle="Prospects and clients, with their contacts and deals." :back="route('crm.index')" back-label="CRM">
        <x-slot:actions>@can('Manage clients')<a href="{{ route('crm.companies.create') }}" class="cb-hero-btn"><i class="ri-add-line"></i>New company</a>@endcan</x-slot:actions>
    </x-cb.hero>

    @include('partials.flash')

    <x-cb.card title="Companies" icon="ri-building-line" :count="$companies->total()" :flush="true">
        <form class="cb-toolbar" method="GET">
            <div class="cb-search"><i class="ri-search-line"></i><input name="q" value="{{ request('q') }}" placeholder="Company, industry, contact or email"></div>
            <select name="status" class="cb-select" onchange="this.form.submit()">
                <option value="">Any status</option>
                @foreach(\App\Models\Crm\Company::STATUSES as $k => $v)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $v }} ({{ $counts[$k] ?? 0 }})</option>@endforeach
            </select>
            <select name="owner" class="cb-select" onchange="this.form.submit()">
                <option value="">Anyone's</option>
                <option value="me" @selected(request('owner') === 'me')>Mine</option>
            </select>
        </form>
        @if($companies->isEmpty())
            <div class="empty-state"><i class="ri-building-line"></i><h6>No companies found</h6></div>
        @else
            <div class="table-responsive"><table class="cb-table stack">
                <thead><tr><th>Company</th><th>Industry</th><th>Owner</th><th>Contacts</th><th>Open deals</th><th>Status</th></tr></thead>
                <tbody>
                @foreach($companies as $c)
                    <tr>
                        <td data-label="Company"><a href="{{ route('crm.companies.show', $c) }}" class="fw-semibold">{{ $c->name }}</a>@if($c->city)<div class="small text-muted">{{ $c->city }}</div>@endif</td>
                        <td data-label="Industry">{{ $c->industry ?: '—' }}</td>
                        <td data-label="Owner">{{ $c->owner?->name ?? '—' }}</td>
                        <td data-label="Contacts">{{ $c->contacts_count }}</td>
                        <td data-label="Open deals">{{ $c->open_deals_count }}</td>
                        <td data-label="Status">@include('modules.crm._pill', ['value' => $c->status])</td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
            <div class="p-3">{{ $companies->links() }}</div>
        @endif
    </x-cb.card>

</div></div></div>
@endsection
