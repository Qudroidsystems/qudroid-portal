@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">
    <x-cb.hero title="Forms and entries" icon="ri-survey-line" subtitle="Forms people fill on the website. Put one on any page with a Form block, or share its own link." :back="route('website.index')" back-label="Website">
        <x-slot:actions><a class="cb-hero-btn" href="{{ route('website.forms.create') }}"><i class="ri-add-line me-1"></i>New form</a></x-slot:actions>
    </x-cb.hero>
    @include('partials.flash')
    <x-cb.card title="All forms" icon="ri-survey-line" :count="$forms->count()" :flush="true">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light"><tr><th>Form</th><th>Link</th><th>Entries</th><th>Goes to CRM</th><th>Status</th><th></th></tr></thead>
                <tbody>
                @forelse($forms as $f)
                    <tr>
                        <td><a href="{{ route('website.forms.edit', $f) }}" class="fw-medium">{{ $f->name }}</a><div class="small text-muted">{{ count($f->fields ?? []) }} fields</div></td>
                        <td><a href="{{ $f->slug === 'contact' ? route('site.contact') : route('site.forms.show', $f) }}" target="_blank" class="small"><code>{{ $f->slug === 'contact' ? '/contact' : '/forms/' . $f->slug }}</code></a></td>
                        <td><a href="{{ route('website.forms.entries', $f) }}">{{ $f->entries_count }}</a>@if($f->new_count) <span class="badge bg-primary">{{ $f->new_count }} new</span>@endif</td>
                        <td>{{ $f->to_crm ? 'Yes, as a new lead' : 'No' }}</td>
                        <td><span class="badge bg-{{ $f->isOpen() ? 'success' : 'secondary' }}-subtle text-{{ $f->isOpen() ? 'success' : 'secondary' }}">{{ $f->isOpen() ? 'Open' : 'Closed' }}</span></td>
                        <td class="text-end text-nowrap"><a href="{{ route('website.forms.entries', $f) }}" class="btn btn-sm btn-soft-primary">Entries</a> <a href="{{ route('website.forms.edit', $f) }}" class="btn btn-sm btn-soft-secondary">Edit</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted p-4">No forms yet. The contact form is made when the website is first opened.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </x-cb.card>
</div></div></div>
@endsection
