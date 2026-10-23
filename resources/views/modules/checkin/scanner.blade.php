@extends('layouts.master')
@section('title', 'Scan · ' . $event->title)

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">
    <x-cb.hero :title="'Scan badges · ' . $event->title" icon="ri-qr-scan-2-line" subtitle="Point the camera at a badge or ticket QR. Each scan checks the person in." :back="route('checkin.events.show', $event)" back-label="Event" />
    <div class="row g-3">
        <div class="col-lg-6">
            <div class="cb-card p-2">
                <div id="reader" style="width:100%;min-height:280px;background:#111;border-radius:10px;overflow:hidden"></div>
                <div class="d-flex gap-2 mt-2">
                    <button class="btn btn-primary btn-sm" id="start"><i class="ri-camera-line me-1"></i>Start camera</button>
                    <button class="btn btn-light btn-sm" id="flip" hidden><i class="ri-camera-switch-line me-1"></i>Switch camera</button>
                </div>
                <form class="d-flex gap-2 mt-3" id="manual">
                    <input class="form-control form-control-sm" id="code" placeholder="Or type a ticket code" autocomplete="off" aria-label="Ticket code">
                    <button class="btn btn-sm btn-soft-primary">Check in</button>
                </form>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="cb-card p-4 text-center" id="result" aria-live="assertive" style="min-height:240px">
                <i class="ri-qr-scan-line text-muted" style="font-size:64px"></i>
                <p class="text-muted mb-0">Waiting for a scan…</p>
            </div>
            <div class="cb-card p-3 mt-3 d-flex justify-content-around text-center">
                <div><div class="fs-3 fw-semibold" id="sIn">{{ $stats['in'] }}</div><small class="text-muted">Checked in</small></div>
                <div><div class="fs-3 fw-semibold" id="sLate">{{ $stats['late'] }}</div><small class="text-muted">Late</small></div>
                <div><div class="fs-3 fw-semibold" id="sExp">{{ $stats['expected'] ?? '—' }}</div><small class="text-muted">Expected</small></div>
            </div>
        </div>
    </div>
</div></div></div>
@endsection

@push('scripts')
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
(function () {
    var scanUrl = @json(route('checkin.events.scan', $event));
    var csrf = document.querySelector('meta[name="csrf-token"]').content;
    var result = document.getElementById('result');
    var last = '', lastAt = 0, busy = false, cams = [], camIdx = 0, reader = null;
    var audio = window.AudioContext ? new AudioContext() : null;
    function beep(ok) {
        if (!audio) return;
        var o = audio.createOscillator(), g = audio.createGain();
        o.frequency.value = ok ? 880 : 220; o.connect(g); g.connect(audio.destination);
        g.gain.setValueAtTime(.2, audio.currentTime); o.start(); o.stop(audio.currentTime + (ok ? .15 : .4));
        if (navigator.vibrate) navigator.vibrate(ok ? 80 : [80, 60, 80]);
    }
    function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; }
    function show(d) {
        var ok = d.ok, tone = !ok ? 'danger' : (d.state === 'already' ? 'info' : (d.status === 'late' ? 'warning' : 'success'));
        var icon = !ok ? 'ri-close-circle-fill' : (d.state === 'out' ? 'ri-logout-box-r-fill' : 'ri-checkbox-circle-fill');
        result.className = 'cb-card p-4 text-center border border-3 border-' + tone;
        result.innerHTML = (d.photo ? '<img src="' + esc(d.photo) + '" class="rounded-circle mb-2" width="96" height="96" style="object-fit:cover" alt="">' : '<i class="' + icon + ' text-' + tone + '" style="font-size:72px"></i>')
            + (d.name ? '<h3 class="mb-1">' + esc(d.name) + '</h3>' : '') + (d.org ? '<div class="text-muted">' + esc(d.org) + '</div>' : '')
            + '<p class="fs-5 mt-2 mb-0 text-' + tone + '">' + esc(d.message) + '</p>';
        if (d.stats) { document.getElementById('sIn').textContent = d.stats.in; document.getElementById('sLate').textContent = d.stats.late; }
        beep(ok && d.state !== 'already');
    }
    function send(code) {
        if (busy || (code === last && Date.now() - lastAt < 4000)) return;
        busy = true; last = code; lastAt = Date.now();
        fetch(scanUrl, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify({ code: code }) })
            .then(function (r) { return r.json(); }).then(show).catch(function () { show({ ok: false, message: 'No connection. Try again.' }); })
            .finally(function () { setTimeout(function () { busy = false; }, 900); });
    }
    window.qmScan = send;
    document.getElementById('manual').addEventListener('submit', function (e) { e.preventDefault(); var v = document.getElementById('code').value.trim(); if (v) { last = ''; send(v); document.getElementById('code').value = ''; } });
    function start(id) {
        if (!window.Html5Qrcode) { show({ ok: false, message: 'The scanner could not load. Type ticket codes instead.' }); return; }
        reader = reader || new Html5Qrcode('reader');
        reader.start(id || { facingMode: 'environment' }, { fps: 10, qrbox: function (w, h) { var s = Math.floor(Math.min(w, h) * .7); return { width: s, height: s }; } }, send, function () {})
            .then(function () { document.getElementById('start').hidden = true; })
            .catch(function () { show({ ok: false, message: 'Allow camera access to scan.' }); });
    }
    document.getElementById('start').addEventListener('click', function () {
        if (audio && audio.state === 'suspended') audio.resume();
        if (window.Html5Qrcode) Html5Qrcode.getCameras().then(function (c) { cams = c; document.getElementById('flip').hidden = c.length < 2; }).catch(function () {});
        start();
    });
    document.getElementById('flip').addEventListener('click', function () {
        if (!cams.length || !reader) return;
        camIdx = (camIdx + 1) % cams.length;
        reader.stop().then(function () { start(cams[camIdx].id); });
    });
})();
</script>
@endpush
