@extends('layouts.auth')

@section('title', 'Sign Up')
@section('heading', 'Create your free account')
@section('subheading', 'Get started with your learning journey')

@section('content')
    <form method="POST" action="{{ route('register') }}" id="registerForm" data-auth-form novalidate>
        @csrf

        @include('auth.partials.errors')

        <div class="mb-4">
            <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
            <input id="email" type="email" class="form-control apple-input @error('email') is-invalid @enderror" name="email" value="{{ old('email') }}" required autocomplete="email" placeholder="your@email.com">
            @error('email')
                <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
            @enderror
        </div>

        <div class="mb-4">
            <label for="name" class="form-label">Full Name <span class="text-danger">*</span></label>
            <input id="name" type="text" class="form-control apple-input @error('name') is-invalid @enderror" name="name" value="{{ old('name') }}" required autocomplete="name" autofocus placeholder="John Doe">
            @error('name')
                <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
            @enderror
        </div>

        <div class="mb-4">
            <label class="form-label" for="password-input">Password <span class="text-danger">*</span></label>
            <div class="position-relative auth-pass-inputgroup">
                <input type="password"
                       class="form-control apple-input password-input pe-5 @error('password') is-invalid @enderror"
                       onpaste="return false"
                       placeholder="Create a strong password"
                       id="password-input"
                       name="password"
                       autocomplete="new-password"
                       required>
                <button class="btn btn-link position-absolute end-0 top-0 text-decoration-none text-muted password-addon" type="button">
                    <i class="ri-eye-fill align-middle"></i>
                </button>
                @error('password')
                    <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                @enderror
            </div>
        </div>

        @include('auth.partials.password-rules')

        <div class="mt-4">
            <button class="btn btn-primary w-100 apple-button" type="submit" id="registerButton" data-loading-text="Creating Account...">
                <span>Create Account</span>
            </button>
        </div>
    </form>
@endsection

@section('footer')
    <p class="mb-0 text-muted small">Already have an account? <a href="{{ route('login') }}" class="fw-semibold text-primary text-decoration-none">Sign In</a></p>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('registerForm');
        const emailInput = document.getElementById('email');
        const nameInput = document.getElementById('name');
        const passwordInput = document.getElementById('password-input');
        const emailOk = (v) => /^[^\s@]+@([^\s@]+\.)+[^\s@]+$/.test(v);

        @if($errors->any())
            @if($errors->has('email')) shakeElement(emailInput); @endif
            @if($errors->has('name')) shakeElement(nameInput); @endif
            @if($errors->has('password')) shakeElement(passwordInput); @endif
        @endif

        emailInput.addEventListener('blur', function () {
            const v = this.value.trim();
            if (v && !emailOk(v)) { fieldError(this, 'Please enter a valid email address.'); shakeElement(this); }
        });
        nameInput.addEventListener('blur', function () {
            const v = this.value.trim();
            if (v && v.length < 2) { fieldError(this, 'Please enter your full name.'); shakeElement(this); }
        });
        [emailInput, nameInput, passwordInput].forEach(function (input) {
            input.addEventListener('input', function () {
                const errorDiv = this.parentElement.querySelector('.invalid-feedback');
                if (errorDiv && errorDiv.innerHTML.includes('already been taken')) return;
                fieldError(this, null);
            });
        });

        form.addEventListener('submit', function (e) {
            let bad = false;
            if (!emailOk(emailInput.value.trim())) { fieldError(emailInput, 'Please enter a valid email address.'); shakeElement(emailInput); bad = true; }
            if (nameInput.value.trim().length < 2) { fieldError(nameInput, 'Please enter your full name.'); shakeElement(nameInput); bad = true; }
            if (!checkPasswordStrength(passwordInput.value)) { fieldError(passwordInput, 'Please meet all password requirements.'); shakeElement(passwordInput); bad = true; }
            if (bad) {
                e.preventDefault();
                showToast('Check your details', 'Please fix the highlighted fields and try again.', 'error');
            }
        });
    });
</script>
@endpush
