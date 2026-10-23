{{-- resources/views/layouts/partials/notification-bell.blade.php — the bell, the notification centre and banners --}}
@php
    $pnUnread = 0;
    try { $pnUnread = \Illuminate\Support\Facades\Schema::hasTable('notifications') ? auth()->user()->unreadNotifications()->count() : 0; } catch (\Throwable $e) {}
@endphp
<div class="position-relative" id="pn-wrapper">
    <button type="button" id="pn-btn" class="btn btn-icon btn-topbar btn-ghost-dark rounded-circle position-relative" style="width:38px;height:38px;" aria-label="Notifications" aria-haspopup="dialog" aria-expanded="false" aria-controls="nc-panel">
        <i class="ri-notification-3-line fs-20"></i>
        <span id="pn-count" class="position-absolute badge rounded-pill bg-danger" style="top:2px;right:0;font-size:10px;{{ $pnUnread ? '' : 'display:none;' }}">{{ $pnUnread > 99 ? '99+' : $pnUnread }}</span>
    </button>
</div>

{{-- The notification centre slides in from the right. --}}
<div id="nc-scrim" hidden></div>
<aside id="nc-panel" role="dialog" aria-modal="false" aria-label="Notifications" hidden>
    <header class="nc-head">
        <h2>Notifications</h2>
        <div class="nc-head-tools">
            <button type="button" class="nc-link" id="nc-readall">Mark all read</button>
            <a class="nc-icon-btn" href="{{ route('notifications.index') }}#settings" title="Notification settings" aria-label="Notification settings"><i class="ri-settings-3-line"></i></a>
            <button type="button" class="nc-icon-btn" id="nc-close" aria-label="Close"><i class="ri-close-line"></i></button>
        </div>
    </header>
    <div class="nc-seg" role="tablist" aria-label="Show">
        @foreach(\App\Services\Messaging\NotificationPresenter::FILTERS as $k => $label)
            <button type="button" role="tab" data-filter="{{ $k }}" aria-selected="{{ $k === 'all' ? 'true' : 'false' }}">{{ $label }}</button>
        @endforeach
    </div>
    <div class="nc-quiet" id="nc-quiet" hidden><i class="ri-moon-line"></i> Quiet hours: banners and sounds are paused.</div>
    <div class="nc-body" id="nc-list" aria-live="polite"><div class="nc-empty">Loading…</div></div>
    <footer class="nc-foot">
        <button type="button" class="nc-link" id="nc-sound"><i class="ri-volume-up-line"></i> <span>Sounds on</span></button>
        <a class="nc-link" href="{{ route('notifications.index') }}">See all</a>
    </footer>
</aside>
<div id="nc-banners" aria-live="polite"></div>

