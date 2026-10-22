@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">

    <x-cb.hero title="Contacts" icon="ri-contacts-line" subtitle="The people you deal with at each company." :back="route('crm.index')" back-label="CRM" />

    @include('partials.flash')

    <div class="row g-3">
        <div class="col-xl-8">
            <x-cb.card title="Contacts" icon="ri-contacts-line" :count="$contacts->total()" :flush="true">
                <form class="cb-toolbar" method="GET">
                    <div class="cb-search"><i class="ri-search-line"></i><input name="q" value="{{ request('q') }}" placeholder="Name, email or phone"></div>
                    <label class="small d-flex align-items-center gap-1"><input type="checkbox" name="portal" value="1" @checked(request()->boolean('portal')) onchange="this.form.submit()"> With portal access</label>
                </form>
                @if($contacts->isEmpty())
                    <div class="empty-state"><i class="ri-contacts-line"></i><h6>No contacts found</h6></div>
                @else
                    <div class="table-responsive"><table class="cb-table stack">
                        <thead><tr><th>Name</th><th>Company</th><th>Email</th><th>Phone</th></tr></thead>
                        <tbody>
                        @foreach($contacts as $c)
                            <tr>
                                <td data-label="Name"><a href="{{ route('crm.contacts.show', $c) }}" class="fw-semibold">{{ $c->name }}</a>@if($c->user_id) <span class="badge bg-success-subtle text-success">Portal</span>@endif<div class="small text-muted">{{ $c->job_title }}</div></td>
                                <td data-label="Company">@if($c->company)<a href="{{ route('crm.companies.show', $c->company) }}">{{ $c->company->name }}</a>@else — @endif</td>
                                <td data-label="Email">{{ $c->email ?: '—' }}</td>
                                <td data-label="Phone">{{ $c->phone ?: '—' }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table></div>
                    <div class="p-3">{{ $contacts->links() }}</div>
                @endif
            </x-cb.card>
        </div>
        @can('Manage clients')
            <div class="col-xl-4">
                <x-cb.card title="Add a contact" icon="ri-user-add-line">
                    <form method="POST" action="{{ route('crm.contacts.store') }}">
                        @csrf
                        @include('modules.crm.contacts._form', ['contact' => new \App\Models\Crm\Contact()])
                        <button class="btn btn-primary btn-sm mt-2">Add contact</button>
                    </form>
                </x-cb.card>
            </div>
        @endcan
    </div>

</div></div></div>
@endsection
