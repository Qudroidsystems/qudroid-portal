{{--
    Shared shell for the sign-in, sign-up and password pages: the dark panel with the orbiting
    module icons on the left, the animated Qlouds logo and the page's form on the right.
    Pages set: title, heading, subheading, content, and optionally footer and scripts.
--}}
@php($authName = $brand['name'] ?? 'Qlouds Workspace')
<!doctype html>
<html lang="en" data-layout="vertical" data-sidebar="dark" data-sidebar-size="lg" data-preloader="disable" data-theme="default" data-topbar="light" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <title>@yield('title') | {{ $authName }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta content="{{ $brand['company'] ?? 'Qudroid Systems' }} portal" name="description">
    <meta content="{{ $brand['company'] ?? 'Qudroid Systems' }}" name="author">
    <link rel="shortcut icon" href="{{ asset($brand['favicon']) }}">
    <link rel="icon" href="{{ asset($brand['favicon']) }}">
    <link rel="apple-touch-icon" href="{{ asset($brand['logo_small']) }}">
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
    <!-- Qlouds logo -->
    <link href="{{ asset('css/qlouds-logo.css') }}?v={{ @filemtime(public_path('css/qlouds-logo.css')) }}" rel="stylesheet" type="text/css">

    <style>
        /* =====================================================
           APPLE OS STYLE AUTH PAGES (sign in, sign up, passwords)
           ===================================================== */

        /* Smooth page entrance animation */
        @keyframes pageFadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeInScale {
            from {
                opacity: 0;
                transform: scale(0.95);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        @keyframes slideInLeft {
            from {
                opacity: 0;
                transform: translateX(-40px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(40px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-4px); }
            20%, 40%, 60%, 80% { transform: translateX(4px); }
        }

        @keyframes fieldPulse {
            0%, 100% { border-color: #e2e8f0; box-shadow: 0 0 0 0 rgba(79, 142, 247, 0); }
            50% { border-color: #4f8ef7; box-shadow: 0 0 0 4px rgba(79, 142, 247, 0.15); }
        }

        @keyframes successCheck {
            0% { transform: scale(0); opacity: 0; }
            50% { transform: scale(1.2); }
            100% { transform: scale(1); opacity: 1; }
        }

        @keyframes orbitClockwise {
            from { transform: rotate(0deg) translate(120px, 0) rotate(0deg); }
            to { transform: rotate(360deg) translate(120px, 0) rotate(-360deg); }
        }

        @keyframes orbitCounterClockwise {
            from { transform: rotate(0deg) translate(120px, 0) rotate(0deg); }
            to { transform: rotate(-360deg) translate(120px, 0) rotate(360deg); }
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.05); opacity: 0.9; }
        }

        @keyframes fadeOut {
            to { opacity: 0; transform: translateX(20px); }
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Page container animation */
        .auth-page-wrapper {
            animation: pageFadeInUp 0.6s cubic-bezier(0.2, 0.9, 0.4, 1.1) forwards;
        }

        /* Card entrance animation */
        .card {
            animation: fadeInScale 0.5s cubic-bezier(0.2, 0.9, 0.4, 1.1) forwards;
            border-radius: 28px !important;
            overflow: hidden;
            backdrop-filter: blur(20px);
            background: rgba(255, 255, 255, 0.98);
        }

        /* Left panel animation */
        .auth-card {
            animation: slideInLeft 0.6s cubic-bezier(0.2, 0.9, 0.4, 1.1) forwards;
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%) !important;
            border-radius: 0 !important;
        }

        /* Right panel animation */
        .col-xxl-6 {
            animation: slideInRight 0.6s cubic-bezier(0.2, 0.9, 0.4, 1.1) forwards;
            animation-delay: 0.1s;
            opacity: 0;
            animation-fill-mode: forwards;
        }

        /* Apple-style input fields */
        .apple-input {
            border-radius: 12px !important;
            border: 1.5px solid #e2e8f0 !important;
            background: #f8fafc !important;
            transition: all 0.25s cubic-bezier(0.2, 0.9, 0.4, 1.1) !important;
            font-size: 16px !important;
            padding: 12px 16px !important;
        }

        .apple-input:focus {
            border-color: #4f8ef7 !important;
            background: #ffffff !important;
            box-shadow: 0 0 0 4px rgba(79, 142, 247, 0.1), 0 2px 8px rgba(0, 0, 0, 0.05) !important;
            outline: none !important;
        }

        .apple-input:hover {
            border-color: #cbd5e1 !important;
            background: #ffffff !important;
        }

        /* Apple-style button */
        .apple-button {
            background: #4f8ef7 !important;
            border: none !important;
            border-radius: 12px !important;
            padding: 14px 20px !important;
            font-weight: 600 !important;
            font-size: 16px !important;
            transition: all 0.25s cubic-bezier(0.2, 0.9, 0.4, 1.1) !important;
            position: relative;
            overflow: hidden;
        }

        .apple-button:hover {
            background: #3b7ae3 !important;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(79, 142, 247, 0.35);
        }

        .apple-button:active {
            transform: translateY(1px);
        }

        /* Shake animation for error */
        .shake-field {
            animation: shake 0.4s cubic-bezier(0.36, 0.07, 0.19, 0.97) both;
        }

        /* Field pulse on focus */
        .field-pulse {
            animation: fieldPulse 0.6s ease;
        }

        /* Label styling */
        .form-label {
            transition: all 0.2s ease;
            font-weight: 500;
            font-size: 14px;
            margin-bottom: 6px;
        }

        /* Checkbox styling */
        .form-check-input {
            border-radius: 6px !important;
            border: 1.5px solid #cbd5e1 !important;
            transition: all 0.2s ease;
        }

        .form-check-input:checked {
            background-color: #4f8ef7 !important;
            border-color: #4f8ef7 !important;
        }

        /* Error message styling */
        .invalid-feedback {
            font-size: 12px;
            margin-top: 6px;
            animation: slideInRight 0.3s ease;
        }

        /* Password toggle button */
        .password-addon {
            border-radius: 0 12px 12px 0 !important;
            padding: 0 16px !important;
            transition: opacity 0.2s ease;
        }

        .password-addon:hover {
            opacity: 0.7;
        }

        /* Loading state on button */
        .apple-button.loading {
            pointer-events: none;
            opacity: 0.7;
        }

        .apple-button.loading::after {
            content: '';
            position: absolute;
            width: 18px;
            height: 18px;
            top: 50%;
            left: 50%;
            margin-left: -9px;
            margin-top: -9px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
        }

        .apple-button.loading span {
            opacity: 0;
        }

        /* Effect circles */
        .effect-circle-1,
        .effect-circle-2,
        .effect-circle-3 {
            transition: all 0.3s ease;
        }

        .effect-circle-1 {
            animation: pulse 2s infinite;
        }

        /* Auth effect main container */
        .auth-effect-main {
            position: relative;
            width: 300px;
            height: 300px;
            margin: 0 auto;
        }

        /* Avatar orbit animations */
        .auth-user-list {
            position: absolute;
            width: 100%;
            height: 100%;
            list-style: none;
            padding: 0;
            margin: 0;
            top: 0;
            left: 0;
        }

        .auth-user-list li {
            position: absolute;
            width: 50px;
            height: 50px;
            transform-origin: center center;
            cursor: pointer;
            transition: transform 0.3s ease;
        }

        .auth-user-list li:nth-child(1) {
            animation: orbitClockwise 12s linear infinite;
            transform: translate(120px, 0);
        }

        .auth-user-list li:nth-child(2) {
            animation: orbitCounterClockwise 14s linear infinite;
            transform: rotate(72deg) translate(115px, 0);
        }

        .auth-user-list li:nth-child(3) {
            animation: orbitClockwise 10s linear infinite;
            transform: rotate(144deg) translate(125px, 0);
        }

        .auth-user-list li:nth-child(4) {
            animation: orbitCounterClockwise 11s linear infinite;
            transform: rotate(216deg) translate(118px, 0);
        }

        .auth-user-list li:nth-child(5) {
            animation: orbitClockwise 13s linear infinite;
            transform: rotate(288deg) translate(122px, 0);
        }

        .auth-user-list li:hover {
            animation-play-state: paused !important;
            transform: scale(1.2) !important;
            z-index: 10;
        }

        .auth-user-list li:hover .avatar-title {
            box-shadow: 0 0 15px rgba(255, 255, 255, 0.7);
        }

        /* Avatar styling */
        .avatar-sm {
            width: 50px;
            height: 50px;
        }

        .avatar-title {
            width: 100%;
            height: 100%;
            overflow: hidden;
            border: 2px solid white;
            transition: box-shadow 0.3s ease;
            border-radius: 50%;
        }

        .avatar-title img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        /* Tooltip styling */
        .avatar-tooltip {
            background-color: #1e293b;
            color: #fff;
            border-radius: 8px;
            padding: 4px 10px;
            font-size: 12px;
            font-weight: 500;
        }

        /* Logo styling */
        .brand-login-logo {
            height: 55px;
            width: auto;
            border-radius: 14px;
            object-fit: contain;
            transition: transform 0.3s ease;
        }

        .brand-login-logo:hover {
            transform: scale(1.02);
        }

        /* Logo container animation */
        .logo-container {
            text-align: center;
            margin-bottom: 20px;
            animation: fadeInScale 0.5s ease;
        }

        /* No staff message styling */
        .no-staff-message {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            text-align: center;
            color: rgba(255, 255, 255, 0.7);
        }

        .no-staff-icon {
            font-size: 48px;
            margin-bottom: 10px;
            animation: fadeInScale 0.5s ease;
        }

        /* Toast notification */
        .login-success {
            position: fixed;
            top: 24px;
            right: 24px;
            color: white;
            padding: 16px 20px;
            border-radius: 14px;
            font-size: 14px;
            font-weight: 500;
            z-index: 9999;
            animation: successCheck 0.4s cubic-bezier(0.34, 1.3, 0.64, 1);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.18);
            display: flex;
            align-items: flex-start;
            gap: 12px;
            max-width: 420px;
            min-width: 300px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            cursor: pointer;
        }

        .login-success.success { background: linear-gradient(135deg, #10b981, #059669); }
        .login-success.error { background: linear-gradient(135deg, #ef4444, #dc2626); }
        .login-success.info { background: linear-gradient(135deg, #4f8ef7, #3b7ae3); }
        .login-success.warning { background: linear-gradient(135deg, #f59e0b, #d97706); }

        .login-success .toast-icon {
            font-size: 22px;
            flex-shrink: 0;
            margin-top: 2px;
        }

        .login-success .toast-content {
            flex: 1;
        }

        .login-success .toast-title {
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 4px;
        }

        .login-success .toast-message {
            font-size: 12px;
            opacity: 0.92;
            line-height: 1.4;
        }

        /* Gold border highlight for session expired */
        .session-expired-highlight {
            border-left: 4px solid #f59e0b !important;
            transition: all 0.5s ease;
        }

        .session-expired-highlight-remove {
            border-left: 4px solid transparent !important;
            transition: all 0.5s ease;
        }

        /* Responsive adjustments */
        @media (max-width: 576px) {
            .auth-effect-main { width: 200px; height: 200px; }
            .auth-user-list li { width: 40px; height: 40px; }
            .auth-user-list li:nth-child(1) { transform: translate(80px, 0); }
            .auth-user-list li:nth-child(2) { transform: rotate(72deg) translate(78px, 0); }
            .auth-user-list li:nth-child(3) { transform: rotate(144deg) translate(82px, 0); }
            .auth-user-list li:nth-child(4) { transform: rotate(216deg) translate(79px, 0); }
            .auth-user-list li:nth-child(5) { transform: rotate(288deg) translate(81px, 0); }
            @keyframes orbitClockwise {
                from { transform: rotate(0deg) translate(80px, 0) rotate(0deg); }
                to { transform: rotate(360deg) translate(80px, 0) rotate(-360deg); }
            }
            @keyframes orbitCounterClockwise {
                from { transform: rotate(0deg) translate(80px, 0) rotate(0deg); }
                to { transform: rotate(-360deg) translate(80px, 0) rotate(360deg); }
            }
            .brand-login-logo { height: 40px; }
            .login-success { top: 12px; right: 12px; left: 12px; max-width: none; min-width: auto; }
        }

        /* Password rules checklist (sign-up and new password pages) */
        #password-contain.password-strength-container { display: block; transition: all 0.3s ease; border-radius: 12px; } /* the theme hides it by default */
        #password-contain p::before { display: none !important; } /* the theme's own ✔/✖; ours are the icons */
        .password-requirement { transition: all 0.2s ease; display: flex; align-items: center; gap: 8px; font-size: 13px; }
        .password-requirement.valid, .password-requirement.valid i { color: #10b981; }
        .password-requirement.invalid { color: #94a3b8; }
        .password-requirement i { font-size: 14px; transition: all 0.2s ease; }

        /* Icon badge above a heading (forgot password) */
        .auth-badge { width: 56px; height: 56px; border-radius: 16px; display: inline-flex; align-items: center; justify-content: center;
            font-size: 26px; color: #4f8ef7; background: rgba(79, 142, 247, 0.1); margin-bottom: 12px; animation: fadeInScale 0.5s ease; }
    </style>
    @stack('styles')
</head>

<body>
    @include('layouts.partials.app-loader')

    <section class="auth-page-wrapper position-relative d-flex align-items-center justify-content-center min-vh-100 py-4">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-11">
                    <div class="card mb-0 border-0 shadow-lg">
                        <div class="row g-0 align-items-center">
                            <div class="col-xxl-5">
                                <div class="card auth-card bg-secondary h-100 border-0 shadow-none d-none d-sm-block mb-0">
                                    <div class="card-body py-5 d-flex justify-content-between flex-column">
                                        <div class="text-center">
                                            <h3 class="text-white" style="animation: fadeInScale 0.6s ease;">Welcome to {{ $authName }}</h3>
                                            <p class="text-white opacity-75 fs-base">{{ $brand['tagline'] ?? '' }}</p>
                                        </div>

                                        <div class="auth-effect-main my-5 position-relative rounded-circle d-flex align-items-center justify-content-center mx-auto">
                                            <div class="effect-circle-1 position-relative mx-auto rounded-circle d-flex align-items-center justify-content-center" style="animation: pulse 2s infinite;">
                                                <div class="effect-circle-2 position-relative mx-auto rounded-circle d-flex align-items-center justify-content-center">
                                                    <div class="effect-circle-3 mx-auto rounded-circle position-relative text-white fs-4xl d-flex align-items-center justify-content-center" style="background: rgba(255,255,255,0.1); backdrop-filter: blur(4px);">
                                                        <span class="text-primary ms-1" style="font-weight: 600;">{{ $authName }}</span>
                                                    </div>
                                                </div>
                                            </div>

                                            <ul class="auth-user-list list-unstyled">
                                                @foreach(collect(config('qudroid.modules'))->take(5)->values() as $index => $mod)
                                                    <li style="animation-delay: {{ $index * 0.2 }}s;">
                                                        <span class="avatar-sm d-inline-block" data-bs-toggle="tooltip" data-bs-placement="top" title="{{ $mod['name'] }}">
                                                            <span class="avatar-title bg-white shadow-lg rounded-circle fs-4" style="color: {{ $mod['color'] ?? '#405189' }};">
                                                                <i class="{{ $mod['icon'] }}"></i>
                                                            </span>
                                                        </span>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        </div>

                                        <div class="text-center">
                                            <p class="text-white opacity-75 mb-0 mt-3">
                                                {{ now()->year }} &copy; {{ $brand['company'] ?? 'Qudroid Systems' }} &middot; {{ $authName }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!--end col-->

                            <div class="col-xxl-6 mx-auto">
                                <div class="card mb-0 border-0 shadow-none mb-0" style="background: transparent;">
                                    <div class="card-body p-sm-5 m-lg-4" id="authCardBody">
                                        <div class="logo-container">
                                            <x-qlouds-logo animate size="52px" :rest="false" />
                                        </div>

                                        <div class="text-center mt-2">
                                            @yield('badge')
                                            <h5 class="fs-2xl fw-semibold" style="animation: fadeInScale 0.5s ease;">@yield('heading')</h5>
                                            <p class="text-muted">@yield('subheading')</p>
                                        </div>

                                        <div class="p-2 mt-3">
                                            @yield('content')
                                        </div>

                                        @hasSection('footer')
                                            <div class="text-center mt-4">
                                                @yield('footer')
                                            </div>
                                        @endif
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
        // Toast in the top corner: showToast(title, message, 'success' | 'error' | 'info' | 'warning')
        function showToast(title, message, type = 'info') {
            document.querySelectorAll('.login-success').forEach(t => t.remove());

            const icons = {
                success: 'ri-checkbox-circle-line',
                error: 'ri-close-circle-line',
                info: 'ri-information-line',
                warning: 'ri-alert-line'
            };
            const toast = document.createElement('div');
            toast.className = 'login-success ' + type;
            toast.innerHTML = `
                <i class="${icons[type] || icons.info} toast-icon"></i>
                <div class="toast-content">
                    <div class="toast-title">${title}</div>
                    <div class="toast-message">${message}</div>
                </div>
            `;
            document.body.appendChild(toast);

            const dismiss = (ms) => {
                toast.style.animation = `fadeOut ${ms / 1000}s ease forwards`;
                setTimeout(() => toast.remove(), ms);
            };
            setTimeout(() => dismiss(400), 5000);
            toast.addEventListener('click', () => dismiss(300));
        }

        // Shake a field that has an error, with a short buzz on phones.
        function shakeElement(element) {
            if (!element) return;
            element.classList.add('shake-field');
            if (window.navigator && window.navigator.vibrate) window.navigator.vibrate(100);
            setTimeout(() => element.classList.remove('shake-field'), 400);
        }

        // Show or clear a message under a field.
        function fieldError(input, message) {
            if (!input) return;
            let errorDiv = input.parentElement.querySelector('.invalid-feedback');
            if (!message) {
                input.classList.remove('is-invalid');
                if (errorDiv) errorDiv.remove();
                return;
            }
            input.classList.add('is-invalid');
            if (!errorDiv) {
                errorDiv = document.createElement('span');
                errorDiv.className = 'invalid-feedback';
                errorDiv.setAttribute('role', 'alert');
                input.parentElement.appendChild(errorDiv);
            }
            errorDiv.innerHTML = '<strong>' + message + '</strong>';
        }

        // Ticks the password rules list, if the page has one. Returns true when every rule passes.
        function checkPasswordStrength(password) {
            const rules = {
                'pass-length': password.length >= 8,
                'pass-lower': /[a-z]/.test(password),
                'pass-upper': /[A-Z]/.test(password),
                'pass-number': /[0-9]/.test(password),
            };
            Object.entries(rules).forEach(([id, ok]) => {
                const el = document.getElementById(id);
                if (!el) return;
                el.classList.toggle('valid', ok);
                el.classList.toggle('invalid', !ok);
                const icon = el.querySelector('i');
                if (icon) icon.className = ok ? 'ri-checkbox-circle-fill' : 'ri-close-circle-fill';
            });
            return Object.values(rules).every(Boolean);
        }

        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
                new bootstrap.Tooltip(el, {
                    template: '<div class="tooltip avatar-tooltip" role="tooltip"><div class="tooltip-inner"></div></div>'
                });
            });

            // Pause an orbiting icon while it is hovered.
            document.querySelectorAll('.auth-user-list li').forEach(function (item) {
                item.addEventListener('mouseenter', () => item.style.animationPlayState = 'paused');
                item.addEventListener('mouseleave', () => item.style.animationPlayState = 'running');
            });

            // Focus pulse on every field.
            document.querySelectorAll('.apple-input').forEach(function (input) {
                input.addEventListener('focus', function () {
                    this.classList.add('field-pulse');
                    setTimeout(() => this.classList.remove('field-pulse'), 600);
                });
            });

            // Show / hide password buttons.
            document.querySelectorAll('.password-addon').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    const input = btn.parentElement.querySelector('input');
                    if (!input) return;
                    input.type = input.type === 'password' ? 'text' : 'password';
                    const icon = btn.querySelector('i');
                    if (icon) icon.className = input.type === 'password' ? 'ri-eye-fill align-middle' : 'ri-eye-off-fill align-middle';
                });
            });

            // Live password rules.
            const newPassword = document.getElementById('password-input');
            if (newPassword && document.getElementById('password-contain')) {
                newPassword.addEventListener('input', () => checkPasswordStrength(newPassword.value));
                if (newPassword.value) checkPasswordStrength(newPassword.value);
            }

            // Spinner on the submit button, unless the page's own checks stopped the submit.
            document.querySelectorAll('form[data-auth-form]').forEach(function (form) {
                form.addEventListener('submit', function (e) {
                    setTimeout(function () {
                        if (e.defaultPrevented) return;
                        const button = form.querySelector('.apple-button');
                        if (!button) return;
                        button.classList.add('loading');
                        const label = button.querySelector('span');
                        if (label && button.dataset.loadingText) label.textContent = button.dataset.loadingText;
                        button.disabled = true;
                    }, 0);
                });
            });

            @if(session('status'))
                showToast('Done', @json(session('status')), 'success');
            @endif
        });
    </script>
    @stack('scripts')
</body>
</html>