<style>
#nc-scrim { position: fixed; inset: 0; z-index: 1055; background: transparent; }
#nc-panel {
    position: fixed; top: 10px; right: 10px; bottom: 10px; width: 400px; max-width: calc(100vw - 20px); z-index: 1056;
    display: flex; flex-direction: column; border-radius: 22px; overflow: hidden;
    background: var(--qd-glass, rgba(255,255,255,.85)); -webkit-backdrop-filter: saturate(180%) blur(30px); backdrop-filter: saturate(180%) blur(30px);
    border: .5px solid var(--qd-sep, rgba(0,0,0,.1)); box-shadow: var(--qd-shadow-pop, 0 18px 50px rgba(0,0,0,.18));
    font-family: var(--qd-font, system-ui); color: var(--qd-label, #1d1d1f);
    animation: ncIn .28s cubic-bezier(.25,.1,.25,1);
}
#nc-panel[hidden], #nc-scrim[hidden] { display: none !important; }
@keyframes ncIn { from { opacity: 0; transform: translateX(24px); } to { opacity: 1; transform: none; } }
.nc-head { display: flex; align-items: center; justify-content: space-between; padding: 18px 18px 8px; }
.nc-head h2 { font-size: 24px; font-weight: 700; letter-spacing: -.02em; margin: 0; color: inherit; }
.nc-head-tools { display: flex; align-items: center; gap: 4px; }
.nc-link { background: none; border: 0; padding: 4px 6px; color: var(--qd-tint-text, #405189); font-size: 13px; font-weight: 500; text-decoration: none; border-radius: 6px; }
.nc-link:hover { background: var(--qd-fill, rgba(120,120,128,.12)); color: var(--qd-tint-text, #405189); }
.nc-icon-btn { width: 30px; height: 30px; border-radius: 50%; border: 0; background: var(--qd-fill, rgba(120,120,128,.12)); color: var(--qd-label-2, #6e6e73); display: inline-flex; align-items: center; justify-content: center; font-size: 16px; text-decoration: none; }
.nc-icon-btn:hover { background: var(--qd-fill-2, rgba(120,120,128,.2)); color: var(--qd-label, #1d1d1f); }
.nc-seg { display: flex; gap: 2px; margin: 4px 18px 10px; padding: 2px; border-radius: 9px; background: var(--qd-fill, rgba(120,120,128,.12)); }
.nc-seg button { flex: 1; border: 0; background: transparent; border-radius: 7px; padding: 5px 4px; font-size: 12.5px; font-weight: 500; color: var(--qd-label, #1d1d1f); }
.nc-seg button[aria-selected="true"] { background: var(--qd-card, #fff); box-shadow: 0 3px 8px rgba(0,0,0,.12), 0 3px 1px rgba(0,0,0,.04); font-weight: 600; }
[data-bs-theme="dark"] .nc-seg button[aria-selected="true"] { background: #636366; }
.nc-quiet { margin: 0 18px 8px; font-size: 12px; color: var(--qd-label-2, #6e6e73); background: var(--qd-fill, rgba(120,120,128,.12)); border-radius: 9px; padding: 6px 10px; }
.nc-body { flex: 1; overflow-y: auto; padding: 0 12px 12px; overscroll-behavior: contain; }
.nc-section { font-size: 13px; font-weight: 600; color: var(--qd-label-2, #6e6e73); margin: 12px 6px 6px; display: flex; justify-content: space-between; }
.nc-empty { text-align: center; color: var(--qd-label-2, #6e6e73); padding: 60px 20px; font-size: 14px; }
.nc-empty i { display: block; font-size: 40px; opacity: .35; margin-bottom: 10px; }
.nc-foot { display: flex; justify-content: space-between; padding: 8px 14px; border-top: .5px solid var(--qd-sep, rgba(0,0,0,.1)); }

/* One notification. Shared by the panel, the banners and the notifications page. */
.nc-item { position: relative; background: var(--qd-card, #fff); border-radius: 16px; padding: 11px 13px 12px; margin-bottom: 8px; box-shadow: 0 1px 2px rgba(0,0,0,.05), 0 0 0 .5px var(--qd-sep, rgba(0,0,0,.08)); cursor: pointer; transition: transform .12s; }
.nc-item:active { transform: scale(.99); }
.nc-item.is-stack { margin-bottom: 18px; }
.nc-item.is-stack::after, .nc-item.is-stack::before { content: ''; position: absolute; left: 10px; right: 10px; bottom: -6px; height: 12px; border-radius: 0 0 14px 14px; background: var(--qd-card, #fff); opacity: .7; box-shadow: 0 1px 2px rgba(0,0,0,.05), 0 0 0 .5px var(--qd-sep, rgba(0,0,0,.08)); z-index: -1; }
.nc-item.is-stack::before { left: 20px; right: 20px; bottom: -11px; opacity: .45; }
.nc-item.open::after, .nc-item.open::before { display: none; }
.nc-top { display: flex; align-items: center; gap: 7px; font-size: 12px; color: var(--qd-label-2, #6e6e73); margin-bottom: 4px; }
.nc-app { width: 20px; height: 20px; border-radius: 6px; display: inline-flex; align-items: center; justify-content: center; color: #fff; font-size: 12px; flex-shrink: 0; }
.nc-appname { text-transform: uppercase; letter-spacing: .02em; font-size: 11px; font-weight: 500; flex: 1; min-width: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.nc-title { font-size: 14px; font-weight: 600; line-height: 1.3; color: var(--qd-label, #1d1d1f); }
.nc-text { font-size: 13.5px; line-height: 1.35; color: var(--qd-label, #1d1d1f); opacity: .85; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; white-space: pre-line; }
.nc-dot { position: absolute; left: -2px; top: 16px; width: 8px; height: 8px; border-radius: 50%; background: var(--qd-blue, #007aff); }
.nc-x { position: absolute; top: -7px; left: -7px; width: 22px; height: 22px; border-radius: 50%; border: 0; background: var(--qd-card-2, #f2f2f7); box-shadow: 0 1px 3px rgba(0,0,0,.2); color: var(--qd-label-2, #6e6e73); font-size: 13px; display: none; align-items: center; justify-content: center; padding: 0; z-index: 2; }
.nc-item:hover .nc-x, .nc-item:focus-within .nc-x { display: inline-flex; }
.nc-actions { display: flex; gap: 6px; margin-top: 9px; flex-wrap: wrap; }
.nc-btn { border: 0; border-radius: 980px; padding: 5px 14px; font-size: 13px; font-weight: 500; background: var(--qd-fill, rgba(120,120,128,.12)); color: var(--qd-tint-text, #405189); }
.nc-btn:hover { background: var(--qd-fill-2, rgba(120,120,128,.2)); }
.nc-btn.primary { background: var(--qd-tint, #405189); color: #fff; }
.nc-btn.primary:hover { filter: brightness(.92); }
.nc-btn.destructive { color: var(--qd-red, #ff3b30); }
.nc-btn[disabled] { opacity: .5; }
.nc-input { display: flex; gap: 6px; margin-top: 9px; }
.nc-input textarea { flex: 1; border: .5px solid var(--qd-sep-strong, rgba(0,0,0,.2)); border-radius: 12px; padding: 6px 10px; font-size: 13.5px; resize: none; background: var(--qd-card, #fff); color: inherit; min-height: 34px; max-height: 120px; }
.nc-input textarea:focus { outline: none; border-color: var(--qd-tint, #405189); box-shadow: 0 0 0 3px rgba(var(--qd-tint-rgb, 64,81,137), .18); }
.nc-done { margin-top: 7px; font-size: 12.5px; font-weight: 500; color: var(--qd-green, #34c759); }
.nc-error { margin-top: 7px; font-size: 12.5px; color: var(--qd-red, #ff3b30); }
.nc-more { font-size: 12px; color: var(--qd-label-2, #6e6e73); margin-top: 6px; background: none; border: 0; padding: 0; }
.nc-sub { border-top: .5px solid var(--qd-sep, rgba(0,0,0,.1)); margin-top: 9px; padding-top: 8px; }
.nc-sub a { display: block; color: inherit; text-decoration: none; padding: 4px 0; font-size: 13px; }
.nc-sub a b { font-weight: 600; }
.nc-sub a span { color: var(--qd-label-2, #6e6e73); }

/* Banners, top right, like macOS */
#nc-banners { position: fixed; top: 76px; right: 16px; z-index: 1060; width: 360px; max-width: calc(100vw - 32px); display: flex; flex-direction: column; gap: 8px; pointer-events: none; }
#nc-banners .nc-item { pointer-events: auto; margin: 0; background: var(--qd-glass, rgba(255,255,255,.85)); -webkit-backdrop-filter: saturate(180%) blur(30px); backdrop-filter: saturate(180%) blur(30px); box-shadow: var(--qd-shadow-pop, 0 18px 50px rgba(0,0,0,.18)); animation: ncBanner .35s cubic-bezier(.2,.9,.3,1.2); }
#nc-banners .nc-item.leaving { animation: ncBannerOut .25s ease forwards; }
@keyframes ncBanner { from { opacity: 0; transform: translateX(40px); } to { opacity: 1; transform: none; } }
@keyframes ncBannerOut { to { opacity: 0; transform: translateX(40px); } }
@media (max-width: 575.98px) { #nc-panel { top: 0; right: 0; bottom: 0; border-radius: 0; max-width: 100vw; width: 100vw; } #nc-banners { top: 64px; right: 8px; left: 8px; width: auto; } }
@media (prefers-reduced-motion: reduce) { #nc-panel, #nc-banners .nc-item { animation: none !important; } }
</style>

<script>
window.QNotify = (function () {
    const FEED = @json(route('notifications.feed')), READALL = @json(route('notifications.read-all'));
    const ACT = @json(route('notifications.act', ['__ID__', '__ACTION__'])), READ = @json(route('notifications.read', '__ID__')), DEL = @json(route('notifications.destroy', '__ID__'));
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const btn = document.getElementById('pn-btn'), panel = document.getElementById('nc-panel'), scrim = document.getElementById('nc-scrim');
    const list = document.getElementById('nc-list'), count = document.getElementById('pn-count'), banners = document.getElementById('nc-banners');
    // The frosted top bar (backdrop-filter) would trap fixed children; the panel lives on <body>.
    [scrim, panel, banners].forEach(el => el && document.body.appendChild(el));
    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    let filter = 'all', quiet = false, lastFocus = null;

    function setCount(n) { count.textContent = n > 99 ? '99+' : n; count.style.display = n ? '' : 'none'; document.dispatchEvent(new CustomEvent('qnotify:unread', { detail: n })); }
    function send(url, method, body) {
        return fetch(url, { method, headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' }, body: body ? JSON.stringify(body) : null })
            .then(r => r.json().catch(() => ({})).then(j => ({ ok: r.ok, j })));
    }

    /** One notification as HTML. opts.banner: no stack, no clear button. */
    function card(n, opts = {}) {
        const stack = !opts.banner && n.stack > 0;
        const acts = (n.actions || []).map(a => '<button type="button" class="nc-btn ' + esc(a.style || '') + '" data-act="' + esc(a.id) + '"' + (a.input ? ' data-input="' + esc(a.input) + '"' : '') + '>' + esc(a.label) + '</button>').join('');
        return '<div class="nc-item' + (stack ? ' is-stack' : '') + '" data-id="' + esc(n.id) + '" data-url="' + esc(n.open_url) + '" data-ids="' + esc((n.stack_ids || [n.id]).join(',')) + '" tabindex="0">' +
            (n.read ? '' : '<span class="nc-dot" aria-label="Unread"></span>') +
            (opts.banner ? '' : '<button type="button" class="nc-x" data-clear aria-label="Clear"><i class="ri-close-line"></i></button>') +
            '<div class="nc-top"><span class="nc-app" style="background:' + esc(n.color) + '"><i class="' + esc(n.icon) + '"></i></span><span class="nc-appname">' + esc(n.group_label) + '</span><span>' + esc(n.time) + '</span></div>' +
            '<div class="nc-title">' + esc(n.title) + '</div>' +
            (n.body ? '<div class="nc-text">' + esc(n.body) + '</div>' : '') +
            (n.done ? '<div class="nc-done"><i class="ri-check-line"></i> ' + esc(n.done) + '</div>' : '') +
            (acts ? '<div class="nc-actions">' + acts + '</div>' : '') +
            (stack ? '<button type="button" class="nc-more" data-more>' + n.stack + ' more from here' + (n.stack_unread > (n.read ? 0 : 1) ? ', ' + (n.stack_unread - (n.read ? 0 : 1)) + ' unread' : '') + '</button>' +
                '<div class="nc-sub" hidden>' + (n.more || []).map(m => '<a href="' + esc(m.open_url) + '"><b>' + esc(m.title) + '</b> <span>' + esc(m.time) + '</span><br><span>' + esc(m.body) + '</span></a>').join('') + '</div>' : '') +
            '</div>';
    }

    function render(items) {
        if (!items.length) {
            list.innerHTML = '<div class="nc-empty"><i class="ri-notification-off-line"></i>' + (filter === 'all' ? 'No notifications. You\'re all caught up.' : 'Nothing here.') + '</div>';
            return;
        }
        const today = new Date().toDateString();
        const isToday = n => n.created_at && new Date(n.created_at).toDateString() === today;
        const t = items.filter(isToday), e = items.filter(n => !isToday(n));
        list.innerHTML = (t.length ? '<div class="nc-section"><span>Today</span></div>' + t.map(n => card(n)).join('') : '') +
            (e.length ? '<div class="nc-section"><span>Earlier</span></div>' + e.map(n => card(n)).join('') : '');
    }

    async function load() {
        try {
            const j = await fetch(FEED + '?filter=' + encodeURIComponent(filter), { headers: { Accept: 'application/json' } }).then(r => r.json());
            setCount(j.unread || 0); quiet = !!j.quiet;
            document.getElementById('nc-quiet').hidden = !quiet;
            render(j.items || []);
            return j;
        } catch (e) { list.innerHTML = '<div class="nc-empty">Could not load notifications.</div>'; }
    }

    function open() {
        lastFocus = document.activeElement;
        panel.hidden = false; scrim.hidden = false; btn.setAttribute('aria-expanded', 'true');
        load(); setTimeout(() => panel.querySelector('.nc-seg [aria-selected="true"]')?.focus(), 50);
    }
    function close() { panel.hidden = true; scrim.hidden = true; btn.setAttribute('aria-expanded', 'false'); lastFocus?.focus?.(); }
    btn.addEventListener('click', e => { e.stopPropagation(); panel.hidden ? open() : close(); });
    scrim.addEventListener('click', close);
    document.getElementById('nc-close').addEventListener('click', close);
    document.addEventListener('keydown', e => { if (e.key === 'Escape' && !panel.hidden) close(); });
    panel.querySelectorAll('.nc-seg button').forEach(b => b.addEventListener('click', () => {
        panel.querySelectorAll('.nc-seg button').forEach(x => x.setAttribute('aria-selected', x === b ? 'true' : 'false'));
        filter = b.dataset.filter; load();
    }));
    document.getElementById('nc-readall').addEventListener('click', async () => { await send(READALL, 'POST'); load(); });

    /** Clicks inside any notification card (panel, banners, notifications page). */
    async function onClick(e) {
        const item = e.target.closest('.nc-item'); if (!item) return;
        const id = item.dataset.id;
        if (e.target.closest('[data-clear]')) {
            e.stopPropagation();
            item.style.opacity = '.3';
            const r = await send(DEL.replace('__ID__', id), 'DELETE', { ids: item.dataset.ids.split(',') });
            if (r.ok) { item.remove(); setCount(r.j.unread || 0); }
            return;
        }
        if (e.target.closest('[data-more]')) { e.stopPropagation(); const sub = item.querySelector('.nc-sub'); sub.hidden = !sub.hidden; item.classList.toggle('open', !sub.hidden); return; }
        if (e.target.closest('.nc-input, .nc-sub a')) return;
        const a = e.target.closest('[data-act]');
        if (a) {
            e.stopPropagation();
            if (a.dataset.input && !item.querySelector('.nc-input')) {
                // Buttons that need words (Reply, a reason) open a box first.
                const box = document.createElement('form');
                box.className = 'nc-input';
                box.innerHTML = '<textarea rows="1" placeholder="' + esc(a.dataset.input) + '" aria-label="' + esc(a.dataset.input) + '"></textarea><button class="nc-btn primary" type="submit">Send</button>';
                item.querySelector('.nc-actions').after(box);
                const ta = box.querySelector('textarea'); ta.focus();
                ta.addEventListener('keydown', ev => { if (ev.key === 'Enter' && !ev.shiftKey) { ev.preventDefault(); box.requestSubmit(); } });
                box.addEventListener('submit', ev => { ev.preventDefault(); run(item, a.dataset.act, ta.value); });
                return;
            }
            if (!a.dataset.input) run(item, a.dataset.act, null);
            return;
        }
        // Anywhere else opens it.
        location.href = item.dataset.url;
    }
    async function run(item, action, input) {
        item.querySelectorAll('.nc-btn').forEach(b => b.disabled = true);
        item.querySelector('.nc-error')?.remove();
        const r = await send(ACT.replace('__ID__', item.dataset.id).replace('__ACTION__', action), 'POST', { input });
        if (r.ok && r.j.ok) {
            item.querySelector('.nc-actions')?.remove(); item.querySelector('.nc-input')?.remove(); item.querySelector('.nc-dot')?.remove();
            (item.querySelector('.nc-text') || item.querySelector('.nc-title')).insertAdjacentHTML('afterend', '<div class="nc-done"><i class="ri-check-line"></i> ' + esc(r.j.done) + '</div>');
            setCount(r.j.unread || 0);
            if (item.closest('#nc-banners')) setTimeout(() => dismiss(item), 1500);
        } else {
            item.querySelectorAll('.nc-btn').forEach(b => b.disabled = false);
            (item.querySelector('.nc-input') || item.querySelector('.nc-actions')).insertAdjacentHTML('afterend', '<div class="nc-error">' + esc(r.j.message || 'That didn\'t work. Try again.') + '</div>');
        }
    }
    panel.addEventListener('click', onClick);
    banners.addEventListener('click', onClick);
    document.addEventListener('keydown', e => { if (e.key === 'Enter' && e.target.classList?.contains('nc-item')) location.href = e.target.dataset.url; });

    // ── Banners for new notifications ──
    function dismiss(el) { el.classList.add('leaving'); setTimeout(() => el.remove(), 250); }
    function banner(n) {
        if (!n || n.level === 'passive' || quiet) return;
        const wrap = document.createElement('div');
        wrap.innerHTML = card(n, { banner: true });
        const el = wrap.firstChild;
        banners.prepend(el);
        while (banners.children.length > 3) banners.lastChild.remove();
        let t = setTimeout(() => dismiss(el), n.level === 'urgent' ? 12000 : 6000);
        el.addEventListener('mouseenter', () => clearTimeout(t));
        el.addEventListener('mouseleave', () => { t = setTimeout(() => dismiss(el), 4000); });
        el.addEventListener('focusin', () => clearTimeout(t));
    }

    // Sounds
    const snd = document.getElementById('nc-sound');
    function showSound() {
        const on = !window.QSound || QSound.on();
        snd.querySelector('i').className = on ? 'ri-volume-up-line' : 'ri-volume-mute-line';
        snd.querySelector('span').textContent = on ? 'Sounds on' : 'Sounds off';
    }
    snd.addEventListener('click', () => { if (!window.QSound) return; QSound.setOn(!QSound.on()); showSound(); if (QSound.on()) QSound.notify(); });
    window.addEventListener('load', showSound);

    // Check every 30 seconds (or at once with instant updates): badge, banner and chime for anything new.
    let seen = null;
    const onConnect = location.pathname.indexOf('/connect') === 0;
    function refresh() {
        fetch(FEED + '?filter=unread', { headers: { Accept: 'application/json' } }).then(r => r.json()).then(j => {
            setCount(j.unread || 0); quiet = !!j.quiet;
            const latest = j.latest || null;
            if (seen !== null && latest && latest.id !== seen) {
                const chat = latest.type === 'message';
                if (!(chat && onConnect)) {
                    banner(latest);
                    if (window.QSound && !quiet && latest.level !== 'passive') { if (chat) QSound.message(); else if (latest.type !== 'call') QSound.notify(); }
                }
                if (!panel.hidden) load();
            }
            seen = latest ? latest.id : '';
        }).catch(() => {});
    }
    refresh();
    setInterval(refresh, 30000);
    let soon;
    document.addEventListener('realtime:notification', () => { clearTimeout(soon); soon = setTimeout(refresh, 300); });

    return { card, onClick, open, refresh, setCount };
})();
</script>
