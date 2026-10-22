@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">

    <x-cb.hero :title="$company->name" icon="ri-building-line" :subtitle="collect([$company->industry, $company->city, $company->owner ? 'Owner: ' . $company->owner->name : null])->filter()->implode(' · ')" :back="route('crm.companies.index')" back-label="Companies">
        <x-slot:actions>
            @can('Manage clients')<a href="{{ route('crm.companies.edit', $company) }}" class="cb-hero-btn"><i class="ri-edit-line"></i>Edit</a>@endcan
        </x-slot:actions>
    </x-cb.hero>

    @include('partials.flash')

    <div class="row g-3">
        <div class="col-xl-4">
            <x-cb.card title="Details" icon="ri-information-line">
                <div class="mb-2">@include('modules.crm._pill', ['value' => $company->status])</div>
                <dl class="row mb-0 small">
                    @if($company->email)<dt class="col-4 text-muted fw-normal">Email</dt><dd class="col-8"><a href="mailto:{{ $company->email }}">{{ $company->email }}</a></dd>@endif
                    @if($company->phone)<dt class="col-4 text-muted fw-normal">Phone</dt><dd class="col-8"><a href="tel:{{ $company->phone }}">{{ $company->phone }}</a></dd>@endif
                    @if($company->website)<dt class="col-4 text-muted fw-normal">Website</dt><dd class="col-8"><a href="{{ $company->website }}" target="_blank" rel="noopener">{{ preg_replace('~^https?://~', '', $company->website) }}</a></dd>@endif
                    @if($company->address || $company->city)<dt class="col-4 text-muted fw-normal">Address</dt><dd class="col-8">{{ collect([$company->address, $company->city, $company->country])->filter()->implode(', ') }}</dd>@endif
                    @if($company->source)<dt class="col-4 text-muted fw-normal">Source</dt><dd class="col-8">{{ $company->source }}</dd>@endif
                </dl>
                @if($company->notes)<div class="small mt-2" style="white-space: pre-line">{{ $company->notes }}</div>@endif
            </x-cb.card>

            <x-cb.card title="Contacts" icon="ri-contacts-line" :count="$company->contacts->count()" :flush="true">
                <ul class="list-group list-group-flush">
                    @forelse($company->contacts as $ct)
                        <li class="list-group-item">
                            <a href="{{ route('crm.contacts.show', $ct) }}" class="fw-semibold">{{ $ct->name }}</a>
                            @if($ct->is_primary)<span class="badge bg-primary-subtle text-primary ms-1">Main</span>@endif
                            @if($ct->user_id)<span class="badge bg-success-subtle text-success ms-1" title="Has a client portal account">Portal</span>@endif
                            <div class="small text-muted">{{ collect([$ct->job_title, $ct->email, $ct->phone])->filter()->implode(' · ') }}</div>
                        </li>
                    @empty
                        <li class="list-group-item text-muted small">No contacts yet.</li>
                    @endforelse
                </ul>
                @can('Manage clients')
                    <details class="p-3 border-top">
                        <summary class="small fw-semibold">Add a contact</summary>
                        <form method="POST" action="{{ route('crm.contacts.store') }}" class="mt-2">
                            @csrf <input type="hidden" name="back" value="1">
                            @include('modules.crm.contacts._form', ['contact' => new \App\Models\Crm\Contact(['is_primary' => $company->contacts->isEmpty()]), 'fixedCompany' => $company, 'companies' => collect()])
                            <button class="btn btn-sm btn-primary mt-2">Add contact</button>
                        </form>
                    </details>
                @endcan
            </x-cb.card>

            @if($projects->isNotEmpty() || $invoices->isNotEmpty())
                <x-cb.card title="Work and billing" icon="ri-folder-2-line" :flush="true">
                    <ul class="list-group list-group-flush">
                        @foreach($projects as $p)
                            <li class="list-group-item small d-flex justify-content-between"><a href="{{ route('projects.show', $p) }}"><i class="ri-layout-column-line"></i> {{ $p->name }}</a>@include('modules.projects._pill', ['value' => $p->status])</li>
                        @endforeach
                        @foreach($invoices as $i)
                            <li class="list-group-item small d-flex justify-content-between">
                                @can('View finance')<a href="{{ route('finance.invoices.show', $i) }}"><i class="ri-file-list-3-line"></i> {{ $i->number }}</a>@else<span><i class="ri-file-list-3-line"></i> {{ $i->number }}</span>@endcan
                                <span>{{ \App\Support\Money::format($i->total, $i->currency) }} · {{ $i->status_label }}</span>
                            </li>
                        @endforeach
                    </ul>
                </x-cb.card>
            @endif
        </div>

        <div class="col-xl-8">
            <x-cb.card title="Deals" icon="ri-git-branch-line" :count="$deals->count()" :flush="true">
                @if($deals->isEmpty())
                    <div class="empty-state"><i class="ri-git-branch-line"></i><h6>No deals yet</h6></div>
                @else
                    <div class="table-responsive"><table class="cb-table stack"><tbody>
                        @foreach($deals as $d)
                            <tr>
                                <td data-label="Deal"><a href="{{ route('crm.deals.show', $d) }}" class="fw-semibold">{{ $d->title }}</a><div class="small text-muted">{{ $d->owner?->name }}</div></td>
                                <td data-label="Stage">@include('modules.crm._pill', ['value' => $d->stage])</td>
                                <td data-label="Value" class="num">{{ \App\Support\Money::format($d->value, $d->currency) }}</td>
                            </tr>
                        @endforeach
                    </tbody></table></div>
                @endif
                @can('Manage clients')
                    <form method="POST" action="{{ route('crm.deals.store') }}" class="d-flex flex-wrap gap-2 p-3 border-top">
                        @csrf
                        <input type="hidden" name="company_id" value="{{ $company->id }}">
                        <input name="title" class="form-control form-control-sm" style="flex: 1 1 14rem" maxlength="200" required placeholder="New deal, e.g. Mobile app build">
                        <input type="number" name="value" step="0.01" min="0" class="form-control form-control-sm" style="max-width: 10rem" placeholder="Value">
                        <button class="btn btn-sm btn-soft-primary">Add deal</button>
                    </form>
                @endcan
            </x-cb.card>

            @include('modules.crm._timeline', ['subjectType' => 'company', 'subjectId' => $company->id, 'showSubject' => true])
        </div>
    </div>

</div></div></div>
@endsection
