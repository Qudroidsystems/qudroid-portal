@extends('layouts.auth')

@section('title', 'Reset Password')
@section('heading', 'Create a new password')
@section('subheading', 'Your new password must be different from the one you used before')

@section('content')
    <form method="POST" action="{{ route('password.update') }}" id="resetForm" data-auth-form>
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        @include('auth.partials.errors')

        <div class="mb-4">
            <label class="form-label" for="email">Email <span class="text-danger">*</span></label>
            <input type="email" class="form-control apple-input @error('email') is-invalid @enderror" name="email" id="email" value="{{ $email ?? old('email') }}" required autocomplete="email">
            @error('email')
                <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
            @enderror
        </div>

        <div class="mb-4">
            <label class="form-label" for="password-input">New password <span class="text-danger">*</span></label>
            <div class="position-relative auth-pass-inputgroup">
                <input type="password" class="form-control apple-input pe-5 password-input @error('password') is-invalid @enderror" onpaste="return false" placeholder="Enter a new password" name="password" id="password-input" autocomplete="new-password" required>
                <button class="btn btn-link position-absolute end-0 top-0 text-decoration-none text-muted password-addon" type="button"><i class="ri-eye-fill align-middle"></i></button>
                @error('password')
                    <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                @enderror
            </div>
        </div>

        <div class="mb-4">
            <label class="form-label" for="confirm-password-input">Confirm password <span class="text-danger">*</span></label>
            <div class="position-relative auth-pass-inputgroup">
                <input type="password" class="form-control apple-input pe-5 password-input" onpaste="return false" placeholder="Type it again" id="confirm-password-input" name="password_confirmation" required autocomplete="new-password">
                <button class="btn btn-link position-absolute end-0 top-0 text-decoration-none text-muted password-addon" type="button"><i class="ri-eye-fill align-middle"></i></button>
            </div>
        </div>

        @include('auth.partials.password-rules')

        <div class="mt-4">
            <button class="btn btn-primary w-100 apple-button" type="submit" data-loading-text="Saving...">
                <span>Reset Password</span>
            </button>
        </div>
    </form>
@endsection

@section('footer')
    <p class="mb-0 text-muted small">Remembered it? <a href="{{ route('login') }}" class="fw-semibold text-primary text-decoration-none">Back to Sign In</a></p>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const password = document.getElementById('password-input');
        const confirm = document.getElementById('confirm-password-input');

        @if($errors->has('password')) shakeElement(password); @endif

        document.getElementById('resetForm').addEventListener('submit', function (e) {
            let bad = false;
            if (!checkPasswordStrength(password.value)) { fieldError(password, 'Please meet all password requirements.'); shakeElement(password); bad = true; }
            if (confirm.value !== password.value) { fieldError(confirm, "The passwords don't match."); shakeElement(confirm); bad = true; }
            if (bad) e.preventDefault();
        });
        confirm.addEventListener('input', () => fieldError(confirm, null));
    });
</script>
@endpush
