@extends('layouts.auth')

@section('title', 'Choose Password')
@section('heading', 'Choose your password')
@section('subheading')
    @if($temporary)
        You signed in with a temporary password. Choose your own to continue.
    @else
        Enter your current password, then choose a new one.
    @endif
@endsection

@section('content')
    @if (session('warning'))
        <div class="alert alert-warning border-0 mb-4" style="border-left: 4px solid #f59e0b !important; background: #fffbeb;">{{ session('warning') }}</div>
    @endif

    <form method="POST" action="{{ route('password.change.update') }}" data-auth-form>
        @csrf

        @unless($temporary)
        <div class="mb-4">
            <label class="form-label" for="current-password">Current password <span class="text-danger">*</span></label>
            <div class="position-relative auth-pass-inputgroup">
                <input type="password" class="form-control apple-input pe-5 @error('current_password') is-invalid @enderror" name="current_password" id="current-password" autocomplete="current-password" required>
                <button class="btn btn-link position-absolute end-0 top-0 text-decoration-none text-muted password-addon" type="button"><i class="ri-eye-fill align-middle"></i></button>
                @error('current_password')
                    <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                @enderror
            </div>
        </div>
        @endunless

        <div class="mb-4">
            <label class="form-label" for="password-input">New password <span class="text-danger">*</span></label>
            <div class="position-relative auth-pass-inputgroup">
                <input type="password" class="form-control apple-input pe-5 password-input @error('password') is-invalid @enderror" placeholder="Enter a new password" name="password" id="password-input" autocomplete="new-password" required>
                <button class="btn btn-link position-absolute end-0 top-0 text-decoration-none text-muted password-addon" type="button"><i class="ri-eye-fill align-middle"></i></button>
                @error('password')
                    <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                @enderror
            </div>
            <div class="form-text">At least 8 characters, with letters and numbers.</div>
        </div>

        <div class="mb-4">
            <label class="form-label" for="confirm-password-input">Confirm new password <span class="text-danger">*</span></label>
            <div class="position-relative auth-pass-inputgroup">
                <input type="password" class="form-control apple-input pe-5 password-input" placeholder="Type it again" id="confirm-password-input" name="password_confirmation" required autocomplete="new-password">
                <button class="btn btn-link position-absolute end-0 top-0 text-decoration-none text-muted password-addon" type="button"><i class="ri-eye-fill align-middle"></i></button>
            </div>
        </div>

        <div class="mt-4">
            <button class="btn btn-primary w-100 apple-button" type="submit" data-loading-text="Saving...">
                <span>Save password</span>
            </button>
        </div>
    </form>
@endsection

@section('footer')
    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit" class="btn btn-link text-muted small text-decoration-none">Sign out</button>
    </form>
@endsection
