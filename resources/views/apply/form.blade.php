@extends('layouts.guest')
@section('title', 'Apply: ' . $cohort->course->title)

@section('content')
    <a href="{{ route('apply.index') }}" class="small text-muted"><i class="ri-arrow-left-line"></i> All courses</a>
    <h2 class="fw-semibold mt-2 mb-1">{{ $cohort->course->title }}</h2>
    <p class="text-muted">{{ $cohort->name }}@if($cohort->starts_on) · starts {{ $cohort->starts_on->format('j M Y') }}@endif · {{ \App\Support\Money::format($cohort->effectiveFee(), $cohort->course->currency) }}</p>

    @if($errors->any())
        <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <form method="POST" action="{{ route('apply.store', $cohort) }}" class="apply-course p-3 p-md-4">
        @csrf
        <div class="hp-field" aria-hidden="true"><label>Website <input name="website" tabindex="-1" autocomplete="off"></label></div>

        @if($student)
            <div class="alert alert-info">Applying as <strong>{{ $student->user->name }}</strong> ({{ $student->student_no }}).</div>
        @else
            <h6 class="mb-3">About you</h6>
            <div class="row g-3 mb-4">
                <div class="col-md-6"><label class="form-label">Full name</label><input name="name" class="form-control" value="{{ old('name') }}" required maxlength="150" autocomplete="name"></div>
                <div class="col-md-6"><label class="form-label">E-mail</label><input type="email" name="email" class="form-control" value="{{ old('email') }}" required maxlength="190" autocomplete="email">
                    <div class="form-text">Already have an account? <a href="{{ route('login') }}">Sign in</a> first.</div></div>
                <div class="col-md-6"><label class="form-label">Phone</label><input name="phone_number" class="form-control" value="{{ old('phone_number') }}" required maxlength="30" autocomplete="tel"></div>
                <div class="col-md-6"><label class="form-label">Gender <span class="text-muted">(optional)</span></label>
                    <select name="gender" class="form-select"><option value="">Prefer not to say</option><option value="male" @selected(old('gender') === 'male')>Male</option><option value="female" @selected(old('gender') === 'female')>Female</option></select></div>
                <div class="col-md-6"><label class="form-label">Highest education <span class="text-muted">(optional)</span></label><input name="education_level" class="form-control" value="{{ old('education_level') }}" maxlength="80" placeholder="e.g. SSCE, OND, BSc"></div>
                <div class="col-md-6"><label class="form-label">Occupation <span class="text-muted">(optional)</span></label><input name="occupation" class="form-control" value="{{ old('occupation') }}" maxlength="120"></div>
                <div class="col-12"><label class="form-label">How did you hear about us? <span class="text-muted">(optional)</span></label><input name="referral_source" class="form-control" value="{{ old('referral_source') }}" maxlength="120"></div>
            </div>
        @endif

        <h6 class="mb-3">Your application</h6>
        <div class="row g-3">
            <div class="col-md-6"><label class="form-label">Coding experience</label>
                <select name="experience_level" class="form-select" required>
                    <option value="">Choose…</option>
                    @foreach(\App\Models\Academy\Application::EXPERIENCE as $k => $v)<option value="{{ $k }}" @selected(old('experience_level') === $k)>{{ $v }}</option>@endforeach
                </select></div>
            <div class="col-md-6"><label class="form-label">Do you have a laptop you can use?</label>
                <select name="has_laptop" class="form-select" required>
                    <option value="">Choose…</option>
                    <option value="1" @selected(old('has_laptop') === '1')>Yes</option>
                    <option value="0" @selected(old('has_laptop') === '0')>No</option>
                </select></div>
            <div class="col-12"><label class="form-label">Why do you want to join this course?</label>
                <textarea name="motivation" class="form-control" rows="5" required minlength="20" maxlength="2000">{{ old('motivation') }}</textarea></div>
            @unless($student)
                <div class="col-12"><div class="form-check">
                    <input class="form-check-input" type="checkbox" name="consent" value="1" id="consent" required @checked(old('consent'))>
                    <label class="form-check-label" for="consent">I agree that {{ $brand['company'] ?? 'Qudroid Systems' }} may store these details to process my application and contact me about it.</label>
                </div></div>
            @endunless
        </div>

        <button class="btn btn-primary mt-4"><i class="ri-send-plane-line me-1"></i>Submit application</button>
    </form>
@endsection
