{{-- Status and do-not-disturb for the signed-in person: the dot on the avatar and the panel behind "Set a status". --}}
@php
    $presenceSvc = app(\App\Services\Presence\Presence::class);
    $myPresence = $presenceSvc->of(auth()->user());
    $myUser = auth()->user();
@endphp
<script>window.QPresence = @json(['dnd' => $myPresence['dnd'], 'state' => $myPresence['state']]);</script>
<div id="st-panel" class="st-panel" role="dialog" aria-labelledby="st-title" hidden>
    <div class="d-flex align-items-center mb-2">
        <h6 class="mb-0 flex-grow-1" id="st-title">Your status</h6>
        <button type="button" class="btn btn-sm btn-ghost-secondary" id="st-close" aria-label="Close"><i class="ri-close-line"></i></button>
    </div>
    <div class="small text-muted mb-3" id="st-line">{{ $presenceSvc->line($myUser) }}</div>

    <div class="st-label">Availability</div>
    <div class="st-choices mb-2" role="radiogroup" aria-label="Availability">
        @foreach(\App\Services\Presence\Presence::CHOICES as $k => $label)
            @php $color = \App\Services\Presence\Presence::STATES[$k === 'auto' ? 'online' : $k]['color']; @endphp
            <button type="button" class="st-choice {{ ($myUser->presence ?? 'auto') === $k ? 'active' : '' }}" data-presence="{{ $k }}" role="radio" aria-checked="{{ ($myUser->presence ?? 'auto') === $k ? 'true' : 'false' }}">
                <span class="st-dot" style="background: {{ $color }}"></span>{{ $label }}
            </button>
        @endforeach
    </div>
    <select class="form-select form-select-sm mb-1" id="st-presence-for" aria-label="For how long">
        <option value="">Until I change it</option>
        <option value="30">For 30 minutes</option>
        <option value="60">For 1 hour</option>
        <option value="120">For 2 hours</option>
        <option value="today">Until the end of today</option>
        <option value="tomorrow8">Until 8 am tomorrow</option>
        <option value="week">Until the end of the week</option>
    </select>
    <div class="form-text mb-3">Do not disturb silences sounds and phone notifications. Messages still arrive.</div>

    <div class="st-label">Status message</div>
    <div class="d-flex gap-2 mb-2">
        <input class="form-control form-control-sm st-emoji" id="st-emoji" maxlength="16" value="{{ \App\Services\Presence\Presence::status($myUser)['emoji'] ?? '' }}" placeholder="😀" aria-label="Emoji">
        <input class="form-control form-control-sm" id="st-text" maxlength="100" value="{{ \App\Services\Presence\Presence::status($myUser)['text'] ?? '' }}" placeholder="What's your status?" aria-label="Status message">
    </div>
    <div class="d-flex flex-wrap gap-1 mb-2">
        @foreach(\App\Services\Presence\Presence::PRESETS as [$e, $t])
            <button type="button" class="st-preset" data-emoji="{{ $e }}" data-text="{{ $t }}">{{ $e }} {{ $t }}</button>
        @endforeach
    </div>
    <select class="form-select form-select-sm mb-3" id="st-status-for" aria-label="Clear the status after">
        <option value="">Don't clear</option>
        <option value="30">Clear after 30 minutes</option>
        <option value="60">Clear after 1 hour</option>
        <option value="240">Clear after 4 hours</option>
        <option value="today" selected>Clear at the end of today</option>
        <option value="week">Clear at the end of the week</option>
    </select>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-sm btn-primary flex-grow-1" id="st-save">Save</button>
        <button type="button" class="btn btn-sm btn-light" id="st-clear">Clear status</button>
    </div>
