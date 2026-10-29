{{--
    The public website's shell: global nav, optional ribbon, the page, and the footer.
    Pages set: title, description (optional), and content.
--}}
@php
    $site = app(\App\Services\Website\Site::class);
    $cfg = $site->config();
    $nav = $site->nav();
    $pageTitle = trim($__env->yieldContent('title'));
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $pageTitle ? $pageTitle . ' - ' . $cfg['name'] : $cfg['name'] . ' - ' . $cfg['tagline'] }}</title>
    <meta name="description" content="{{ trim($__env->yieldContent('description')) ?: $cfg['description'] }}">
    <meta property="og:title" content="{{ $pageTitle ?: $cfg['name'] }}">
    <meta property="og:description" content="{{ trim($__env->yieldContent('description')) ?: $cfg['description'] }}">
    <meta property="og:site_name" content="{{ $cfg['name'] }}">
    @hasSection('og_image')<meta property="og:image" content="@yield('og_image')">@endif
    <link rel="icon" href="{{ asset($brand['favicon'] ?? 'favicon.ico') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="{{ asset('theme/layouts/assets/css/icons.min.css') }}" rel="stylesheet">
    <link href="{{ asset('css/qlouds-logo.css') }}?v={{ @filemtime(public_path('css/qlouds-logo.css')) }}" rel="stylesheet">
    <link href="{{ asset('css/site.css') }}?v={{ @filemtime(public_path('css/site.css')) }}" rel="stylesheet">
    @stack('head')
</head>
<body class="site">
    <a href="#main" class="s-sr">Skip to content</a>
    <nav class="s-nav" id="sNav" aria-label="Main">
        <div class="s-wrap">
            <a href="{{ route('site.home') }}" class="s-brand" aria-label="{{ $cfg['name'] }} home"><x-qlouds-logo :name="$cfg['name']" size="19px" /></a>
            <ul>
                @foreach($nav as $link)
                    <li><a href="{{ $link['url'] }}" @class(['is-active' => ($link['active'] && request()->routeIs($link['active'])) || (isset($link['slug']) && request()->is($link['slug']))])>{{ $link['label'] }}</a></li>
                @endforeach
            </ul>
            <div class="s-nav-end">
                @auth
                    <a href="{{ route('dashboard') }}" title="Open the portal"><i class="ri-apps-2-line"></i> Portal</a>
                @else
                    <a href="{{ route('login') }}" title="Sign in"><i class="ri-user-line"></i> Sign in</a>
                @endauth
                <button class="s-burger" type="button" aria-label="Menu" aria-expanded="false" onclick="var n=document.getElementById('sNav');var o=n.classList.toggle('is-open');this.setAttribute('aria-expanded',o);this.innerHTML=o?'<i class=&quot;ri-close-line&quot;></i>':'<i class=&quot;ri-menu-line&quot;></i>'"><i class="ri-menu-line"></i></button>
            </div>
        </div>
    </nav>
    @if($cfg['announcement'])
        <div class="s-ribbon">{{ $cfg['announcement'] }} @if($cfg['announcement_url'])<a href="{{ $cfg['announcement_url'] }}" class="s-more">Learn more</a>@endif</div>
    @endif

    <main id="main">
        @yield('content')
    </main>

    <footer class="s-footer">
        <div class="s-wrap">
            @if($cfg['footer_note'])<div class="s-foot-note">{{ $cfg['footer_note'] }}</div>@endif
            <div class="s-foot-cols">
                @foreach($site->footer() as $heading => $links)
                    <div>
                        <h4>{{ $heading }}</h4>
                        <ul>@foreach($links as $l)<li><a href="{{ $l['url'] }}">{{ $l['label'] }}</a></li>@endforeach</ul>
                    </div>
                @endforeach
            </div>
            @if($cfg['email'] || $cfg['phone'] || $cfg['address'])
                <p style="margin:0 0 14px">
                    @if($cfg['email'])<a href="mailto:{{ $cfg['email'] }}">{{ $cfg['email'] }}</a>@endif
                    @if($cfg['phone']) · <a href="tel:{{ preg_replace('/[^0-9+]/', '', $cfg['phone']) }}">{{ $cfg['phone'] }}</a>@endif
                    @if($cfg['address']) · {{ $cfg['address'] }}@endif
                </p>
            @endif
            <div class="s-foot-base">
                <span>Copyright © {{ now()->year }} {{ $cfg['name'] }}. All rights reserved.@if($cfg['tagline']) <em class="s-motto">{{ $cfg['tagline'] }}</em>@endif</span>
                <span class="s-socials">
                    @foreach(\App\Services\Website\Site::SOCIALS as $key => $label)
                        @if(!empty($cfg['socials'][$key]))
                            <a href="{{ $cfg['socials'][$key] }}" target="_blank" rel="noopener" aria-label="{{ $label }}"><i class="ri-{{ $key === 'x' ? 'twitter-x' : $key }}-{{ in_array($key, ['linkedin', 'facebook']) ? 'box-' : '' }}fill"></i></a>
                        @endif
                    @endforeach
                </span>
            </div>
        </div>
    </footer>

    <script>
        // Sections ease in as they scroll into view.
        (function () {
            var els = document.querySelectorAll('.s-reveal');
            if (!('IntersectionObserver' in window)) { els.forEach(function (e) { e.classList.add('is-in'); }); return; }
            var io = new IntersectionObserver(function (entries) {
                entries.forEach(function (en) { if (en.isIntersecting) { en.target.classList.add('is-in'); io.unobserve(en.target); } });
            }, { rootMargin: '0px 0px -8% 0px' });
            els.forEach(function (e) { io.observe(e); });
        })();
    </script>
    @stack('scripts')
</body>
</html>
