{{--
    Qlouds start-up loader, like an Apple device starting up: plain white (black in dark mode), the animated Qlouds
    logotype (as in the mobile app) at the centre, then a thin progress bar; it fades away once the page has loaded.

    Shown once per browser session (the first page opened), not on every click. Respects reduced motion and the
    portal's light / dark setting. Include it straight after <body>.
--}}
@php
    $qlFirst = explode(' ', $brand['name'] ?? 'Qlouds Workspace')[0];
@endphp
<div id="ql-loader" role="status" aria-live="polite" aria-label="Loading {{ $brand['name'] ?? 'Qlouds Workspace' }}">
    <style>
        #ql-loader { --ql-bg: #ffffff; --ql-ink: #0F172A; --ql-mark: #0EA5E9;
            position: fixed; inset: 0; z-index: 2147483000; background: var(--ql-bg);
            display: flex; align-items: center; justify-content: center;
            font-family: -apple-system, BlinkMacSystemFont, 'SF Pro Display', Inter, Poppins, 'Segoe UI', Arial, sans-serif;
            transition: opacity .5s cubic-bezier(.25,.1,.25,1), visibility .5s; }
        #ql-loader.ql-dark { --ql-bg: #000000; --ql-ink: #ffffff; --ql-mark: #ffffff; }
        #ql-loader .qlogo { color: var(--ql-ink); }
        #ql-loader.ql-dark .qlogo { --qlogo-accent: #ffffff; --qlogo-ink: #ffffff; }
        #ql-loader.ql-done { opacity: 0; visibility: hidden; }
        #ql-loader .ql-lockup { display: flex; align-items: center; gap: 12px; transition: transform .5s cubic-bezier(.25,.1,.25,1); }
        #ql-loader.ql-done .ql-lockup { transform: scale(.97); }
        #ql-loader svg  { width: 46px; height: 46px; fill: var(--ql-mark); animation: ql-mark .72s cubic-bezier(.25,.1,.25,1) both; }
        #ql-loader .ql-name { color: var(--ql-ink); font-size: 30px; font-weight: 700; line-height: 1;
            animation: ql-name .8s cubic-bezier(.25,.1,.25,1) .4s both; }
        #ql-loader .ql-bar { position: absolute; left: 50%; top: 66%; width: 110px; height: 3px; margin-left: -55px; border-radius: 2px;
            background: color-mix(in srgb, var(--ql-ink) 12%, transparent); overflow: hidden; animation: ql-fade .4s ease 1.1s both; }
        #ql-loader .ql-bar::after { content: ''; position: absolute; top: 0; bottom: 0; left: -40%; width: 40%; border-radius: 2px;
            background: color-mix(in srgb, var(--ql-ink) 85%, transparent); animation: ql-slide 1.15s cubic-bezier(.4,0,.2,1) infinite; }
        #ql-loader .ql-foot { position: absolute; left: 0; right: 0; bottom: 24px; text-align: center; font-size: 12px;
            color: color-mix(in srgb, var(--ql-ink) 55%, transparent); animation: ql-fade .4s ease 1.1s both; }
        #ql-loader .ql-foot b { color: var(--ql-ink); font-weight: 600; }
        @keyframes ql-mark  { from { opacity: 0; transform: scale(.86); } to { opacity: 1; transform: none; } }
        @keyframes ql-name  { from { opacity: 0; transform: translateX(8px); letter-spacing: 2.2px; } to { opacity: 1; transform: none; letter-spacing: -.8px; } }
        @keyframes ql-fade  { from { opacity: 0; } to { opacity: 1; } }
        @keyframes ql-slide { from { left: -40%; } to { left: 100%; } }
        @media (prefers-reduced-motion: reduce) {
            #ql-loader *, #ql-loader *::after { animation-duration: 1ms !important; animation-delay: 0s !important; animation-iteration-count: 1 !important; }
        }
    </style>
    <div class="ql-lockup">
        <x-qlouds-logo animate size="46px" :rest="false" />
    </div>
    <div class="ql-bar"></div>
    <div class="ql-foot">A <b>{{ $brand['company'] ?? 'Qudroid Systems' }}</b> product</div>
    <script>
        (function () {
            var el = document.getElementById('ql-loader');
            if (!el) return;
            // Only the first page of a browser session (start-up); later pages open straight away.
            try {
                if (sessionStorage.getItem('ql-started')) { el.remove(); return; }
                sessionStorage.setItem('ql-started', '1');
            } catch (e) {}
            // Match the portal's light / dark setting.
            try {
                var mode = localStorage.getItem('app-theme');
                var dark = mode === 'dark' || (mode === 'auto' && window.matchMedia && matchMedia('(prefers-color-scheme: dark)').matches)
                    || document.documentElement.getAttribute('data-bs-theme') === 'dark';
                if (dark) el.classList.add('ql-dark');
            } catch (e) {}
            var shownAt = Date.now(), gone = false;
            function hide() {
                if (gone) return; gone = true;
                // Let the mark and name finish easing in before it fades away.
                setTimeout(function () {
                    el.classList.add('ql-done');
                    setTimeout(function () { el.remove(); }, 550);
                }, Math.max(0, 1800 - (Date.now() - shownAt)));
            }
            if (document.readyState === 'complete') hide(); else window.addEventListener('load', hide);
            setTimeout(hide, 8000); // never hold the page hostage to a slow image or script
        })();
    </script>
</div>