</div>
<style>
    .st-avatar-dot { position: absolute; right: -3px; bottom: -3px; width: 13px; height: 13px; border-radius: 50%; border: 2px solid var(--vz-header-bg, #fff); }
    .st-panel { position: fixed; top: 64px; right: 12px; width: min(340px, calc(100vw - 24px)); background: var(--cb-surface, #fff); border: 1px solid var(--cb-border, #e2e8f0); border-radius: 12px; box-shadow: 0 12px 32px rgba(15,35,66,.18); padding: 14px; z-index: 10000; }
    .st-label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: var(--cb-muted); margin-bottom: 6px; }
    .st-choices { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; }
    .st-choice { display: flex; align-items: center; gap: 8px; border: 1px solid var(--cb-border); background: var(--cb-surface); border-radius: 8px; padding: 6px 8px; font-size: 13px; color: var(--cb-text); text-align: left; }
    .st-choice.active { border-color: var(--cb-teal); box-shadow: 0 0 0 1px var(--cb-teal); }
    .st-dot { width: 10px; height: 10px; border-radius: 50%; flex: 0 0 10px; }
    .st-emoji { max-width: 3.4rem; text-align: center; }
    .st-preset { border: 1px solid var(--cb-border); background: var(--cb-surface-2); border-radius: 14px; font-size: 12px; padding: 2px 8px; color: var(--cb-text); }
    .st-preset:hover { border-color: var(--cb-teal); }
</style>
<script>
(function () {
    const panel = document.getElementById('st-panel');
    const url = @json(route('status.update'));
    const token = document.querySelector('meta[name="csrf-token"]')?.content;
    let choice = @json($myUser->presence ?? 'auto');
    const dot = document.getElementById('st-avatar-dot');

    function paint(p) {
        window.QPresence = { dnd: p.dnd, state: p.state };
        if (dot) { dot.style.background = p.color; dot.title = p.line; }
        document.getElementById('st-line').textContent = p.line;
        document.dispatchEvent(new CustomEvent('presence:change', { detail: p }));
    }
    function open() { panel.hidden = false; document.getElementById('user-dropdown')?.style.setProperty('display', 'none'); panel.querySelector('.st-choice.active')?.focus(); }
    function close() { panel.hidden = true; }
    window.QStatus = { open };

    document.addEventListener('click', e => {
        if (e.target.closest('[data-open-status]')) { e.preventDefault(); open(); return; }
        if (!panel.hidden && !panel.contains(e.target)) close();
    });
    document.getElementById('st-close').addEventListener('click', close);
    panel.addEventListener('keydown', e => { if (e.key === 'Escape') close(); });
    panel.querySelectorAll('.st-choice').forEach(b => b.addEventListener('click', () => {
        choice = b.dataset.presence;
        panel.querySelectorAll('.st-choice').forEach(x => { x.classList.toggle('active', x === b); x.setAttribute('aria-checked', x === b); });
        document.getElementById('st-presence-for').disabled = choice === 'auto';
    }));
    document.getElementById('st-presence-for').disabled = choice === 'auto';
    panel.querySelectorAll('.st-preset').forEach(b => b.addEventListener('click', () => {
        document.getElementById('st-emoji').value = b.dataset.emoji;
        document.getElementById('st-text').value = b.dataset.text;
    }));

    function send(body) {
        return fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token }, body: JSON.stringify(body) })
            .then(r => r.ok ? r.json() : Promise.reject(r))
            .then(p => { paint(p); close(); window.Live?.toast(p.dnd ? 'Do not disturb is on. Sounds and phone notifications are paused.' : 'Status updated.'); })
            .catch(() => window.Live ? Live.toast('Your status was not saved.', 'danger') : alert('Your status was not saved.'));
    }
    document.getElementById('st-save').addEventListener('click', () => send({
        presence: choice, presence_for: document.getElementById('st-presence-for').value,
        status_emoji: document.getElementById('st-emoji').value, status_text: document.getElementById('st-text').value,
        status_for: document.getElementById('st-status-for').value,
    }));
    document.getElementById('st-clear').addEventListener('click', () => {
        document.getElementById('st-emoji').value = ''; document.getElementById('st-text').value = '';
        send({ clear_status: 1, presence: choice, presence_for: document.getElementById('st-presence-for').value });
    });

    // A timed status runs out while the page is open: check now and then.
    setInterval(() => { if (!document.hidden) fetch(@json(route('status.show')), { headers: { 'Accept': 'application/json' } }).then(r => r.ok ? r.json() : null).then(p => p && paint(p)).catch(() => {}); }, 120000);
})();
</script>
