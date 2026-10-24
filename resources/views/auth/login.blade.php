@extends('layouts.auth')

@section('title', 'Sign In')
@section('heading', 'Welcome back')
@section('subheading', 'Sign in to continue')

@section('content')
    <form method="POST" action="{{ route('login') }}" id="loginForm" data-auth-form>
        @csrf

        {{-- Session expired warning --}}
        @if(session('session_expired') || session('error') || request()->boolean('expired'))
            <div id="sessionExpiredAlert" class="alert alert-warning alert-dismissible fade show mb-4" role="alert" style="border-left: 4px solid #f59e0b; background: #fffbeb;">
                <div class="d-flex align-items-center">
                    <i class="ri-alert-line me-2" style="font-size: 18px; color: #d97706;"></i>
                    <div>
                        <strong style="color: #92400e;">Session Expired</strong>
                        <p class="mb-0 small" style="color: #78350f;">{{ session('error') ?? 'Your session has expired. Please login again.' }}</p>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        @include('auth.partials.errors')

        <div class="mb-4">
            <label for="email" class="form-label">Email, username or phone <span class="text-danger">*</span></label>
            <div class="position-relative">
                <input type="text"
                       class="form-control apple-input @error('email') is-invalid @enderror"
                       id="email"
                       name="email"
                       placeholder="you@qudroids.com"
                       value="{{ old('email') }}"
                       required
                       autocomplete="username"
                       autofocus>
                @error('email')
                    <span class="invalid-feedback" role="alert">
                        <strong>{{ $message }}</strong>
                    </span>
                @enderror
            </div>
        </div>

        <div class="mb-4">
            <div class="d-flex justify-content-between align-items-center">
                <label class="form-label" for="password">Password <span class="text-danger">*</span></label>
                <span class="small">
                @if (Route::has('password.request'))
                    <a href="{{ route('password.request') }}" class="text-muted text-decoration-none">Forgot password?</a>
                @endif
                </span>
            </div>
            <div class="position-relative auth-pass-inputgroup">
                <input type="password"
                       id="password"
                       class="form-control apple-input pe-5 @error('password') is-invalid @enderror"
                       name="password"
                       autocomplete="current-password"
                       placeholder="Enter your password"
                       required>
                <button class="btn btn-link position-absolute end-0 top-0 text-decoration-none text-muted password-addon" type="button" id="password-addon">
                    <i class="ri-eye-fill align-middle"></i>
                </button>
            </div>
            @error('password')
                <span class="invalid-feedback" role="alert">
                    <strong>{{ $message }}</strong>
                </span>
            @enderror
        </div>

        <div class="form-check mb-4">
            <input class="form-check-input" type="checkbox" name="remember" id="remember" {{ old('remember') ? 'checked' : '' }}>
            <label class="form-check-label" for="remember">Remember me</label>
        </div>

        <div class="mt-4">
            <button class="btn btn-primary w-100 apple-button" type="submit" id="loginButton" data-loading-text="Signing In...">
                <span>Sign In</span>
            </button>
        </div>
    </form>
@endsection

@if (Route::has('register'))
    @section('footer')
        <p class="mb-0 text-muted small">Don't have an account? <a href="{{ route('register') }}" class="fw-semibold text-primary text-decoration-none">Sign Up</a></p>
    @endsection
@endif

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const emailInput = document.getElementById('email');
        const passwordInput = document.getElementById('password');

        @if($errors->any())
            @if($errors->has('email'))
                shakeElement(emailInput);
            @endif
            @if($errors->has('password'))
                shakeElement(passwordInput);
            @endif
            showToast('Login Failed', 'Invalid email or password. Please try again.', 'error');
        @endif

        @if(session('session_expired') || request()->boolean('expired'))
            showToast('Session Expired', @json(session('error') ?? 'Your session has expired. Please login again.'), 'warning');

            const sessionAlert = document.getElementById('sessionExpiredAlert');
            if (sessionAlert) {
                setTimeout(() => {
                    const bsAlert = bootstrap.Alert.getInstance(sessionAlert);
                    if (bsAlert) bsAlert.close();
                }, 5000);
            }

            // Gold edge on the form for a moment.
            const card = document.getElementById('authCardBody');
            if (card) {
                card.classList.add('session-expired-highlight');
                setTimeout(() => {
                    card.classList.remove('session-expired-highlight');
                    card.classList.add('session-expired-highlight-remove');
                }, 3000);
            }
        @endif

        // Clear a field's error as soon as it is edited.
        [emailInput, passwordInput].forEach(function (input) {
            if (!input) return;
            input.addEventListener('input', function () {
                this.classList.remove('is-invalid');
                const errorDiv = this.parentElement.querySelector('.invalid-feedback');
                if (errorDiv && !errorDiv.innerHTML.includes('credentials')) errorDiv.remove();
            });
        });

        // The field also takes a username or phone, so only check addresses that contain an @.
        if (emailInput) {
            emailInput.addEventListener('blur', function () {
                const value = this.value.trim();
                if (value.includes('@') && !/^[^\s@]+@([^\s@]+\.)+[^\s@]+$/.test(value)) {
                    fieldError(this, 'Please enter a valid email address.');
                    shakeElement(this);
                }
            });
        }

        // Don't show the expired notice again on refresh.
        if (window.history && window.history.replaceState) {
            const url = new URL(window.location.href);
            if (url.searchParams.has('session_expired')) {
                url.searchParams.delete('session_expired');
                window.history.replaceState({}, document.title, url.toString());
            }
        }
    });

    // Staff credential fill function
    function fillStaffCredentials(email) {
        const emailInput = document.getElementById('email');
        const passwordInput = document.getElementById('password');

        if (emailInput) {
            emailInput.value = email;
            emailInput.classList.remove('is-invalid');
            emailInput.dispatchEvent(new Event('focus'));
            emailInput.style.transition = 'all 0.3s ease';
            emailInput.style.backgroundColor = '#e8f0fe';
            setTimeout(() => emailInput.style.backgroundColor = '', 500);
        }
        if (passwordInput) passwordInput.focus();

        showToast('Staff Selected', 'Email filled. Enter your password to continue.', 'info');
    }
</script>
@endpush
