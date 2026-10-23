{{-- The door screen: a big QR that changes every few seconds. Meant for a TV or laptop at the entrance. --}}
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $event->title }} · Check-in</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="{{ asset('theme/layouts/assets/css/icons.min.css') }}" rel="stylesheet">
    <style>
        :root { --bg: #0f172a; --card: #ffffff; --ink: #f8fafc; --muted: #94a3b8; --accent: #22c55e; --warn: #f59e0b; }
        * { box-sizing: border-box; }
        html, body { margin: 0; height: 100%; background: var(--bg); color: var(--ink); font-family: Poppins, system-ui, sans-serif; }
        .wrap { display: grid; grid-template-columns: minmax(0, 1.25fr) minmax(0, 1fr); gap: 4vw; height: 100%; padding: 4vh 5vw; align-items: center; }
        .qr { background: var(--card); border-radius: 24px; padding: 2.5vh; aspect-ratio: 1; width: min(70vh, 100%); margin: 0 auto; position: relative; }
        .qr svg { width: 100%; height: 100%; display: block; }
        .bar { height: 6px; background: #1e293b; border-radius: 3px; margin: 2vh auto 0; width: min(70vh, 100%); overflow: hidden; }
        .bar i { display: block; height: 100%; background: var(--accent); transition: width 1s linear; }
        h1 { font-size: clamp(22px, 3.6vw, 52px); margin: 0 0 .4em; line-height: 1.15; }
        .sub { color: var(--muted); font-size: clamp(14px, 1.5vw, 22px); margin: 0 0 3vh; }
        .how { font-size: clamp(16px, 1.8vw, 26px); margin: 0 0 3vh; }
        .count { font-size: clamp(40px, 6vw, 96px); font-weight: 700; line-height: 1; }
        .count small { font-size: .3em; color: var(--muted); font-weight: 400; display: block; margin-top: .4em; }
        .recent { list-style: none; padding: 0; margin: 4vh 0 0; }
        .recent li { display: flex; align-items: center; gap: 12px; padding: 10px 0; border-top: 1px solid #1e293b; font-size: clamp(14px, 1.4vw, 20px); animation: pop .4s ease; }
        .recent img { width: 36px; height: 36px; border-radius: 50%; object-fit: cover; background: #334155; }
        .recent .t { margin-left: auto; color: var(--muted); font-size: .85em; }
        .late { color: var(--warn); font-size: .8em; }
        .closed { position: absolute; inset: 0; background: rgba(255,255,255,.96); color: #0f172a; border-radius: 24px; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; padding: 20px; font-size: clamp(18px, 2vw, 28px); }
        .fs { position: fixed; right: 16px; bottom: 12px; background: none; border: 1px solid #334155; color: var(--muted); border-radius: 8px; padding: 6px 10px; cursor: pointer; }
        @keyframes pop { from { opacity: 0; transform: translateY(-6px); } to { opacity: 1; transform: none; } }
        @media (max-width: 800px), (orientation: portrait) { .wrap { grid-template-columns: 1fr; height: auto; } .qr { width: min(88vw, 60vh); } }
    </style>
</head>
<body>
<div class="wrap">
    <div>
        <div class="qr" id="qr" aria-label="Check-in QR code"><div class="closed">Loading…</div></div>
        <div class="bar"><i id="bar" style="width:100%"></i></div>
    </div>
    <div>
        <h1>{{ $event->title }}</h1>
        <p class="sub">{{ $event->whenLabel() }}@if($event->location) · {{ $event->location }}@endif</p>
        <p class="how"><i class="ri-smartphone-line"></i> Point your phone camera at the code and tap the link to check in.@if($event->kind === 'staff') Scan again when you leave.@endif</p>
        <div class="count"><span id="count">0</span><small id="countLabel">checked in</small></div>
        <ul class="recent" id="recent" aria-live="polite"></ul>
    </div>
</div>
<button class="fs" onclick="document.documentElement.requestFullscreen && document.documentElement.requestFullscreen()"><i class="ri-fullscreen-line"></i> Full screen</button>
<script>
(function () {
    var dataUrl = @json(route('checkin.events.screen.data', $event));
    var qr = document.getElementById('qr'), bar = document.getElementById('bar'), recent = document.getElementById('recent');
    var current = null, left = 20, seen = {};
    function esc(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
    function load() {
        fetch(dataUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (d) {
            if (!d.open) {
                qr.innerHTML = '<div class="closed"><i class="ri-door-closed-line" style="font-size:3em"></i>' + (d.status === 'open' ? 'Check-in opens at ' + esc(d.opens_at) : 'Check-in is closed') + '</div>';
                current = null;
            } else if (d.url !== current) { qr.innerHTML = d.qr; current = d.url; }
            left = d.seconds;
            var s = d.stats;
            document.getElementById('count').textContent = s.in + (s.expected ? ' / ' + s.expected : '');
            document.getElementById('countLabel').textContent = s.late ? 'checked in · ' + s.late + ' late' : 'checked in';
            recent.innerHTML = d.recent.map(function (p) {
                return '<li>' + (p.photo ? '<img src="' + esc(p.photo) + '" alt="">' : '<img alt="">') + '<span>' + esc(p.name) + (p.status === 'late' && !p.out ? ' <span class="late">late</span>' : '') + (p.out ? ' <span class="late" style="color:#94a3b8">left</span>' : '') + '</span><span class="t">' + esc(p.at) + '</span></li>';
            }).join('');
        }).catch(function () {});
    }
    load();
    setInterval(load, 3000);
    setInterval(function () { left = Math.max(0, left - 1); bar.style.width = (left / 20 * 100) + '%'; if (left === 0) load(); }, 1000);
    // Keep the screen awake where the browser allows it.
    try { navigator.wakeLock && navigator.wakeLock.request('screen'); } catch (e) {}
})();
</script>
</body>
</html>
