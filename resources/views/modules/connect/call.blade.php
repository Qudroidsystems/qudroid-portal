<!doctype html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $call->is_huddle ? 'Huddle' : ($call->kind === 'audio' ? 'Voice call' : 'Video call') }} · {{ $title }}</title>
    <link rel="icon" href="{{ asset($brand['favicon'] ?? 'theme/layouts/assets/images/favicon.ico') }}">
    <link href="{{ asset('theme/layouts/assets/css/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ asset('theme/layouts/assets/css/icons.min.css') }}" rel="stylesheet">
    <style>
        :root { --bg: #0f1115; --tile: #1b1e25; --line: rgba(255,255,255,.08); --muted: #9aa0ab; --accent: #0d9488; }
        html, body { height: 100%; }
        body { margin: 0; background: var(--bg); color: #f3f4f6; font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; display: flex; flex-direction: column; overflow: hidden; }
        header { display: flex; align-items: center; gap: 12px; padding: 12px 16px; border-bottom: 1px solid var(--line); }
        header .title { font-weight: 600; flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        header .meta { color: var(--muted); font-size: 13px; white-space: nowrap; }
        main { flex: 1; min-height: 0; padding: 12px; display: grid; gap: 12px; grid-template-columns: repeat(auto-fit, minmax(min(100%, 320px), 1fr)); grid-auto-rows: minmax(180px, 1fr); overflow: auto; }
        .tile { position: relative; background: var(--tile); border-radius: 14px; overflow: hidden; display: flex; align-items: center; justify-content: center; border: 2px solid transparent; min-height: 180px; }
        .tile video { width: 100%; height: 100%; object-fit: cover; background: #000; }
        .tile.screen video { object-fit: contain; }
        .tile.me video { transform: scaleX(-1); }
        .tile.me.screen video { transform: none; }
        .tile .face { position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 8px; background: var(--tile); }
        .tile .face img { width: 84px; height: 84px; border-radius: 50%; object-fit: cover; }
        .tile.has-video .face { display: none; }
        .tile .label { position: absolute; left: 10px; bottom: 10px; background: rgba(0,0,0,.55); padding: 3px 10px; border-radius: 999px; font-size: 13px; display: flex; gap: 6px; align-items: center; max-width: calc(100% - 20px); }
        .tile .label span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .tile .state { position: absolute; right: 10px; top: 10px; font-size: 12px; color: var(--muted); background: rgba(0,0,0,.55); padding: 2px 8px; border-radius: 999px; }
        .tile.speaking { border-color: var(--accent); }
        footer { display: flex; justify-content: center; gap: 12px; padding: 14px; border-top: 1px solid var(--line); flex-wrap: wrap; }
        .ctl { width: 52px; height: 52px; border-radius: 50%; border: 0; background: #2a2e37; color: #fff; font-size: 22px; display: inline-flex; align-items: center; justify-content: center; }
        .ctl:hover { background: #343945; }
        .ctl.off { background: #f1f3f4; color: #111; }
        .ctl.hang { background: #dc2626; width: 64px; border-radius: 26px; }
        .ctl:disabled { opacity: .4; }
        .lobby, .ended { position: fixed; inset: 0; background: var(--bg); display: flex; align-items: center; justify-content: center; z-index: 10; padding: 16px; }
        .lobby .card-x { background: var(--tile); border-radius: 18px; padding: 20px; width: min(560px, 100%); text-align: center; }
        .lobby video { width: 100%; aspect-ratio: 16/9; background: #000; border-radius: 12px; object-fit: cover; transform: scaleX(-1); margin-bottom: 14px; }
        .note { color: var(--muted); font-size: 13px; }
        .hidden { display: none !important; }
        /* Docked inside the chat page (huddles) */
        body.embed header { padding: 8px 12px; }
        body.embed main { padding: 8px; gap: 8px; grid-template-columns: repeat(auto-fit, minmax(min(100%, 140px), 1fr)); grid-auto-rows: minmax(110px, 1fr); }
        body.embed .tile { min-height: 110px; border-radius: 10px; }
        body.embed .tile .face img { width: 48px; height: 48px; }
        body.embed footer { padding: 8px; gap: 8px; }
        body.embed .ctl { width: 42px; height: 42px; font-size: 18px; }
        body.embed .ctl.hang { width: 52px; }
        body.embed .lobby video { display: none; }
        .rec { color: #fff; background: #dc2626; border-radius: 999px; padding: 2px 10px; font-size: 12px; font-weight: 600; white-space: nowrap; display: inline-flex; gap: 5px; align-items: center; }
        .rec i { animation: rec-blink 1.4s infinite; }
        @keyframes rec-blink { 50% { opacity: .3; } }
        .cc-on { color: #fff; background: #374151; border-radius: 999px; padding: 2px 10px; font-size: 12px; white-space: nowrap; }
        .ctl.rec-on { background: #dc2626; }
        .ctl.cc-active { background: #f1f3f4; color: #111; }
        #captions { position: fixed; left: 50%; transform: translateX(-50%); bottom: 96px; width: min(760px, calc(100% - 24px)); z-index: 5; pointer-events: none; display: flex; flex-direction: column; gap: 4px; }
        #captions div { background: rgba(0,0,0,.72); padding: 6px 12px; border-radius: 8px; font-size: 15px; line-height: 1.35; }
        #captions div.interim { opacity: .65; }
        #captions b { color: #5eead4; font-weight: 600; margin-right: 6px; }
        body.embed #captions { bottom: 64px; }
        body.embed #captions div { font-size: 13px; }
    </style>
</head>
<body class="{{ $embed ?? false ? 'embed' : '' }}">
    <header>
        <i class="{{ $call->is_huddle ? 'ri-headphone-line' : ($call->kind === 'audio' ? 'ri-phone-line' : 'ri-vidicon-line') }} fs-4"></i>
        <div class="title">{{ $title }}</div>
        <span class="rec hidden" id="recBadge" role="status"><i class="ri-record-circle-fill"></i><span>Recording</span></span>
        <span class="cc-on hidden" id="ccBadge"><i class="ri-closed-captioning-line"></i> Transcript on</span>
        <div class="meta"><span id="people">0</span> in call · <span id="timer">0 sec</span></div>
    </header>

    <main id="grid" aria-label="People in the call"></main>
    <div id="captions" aria-live="polite"></div>

    <footer>
        <button class="ctl" id="btnMic" title="Mute (M)" aria-label="Mute microphone"><i class="ri-mic-line"></i></button>
        <button class="ctl" id="btnCam" title="Camera (V)" aria-label="Turn camera on or off"><i class="ri-vidicon-line"></i></button>
        <button class="ctl" id="btnScreen" title="Share your screen" aria-label="Share your screen"><i class="ri-computer-line"></i></button>
        <button class="ctl" id="btnCC" title="Transcript and captions" aria-label="Turn the transcript on or off" aria-pressed="false"><i class="ri-closed-captioning-line"></i></button>
        <button class="ctl" id="btnRec" title="Record" aria-label="Start or stop recording" aria-pressed="false"><i class="ri-record-circle-line"></i></button>
        <button class="ctl hang" id="btnLeave" title="Leave call" aria-label="Leave call"><i class="ri-phone-fill" style="transform: rotate(135deg)"></i></button>
    </footer>

    <div class="lobby" id="lobby">
        <div class="card-x">
            <h5 class="mb-1">{{ $call->ended_at ? 'This call has ended' : 'Ready to join?' }}</h5>
            <p class="note mb-3">{{ $title }} · {{ $call->is_huddle ? 'Huddle' : ($call->kind === 'audio' ? 'Voice call' : 'Video call') }}</p>
            @if($call->ended_at)
                <a href="{{ route('connect.show', $call->conversation_id) }}" class="btn btn-light" target="_top">Back to the chat</a>
            @else
                <video id="preview" autoplay playsinline muted class="{{ $call->kind === 'audio' ? 'hidden' : '' }}"></video>
                <p class="note" id="lobbyNote">Checking your microphone{{ $call->kind === 'audio' ? '' : ' and camera' }}…</p>
                <div class="d-flex gap-2 justify-content-center">
                    <button class="btn btn-success px-4" id="btnJoin" disabled>Join now</button>
                    <a href="{{ route('connect.show', $call->conversation_id) }}" class="btn btn-outline-light" id="btnNotNow" target="_top">Not now</a>
                </div>
            @endif
        </div>
    </div>

    <div class="ended hidden" id="ended">
        <div class="text-center">
            <i class="ri-phone-line" style="font-size: 48px"></i>
            <h5 class="mt-2" id="endedText">You left the call</h5>
            <p class="note" id="endedTime"></p>
            <a href="{{ route('connect.show', $call->conversation_id) }}" class="btn btn-light" id="btnBack" target="_top">Back to the chat</a>
            <button class="btn btn-outline-light ms-1" id="btnRejoin">Rejoin</button>
            <a href="{{ route('connect.calls.recap', $call) }}" class="btn btn-outline-light ms-1" target="_top">Recap</a>
        </div>
    </div>

@unless($call->ended_at)
@include('layouts.partials.realtime')
@include('layouts.partials.sounds')
<script>
(function () {
    const ME = @json($me->id);
    const KIND = @json($call->kind);
    const HUDDLE = @json((bool) $call->is_huddle);
    const EMBED = @json((bool) ($embed ?? false));
    const tellParent = type => { if (EMBED && window.parent !== window) window.parent.postMessage({ type: 'huddle:' + type, call: @json($call->id) }, location.origin); };
    const ICE = @json($iceServers);
    const URLS = {
        join: @json(route('connect.calls.join', $call)), poll: @json(route('connect.calls.poll', $call)),
        signal: @json(route('connect.calls.signal', $call)), leave: @json(route('connect.calls.leave', $call)),
        record: @json(route('connect.recordings.start', $call)), transcribe: @json(route('connect.calls.transcribe', $call)),
        say: @json(route('connect.calls.say', $call)),
    };
    const LANG = @json(config('qudroid.calls.transcript_lang', 'en-NG'));
    const ME_INFO = { name: @json($me->name), avatar: @json($me->avatar_url) };
    const token = document.querySelector('meta[name=csrf-token]').content;
    const headers = { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest' };
    const $ = id => document.getElementById(id);

    const peers = new Map();       // userId -> { pc, stream, pendingIce, outIce, flush }
    const people = new Map();      // userId -> { name, avatar, mic, cam, screen }
    let mic = null, cam = null, screen = null;
    let micOn = true, camOn = KIND === 'video', inCall = false, after = 0, timer = null;

    // ── Devices ────────────────────────────────────────────────────────
    async function openDevices() {
        try {
            const s = await navigator.mediaDevices.getUserMedia({ audio: true, video: camOn });
            mic = s.getAudioTracks()[0] || null; cam = s.getVideoTracks()[0] || null;
        } catch (e) {
            try { const s = await navigator.mediaDevices.getUserMedia({ audio: true }); mic = s.getAudioTracks()[0]; } catch (e2) {}
            camOn = !!cam;
        }
        if (!mic) micOn = false;
        const preview = $('preview');
        if (preview) preview.srcObject = new MediaStream([mic, cam].filter(Boolean));
        $('lobbyNote').textContent = mic ? (cam ? 'Your camera and microphone are ready.' : 'Your microphone is ready.') : 'No microphone found. You can still listen and watch.';
        $('btnJoin').disabled = false;
        if (EMBED) join();   // the person already chose to join from the chat
    }
    const outgoingVideo = () => screen || (camOn ? cam : null);

    function applyTracks() {
        if (mic) mic.enabled = micOn;
        for (const p of peers.values()) {
            const [a, v] = transceivers(p.pc);
            a?.sender.replaceTrack(mic).catch(() => {});
            v?.sender.replaceTrack(outgoingVideo()).catch(() => {});
        }
        renderMe();
        $('btnMic').classList.toggle('off', !micOn);
        $('btnMic').innerHTML = '<i class="' + (micOn ? 'ri-mic-line' : 'ri-mic-off-line') + '"></i>';
        $('btnCam').classList.toggle('off', !camOn);
        $('btnCam').innerHTML = '<i class="' + (camOn ? 'ri-vidicon-line' : 'ri-video-off-line') + '"></i>';
        $('btnScreen').classList.toggle('off', !!screen);
        $('btnMic').disabled = !mic;
    }

    // ── Peers ──────────────────────────────────────────────────────────
    function transceivers(pc) {
        const all = pc.getTransceivers();
        return [all.find(t => t.receiver.track.kind === 'audio'), all.find(t => t.receiver.track.kind === 'video')];
    }

    function send(to, type, payload) {
        return fetch(URLS.signal, { method: 'POST', headers, body: JSON.stringify({ to, type, payload: payload == null ? null : JSON.stringify(payload) }) });
    }

    function peer(id) {
        close(id);
        const pc = new RTCPeerConnection({ iceServers: ICE });
        const p = { pc, stream: new MediaStream(), pendingIce: [], outIce: [], flush: null };
        pc.onicecandidate = e => {
            if (!e.candidate) return;
            p.outIce.push(e.candidate.toJSON());
            clearTimeout(p.flush);
            p.flush = setTimeout(() => { const list = p.outIce.splice(0); if (list.length) send(id, 'ice', list); }, 250);
        };
        pc.ontrack = e => {
            p.stream.getTracks().filter(t => t.kind === e.track.kind).forEach(t => p.stream.removeTrack(t));
            p.stream.addTrack(e.track);
            e.track.onunmute = () => render(id);
            e.track.onmute = () => render(id);
            render(id);
        };
        pc.onconnectionstatechange = () => {
            if (pc.connectionState === 'failed' && ME < id) setTimeout(() => { if (peers.get(id)?.pc === pc) call(id); }, 1500);
            render(id);
        };
        peers.set(id, p);
        return p;
    }

    // The newcomer (or, on a retry, the lower id) makes the offer.
    async function call(id) {
        const p = peer(id);
        p.pc.addTransceiver('audio', { direction: 'sendrecv' });
        p.pc.addTransceiver('video', { direction: 'sendrecv' });
        applyTracks();
        await p.pc.setLocalDescription(await p.pc.createOffer());
        send(id, 'offer', p.pc.localDescription);
    }

    async function onSignal(s) {
        const from = s.from, data = s.payload ? JSON.parse(s.payload) : null;
        if (s.type === 'offer') {
            const p = peer(from);
            await p.pc.setRemoteDescription(data);
            p.pc.getTransceivers().forEach(t => t.direction = 'sendrecv');
            applyTracks();
            await p.pc.setLocalDescription(await p.pc.createAnswer());
            send(from, 'answer', p.pc.localDescription);
            for (const c of p.pendingIce.splice(0)) p.pc.addIceCandidate(c).catch(() => {});
        } else if (s.type === 'answer') {
            const p = peers.get(from); if (!p) return;
            await p.pc.setRemoteDescription(data).catch(() => {});
            for (const c of p.pendingIce.splice(0)) p.pc.addIceCandidate(c).catch(() => {});
        } else if (s.type === 'ice') {
            const p = peers.get(from); if (!p) return;
            for (const c of data || []) p.pc.remoteDescription ? p.pc.addIceCandidate(c).catch(() => {}) : p.pendingIce.push(c);
        } else if (s.type === 'bye') {
            close(from); render(from);
        }
    }

    function close(id) {
        const p = peers.get(id);
        if (p) { clearTimeout(p.flush); try { p.pc.close(); } catch (e) {} peers.delete(id); }
    }

    // ── Tiles ──────────────────────────────────────────────────────────
    function tile(id) {
        let t = document.querySelector('.tile[data-id="' + id + '"]');
        if (!t) {
            t = document.createElement('div');
            t.className = 'tile' + (id === ME ? ' me' : '');
            t.dataset.id = id;
            t.innerHTML = '<video autoplay playsinline></video><div class="face"><img alt=""><div class="note"></div></div><div class="label"><i class="ri-mic-off-line hidden"></i><span></span></div><div class="state hidden"></div>';
            if (id === ME) t.querySelector('video').muted = true;
            t.addEventListener('dblclick', () => t.requestFullscreen?.());
            id === ME ? $('grid').prepend(t) : $('grid').appendChild(t);
        }
        return t;
    }

    function renderMe() {
        const t = tile(ME), v = t.querySelector('video'), track = outgoingVideo();
        const stream = new MediaStream(track ? [track] : []);
        if (!v.srcObject || v.srcObject.getTracks()[0] !== track) v.srcObject = stream;
        t.classList.toggle('has-video', !!track);
        t.classList.toggle('screen', !!screen);
        t.querySelector('.face img').src = ME_INFO.avatar;
        t.querySelector('.label span').textContent = 'You' + (screen ? ' (sharing screen)' : '');
        t.querySelector('.label i').classList.toggle('hidden', micOn);
    }

    function render(id) {
        if (id === ME) return renderMe();
        const info = people.get(id);
        const t = document.querySelector('.tile[data-id="' + id + '"]');
        if (!info) { t?.remove(); return; }
        const el = tile(id), v = el.querySelector('video'), p = peers.get(id);
        if (p && v.srcObject !== p.stream) v.srcObject = p.stream;
        const showVideo = !!(p && (info.cam || info.screen) && p.stream.getVideoTracks().some(tr => !tr.muted));
        el.classList.toggle('has-video', showVideo);
        el.classList.toggle('screen', !!info.screen);
        el.querySelector('.face img').src = info.avatar || '';
        el.querySelector('.label span').textContent = (info.name || 'Someone') + (info.screen ? ' (sharing screen)' : '');
        el.querySelector('.label i').classList.toggle('hidden', info.mic);
        const state = !p ? 'Connecting…' : ({ connecting: 'Connecting…', failed: 'Reconnecting…', disconnected: 'Reconnecting…' })[p.pc.connectionState] || '';
        el.querySelector('.state').textContent = state;
        el.querySelector('.state').classList.toggle('hidden', !state);
        v.play?.().catch(() => {});
    }

    // ── Joining, checking in, leaving ──────────────────────────────────
    async function join() {
        $('btnJoin').disabled = true;
        const r = await fetch(URLS.join, { method: 'POST', headers });
        const j = await r.json().catch(() => ({}));
        if (!r.ok) { $('lobbyNote').textContent = (j.errors && Object.values(j.errors).flat()[0]) || j.message || 'Could not join the call.'; $('btnJoin').disabled = false; return; }
        $('lobby').classList.add('hidden');
        $('ended').classList.add('hidden');
        inCall = true; after = 0;
        applyTracks();
        for (const id of j.others || []) { people.set(id, people.get(id) || { name: '', mic: true }); call(id); }
        loop();
    }

    let busy = false, again = false;
    async function check() {
        if (busy) { again = true; return; }
        if (!inCall) return;
        busy = true;
        try {
            const q = new URLSearchParams({ after, cap: capAfter, mic: micOn ? 1 : 0, cam: camOn && !!cam ? 1 : 0, screen: screen ? 1 : 0 });
            const r = await fetch(URLS.poll + '?' + q, { headers });
            if (!r.ok) return;
            const d = await r.json();
            for (const s of d.signals || []) { after = Math.max(after, s.id); await onSignal(s).catch(() => {}); }
            const ids = new Set();
            for (const p of d.people || []) {
                ids.add(p.id);
                if (p.id !== ME) people.set(p.id, p);
            }
            for (const id of [...people.keys()]) if (!ids.has(id)) { people.delete(id); close(id); }
            [...people.keys()].forEach(render);
            renderMe();
            $('people').textContent = d.people?.length || 1;
            tones(people.size);
            $('timer').textContent = d.duration || '';
            extras(d);
            if (d.ended) return finish('The call has ended');
            if (!d.in) return finish('You were disconnected');
        } catch (e) {
        } finally { busy = false; if (again) { again = false; setTimeout(check, 0); } }
    }
    function loop() { clearInterval(timer); check(); timer = setInterval(check, 1000); }
    // Instant updates: offers, answers and people joining arrive without waiting for the next second.
    if (window.QRealtime) {
        QRealtime.on('user.' + ME, 'signal', d => { if (!d || d.call_id == @json($call->id)) check(); });
        QRealtime.on('call.' + @json($call->id), 'people', () => check());
    }

    function hangUp(beacon) {
        if (!inCall) return;
        inCall = false; clearInterval(timer);
        for (const id of peers.keys()) send(id, 'bye');
        [...peers.keys()].forEach(close);
        if (beacon) { const f = new FormData(); f.append('_token', token); navigator.sendBeacon(URLS.leave, f); }
        else fetch(URLS.leave, { method: 'POST', headers });
    }
    // Ringback while you wait alone (up to a minute), a blip when someone joins, falling beeps at the end.
    let stopRingback = null, waitedFrom = null, lastCount = 0;
    function tones(others) {
        if (!window.QSound) return;
        if (others === 0 && inCall) {
            waitedFrom ??= Date.now();
            if (!HUDDLE && !stopRingback && lastCount === 0 && Date.now() - waitedFrom < 60000) stopRingback = QSound.ringback();
        } else if (stopRingback) { stopRingback(); stopRingback = null; }
        if (others > lastCount && inCall) QSound.joined();
        lastCount = others;
    }
    function finish(text) {
        stopRecording();
        listen(false);
        if (stopRingback) { stopRingback(); stopRingback = null; }
        if (inCall && window.QSound) QSound.ended();
        hangUp(false);
        people.clear();
        document.querySelectorAll('.tile:not(.me)').forEach(t => t.remove());
        $('endedText').textContent = text;
        $('ended').classList.remove('hidden');
        $('btnRejoin').classList.toggle('hidden', text === 'The call has ended');
        tellParent('ended');
    }

    // ── Controls ───────────────────────────────────────────────────────
    $('btnMic').onclick = () => { micOn = !micOn; applyTracks(); listen(transcribing && micOn); };
    $('btnCam').onclick = async () => {
        if (!camOn) {
            try { if (!cam || cam.readyState === 'ended') cam = (await navigator.mediaDevices.getUserMedia({ video: true })).getVideoTracks()[0]; camOn = true; }
            catch (e) { alert('The camera could not be started. Check the browser\'s permission for this site.'); }
        } else { camOn = false; cam?.stop(); cam = null; }
        applyTracks();
    };
    $('btnScreen').onclick = async () => {
        if (screen) { screen.stop(); screen = null; return applyTracks(); }
        if (!navigator.mediaDevices.getDisplayMedia) return alert('This browser can\'t share the screen.');
        try {
            screen = (await navigator.mediaDevices.getDisplayMedia({ video: true })).getVideoTracks()[0];
            screen.onended = () => { screen = null; applyTracks(); };
            applyTracks();
        } catch (e) {}
    };
    $('btnLeave').onclick = () => finish('You left the call');
    $('btnRejoin').onclick = () => join();
    $('btnJoin').onclick = () => join();
    document.addEventListener('keydown', e => {
        if (!inCall || e.target.closest('input, textarea')) return;
        if (e.key === 'm' || e.key === 'M') $('btnMic').click();
        if (e.key === 'v' || e.key === 'V') $('btnCam').click();
    });
    window.addEventListener('pagehide', () => { stopRecording(true); hangUp(true); });

    // ── Recording ──────────────────────────────────────────────────────
    // Everyone's sound is mixed; for video calls the tiles are drawn onto a canvas.
    // Pieces upload every few seconds, in order, so a closed tab loses little.
    let rec = null;
    function pickMime(video) {
        const list = video ? ['video/webm;codecs=vp8,opus', 'video/webm', 'video/mp4'] : ['audio/webm;codecs=opus', 'audio/webm', 'audio/mp4', 'audio/ogg'];
        return list.find(m => window.MediaRecorder && MediaRecorder.isTypeSupported(m)) || '';
    }
    async function startRecording() {
        if (rec || !window.MediaRecorder) { if (!window.MediaRecorder) alert('This browser can\'t record. Try Chrome, Edge or Firefox.'); return; }
        if (!confirm('Record this call? Everyone in it will see that it is being recorded, and the recording is shared in the chat.')) return;
        const video = KIND === 'video' && !HUDDLE;
        let mime = pickMime(video);
        if (!mime) return alert('This browser can\'t record calls.');
        const r = await fetch(URLS.record, { method: 'POST', headers, body: JSON.stringify({ mime }) });
        const j = await r.json().catch(() => ({}));
        if (!r.ok) return alert((j.errors && Object.values(j.errors).flat()[0]) || 'Could not start recording.');

        const ctx = new (window.AudioContext || window.webkitAudioContext)();
        const mix = ctx.createMediaStreamDestination();
        const sources = new Map();
        const addAudio = () => {
            const tracks = [mic, ...[...peers.values()].flatMap(p => p.stream.getAudioTracks())].filter(t => t && t.readyState === 'live');
            for (const t of tracks) if (!sources.has(t.id)) { try { const src = ctx.createMediaStreamSource(new MediaStream([t])); src.connect(mix); sources.set(t.id, src); } catch (e) {} }
        };
        addAudio();
        const tracks = [...mix.stream.getAudioTracks()];
        let canvas = null, draw = null;
        if (video) {
            canvas = document.createElement('canvas'); canvas.width = 1280; canvas.height = 720;
            const g = canvas.getContext('2d');
            draw = setInterval(() => {
                const tiles = [...document.querySelectorAll('#grid .tile')];
                const n = Math.max(1, tiles.length), cols = Math.ceil(Math.sqrt(n)), rows = Math.ceil(n / cols);
                const w = canvas.width / cols, h = canvas.height / rows;
                g.fillStyle = '#0f1115'; g.fillRect(0, 0, canvas.width, canvas.height);
                tiles.forEach((t, i) => {
                    const x = (i % cols) * w, y = Math.floor(i / cols) * h, v = t.querySelector('video');
                    g.fillStyle = '#1b1e25'; g.fillRect(x + 4, y + 4, w - 8, h - 8);
                    if (t.classList.contains('has-video') && v.videoWidth) {
                        const s = Math.min((w - 8) / v.videoWidth, (h - 8) / v.videoHeight), vw = v.videoWidth * s, vh = v.videoHeight * s;
                        g.drawImage(v, x + (w - vw) / 2, y + (h - vh) / 2, vw, vh);
                    }
                    g.fillStyle = 'rgba(0,0,0,.55)'; g.fillRect(x + 12, y + h - 40, Math.min(w - 24, 260), 26);
                    g.fillStyle = '#fff'; g.font = '15px system-ui, sans-serif';
                    g.fillText((t.querySelector('.label span')?.textContent || '').slice(0, 32), x + 20, y + h - 22);
                });
                addAudio();
            }, 1000 / 15);
            tracks.unshift(canvas.captureStream(15).getVideoTracks()[0]);
        } else {
            draw = setInterval(addAudio, 1000);
        }
        const recorder = new MediaRecorder(new MediaStream(tracks), { mimeType: mime });
        const mine = { id: j.id, chunkUrl: j.chunk, stopUrl: j.stop, recorder, ctx, draw, seq: 0, queue: [], sending: false, started: Date.now(), beaconed: false };
        recorder.ondataavailable = e => { if (e.data && e.data.size) rec_push(mine, e.data); };
        // The last piece arrives after stop(); the stop request goes after it.
        recorder.onstop = () => { if (!mine.beaconed) { mine.queue.push('stop'); rec_flush(mine); } };
        rec = mine;
        recorder.start(4000);
        recUI();
    }
    function rec_push(r, blob) { r.queue.push(blob); rec_flush(r); }
    async function rec_flush(r) {
        if (r.sending) return;
        r.sending = true;
        while (r.queue.length) {
            const item = r.queue[0];
            if (item === 'stop') {
                const f = new FormData(); f.append('_token', token); f.append('seconds', Math.round((Date.now() - r.started) / 1000));
                await fetch(r.stopUrl, { method: 'POST', body: f, headers: { 'Accept': 'application/json' } }).catch(() => {});
                r.queue.shift();
                continue;
            }
            const f = new FormData(); f.append('_token', token); f.append('seq', r.seq); f.append('piece', item, 'piece');
            let ok = false;
            for (let tries = 0; tries < 4 && !ok; tries++) {
                ok = await fetch(r.chunkUrl, { method: 'POST', body: f, headers: { 'Accept': 'application/json' } }).then(x => x.ok).catch(() => false);
                if (!ok) await new Promise(res => setTimeout(res, 1000 * (tries + 1)));
            }
            if (!ok) { r.queue = r.queue.filter(x => x === 'stop'); break; }
            r.seq++; r.queue.shift();
        }
        r.sending = false;
    }
    function stopRecording(leaving) {
        if (!rec) return;
        const r = rec; rec = null;
        clearInterval(r.draw);
        try { r.recorder.state !== 'inactive' && r.recorder.stop(); } catch (e) {}
        try { r.ctx.close(); } catch (e) {}
        if (leaving) { r.beaconed = true; const f = new FormData(); f.append('_token', token); f.append('seconds', Math.round((Date.now() - r.started) / 1000)); navigator.sendBeacon(r.stopUrl, f); }
        recUI();
    }
    let recState = null;
    function recUI() {
        const someoneElse = recState && !recState.mine;
        $('btnRec').classList.toggle('rec-on', !!rec);
        $('btnRec').setAttribute('aria-pressed', rec ? 'true' : 'false');
        $('btnRec').title = rec ? 'Stop recording' : (someoneElse ? recState.by + ' is recording' : 'Record');
        $('btnRec').disabled = !!someoneElse;
        $('recBadge').classList.toggle('hidden', !rec && !recState);
        $('recBadge').querySelector('span').textContent = rec ? 'Recording' : (recState ? 'Recording · ' + (recState.by || 'someone') : '');
    }
    $('btnRec').onclick = () => rec ? stopRecording() : startRecording();

    // ── Transcript and captions ────────────────────────────────────────
    // Each browser writes down its own person's speech, so every line has the right name.
    const SR = window.SpeechRecognition || window.webkitSpeechRecognition;
    let transcribing = false, recognizer = null, wantListen = false, capAfter = 0, interim = '', warned = false;
    const captions = [];
    function listen(on) {
        wantListen = on && !!SR;
        if (on && !SR && !warned) { warned = true; showCaptions('This browser can\'t transcribe your voice. Others in the call are still written down.'); }
        if (wantListen && !recognizer) {
            recognizer = new SR();
            recognizer.lang = LANG; recognizer.continuous = true; recognizer.interimResults = true;
            recognizer.onresult = e => {
                interim = '';
                for (let i = e.resultIndex; i < e.results.length; i++) {
                    const text = e.results[i][0].transcript.trim();
                    if (!text) continue;
                    if (e.results[i].isFinal) fetch(URLS.say, { method: 'POST', headers, body: JSON.stringify({ text }) }).catch(() => {});
                    else interim += text + ' ';
                }
                showCaptions();
            };
            recognizer.onend = () => { recognizer = null; if (wantListen && inCall) setTimeout(() => listen(transcribing && micOn), 300); };
            recognizer.onerror = e => { if (e.error === 'not-allowed' || e.error === 'service-not-allowed') wantListen = false; };
            try { recognizer.start(); } catch (e) {}
        } else if (!wantListen && recognizer) {
            const r = recognizer; recognizer = null; try { r.stop(); } catch (e) {}
            interim = ''; showCaptions();
        }
    }
    function showCaptions(note) {
        const box = $('captions'); box.innerHTML = '';
        if (!transcribing && !note) return;
        const rows = captions.slice(-3).map(c => [c.who, c.text, false]);
        if (interim) rows.push(['You', interim, true]);
        if (note) rows.push(['', note, true]);
        for (const [who, text, faint] of rows.slice(-3)) {
            const d = document.createElement('div'); if (faint) d.className = 'interim';
            if (who) { const b = document.createElement('b'); b.textContent = who; d.appendChild(b); }
            d.appendChild(document.createTextNode(text)); box.appendChild(d);
        }
    }
    $('btnCC').onclick = async () => {
        const r = await fetch(URLS.transcribe, { method: 'POST', headers, body: JSON.stringify({ on: !transcribing }) });
        if (r.ok) { const j = await r.json(); setTranscribing(j.transcribing); }
    };
    function setTranscribing(on) {
        if (on === transcribing) return;
        transcribing = on;
        $('btnCC').classList.toggle('cc-active', on);
        $('btnCC').setAttribute('aria-pressed', on ? 'true' : 'false');
        $('ccBadge').classList.toggle('hidden', !on);
        listen(on && micOn);
        showCaptions();
    }
    // Recording and transcript state from each check-in.
    function extras(d) {
        recState = d.recording || null;
        if (d.recording && !d.recording.mine && rec) stopRecording();
        if (!d.recording && rec && rec.seq > 0 && Date.now() - rec.started > 15000) stopRecording();   // stopped on the server
        recUI();
        setTranscribing(!!d.transcribing);
        if (d.captions && d.captions.length) {
            for (const c of d.captions) { capAfter = Math.max(capAfter, c.id); captions.push({ who: c.me === ME ? 'You' : c.who, text: c.text }); }
            captions.splice(0, Math.max(0, captions.length - 10));
            showCaptions();
        }
    }

    if (EMBED) {
        ['btnNotNow', 'btnBack'].forEach(id => { const a = $(id); if (a) { a.textContent = 'Close'; a.onclick = e => { e.preventDefault(); tellParent('close'); }; } });
    }

    if (!window.RTCPeerConnection || !navigator.mediaDevices) {
        $('lobbyNote').textContent = 'This browser can\'t make calls. Try a recent Chrome, Edge, Firefox or Safari.';
    } else {
        openDevices();
    }
})();
</script>
@endunless
</body>
</html>
