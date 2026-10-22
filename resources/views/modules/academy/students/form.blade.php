@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">

    @php $editing = $student->exists; $f = fn ($k) => old($k, $student->$k); $u = fn ($k) => old($k, $user->$k); @endphp
    <x-cb.hero :title="$editing ? 'Edit ' . $user->name : 'New student'" icon="ri-user-add-line"
               :subtitle="$editing ? $student->student_no : 'Creates the student\'s sign-in account and academy record. A student number is assigned automatically.'"
               :back="$editing ? route('academy.students.show', $student) : route('academy.students.index')" :back-label="$editing ? 'Student' : 'Students'" />

    @include('modules.academy.partials.flash')

    <form method="POST" action="{{ $editing ? route('academy.students.update', $student) : route('academy.students.store') }}">
        @csrf @if($editing) @method('PUT') @endif
        <div class="row g-3">
            <div class="col-xl-6">
                <x-cb.card title="Personal details" icon="ri-user-3-line">
                    <div class="row g-3">
                        <div class="col-12"><label class="form-label">Full name</label><input name="name" class="form-control" required maxlength="150" value="{{ $u('name') }}"></div>
                        <div class="col-md-7"><label class="form-label">E-mail</label><input name="email" type="email" class="form-control" required maxlength="190" value="{{ $u('email') }}"></div>
                        <div class="col-md-5"><label class="form-label">Phone</label><input name="phone_number" class="form-control" maxlength="30" value="{{ $u('phone_number') }}"></div>
                        <div class="col-md-6"><label class="form-label">Gender</label>
                            <select name="gender" class="form-select"><option value="">—</option><option value="male" @selected($u('gender') === 'male')>Male</option><option value="female" @selected($u('gender') === 'female')>Female</option></select>
                        </div>
                        <div class="col-md-6"><label class="form-label">Date of birth</label><input name="date_of_birth" type="date" class="form-control" value="{{ old('date_of_birth', $user->date_of_birth?->format('Y-m-d')) }}"></div>
                        <div class="col-12"><label class="form-label">Address</label><input name="address" class="form-control" maxlength="500" value="{{ $u('address') }}"></div>
                    </div>
                </x-cb.card>
                @unless($editing)
                    <x-cb.card title="Admission" icon="ri-door-open-line">
                        <div class="mb-3">
                            <div class="form-check"><input class="form-check-input" type="radio" name="status" id="st-adm" value="admitted" @checked(old('status', 'admitted') === 'admitted')><label class="form-check-label" for="st-adm">Admit now (can be enrolled straight away)</label></div>
                            <div class="form-check"><input class="form-check-input" type="radio" name="status" id="st-app" value="applicant" @checked(old('status') === 'applicant')><label class="form-check-label" for="st-app">Record as applicant (admit later)</label></div>
                        </div>
                        <label class="form-label">Password</label><input name="password" type="text" class="form-control" minlength="8" maxlength="100" autocomplete="off" placeholder="Leave empty to generate one">
                        <div class="form-text">Either way it is temporary: the student chooses their own at first sign-in.</div>
                    </x-cb.card>
                @endunless
            </div>
            <div class="col-xl-6">
                <x-cb.card title="Background" icon="ri-book-mark-line">
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">Highest education</label><input name="education_level" class="form-control" maxlength="80" placeholder="e.g. BSc, OND, SSCE" value="{{ $f('education_level') }}"></div>
                        <div class="col-md-6"><label class="form-label">School / institution</label><input name="institution" class="form-control" maxlength="150" value="{{ $f('institution') }}"></div>
                        <div class="col-md-6"><label class="form-label">Occupation</label><input name="occupation" class="form-control" maxlength="120" value="{{ $f('occupation') }}"></div>
                        <div class="col-md-6"><label class="form-label">How they heard of us</label><input name="referral_source" class="form-control" maxlength="120" value="{{ $f('referral_source') }}"></div>
                    </div>
                </x-cb.card>
                <x-cb.card title="Contacts" icon="ri-contacts-line">
                    <div class="row g-3">
                        <div class="col-md-7"><label class="form-label">Emergency contact</label><input name="emergency_contact_name" class="form-control" maxlength="150" value="{{ $f('emergency_contact_name') }}"></div>
                        <div class="col-md-5"><label class="form-label">Their phone</label><input name="emergency_contact_phone" class="form-control" maxlength="30" value="{{ $f('emergency_contact_phone') }}"></div>
                        <div class="col-md-7"><label class="form-label">Parent / guardian <span class="text-muted">(minors)</span></label><input name="guardian_name" class="form-control" maxlength="150" value="{{ $f('guardian_name') }}"></div>
                        <div class="col-md-5"><label class="form-label">Their phone</label><input name="guardian_phone" class="form-control" maxlength="30" value="{{ $f('guardian_phone') }}"></div>
                        <div class="col-12"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="3">{{ $f('notes') }}</textarea></div>
                    </div>
                </x-cb.card>
                <button class="btn btn-primary w-100">{{ $editing ? 'Save changes' : 'Create student' }}</button>
            </div>
        </div>
    </form>

</div></div></div>
@endsection
