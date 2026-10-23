{{-- Incoming calls, on every portal page: a prompt to join or decline. --}}
<div id="cxRing" class="cx-ring d-none" role="alertdialog" aria-live="assertive" aria-labelledby="cxRingWho">
    <img id="cxRingAvatar" alt="">
    <div class="flex-grow-1 min-w-0">
        <div class="fw-semibold text-truncate" id="cxRingWho"></div>
        <div class="small text-muted text-truncate" id="cxRingWhat"></div>
    </div>
    <button type="button" class="btn btn-sm btn-danger rounded-pill" id="cxRingNo" aria-label="Decline"><i class="ri-phone-fill" style="display:inline-block;transform:rotate(135deg)"></i></button>
    <a class="btn btn-sm btn-success rounded-pill" id="cxRingYes" target="_blank" rel="noopener" aria-label="Join"><i class="ri-phone-line"></i> Join</a>
</div>
<style>
    .cx-ring { position: fixed; right: 20px; bottom: 20px; z-index: 2000; width: min(360px, calc(100vw - 32px)); background: var(--vz-card-bg, #fff); border: 1px solid var(--cb-border, #e9ebec); border-radius: 16px; box-shadow: 0 12px 40px rgba(0,0,0,.18); padding: 12px 14px; display: flex; align-items: center; gap: 10px; animation: cxRingIn .25s ease-out; }
    .cx-ring img { width: 44px; height: 44px; border-radius: 50%; object-fit: cover; animation: cxPulse 1.4s infinite; }
    @keyframes cxRingIn { from { transform: translateY(20px); opacity: 0; } to { transform: none; opacity: 1; } }
    @keyframes cxPulse { 0% { box-shadow: 0 0 0 0 rgba(34,197,94,.6); } 100% { box-shadow: 0 0 0 14px rgba(34,197,94,0); } }
</style>
<script>
(function () {
    const box = document.getElementById('cxRing');
    if (!box || location.pathname.indexOf('/connect/calls/') === 0) return;
    const url = @json(route('connect.calls.ringing'));
    const token = document.querySelector('meta[name="csrf-token"]')?.content;
    const dismissed = new Set();
    let current = null, stopTone = null;

    function ring() { if (!stopTone && window.QSound) stopTone = QSound.ringtone(); }
    function quiet() { if (stopTone) { stopTone(); stopTone = null; } }
    function show(c) {
        current = c;
        document.getElementById('cxRingAvatar').src = c.avatar || '';
        document.getElementById('cxRingWho').textContent = c.from || 'Someone';
        document.getElementById('cxRingWhat').textContent = (c.kind === 'audio' ? 'Voice call' : 'Video call') + (c.where ? ' in ' + c.where : '');
        document.getElementById('cxRingYes').href = c.url;
        box.classList.remove('d-none');
        ring();
    }
    function hide() { box.classList.add('d-none'); current = null; quiet(); }
    document.getElementById('cxRingYes').addEventListener('click', () => { if (current) dismissed.add(current.id); hide(); });
    document.getElementById('cxRingNo').addEventListener('click', () => {
        if (!current) return;
        dismissed.add(current.id);
        fetch(current.decline, { method: 'POST', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest' } });
        hide();
    });
    function check() {
        fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } }).then(r => r.ok ? r.json() : null).then(d => {
            const c = (d?.calls || []).find(c => !dismissed.has(c.id));
            if (c && (!current || current.id !== c.id)) show(c);
            else if (!c && current) hide();
        }).catch(() => {});
    }
    check();
    setInterval(check, 4000);
    document.addEventListener('realtime:call', () => check());
})();
</script>
