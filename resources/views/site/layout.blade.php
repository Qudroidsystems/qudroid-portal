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

    <button type="button" class="s-top" aria-label="Back to top"><i class="ri-arrow-up-line"></i></button>
    <script>
        (function () {
            var calm = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;

            // Sections and grids ease in as they scroll into view; numbers count up.
            var els = document.querySelectorAll('.s-reveal, .s-stagger, [data-count]');
            function show(e) {
                e.classList.add('is-in');
                if (e.hasAttribute('data-count')) count(e);
            }
            if (!('IntersectionObserver' in window)) { els.forEach(show); }
            else {
                var io = new IntersectionObserver(function (entries) {
                    entries.forEach(function (en) { if (en.isIntersecting) { show(en.target); io.unobserve(en.target); } });
                }, { rootMargin: '0px 0px -8% 0px' });
                els.forEach(function (e) { io.observe(e); });
            }
            function count(el) {
                var to = parseFloat(el.dataset.count), dec = (el.dataset.count.split('.')[1] || '').length;
                if (calm || !isFinite(to)) return;
                var t0 = null, dur = 1600;
                function fmt(v) { var s = v.toFixed(dec); return el.dataset.commas === '1' ? Number(s).toLocaleString('en', { minimumFractionDigits: dec }) : s; }
                function step(t) {
                    t0 = t0 || t; var k = Math.min(1, (t - t0) / dur), e = 1 - Math.pow(1 - k, 3);
                    el.textContent = el.dataset.prefix + fmt(to * e) + el.dataset.suffix;
                    if (k < 1) requestAnimationFrame(step);
                }
                requestAnimationFrame(step);
            }

            // Rotating words in the hero.
            document.querySelectorAll('.s-rotate').forEach(function (box) {
                var words = JSON.parse(box.dataset.words || '[]'), w = box.querySelector('.s-rotate-word'), i = 0;
                if (words.length < 2 || calm) return;
                setInterval(function () {
                    w.classList.add('is-out');
                    setTimeout(function () {
                        i = (i + 1) % words.length; w.textContent = words[i];
                        w.classList.remove('is-out'); w.classList.add('is-pre');
                        void w.offsetWidth; w.classList.remove('is-pre');
                    }, 350);
                }, 2600);
            });

            // A soft light follows the pointer over service cards.
            document.querySelectorAll('.s-feature').forEach(function (c) {
                c.addEventListener('pointermove', function (e) {
                    var r = c.getBoundingClientRect();
                    c.style.setProperty('--mx', (e.clientX - r.left) + 'px'); c.style.setProperty('--my', (e.clientY - r.top) + 'px');
                });
            });

            // Quote sliders: arrows, dots, and a gentle auto-advance.
            document.querySelectorAll('[data-slider]').forEach(function (sl) {
                var track = sl.querySelector('.s-slides'), slides = track.children, dots = sl.querySelectorAll('[data-dot]'), timer;
                function at() { return Math.round(track.scrollLeft / (slides[0].offsetWidth + 24)); }
                function go(i) { i = (i + slides.length) % slides.length; track.scrollTo({ left: slides[i].offsetLeft - track.offsetLeft, behavior: 'smooth' }); }
                function mark() { var i = at(); dots.forEach(function (d, j) { d.classList.toggle('is-on', j === i); }); }
                sl.querySelector('[data-prev]') && sl.querySelector('[data-prev]').addEventListener('click', function () { go(at() - 1); });
                sl.querySelector('[data-next]') && sl.querySelector('[data-next]').addEventListener('click', function () { go(at() + 1); });
                dots.forEach(function (d) { d.addEventListener('click', function () { go(+d.dataset.dot); }); });
                track.addEventListener('scroll', function () { window.requestAnimationFrame(mark); });
                mark();
                if (calm || slides.length < 2) return;
                function auto() { timer = setInterval(function () { var i = at(); go(track.scrollLeft + track.clientWidth >= track.scrollWidth - 4 ? 0 : i + 1); }, 6000); }
                sl.addEventListener('pointerenter', function () { clearInterval(timer); });
                sl.addEventListener('pointerleave', auto);
                auto();
            });

            // Back to top, with how far down the page you are.
            var top = document.querySelector('.s-top');
            function onScroll() {
                var h = document.documentElement, max = h.scrollHeight - h.clientHeight;
                top.style.setProperty('--p', max > 0 ? Math.round(h.scrollTop / max * 100) : 0);
                top.classList.toggle('is-on', h.scrollTop > 600);
            }
            window.addEventListener('scroll', onScroll, { passive: true }); onScroll();
            top.addEventListener('click', function () { window.scrollTo({ top: 0, behavior: calm ? 'auto' : 'smooth' }); });
        })();
    </script>
    @stack('scripts')
</body>
</html>
