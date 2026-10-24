{{-- Public pages (no sign-in): the Apply pages. Same theme as the portal, no sidebar. --}}
<!doctype html>
<html lang="en" data-layout="vertical" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <title>@yield('title') | {{ $brand['company'] ?? 'Qudroid Systems' }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="@yield('description', ($brand['company'] ?? 'Qudroid Systems') . ' Academy')">
    <link rel="icon" href="{{ asset($brand['favicon']) }}">
    <link rel="preconnect" href="https://fonts.googleapis.com/">
    <link rel="preconnect" href="https://fonts.gstatic.com/" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&amp;display=swap" rel="stylesheet">
    <link href="{{ asset('theme/layouts/assets/css/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ asset('theme/layouts/assets/css/icons.min.css') }}" rel="stylesheet">
    <link href="{{ asset('theme/layouts/assets/css/app.min.css') }}" rel="stylesheet">
    <link href="{{ asset('css/cb-ui.css') }}?v={{ @filemtime(public_path('css/cb-ui.css')) }}" rel="stylesheet">
    <style>
        body { background: #f5f7fb; font-family: 'Poppins', sans-serif; }
        .guest-bar { background: #fff; border-bottom: 1px solid #e9ebf0; }
        .guest-bar img { height: 34px; }
        .guest-main { max-width: 960px; margin: 0 auto; padding: 32px 16px 64px; }
        .apply-course { border: 1px solid #e9ebf0; border-radius: 14px; background: #fff; }
        .apply-cohort { border-top: 1px solid #f0f1f5; }
        .hp-field { position: absolute; left: -10000px; width: 1px; height: 1px; overflow: hidden; }
    </style>
    <link href="{{ asset('css/qlouds-logo.css') }}?v={{ @filemtime(public_path('css/qlouds-logo.css')) }}" rel="stylesheet" type="text/css">
</head>
<body>
    @include('layouts.partials.app-loader')
    <header class="guest-bar">
        <div class="guest-main py-3 d-flex align-items-center justify-content-between" style="padding-bottom:12px">
            <a href="{{ route('apply.index') }}" class="text-decoration-none"><x-qlouds-logo size="24px" /></a>
            @auth
                <a href="{{ route('academy.my') }}" class="btn btn-sm btn-soft-primary">My academy</a>
            @else
                <a href="{{ route('login') }}" class="btn btn-sm btn-soft-primary">Sign in</a>
            @endauth
        </div>
    </header>
    <main class="guest-main">
        @yield('content')
    </main>
    <footer class="text-center text-muted small pb-4">{{ now()->year }} &copy; {{ $brand['company'] ?? 'Qudroid Systems' }} &middot; {{ $brand['name'] ?? 'Qlouds Workspace' }}</footer>
</body>
</html>
