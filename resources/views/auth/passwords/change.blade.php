<!doctype html>
<html lang="en" data-layout="vertical" data-sidebar="dark" data-sidebar-size="lg" data-preloader="disable" data-theme="default" data-topbar="light" data-bs-theme="light">




<head>

    <meta charset="utf-8">
    <title>Choose Password | {{ $brand['name'] }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta content="{{ $brand['company'] }} portal" name="description">
    <meta content="Themesbrand" name="author">
    <!-- App favicon -->
    <link rel="shortcut icon" href="{{ asset($brand['favicon']) }}">

    <!-- Fonts css load -->
    <link rel="preconnect" href="https://fonts.googleapis.com/">
    <link rel="preconnect" href="https://fonts.gstatic.com/" crossorigin>
    <link id="fontsLink" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&amp;display=swap" rel="stylesheet">

    <!-- Layout config Js -->
    <script src="{{ asset('theme/layouts/assets/js/layout.js')}}"></script>
    <!-- Bootstrap Css -->
    <link href="{{ asset('theme/layouts/assets/css/bootstrap.min.css')}}" rel="stylesheet" type="text/css">
    <!-- Icons Css -->
    <link href="{{ asset('theme/layouts/assets/css/icons.min.css')}}" rel="stylesheet" type="text/css">
    <!-- App Css-->
    <link href="{{ asset('theme/layouts/assets/css/app.min.css')}}" rel="stylesheet" type="text/css">
    <!-- custom Css-->
    <link href="{{ asset('theme/layouts/assets/css/custom.min.css')}}" rel="stylesheet" type="text/css">

</head>

<body>

    <section class="auth-page-wrapper py-5 position-relative d-flex align-items-center justify-content-center min-vh-100">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-11">
                    <div class="card mb-0">
                        <div class="row g-0 align-items-center">
                            <div class="col-xxl-5">
                                <div class="card auth-card bg-secondary h-100 border-0 shadow-none d-none d-sm-block mb-0">
                                    <div class="card-body py-5 d-flex justify-content-between flex-column">
                                        <div class="text-center">
                                            <h3 class="text-white">Start your journey with us.</h3>
                                            <p class="text-white opacity-75 fs-base">{{ $brand['tagline'] }}</p>
                                        </div>
                        
                                        <div class="auth-effect-main my-5 position-relative rounded-circle d-flex align-items-center justify-content-center mx-auto">
                                            <div class="effect-circle-1 position-relative mx-auto rounded-circle d-flex align-items-center justify-content-center">
                                                <div class="effect-circle-2 position-relative mx-auto rounded-circle d-flex align-items-center justify-content-center">
                                                    <div class="effect-circle-3 mx-auto rounded-circle position-relative text-white fs-4xl d-flex align-items-center justify-content-center">
                                                        Welcome to <span class="text-primary ms-1">{{ $brand['name'] }}</span>
                                                    </div>
                                                </div>
                                            </div>
                                            <ul class="auth-user-list list-unstyled">
                                                <li>
                                                    <div class="avatar-sm d-inline-block">
                                                        <div class="avatar-title bg-white shadow-lg overflow-hidden rounded-circle">
                                                            <img src="{{ asset('theme/layouts/assets/images/users/avatar-1.jpg')}}" alt="" class="img-fluid">
                                                        </div>
                                                    </div>
                                                </li>
                                                <li>
                                                    <div class="avatar-sm d-inline-block">
                                                        <div class="avatar-title bg-white shadow-lg overflow-hidden rounded-circle">
                                                            <img src="{{ asset('theme/layouts/assets/images/users/avatar-2.jpg')}}" alt="" class="img-fluid">
                                                        </div>
                                                    </div>
                                                </li>
                                                <li>
                                                    <div class="avatar-sm d-inline-block">
                                                        <div class="avatar-title bg-white shadow-lg overflow-hidden rounded-circle">
                                                            <img src="{{ asset('theme/layouts/assets/images/users/avatar-3.jpg')}}" alt="" class="img-fluid">
                                                        </div>
                                                    </div>
                                                </li>
                                                <li>
                                                    <div class="avatar-sm d-inline-block">
                                                        <div class="avatar-title bg-white shadow-lg overflow-hidden rounded-circle">
                                                            <img src="{{ asset('theme/layouts/assets/images/users/avatar-4.jpg')}}" alt="" class="img-fluid">
                                                        </div>
                                                    </div>
                                                </li>
                                                <li>
                                                    <div class="avatar-sm d-inline-block">
                                                        <div class="avatar-title bg-white shadow-lg overflow-hidden rounded-circle">
                                                            <img src="{{ asset('theme/layouts/assets/images/users/avatar-5.jpg')}}" alt="" class="img-fluid">
                                                        </div>
                                                    </div>
                                                </li>
                                            </ul>
                                        </div>
                        
                                        <div class="text-center">
                                            <p class="text-white opacity-75 mb-0 mt-3">
                                                {{ now()->year }} &copy; {{ $brand['company'] ?? 'Qudroid Systems' }} &middot; {{ $brand['name'] ?? 'Qlouds Workspace' }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!--end col-->
                            <div class="col-xxl-6 mx-auto">
                                <div class="card mb-0 border-0 shadow-none mb-0">
                                    <div class="card-body p-sm-5 m-lg-4">
                                        <div class="text-center">
                                            <h5 class="fs-3xl">Choose your password</h5>
                                            <p class="text-muted mb-4">
                                                @if($temporary)
                                                    You signed in with a temporary password. Choose your own to continue.
                                                @else
                                                    Enter your current password, then choose a new one.
                                                @endif
                                            </p>
                                        </div>

                                        @if (session('warning'))
                                            <div class="alert alert-warning border-0 text-center">{{ session('warning') }}</div>
                                        @endif

                                        <div class="p-2">
                                            <form method="POST" action="{{ route('password.change.update') }}">
                                                @csrf

                                                @unless($temporary)
                                                <div class="mb-3">
                                                    <label class="form-label" for="current-password">Current password</label>
                                                    <input type="password" class="form-control @error('current_password') is-invalid @enderror" name="current_password" id="current-password" autocomplete="current-password" required>
                                                    @error('current_password')
                                                        <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                                                    @enderror
                                                </div>
                                                @endunless

                                                <div class="mb-3">
                                                    <label class="form-label" for="password-input">New password</label>
                                                    <div class="position-relative auth-pass-inputgroup">
                                                        <input type="password" class="form-control pe-5 password-input @error('password') is-invalid @enderror" placeholder="Enter password" name="password" id="password-input" aria-describedby="passwordInput" autocomplete="new-password" required>
                                                        <button class="btn btn-link position-absolute end-0 top-0 text-decoration-none text-muted password-addon" type="button" id="password-addon"><i class="ri-eye-fill align-middle"></i></button>
                                                        @error('password')
                                                            <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                                                        @enderror
                                                    </div>
                                                    <div id="passwordInput" class="form-text">At least 8 characters, with letters and numbers.</div>
                                                </div>

                                                <div class="mb-3">
                                                    <label class="form-label" for="confirm-password-input">Confirm new password</label>
                                                    <div class="position-relative auth-pass-inputgroup mb-3">
                                                        <input type="password" class="form-control pe-5 password-input" placeholder="Confirm password" id="confirm-password-input" name="password_confirmation" required autocomplete="new-password">
                                                        <button class="btn btn-link position-absolute end-0 top-0 text-decoration-none text-muted password-addon" type="button"><i class="ri-eye-fill align-middle"></i></button>
                                                    </div>
                                                </div>

                                                <div class="mt-4">
                                                    <button class="btn btn-primary w-100" type="submit">Save password</button>
                                                </div>
                                            </form>
                                        </div>
                                        <div class="mt-4 text-center">
                                            <form method="POST" action="{{ route('logout') }}">
                                                @csrf
                                                <button type="submit" class="btn btn-link text-muted">Sign out</button>
                                            </form>
                                        </div>
                                    </div><!-- end card body -->
                                </div><!-- end card -->
                            </div>
                            <!--end col-->
                        </div>
                        <!--end row-->
                    </div>
                </div>
                <!--end col-->
            </div>
            <!--end row-->
        </div>
        <!--end container-->
    </section>

    <!-- JAVASCRIPT -->
    <script src="{{ asset('theme/layouts/assets/libs/bootstrap/js/bootstrap.bundle.min.js')}}"></script>
    <script src="{{ asset('theme/layouts/assets/libs/simplebar/simplebar.min.js')}}"></script>
    <script src="{{ asset('theme/layouts/assets/js/plugins.js')}}"></script>

    <script>
        document.querySelectorAll('.password-addon').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var input = btn.parentElement.querySelector('input');
                input.type = input.type === 'password' ? 'text' : 'password';
            });
        });
    </script>

</body>



</html>