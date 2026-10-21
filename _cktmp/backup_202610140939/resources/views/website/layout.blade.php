@php
    $s = fn ($k, $d = null) => (isset($site[$k]) && $site[$k] !== '') ? $site[$k] : $d;
    $title = trim(($pageTitle ?? '') ? ($pageTitle . ' — ' . $school->name) : ($school->name . ($school->motto ? ' | ' . $school->motto : '')));
    $about = $menu['about'] ?? [];
    $admissions = $menu['admissions'] ?? [];
    $academics = $menu['academics'] ?? [];
    $life = $menu['life'] ?? [];
    $socials = array_filter([
        'facebook' => $s('social_facebook'), 'instagram' => $s('social_instagram'), 'twitter-x' => $s('social_x'),
        'youtube' => $s('social_youtube'), 'tiktok' => $s('social_tiktok'),
    ]);
    $wa = preg_replace('/[^0-9]/', '', (string) $s('whatsapp', ''));
    if ($wa && str_starts_with($wa, '0')) $wa = '234' . substr($wa, 1);
    $cur = request()->path();
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    <meta name="description" content="{{ $metaDescription ?? $s('meta_description', $school->name) }}">
    <meta property="og:title" content="{{ $title }}">
    <meta property="og:description" content="{{ $metaDescription ?? $s('meta_description', $school->name) }}">
    @if(!empty($ogImage ?? null) || $school->logo)<meta property="og:image" content="{{ $ogImage ?? $school->logo }}">@endif
    <meta name="theme-color" content="{{ $s('primary_color', '#AE152D') }}">
    @if($school->logo)<link rel="icon" href="{{ $school->logo }}">@endif
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Source+Sans+3:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="{{ asset('website/remixicon/remixicon.css') }}" rel="stylesheet">
    <link href="{{ asset('website/site.css') }}?v={{ @filemtime(public_path('website/site.css')) }}" rel="stylesheet">
    <style>:root{--primary:{{ $s('primary_color', '#AE152D') }};--secondary:{{ $s('secondary_color', '#0B3D2E') }};--accent:{{ $s('accent_color', '#2E9E5B') }};}</style>
    @stack('head')
