{{--
    Qlouds start-up loader, like an Apple device starting up: plain white (black in dark mode), the Qlouds mark and
    name easing in at the centre, then a thin progress bar; it fades away once the page has loaded.

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
        <svg viewBox="100 100 335 303" aria-hidden="true"><path fill-rule="evenodd" d="M399.9 244.5 L399.4 237.1 L398.6 229.8 L397.5 222.5 L395.9 215.3 L394.1 208.2 L391.8 201.2 L389.3 194.3 L386.4 187.5 L383.1 180.9 L379.6 174.5 L375.7 168.2 L371.6 162.1 L367.1 156.3 L362.4 150.6 L357.4 145.2 L352.1 140.1 L346.6 135.2 L340.8 130.6 L334.9 126.3 L328.7 122.3 L322.3 118.6 L315.8 115.2 L309.1 112.1 L302.3 109.4 L295.3 107.0 L288.2 105.0 L281.1 103.3 L273.8 101.9 L266.5 100.9 L259.2 100.3 L251.8 100.0 L244.5 100.1 L237.1 100.6 L229.8 101.4 L222.5 102.5 L215.3 104.1 L208.2 105.9 L201.2 108.2 L194.3 110.7 L187.5 113.6 L180.9 116.9 L174.5 120.4 L168.2 124.3 L162.1 128.4 L156.3 132.9 L150.6 137.6 L145.2 142.6 L140.1 147.9 L135.2 153.4 L130.6 159.2 L126.3 165.1 L122.3 171.3 L118.6 177.7 L115.2 184.2 L112.1 190.9 L109.4 197.7 L107.0 204.7 L105.0 211.8 L103.3 218.9 L101.9 226.2 L100.9 233.5 L100.3 240.8 L100.0 248.2 L100.1 255.5 L100.6 262.9 L101.4 270.2 L102.5 277.5 L104.1 284.7 L105.9 291.8 L108.2 298.8 L110.7 305.7 L113.6 312.5 L116.9 319.1 L120.4 325.5 L124.3 331.8 L128.4 337.9 L132.9 343.7 L137.6 349.4 L142.6 354.8 L147.9 359.9 L153.4 364.8 L159.2 369.4 L165.1 373.7 L171.3 377.7 L177.7 381.4 L184.2 384.8 L190.9 387.9 L197.7 390.6 L204.7 393.0 L211.8 395.0 L218.9 396.7 L226.2 398.1 L233.5 399.1 L240.8 399.7 L248.2 400.0 L255.5 399.9 L266.5 399.1 L277.5 397.5 L286.4 395.5 L297.1 392.4 L305.7 389.3 L315.8 384.8 L325.5 379.6 L333.6 374.5 L327.2 369.6 L321.9 365.1 L315.0 359.0 L312.7 356.7 L309.6 352.7 L307.9 349.8 L305.9 345.2 L304.6 340.4 L304.0 335.4 L304.2 330.4 L305.1 325.5 L306.7 320.8 L309.0 316.3 L311.9 312.3 L315.3 308.7 L317.9 306.6 L320.7 304.7 L323.7 303.2 L326.8 301.9 L330.0 301.0 L333.2 300.3 L336.6 300.0 L341.6 300.2 L344.9 300.7 L348.1 301.5 L352.7 303.4 L357.1 305.9 L359.7 307.9 L369.6 316.4 L375.0 320.5 L380.3 324.3 L384.8 315.8 L389.3 305.7 L393.0 295.3 L395.5 286.4 L397.5 277.5 L399.1 266.5 L399.9 255.5 Z M308.9 213.0 L316.1 214.4 L322.1 216.5 L327.8 219.5 L332.9 223.3 L336.7 227.1 L340.6 232.1 L343.8 237.6 L346.1 243.6 L347.1 247.7 L347.6 250.9 L347.9 257.3 L347.3 263.6 L346.7 266.7 L345.7 269.9 L346.9 272.6 L347.7 275.8 L348.0 279.1 L347.7 282.5 L347.3 284.4 L346.4 287.0 L345.5 288.8 L344.1 291.1 L342.4 293.1 L341.0 294.5 L338.9 296.2 L336.5 297.5 L334.1 298.5 L332.1 299.1 L330.2 299.4 L327.5 299.6 L171.2 299.6 L168.5 299.2 L165.9 298.5 L163.5 297.5 L161.1 296.2 L159.0 294.5 L157.1 292.6 L155.5 290.5 L154.1 288.2 L152.7 284.4 L152.1 280.5 L152.1 277.8 L152.4 275.1 L153.1 272.6 L153.9 270.6 L153.0 267.4 L152.3 263.5 L152.1 259.7 L152.1 255.8 L152.8 250.9 L154.0 246.2 L155.8 241.7 L157.7 238.3 L160.4 234.3 L163.7 230.6 L167.3 227.4 L171.3 224.6 L175.6 222.4 L180.2 220.7 L184.9 219.6 L190.5 219.0 L190.8 215.6 L191.4 211.3 L192.3 207.0 L193.5 202.8 L195.0 198.7 L196.8 194.7 L200.5 188.4 L204.9 182.6 L207.9 179.4 L211.1 176.4 L214.5 173.7 L218.2 171.2 L224.6 167.7 L229.9 165.4 L237.0 163.4 L244.2 162.2 L251.5 162.0 L258.7 162.6 L265.9 164.1 L271.4 165.9 L278.1 169.0 L284.3 172.8 L288.9 176.4 L294.1 181.5 L298.7 187.2 L301.8 192.1 L304.4 197.4 L306.0 201.4 L307.4 205.6 Z M324.0 352.4 L332.2 359.4 L341.0 366.3 L350.4 372.9 L360.3 379.2 L370.7 385.3 L381.9 391.2 L393.3 396.8 L404.9 401.9 L409.3 402.8 L413.8 402.9 L418.2 402.1 L422.4 400.5 L426.2 398.1 L429.5 395.0 L432.1 391.3 L433.9 387.1 L434.7 383.8 L435.0 380.5 L434.8 377.1 L434.1 373.8 L433.0 370.6 L431.4 367.6 L429.4 364.9 L427.0 362.5 L425.2 361.1 L422.3 359.4 L412.7 355.1 L402.6 350.2 L393.2 345.2 L384.3 340.0 L376.1 334.7 L367.0 328.3 L360.0 322.7 L352.7 316.3 L350.9 315.0 L348.0 313.3 L344.8 312.0 L341.5 311.3 L337.0 311.0 L333.7 311.4 L329.3 312.7 L325.3 314.8 L322.7 316.9 L319.6 320.2 L317.8 323.0 L316.0 327.2 L315.3 330.5 L315.0 333.8 L315.4 338.3 L316.3 341.6 L318.2 345.7 L320.9 349.3 Z "/></svg>
        <span class="ql-name">{{ $qlFirst }}</span>
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
