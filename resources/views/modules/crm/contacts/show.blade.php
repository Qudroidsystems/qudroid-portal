@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">

    <x-cb.hero :title="$contact->name" icon="ri-contacts-line" :subtitle="collect([$contact->job_title, $contact->company?->name])->filter()->implode(' at ')" :back="$contact->company ? route('crm.companies.show', $contact->company) : route('crm.contacts.index')" :back-label="$contact->company?->name ?? 'Contacts'" />

    @include('partials.flash')

    <div class="row g-3">
        <div class="col-xl-4">
            <x-cb.card title="Details" icon="ri-information-line">
                <dl class="row mb-0 small">
                    <dt class="col-4 text-muted fw-normal">Email</dt><dd class="col-8">@if($contact->email)<a href="mailto:{{ $contact->email }}">{{ $contact->email }}</a>@else — @endif</dd>
                    <dt class="col-4 text-muted fw-normal">Phone</dt><dd class="col-8">@if($contact->phone)<a href="tel:{{ $contact->phone }}">{{ $contact->phone }}</a>@else — @endif</dd>
                    <dt class="col-4 text-muted fw-normal">Portal</dt><dd class="col-8">{{ $contact->user ? 'Yes, signs in as ' . $contact->user->email : 'No account' }}</dd>
                </dl>
                @can('Manage clients')
                    @unless($contact->user_id)
                        <form method="POST" action="{{ route('crm.contacts.access', $contact) }}" class="mt-3" onsubmit="return confirm('Create a client portal account for {{ e($contact->name) }}?')">
                            @csrf
                            <button class="btn btn-sm btn-soft-success w-100" @disabled(!$contact->email)><i class="ri-key-2-line"></i> Give portal access</button>
                            <div class="form-text">{{ $contact->email ? 'They can then follow their projects and pay invoices.' : 'Add an email address first.' }}</div>
                        </form>
                    @endunless
                @endcan
            </x-cb.card>

            @can('Manage clients')
                <x-cb.card title="Edit" icon="ri-edit-line">
                    <form method="POST" action="{{ route('crm.contacts.update', $contact) }}">
                        @csrf @method('PUT')
                        @include('modules.crm.contacts._form')
                        <textarea name="notes" rows="2" class="form-control form-control-sm mt-2" maxlength="5000" placeholder="Notes">{{ $contact->notes }}</textarea>
                        <button class="btn btn-sm btn-primary mt-2">Save</button>
                    </form>
                    @unless($contact->user_id)
                        <form method="POST" action="{{ route('crm.contacts.destroy', $contact) }}" class="mt-2" onsubmit="return confirm('Delete this contact?')">
                            @csrf @method('DELETE')<button class="btn btn-sm btn-ghost-danger"><i class="ri-delete-bin-line"></i> Delete contact</button>
                        </form>
                    @endunless
                </x-cb.card>
            @endcan

            @if($contact->deals->isNotEmpty())
                <x-cb.card title="Deals" icon="ri-git-branch-line" :flush="true">
                    <ul class="list-group list-group-flush">
                        @foreach($contact->deals as $d)
                            <li class="list-group-item small d-flex justify-content-between"><a href="{{ route('crm.deals.show', $d) }}">{{ $d->title }}</a>@include('modules.crm._pill', ['value' => $d->stage])</li>
                        @endforeach
                    </ul>
                </x-cb.card>
            @endif
        </div>
        <div class="col-xl-8">
            @include('modules.crm._timeline', ['subjectType' => 'contact', 'subjectId' => $contact->id])
        </div>
    </div>

</div></div></div>
@endsection
