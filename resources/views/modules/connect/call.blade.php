<!doctype html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $call->kind === 'audio' ? 'Voice' : 'Video' }} call · {{ $title }}</title>
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
    </style>
</head>
<body>
    <header>
        <i class="{{ $call->kind === 'audio' ? 'ri-phone-line' : 'ri-vidicon-line' }} fs-4"></i>
        <div class="title">{{ $title }}</div>
        <div class="meta"><span id="people">0</span> in call · <span id="timer">0 sec</span></div>
    </header>

    <main id="grid" aria-label="People in the call"></main>

    <footer>
        <button class="ctl" id="btnMic" title="Mute (M)" aria-label="Mute microphone"><i class="ri-mic-line"></i></button>
        <button class="ctl" id="btnCam" title="Camera (V)" aria-label="Turn camera on or off"><i class="ri-vidicon-line"></i></button>
        <button class="ctl" id="btnScreen" title="Share your screen" aria-label="Share your screen"><i class="ri-computer-line"></i></button>
        <button class="ctl hang" id="btnLeave" title="Leave call" aria-label="Leave call"><i class="ri-phone-fill" style="transform: rotate(135deg)"></i></button>
    </footer>

    <div class="lobby" id="lobby">
        <div class="card-x">
            <h5 class="mb-1">{{ $call->ended_at ? 'This call has ended' : 'Ready to join?' }}</h5>
            <p class="note mb-3">{{ $title }} · {{ $call->kind === 'audio' ? 'Voice call' : 'Video call' }}</p>
            @if($call->ended_at)
                <a href="{{ route('connect.show', $call->conversation_id) }}" class="btn btn-light">Back to the chat</a>
            @else
                <video id="preview" autoplay playsinline muted class="{{ $call->kind === 'audio' ? 'hidden' : '' }}"></video>
                <p class="note" id="lobbyNote">Checking your microphone{{ $call->kind === 'audio' ? '' : ' and camera' }}…</p>
                <div class="d-flex gap-2 justify-content-center">
                    <button class="btn btn-success px-4" id="btnJoin" disabled>Join now</button>
                    <a href="{{ route('connect.show', $call->conversation_id) }}" class="btn btn-outline-light">Not now</a>
                </div>
            @endif
        </div>
    </div>

    <div class="ended hidden" id="ended">
        <div class="text-center">
            <i class="ri-phone-line" style="font-size: 48px"></i>
            <h5 class="mt-2" id="endedText">You left the call</h5>
            <p class="note" id="endedTime"></p>
            <a href="{{ route('connect.show', $call->conversation_id) }}" class="btn btn-light">Back to the chat</a>
            <button class="btn btn-outline-light ms-1" id="btnRejoin">Rejoin</button>
        </div>
    </div>

@unless($call->ended_at)
<script>
(function () {
    const ME = @json($me->id);
    const KIND = @json($call->kind);
    const ICE = @json($iceServers);
    const URLS = {
        join: @json(route('connect.calls.join', $call)), poll: @json(route('connect.calls.poll', $call)),
        signal: @json(route('connect.calls.signal', $call)), leave: @json(route('connect.calls.leave', $call)),
    };
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

    let busy = false;
    async function check() {
        if (busy || !inCall) return;
        busy = true;
        try {
            const q = new URLSearchParams({ after, mic: micOn ? 1 : 0, cam: camOn && !!cam ? 1 : 0, screen: screen ? 1 : 0 });
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
            $('timer').textContent = d.duration || '';
            if (d.ended) return finish('The call has ended');
            if (!d.in) return finish('You were disconnected');
        } catch (e) {
        } finally { busy = false; }
    }
    function loop() { clearInterval(timer); check(); timer = setInterval(check, 1000); }

    function hangUp(beacon) {
        if (!inCall) return;
        inCall = false; clearInterval(timer);
        for (const id of peers.keys()) send(id, 'bye');
        [...peers.keys()].forEach(close);
        if (beacon) { const f = new FormData(); f.append('_token', token); navigator.sendBeacon(URLS.leave, f); }
        else fetch(URLS.leave, { method: 'POST', headers });
    }
    function finish(text) {
        hangUp(false);
        people.clear();
        document.querySelectorAll('.tile:not(.me)').forEach(t => t.remove());
        $('endedText').textContent = text;
        $('ended').classList.remove('hidden');
        $('btnRejoin').classList.toggle('hidden', text === 'The call has ended');
    }

    // ── Controls ───────────────────────────────────────────────────────
    $('btnMic').onclick = () => { micOn = !micOn; applyTracks(); };
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
    window.addEventListener('pagehide', () => hangUp(true));

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