</head>
<body>
    <div class="topbar">
        <div class="container">
            <div class="social">
                @foreach($socials as $icon => $url)
                    <a href="{{ $url }}" target="_blank" rel="noopener" aria-label="{{ $icon }}"><i class="ri-{{ $icon }}-{{ $icon === 'twitter-x' ? 'line' : 'fill' }}"></i></a>
                @endforeach
                @if(!$socials && $school->phones)<span><i class="ri-phone-line"></i> {{ $school->phones[0] }}</span>@endif
            </div>
            <div class="quick">
                <a href="{{ route('site.admissions') }}#apply">Apply</a>
                <a href="{{ route('site.contact') }}#visit">Visit us</a>
                <a href="{{ route('site.news') }}">News &amp; updates</a>
                <a href="{{ route('site.gallery') }}">Gallery</a>
                <a href="{{ url('/login') }}" class="login"><i class="ri-user-3-line"></i> Portal login</a>
            </div>
        </div>
    </div>

    <header class="header">
        <div class="container">
            <a href="{{ route('site.home') }}" class="brand">
                @if($school->logo)<img src="{{ $school->logo }}" alt="{{ $school->name }} logo">@endif
                <span class="name">{{ $school->name }}@if($school->motto)<small>{{ $school->motto }}</small>@endif</span>
            </a>
            <button class="burger" aria-label="Open menu"><i class="ri-menu-3-line"></i></button>
            <nav class="nav" aria-label="Main">
                <button class="nav-close" aria-label="Close menu"><i class="ri-close-line"></i></button>
                <div class="item {{ $cur === '/' ? 'active' : '' }}"><a href="{{ route('site.home') }}">Home</a></div>
                <div class="item has-drop">
                    <a href="{{ isset($about[0]) ? route('site.page', $about[0]['slug']) : route('site.people') }}">About <i class="ri-arrow-down-s-line"></i></a>
                    <div class="drop">
                        @foreach($about as $p)<a href="{{ route('site.page', $p['slug']) }}">{{ $p['title'] }}</a>@endforeach
                        <a href="{{ route('site.people') }}">Our people</a>
                    </div>
                </div>
                <div class="item has-drop">
                    <a href="{{ route('site.admissions') }}">Admissions <i class="ri-arrow-down-s-line"></i></a>
                    <div class="drop">
                        <a href="{{ route('site.admissions') }}">Admissions overview</a>
                        @foreach($admissions as $p)<a href="{{ route('site.page', $p['slug']) }}">{{ $p['title'] }}</a>@endforeach
                        <a href="{{ route('site.admissions') }}#apply">Apply online</a>
                    </div>
                </div>
                <div class="item has-drop">
                    <a href="{{ route('site.academics') }}">Academics <i class="ri-arrow-down-s-line"></i></a>
                    <div class="drop">
                        <a href="{{ route('site.academics') }}">Classes &amp; subjects</a>
                        @foreach($academics as $p)<a href="{{ route('site.page', $p['slug']) }}">{{ $p['title'] }}</a>@endforeach
                        <a href="{{ route('site.calendar') }}">School calendar</a>
                    </div>
                </div>
                <div class="item has-drop">
                    <a href="{{ route('site.news') }}">School life <i class="ri-arrow-down-s-line"></i></a>
                    <div class="drop">
                        <a href="{{ route('site.news') }}">News &amp; updates</a>
                        <a href="{{ route('site.news', ['type' => 'event']) }}">Events</a>
                        <a href="{{ route('site.gallery') }}">Gallery</a>
                        @foreach($life as $p)<a href="{{ route('site.page', $p['slug']) }}">{{ $p['title'] }}</a>@endforeach
                    </div>
                </div>
                <div class="item {{ str_starts_with($cur, 'app') ? 'active' : '' }}"><a href="{{ route('site.app') }}">Mobile app</a></div>
                <div class="item {{ str_starts_with($cur, 'contact') ? 'active' : '' }}"><a href="{{ route('site.contact') }}">Contact</a></div>
                <div class="item cta"><a class="btn btn-primary btn-sm" href="{{ route('site.admissions') }}#apply" style="padding:11px 22px">Apply now</a></div>
            </nav>
        </div>
    </header>
    <div class="scrim"></div>

    <main>
        @yield('content')
    </main>

    <footer class="footer">
        <div class="container">
            <div class="cols">
                <div>
                    <a href="{{ route('site.home') }}" class="brand" style="margin-bottom:16px">
                        @if($school->logo)<img src="{{ $school->logo }}" alt="">@endif
                        <span class="name">{{ $school->name }}</span>
                    </a>
                    <p>{{ $s('footer_about', $school->motto) }}</p>
                    <div class="social" style="margin-top:16px">
                        @foreach($socials as $icon => $url)
                            <a href="{{ $url }}" target="_blank" rel="noopener" aria-label="{{ $icon }}"><i class="ri-{{ $icon }}-{{ $icon === 'twitter-x' ? 'line' : 'fill' }}"></i></a>
                        @endforeach
                    </div>
                </div>
                <div>
                    <h5>About</h5>
                    <ul>
                        @foreach($about as $p)<li><a href="{{ route('site.page', $p['slug']) }}">{{ $p['title'] }}</a></li>@endforeach
                        <li><a href="{{ route('site.people') }}">Our people</a></li>
                        <li><a href="{{ route('site.gallery') }}">Gallery</a></li>
                        <li><a href="{{ route('site.news') }}">News &amp; updates</a></li>
                    </ul>
                </div>
                <div>
                    <h5>Admissions &amp; academics</h5>
                    <ul>
                        <li><a href="{{ route('site.admissions') }}">Admissions</a></li>
                        @foreach(array_slice($admissions, 0, 3) as $p)<li><a href="{{ route('site.page', $p['slug']) }}">{{ $p['title'] }}</a></li>@endforeach
                        <li><a href="{{ route('site.academics') }}">Classes &amp; subjects</a></li>
                        <li><a href="{{ route('site.calendar') }}">School calendar</a></li>
                        <li><a href="{{ route('site.app') }}">Download our app</a></li>
                    </ul>
                </div>
                <div>
                    <h5>Get in touch</h5>
                    <ul>
                        @if($school->address)<li><i class="ri-map-pin-2-line"></i> {{ $school->address }}</li>@endif
                        @foreach($school->phones as $ph)<li><i class="ri-phone-line"></i> <a href="tel:{{ preg_replace('/\s+/', '', $ph) }}">{{ $ph }}</a></li>@endforeach
                        @if($school->email)<li><i class="ri-mail-line"></i> <a href="mailto:{{ $school->email }}">{{ $school->email }}</a></li>@endif
                    </ul>
                    <form class="news" method="POST" action="{{ route('site.newsletter') }}">@csrf
                        <input type="email" name="email" required placeholder="Your e-mail for our newsletter" aria-label="E-mail">
                        <input type="text" name="website" class="hp" tabindex="-1" autocomplete="off">
                        <button type="submit">Join</button>
                    </form>
                </div>
            </div>
            <div class="bottom">
                <span>© {{ date('Y') }} {{ $school->name }}. All rights reserved.</span>
                <span><a href="{{ url('/login') }}">Portal login</a> · <a href="{{ route('site.app') }}">Mobile app</a> · <a href="{{ route('site.contact') }}">Contact</a></span>
            </div>
        </div>
    </footer>

    @if($wa)<a class="float-wa" href="https://wa.me/{{ $wa }}" target="_blank" rel="noopener" aria-label="Chat on WhatsApp"><i class="ri-whatsapp-line"></i></a>@endif
    <button class="to-top" aria-label="Back to top"><i class="ri-arrow-up-line"></i></button>
    @if(session('site_success'))<div class="toast" role="status"><i class="ri-checkbox-circle-fill"></i> {{ session('site_success') }}</div>@endif
    @if($errors->any())<div class="toast" role="alert" style="background:#9b1c1c"><i class="ri-error-warning-fill"></i> {{ $errors->first() }}</div>@endif
    <script src="{{ asset('website/site.js') }}?v={{ @filemtime(public_path('website/site.js')) }}" defer></script>
    @stack('scripts')
</body>
</html>
