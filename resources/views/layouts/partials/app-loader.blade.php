{{--
    Qlouds page loader, like an Apple device starting up: plain white (black in dark mode), the animated Qlouds
    logotype (as in the mobile app) at the centre, then a thin progress bar.

    The first page of a browser session gets the full start-up (about 1.8s). After that it shows whenever a page
    is loading: when you click a link or submit a form it comes up while the next page loads, and it fades in on a
    fresh load only if the page takes longer than a moment, so fast pages never flash it. Respects reduced motion
    and the portal's light / dark setting. Include it straight after <body>; add data-no-loader to a link or form
    that should not show it (downloads, for example).
--}}
<div id="ql-loader" role="status" aria-live="polite" aria-label="Loading {{ $brand['name'] ?? 'Qlouds Workspace' }}">
    <style>
        #ql-loader { --ql-bg: #ffffff; --ql-ink: #0F172A;
            position: fixed; inset: 0; z-index: 2147483000; background: var(--ql-bg);
            display: flex; align-items: center; justify-content: center;
            font-family: -apple-system, BlinkMacSystemFont, 'SF Pro Display', Inter, Poppins, 'Segoe UI', Arial, sans-serif;
            transition: opacity .35s cubic-bezier(.25,.1,.25,1), visibility .35s; }
        #ql-loader.ql-dark { --ql-bg: #000000; --ql-ink: #ffffff; }
        #ql-loader .qlogo { color: var(--ql-ink); }
        #ql-loader.ql-dark .qlogo { --qlogo-accent: #ffffff; --qlogo-ink: #ffffff; }
        /* A repeat load stays invisible for a moment, so a fast page never flashes the loader. */
        #ql-loader.ql-quick { animation: ql-fade .25s ease .3s both; }
        #ql-loader.ql-done { opacity: 0; visibility: hidden; animation: none; pointer-events: none; }
        #ql-loader .ql-lockup { display: flex; align-items: center; transition: transform .35s cubic-bezier(.25,.1,.25,1); }
        #ql-loader.ql-done .ql-lockup { transform: scale(.97); }
        #ql-loader .ql-bar { position: absolute; left: 50%; top: 66%; width: 110px; height: 3px; margin-left: -55px; border-radius: 2px;
            background: color-mix(in srgb, var(--ql-ink) 12%, transparent); overflow: hidden; animation: ql-fade .4s ease 1.1s both; }
        #ql-loader.ql-quick .ql-bar, #ql-loader.ql-quick .ql-foot { animation-delay: .5s; }
        #ql-loader .ql-bar::after { content: ''; position: absolute; top: 0; bottom: 0; left: -40%; width: 40%; border-radius: 2px;
            background: color-mix(in srgb, var(--ql-ink) 85%, transparent); animation: ql-slide 1.15s cubic-bezier(.4,0,.2,1) infinite; }
        #ql-loader .ql-foot { position: absolute; left: 0; right: 0; bottom: 24px; text-align: center; font-size: 12px;
            color: color-mix(in srgb, var(--ql-ink) 55%, transparent); animation: ql-fade .4s ease 1.1s both; }
        #ql-loader .ql-foot b { color: var(--ql-ink); font-weight: 600; }
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

            // The first page of a browser session gets the full start-up; later loads use the quick mode.
            var firstOfSession = true;
            try {
                firstOfSession = !sessionStorage.getItem('ql-started');
                sessionStorage.setItem('ql-started', '1');
            } catch (e) {}
            if (!firstOfSession) el.classList.add('ql-quick');

            // Match the portal's light / dark setting.
            try {
                var mode = localStorage.getItem('app-theme');
                var dark = mode === 'dark' || (mode === 'auto' && window.matchMedia && matchMedia('(prefers-color-scheme: dark)').matches)
                    || document.documentElement.getAttribute('data-bs-theme') === 'dark';
                if (dark) el.classList.add('ql-dark');
            } catch (e) {}

            var shownAt = Date.now(), loaded = false, safety;

            function hide() {
                clearTimeout(safety);
                // The start-up lets the logo finish its entrance; a quick load goes as soon as the page is ready.
                var wait = firstOfSession ? Math.max(0, 1800 - (Date.now() - shownAt)) : 0;
                setTimeout(function () { el.classList.add('ql-done'); }, wait);
            }

            // Bring the loader back while the next page loads, replaying the logo's entrance.
            function show() {
                firstOfSession = false;
                var lockup = el.querySelector('.ql-lockup');
                if (lockup) lockup.replaceWith(lockup.cloneNode(true));
                el.classList.remove('ql-done', 'ql-quick');
                void el.offsetWidth;
                el.classList.add('ql-quick');
                shownAt = Date.now();
                clearTimeout(safety);
                safety = setTimeout(hide, 10000); // e.g. a link that turned out to be a download
            }

            function onLoad() { loaded = true; hide(); }
            if (document.readyState === 'complete') onLoad(); else window.addEventListener('load', onLoad);
            safety = setTimeout(hide, 8000); // never hold the page hostage to a slow image or script

            // Coming back with the browser's back button shows the cached page: no loader.
            window.addEventListener('pageshow', function (e) { if (e.persisted) { clearTimeout(safety); el.classList.add('ql-done'); } });

            // Links to another page of the portal.
            document.addEventListener('click', function (e) {
                var a = e.target.closest && e.target.closest('a[href]');
                if (!a || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
                if (a.target && a.target !== '_self') return;
                if (a.hasAttribute('download') || a.hasAttribute('data-no-loader') || a.hasAttribute('data-bs-toggle')) return;
                var href = a.getAttribute('href');
                if (!href || href.charAt(0) === '#' || /^(javascript|mailto|tel):/i.test(href)) return;
                var url;
                try { url = new URL(a.href, location.href); } catch (err) { return; }
                if (url.origin !== location.origin) return;
                if (/(export|download|\.(pdf|csv|xlsx?|zip|docx?))(\/|\?|$)/i.test(url.pathname)) return; // files, not pages
                if (url.pathname === location.pathname && url.search === location.search && url.hash) return;
                // Let the page's own handlers decide first; a handled click (preventDefault) stays on this page.
                setTimeout(function () { if (!e.defaultPrevented) show(); }, 0);
            });

            // Ordinary form posts (AJAX forms call preventDefault, so they are left alone).
            document.addEventListener('submit', function (e) {
                var form = e.target;
                if (form.hasAttribute('data-no-loader') || (form.target && form.target !== '_self')) return;
                setTimeout(function () { if (!e.defaultPrevented) show(); }, 0);
            });
        })();
    </script>
</div>
