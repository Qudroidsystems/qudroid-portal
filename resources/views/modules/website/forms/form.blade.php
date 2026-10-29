@extends('layouts.master')

@section('content')
@php $editing = $form->exists; $fields = old('fields', $form->fields ?? []); @endphp
<div class="main-content"><div class="page-content"><div class="container-fluid">
    <x-cb.hero :title="$editing ? $form->name : 'New form'" icon="ri-survey-line" subtitle="Fields people fill in, what they see after sending, and who is told." :back="route('website.forms.index')" back-label="Forms">
        @if($editing)
        <x-slot:actions>
            <a class="cb-hero-btn" href="{{ route('website.forms.entries', $form) }}"><i class="ri-inbox-line me-1"></i>Entries</a>
            <a class="cb-hero-btn" href="{{ $form->slug === 'contact' ? route('site.contact') : route('site.forms.show', $form) }}" target="_blank"><i class="ri-external-link-line me-1"></i>View</a>
        </x-slot:actions>
        @endif
    </x-cb.hero>
    @include('partials.flash')
    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

    <form method="POST" action="{{ $editing ? route('website.forms.update', $form) : route('website.forms.store') }}">
        @csrf @if($editing) @method('PUT') @endif
        <div class="row g-3">
            <div class="col-xl-8">
                <x-cb.card title="Form" icon="ri-file-text-line">
                    <div class="row g-3">
                        <div class="col-md-7"><label class="form-label">Name</label><input name="name" class="form-control" required maxlength="120" value="{{ old('name', $form->name) }}" placeholder="e.g. Request a quote"></div>
                        <div class="col-md-5"><label class="form-label">Address</label>
                            @if($form->slug === 'contact')<input class="form-control" value="/contact" disabled>
                            @else<div class="input-group"><span class="input-group-text">/forms/</span><input name="slug" class="form-control" maxlength="80" value="{{ old('slug', $form->slug) }}" placeholder="from the name"></div>@endif
                        </div>
                        <div class="col-12"><label class="form-label">Intro</label><textarea name="intro" class="form-control" rows="2" maxlength="1000">{{ old('intro', $form->intro) }}</textarea></div>
                    </div>
                </x-cb.card>
                <x-cb.card title="Fields" icon="ri-input-method-line" class="mt-3">
                    <div data-repeater>
                        <div data-rows>
                            @foreach($fields as $i => $f)@include('modules.website.forms._field', ['i' => $i, 'f' => $f])@endforeach
                        </div>
                        <template>@include('modules.website.forms._field', ['i' => '__i__', 'f' => ['type' => 'text']])</template>
                        <button type="button" class="btn btn-sm btn-soft-secondary" data-add-row><i class="ri-add-line"></i> Add a field</button>
                        <div class="form-text">Fields named like "name", "email" and "phone" fill the lead's details in CRM.</div>
                    </div>
                </x-cb.card>
            </div>
            <div class="col-xl-4">
                <x-cb.card title="After sending" icon="ri-send-plane-line">
                    <div class="mb-2"><label class="form-label small">Button text</label><input name="button_label" class="form-control" maxlength="40" value="{{ old('button_label', $form->button_label) }}"></div>
                    <div class="mb-2"><label class="form-label small">Thank-you message</label><textarea name="success_message" class="form-control" rows="2" maxlength="500">{{ old('success_message', $form->success_message) }}</textarea></div>
                    <div class="mb-2"><label class="form-label small">E-mail each entry to (optional)</label><input name="notify" class="form-control" maxlength="500" value="{{ old('notify', $form->notify) }}" placeholder="sales@qudroid.com, ops@qudroid.com"><div class="form-text">Website managers also get a portal notification.</div></div>
                    <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="to_crm" value="1" id="crm" @checked(old('to_crm', $form->to_crm))><label class="form-check-label" for="crm">Add each entry to CRM as a new lead</label></div>
                </x-cb.card>
                <x-cb.card title="Status" icon="ri-eye-line" class="mt-3">
                    <select name="status" class="form-select mb-3"><option value="open" @selected(old('status', $form->status) === 'open')>Open: taking entries</option><option value="closed" @selected(old('status', $form->status) === 'closed')>Closed</option></select>
                    <button class="btn btn-primary w-100">{{ $editing ? 'Save form' : 'Create form' }}</button>
                    @if($editing && $form->slug !== 'contact')<div class="text-end mt-1"><button form="deleteItem" class="btn btn-link text-danger btn-sm">Delete form and entries</button></div>@endif
                </x-cb.card>
            </div>
        </div>
    </form>
    @if($editing && $form->slug !== 'contact')<form method="POST" action="{{ route('website.forms.destroy', $form) }}" id="deleteItem" onsubmit="return confirm('Delete this form and all its entries?')">@csrf @method('DELETE')</form>@endif
</div></div></div>
@include('modules.website._repeater')
@endsection
