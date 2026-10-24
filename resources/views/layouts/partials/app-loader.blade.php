{{--
    Qlouds start-up loader, like Gmail's: the logo draws itself in (the Q springs in, the cloud rises into it, the
    orange tail sweeps on and the green light pops, then pulses) with the name and a slim loading bar, and fades
    away once the page has loaded.

    Shown once per browser session (the first page opened), not on every click. Respects reduced motion and the
    portal's light / dark setting. Include it straight after <body>.
--}}
@php
    $qlName  = $brand['name'] ?? 'Qlouds Workspace';
    $qlWords = explode(' ', $qlName, 2);
@endphp
<div id="ql-loader" role="status" aria-live="polite" aria-label="Loading {{ $qlName }}">
    <style>
        #ql-loader { --ql-bg: #ffffff; --ql-word: #0C4A6E; --ql-sub: #F97316; --ql-track: #E0F2FE;
            position: fixed; inset: 0; z-index: 2147483000; display: flex; flex-direction: column; align-items: center; justify-content: center;
            background: var(--ql-bg); transition: opacity .45s ease, visibility .45s; font-family: Poppins, Inter, 'Segoe UI', system-ui, -apple-system, Arial, sans-serif; }
        #ql-loader.ql-dark { --ql-bg: #0C2A3F; --ql-word: #ffffff; --ql-sub: #FDBA74; --ql-track: rgba(255,255,255,.14); }
        #ql-loader.ql-done { opacity: 0; visibility: hidden; }
        #ql-loader.ql-done .ql-stage { transform: scale(1.06); }
        #ql-loader .ql-stage { display: flex; flex-direction: column; align-items: center; transition: transform .45s ease; }
        #ql-loader svg { width: 112px; height: 112px; overflow: visible; }
        #ql-loader .ql-disc, #ql-loader .ql-light, #ql-loader .ql-ring { transform-box: fill-box; transform-origin: center; }
        #ql-loader .ql-disc  { animation: ql-pop .6s cubic-bezier(.34,1.56,.64,1) both; }
        #ql-loader .ql-cloud { fill: var(--ql-bg); animation: ql-rise .55s cubic-bezier(.2,.9,.25,1) .2s both; }
        #ql-loader .ql-tail  { stroke-dasharray: 100; animation: ql-draw .4s cubic-bezier(.4,0,.2,1) .66s both; }
        #ql-loader .ql-light { animation: ql-pop .35s cubic-bezier(.34,1.56,.64,1) .98s both; }
        #ql-loader .ql-ring  { opacity: 0; animation: ql-pulse 1.3s ease-out 1.35s infinite; }
        #ql-loader .ql-name  { margin-top: 22px; font-size: 30px; font-weight: 700; letter-spacing: -.6px; line-height: 1; color: var(--ql-word); animation: ql-up .45s cubic-bezier(.2,.9,.25,1) .8s both; }
        #ql-loader .ql-sub   { margin-top: 8px; font-size: 12px; font-weight: 600; color: var(--ql-sub); text-transform: uppercase; animation: ql-open .6s cubic-bezier(.2,.9,.25,1) .95s both; }
        #ql-loader .ql-bar   { margin-top: 34px; width: 140px; height: 3px; border-radius: 3px; background: var(--ql-track); overflow: hidden; position: relative; animation: ql-fade .3s ease 1.1s both; }
        #ql-loader .ql-bar::after { content: ''; position: absolute; top: 0; bottom: 0; left: -40%; width: 40%; border-radius: 3px;
            background: linear-gradient(90deg, #38BDF8, #0284C7); animation: ql-slide 1.1s cubic-bezier(.4,0,.2,1) infinite; }
        @keyframes ql-pop   { from { transform: scale(0); opacity: 0; } 40% { opacity: 1; } to { transform: scale(1); opacity: 1; } }
        @keyframes ql-rise  { from { transform: translateY(70px) scale(.8); } to { transform: none; } }
        @keyframes ql-draw  { from { stroke-dashoffset: 100; } to { stroke-dashoffset: 0; } }
        @keyframes ql-pulse { from { transform: scale(1); opacity: .45; } to { transform: scale(2.4); opacity: 0; } }
        @keyframes ql-up    { from { transform: translateY(12px); opacity: 0; } to { transform: none; opacity: 1; } }
        @keyframes ql-open  { from { letter-spacing: 2px; opacity: 0; } to { letter-spacing: 6.5px; opacity: 1; } }
        @keyframes ql-fade  { from { opacity: 0; } to { opacity: 1; } }
        @keyframes ql-slide { from { left: -40%; } to { left: 100%; } }
        @media (prefers-reduced-motion: reduce) {
            #ql-loader *, #ql-loader *::after { animation-duration: 1ms !important; animation-delay: 0s !important; animation-iteration-count: 1 !important; }
        }
    </style>
    <div class="ql-stage">
        <svg viewBox="100 100 340 300" aria-hidden="true">
            <defs>
                <linearGradient id="ql-q" x1="0.1" y1="0" x2="0.8" y2="1"><stop offset="0" stop-color="#38BDF8"/><stop offset="1" stop-color="#0369A1"/></linearGradient>
                <linearGradient id="ql-t" gradientUnits="userSpaceOnUse" x1="322" y1="318" x2="404" y2="374"><stop offset="0" stop-color="#FB923C"/><stop offset="1" stop-color="#EA580C"/></linearGradient>
                <clipPath id="ql-clip"><circle cx="250" cy="250" r="150"/></clipPath>
            </defs>
            <g class="ql-disc">
                <circle cx="250" cy="250" r="150" fill="url(#ql-q)"/>
                <g clip-path="url(#ql-clip)"><path class="ql-cloud" d="M308.9 213.0 L307.0 204.2 L304.4 197.4 L301.1 190.9 L297.8 186.0 L293.1 180.4 L288.9 176.4 L283.1 172.0 L276.8 168.3 L270.1 165.4 L263.0 163.4 L257.3 162.4 L250.0 162.0 L242.7 162.4 L237.0 163.4 L229.9 165.4 L223.2 168.3 L219.4 170.4 L215.7 172.8 L210.0 177.4 L204.9 182.6 L202.2 186.0 L199.7 189.6 L197.5 193.4 L195.6 197.4 L193.0 204.2 L191.4 211.3 L190.5 219.0 L184.9 219.6 L180.2 220.7 L175.6 222.4 L171.3 224.6 L167.3 227.4 L163.7 230.6 L159.8 235.0 L157.2 239.1 L155.1 243.5 L153.7 247.2 L152.8 250.9 L152.1 255.8 L152.1 259.7 L152.3 263.5 L153.0 267.4 L153.9 270.6 L152.4 275.1 L152.1 277.8 L152.1 280.5 L152.7 284.4 L154.1 288.2 L155.5 290.5 L157.1 292.6 L159.0 294.5 L161.1 296.2 L163.5 297.5 L165.9 298.5 L168.5 299.2 L171.2 299.6 L327.5 299.6 L332.1 299.1 L336.5 297.5 L338.9 296.2 L341.0 294.5 L344.1 291.1 L346.4 287.0 L347.7 282.5 L348.0 279.1 L347.7 275.8 L346.9 272.6 L345.7 269.9 L346.7 266.7 L347.5 262.6 L347.9 258.3 L347.9 254.1 L347.3 248.8 L345.8 242.6 L343.3 236.7 L340.6 232.1 L336.7 227.1 L332.9 223.3 L327.8 219.5 L322.1 216.5 L316.1 214.4 L313.0 213.6 Z"/></g>
            </g>
            <path class="ql-tail" pathLength="100" d="M322 318 Q354 352 404 374" fill="none" stroke="url(#ql-t)" stroke-width="46" stroke-linecap="round"/>
            <circle class="ql-ring" cx="388" cy="138" r="25" fill="#22C55E"/>
            <circle class="ql-light" cx="388" cy="138" r="25" fill="#22C55E"/>
        </svg>
        <div class="ql-name">{{ $qlWords[0] }}</div>
        @if (!empty($qlWords[1]))
            <div class="ql-sub">{{ $qlWords[1] }}</div>
        @endif
        <div class="ql-bar"></div>
    </div>
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
                // Let the logo finish drawing in before it fades away.
                setTimeout(function () {
                    el.classList.add('ql-done');
                    setTimeout(function () { el.remove(); }, 500);
                }, Math.max(0, 1700 - (Date.now() - shownAt)));
            }
            if (document.readyState === 'complete') hide(); else window.addEventListener('load', hide);
            setTimeout(hide, 8000); // never hold the page hostage to a slow image or script
        })();
    </script>
</div>
