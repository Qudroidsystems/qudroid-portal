@extends('layouts.auth')

@section('title', 'Forgot Password')
@section('badge')<span class="auth-badge"><i class="ri-mail-send-line"></i></span>@endsection
@section('heading', 'Forgot your password?')
@section('subheading', "Enter your email and we'll send you a link to reset it")

@section('content')
    @if (session('status'))
        <div class="alert alert-success border-0 mb-4" role="alert" style="border-left: 4px solid #10b981 !important;">
            <i class="ri-checkbox-circle-line me-1"></i> {{ session('status') }}
        </div>
    @endif

    <form method="POST" action="{{ route('password.email') }}" data-auth-form>
        @csrf

        <div class="mb-4">
            <label class="form-label" for="email">Email <span class="text-danger">*</span></label>
            <input id="email" type="email" class="form-control apple-input @error('email') is-invalid @enderror" name="email" value="{{ old('email') }}" required autocomplete="email" autofocus placeholder="you@qudroids.com">
            @error('email')
                <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
            @enderror
        </div>

        <div class="mt-4">
            <button class="btn btn-primary w-100 apple-button" type="submit" data-loading-text="Sending...">
                <span>Send Reset Link</span>
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
        @error('email') shakeElement(document.getElementById('email')); @enderror
    });
</script>
@endpush
