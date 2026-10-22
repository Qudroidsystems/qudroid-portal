@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">

    @php $editing = $company->exists; @endphp
    <x-cb.hero :title="$editing ? 'Edit ' . $company->name : 'New company'" icon="ri-building-line" :back="$editing ? route('crm.companies.show', $company) : route('crm.companies.index')" :back-label="$editing ? $company->name : 'Companies'" />

    @include('partials.flash')

    <div class="row"><div class="col-xl-9">
        <x-cb.card>
            <form method="POST" action="{{ $editing ? route('crm.companies.update', $company) : route('crm.companies.store') }}">
                @csrf
                @if($editing) @method('PUT') @endif
                <div class="row g-3">
                    <div class="col-md-8"><label class="form-label">Name</label><input name="name" class="form-control" value="{{ old('name', $company->name) }}" maxlength="150" required></div>
                    <div class="col-md-4"><label class="form-label">Status</label>
                        <select name="status" class="form-select">@foreach(\App\Models\Crm\Company::STATUSES as $k => $v)<option value="{{ $k }}" @selected(old('status', $company->status) === $k)>{{ $v }}</option>@endforeach</select></div>
                    <div class="col-md-4"><label class="form-label">Industry</label><input name="industry" class="form-control" value="{{ old('industry', $company->industry) }}" maxlength="100"></div>
                    <div class="col-md-4"><label class="form-label">Website</label><input name="website" class="form-control" value="{{ old('website', $company->website) }}" maxlength="200" placeholder="example.com"></div>
                    <div class="col-md-4"><label class="form-label">Found us through</label><input name="source" class="form-control" value="{{ old('source', $company->source) }}" maxlength="50" list="sources" placeholder="Referral, website…">
                        <datalist id="sources"><option>Referral</option><option>Website</option><option>Social media</option><option>Event</option><option>Cold outreach</option><option>Existing client</option></datalist></div>
                    <div class="col-md-6"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="{{ old('email', $company->email) }}" maxlength="150"></div>
                    <div class="col-md-6"><label class="form-label">Phone</label><input name="phone" class="form-control" value="{{ old('phone', $company->phone) }}" maxlength="40"></div>
                    <div class="col-md-6"><label class="form-label">Address</label><input name="address" class="form-control" value="{{ old('address', $company->address) }}" maxlength="255"></div>
                    <div class="col-md-3"><label class="form-label">City</label><input name="city" class="form-control" value="{{ old('city', $company->city) }}" maxlength="100"></div>
                    <div class="col-md-3"><label class="form-label">Country</label><input name="country" class="form-control" value="{{ old('country', $company->country ?? 'Nigeria') }}" maxlength="100"></div>
                    <div class="col-md-6"><label class="form-label">Account owner</label>
                        <select name="owner_id" class="form-select"><option value="">{{ $editing ? 'Nobody' : 'Me' }}</option>@foreach($staff as $s)<option value="{{ $s->id }}" @selected((int) old('owner_id', $company->owner_id) === $s->id)>{{ $s->name }}</option>@endforeach</select></div>
                    <div class="col-12"><label class="form-label">Notes</label><textarea name="notes" rows="3" class="form-control" maxlength="5000">{{ old('notes', $company->notes) }}</textarea></div>
                </div>
                <div class="mt-4"><button class="btn btn-primary">{{ $editing ? 'Save' : 'Add company' }}</button></div>
            </form>
        </x-cb.card>
    </div></div>

</div></div></div>
@endsection
