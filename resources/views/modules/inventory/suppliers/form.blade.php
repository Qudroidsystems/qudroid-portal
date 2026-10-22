@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">

    @php $editing = $supplier->exists; $v = fn ($f) => old($f, $supplier->{$f}); @endphp
    <x-cb.hero :title="$editing ? 'Edit ' . $supplier->name : 'New supplier'" icon="ri-store-2-line" :back="$editing ? route('inventory.suppliers.show', $supplier) : route('inventory.suppliers.index')" :back-label="$editing ? $supplier->name : 'Suppliers'" />

    @include('partials.flash')

    <div class="row"><div class="col-xl-8">
        <x-cb.card>
            <form method="POST" action="{{ $editing ? route('inventory.suppliers.update', $supplier) : route('inventory.suppliers.store') }}">
                @csrf
                @if($editing) @method('PUT') @endif
                <div class="row g-3">
                    <div class="col-md-8"><label class="form-label">Name</label><input name="name" class="form-control" value="{{ $v('name') }}" maxlength="150" required></div>
                    <div class="col-md-4"><label class="form-label">Contact person</label><input name="contact_name" class="form-control" value="{{ $v('contact_name') }}" maxlength="150"></div>
                    <div class="col-md-6"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="{{ $v('email') }}" maxlength="150"></div>
                    <div class="col-md-6"><label class="form-label">Phone</label><input name="phone" class="form-control" value="{{ $v('phone') }}" maxlength="40"></div>
                    <div class="col-md-6"><label class="form-label">Address</label><input name="address" class="form-control" value="{{ $v('address') }}" maxlength="255"></div>
                    <div class="col-md-6"><label class="form-label">Bank details</label><input name="bank_details" class="form-control" value="{{ $v('bank_details') }}" maxlength="255" placeholder="Bank, account number, name"></div>
                    <div class="col-12"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="3" maxlength="5000">{{ $v('notes') }}</textarea></div>
                    @if($editing)
                        <div class="col-12"><div class="form-check form-switch"><input type="checkbox" class="form-check-input" name="is_active" value="1" id="act" @checked($v('is_active'))><label class="form-check-label" for="act">Active (untick to archive)</label></div></div>
                    @endif
                </div>
                <div class="mt-4"><button class="btn btn-primary">{{ $editing ? 'Save' : 'Add supplier' }}</button></div>
            </form>
        </x-cb.card>
    </div></div>

</div></div></div>
@endsection
