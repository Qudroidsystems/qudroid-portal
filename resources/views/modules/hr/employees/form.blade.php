@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">

    @php $editing = $employee->exists; $pay = auth()->user()->can('Manage payroll'); $v = fn ($f) => old($f, $employee->{$f}); @endphp
    <x-cb.hero :title="$editing ? 'Edit ' . $employee->user->name : 'Add employee'" icon="ri-user-add-line" :back="$editing ? route('hr.employees.show', $employee) : route('hr.employees.index')" :back-label="$editing ? $employee->user->name : 'Employees'" />

    @include('partials.flash')

    <form method="POST" action="{{ $editing ? route('hr.employees.update', $employee) : route('hr.employees.store') }}">
        @csrf
        @if($editing) @method('PUT') @endif
        <div class="row g-3"><div class="col-xl-9">

        @unless($editing)
            <x-cb.card title="Portal account" icon="ri-account-circle-line">
                <p class="text-muted small">Every employee signs in to the portal. Choose their existing staff account, or enter a name and email to create one.</p>
                <div class="row g-3">
                    <div class="col-md-12"><label class="form-label">Existing staff account</label>
                        <select name="user_id" class="form-select" id="emp-user"><option value="">Create a new account</option>@foreach($accounts as $a)<option value="{{ $a->id }}" @selected((int) old('user_id') === $a->id)>{{ $a->name }} ({{ $a->email }})</option>@endforeach</select></div>
                    <div class="col-md-5 new-acct"><label class="form-label">Full name</label><input name="name" class="form-control" value="{{ old('name') }}" maxlength="150"></div>
                    <div class="col-md-4 new-acct"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="{{ old('email') }}" maxlength="150"></div>
                    <div class="col-md-3 new-acct"><label class="form-label">Phone</label><input name="phone" class="form-control" value="{{ old('phone') }}" maxlength="40"></div>
                </div>
            </x-cb.card>
        @endunless

        <x-cb.card title="Job" icon="ri-briefcase-line">
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label">Job title</label><input name="job_title" class="form-control" value="{{ $v('job_title') }}" maxlength="100"></div>
                <div class="col-md-6"><label class="form-label">Department</label>
                    <select name="department_id" class="form-select"><option value="">None</option>@foreach($departments as $d)<option value="{{ $d->id }}" @selected((int) $v('department_id') === $d->id)>{{ $d->name }}</option>@endforeach</select></div>
                @include('partials.unit-select', ['value' => $employee->business_unit_id ?? null, 'class' => 'col-md-6'])
                <div class="col-md-6"><label class="form-label">Reports to</label>
                    <select name="manager_id" class="form-select"><option value="">Nobody</option>@foreach($managers as $m)<option value="{{ $m->id }}" @selected((int) $v('manager_id') === $m->id)>{{ $m->name }}</option>@endforeach</select>
                    <div class="form-text">Their manager approves their leave.</div></div>
                <div class="col-md-3"><label class="form-label">Type</label>
                    <select name="employment_type" class="form-select">@foreach(\App\Models\Hr\Employee::TYPES as $k => $t)<option value="{{ $k }}" @selected($v('employment_type') === $k)>{{ $t }}</option>@endforeach</select></div>
                <div class="col-md-3"><label class="form-label">Start date</label><input type="date" name="start_date" class="form-control" value="{{ old('start_date', $employee->start_date?->toDateString()) }}"></div>
                <div class="col-md-3"><label class="form-label">Annual leave days</label><input type="number" name="annual_leave_days" class="form-control" value="{{ $v('annual_leave_days') }}" min="0" max="60" required></div>
                @if($editing)
                    <div class="col-md-3"><label class="form-label">Status</label>
                        <select name="status" class="form-select">@foreach(\App\Models\Hr\Employee::STATUSES as $k => $t)<option value="{{ $k }}" @selected($v('status') === $k)>{{ $t }}</option>@endforeach</select></div>
                    <div class="col-md-3"><label class="form-label">Exit date</label><input type="date" name="exit_date" class="form-control" value="{{ old('exit_date', $employee->exit_date?->toDateString()) }}"></div>
                    <div class="col-md-9"><label class="form-label">Reason for leaving</label><input name="exit_reason" class="form-control" value="{{ $v('exit_reason') }}" maxlength="255"></div>
                @endif
                <div class="col-md-6"><label class="form-label">Next of kin</label><input name="next_of_kin_name" class="form-control" value="{{ $v('next_of_kin_name') }}" maxlength="150"></div>
                <div class="col-md-6"><label class="form-label">Next of kin phone</label><input name="next_of_kin_phone" class="form-control" value="{{ $v('next_of_kin_phone') }}" maxlength="40"></div>
                <div class="col-12"><label class="form-label">Notes</label><textarea name="notes" rows="2" class="form-control" maxlength="5000">{{ $v('notes') }}</textarea></div>
            </div>
        </x-cb.card>

        @if($pay)
            <x-cb.card title="Monthly pay" icon="ri-money-dollar-circle-line">
                <div class="row g-3">
                    <div class="col-md-3"><label class="form-label">Basic</label><input type="number" step="0.01" min="0" name="basic_salary" class="form-control" value="{{ $v('basic_salary') }}"></div>
                    <div class="col-md-3"><label class="form-label">Housing</label><input type="number" step="0.01" min="0" name="housing_allowance" class="form-control" value="{{ $v('housing_allowance') }}"></div>
                    <div class="col-md-3"><label class="form-label">Transport</label><input type="number" step="0.01" min="0" name="transport_allowance" class="form-control" value="{{ $v('transport_allowance') }}"></div>
                    <div class="col-md-3"><label class="form-label">Other allowances</label><input type="number" step="0.01" min="0" name="other_allowance" class="form-control" value="{{ $v('other_allowance') }}"></div>
                    <div class="col-md-4"><label class="form-label">Annual rent paid</label><input type="number" step="0.01" min="0" name="annual_rent" class="form-control" value="{{ $v('annual_rent') }}">
                        <div class="form-text">For rent relief: 20% of rent, up to ₦500,000 a year, comes off taxable pay.</div></div>
                    <div class="col-md-4 d-flex align-items-center"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="pension_enrolled" value="1" id="pen" @checked($v('pension_enrolled'))><label class="form-check-label" for="pen">Pension (8% employee, 10% employer)</label></div></div>
                    <div class="col-md-4 d-flex align-items-center"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="nhf_enrolled" value="1" id="nhf" @checked($v('nhf_enrolled'))><label class="form-check-label" for="nhf">NHF (2.5% of basic)</label></div></div>
                    <div class="col-md-4"><label class="form-label">Bank</label><input name="bank_name" class="form-control" value="{{ $v('bank_name') }}" maxlength="100"></div>
                    <div class="col-md-4"><label class="form-label">Account number</label><input name="account_number" class="form-control" value="{{ $v('account_number') }}" maxlength="20" inputmode="numeric"></div>
                    <div class="col-md-4"><label class="form-label">Account name</label><input name="account_name" class="form-control" value="{{ $v('account_name') }}" maxlength="150"></div>
                    <div class="col-md-3"><label class="form-label">Pension administrator</label><input name="pension_pfa" class="form-control" value="{{ $v('pension_pfa') }}" maxlength="100"></div>
                    <div class="col-md-3"><label class="form-label">Pension PIN</label><input name="pension_pin" class="form-control" value="{{ $v('pension_pin') }}" maxlength="30"></div>
                    <div class="col-md-3"><label class="form-label">Tax ID</label><input name="tax_id" class="form-control" value="{{ $v('tax_id') }}" maxlength="30"></div>
                    <div class="col-md-3"><label class="form-label">NHF number</label><input name="nhf_number" class="form-control" value="{{ $v('nhf_number') }}" maxlength="30"></div>
                </div>
            </x-cb.card>
        @else
            <div class="cb-banner info"><i class="ri-information-line"></i><div>Pay and bank details are set by someone with payroll access.</div></div>
        @endif

        <button class="btn btn-primary">{{ $editing ? 'Save' : 'Add employee' }}</button>
        </div></div>
    </form>

</div></div></div>
@endsection

@unless($employee->exists)
@push('scripts')
<script>
(function () {
    var sel = document.getElementById('emp-user');
    function sync() { document.querySelectorAll('.new-acct').forEach(function (el) { el.style.display = sel.value ? 'none' : ''; }); }
    sel.addEventListener('change', sync); sync();
})();
</script>
@endpush
@endunless
