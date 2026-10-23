@extends('modules.mail.layout')

@section('mail')
@php $binView = in_array($view, ['trash', 'junk'], true); @endphp
<div class="qm-bar" id="qmBar">
    <input type="checkbox" class="form-check-input m-0 mx-2" id="qmAll" aria-label="Select all">
    <span class="qm-nosel-only">
        <form method="POST" action="{{ route('mail.sync', $account) }}" class="d-inline">@csrf<button class="btn-icon" title="Check for new mail" aria-label="Check for new mail"><i class="ri-refresh-line"></i></button></form>
        @if($view === 'trash' && $threads->total())<span class="small text-muted ms-2">Messages in the bin are deleted for good when you choose Delete for good.</span>@endif
    </span>
    @if($binView)
        <button class="btn-icon qm-sel-only" data-bulk="inbox" title="Move to inbox" aria-label="Move to inbox"><i class="ri-inbox-unarchive-line"></i></button>
        <button class="btn-icon qm-sel-only" data-bulk="delete" title="Delete for good" aria-label="Delete for good"><i class="ri-delete-bin-2-line"></i></button>
    @else
        @if($view !== 'archive')<button class="btn-icon qm-sel-only" data-bulk="archive" title="Archive" aria-label="Archive"><i class="ri-archive-line"></i></button>@endif
        <button class="btn-icon qm-sel-only" data-bulk="spam" title="Report spam" aria-label="Report spam"><i class="ri-spam-2-line"></i></button>
        <button class="btn-icon qm-sel-only" data-bulk="trash" title="Delete" aria-label="Delete"><i class="ri-delete-bin-6-line"></i></button>
    @endif
    <button class="btn-icon qm-sel-only" data-bulk="read" title="Mark as read" aria-label="Mark as read"><i class="ri-mail-open-line"></i></button>
    <button class="btn-icon qm-sel-only" data-bulk="unread" title="Mark as unread" aria-label="Mark as unread"><i class="ri-mail-unread-line"></i></button>
    @if(!$binView)
        <button class="btn-icon qm-sel-only" data-bulk="snooze" title="Snooze" aria-label="Snooze"><i class="ri-time-line"></i></button>
        @if($view === 'snoozed')<button class="btn-icon qm-sel-only" data-bulk="unsnooze" title="Unsnooze" aria-label="Unsnooze"><i class="ri-alarm-off-line"></i></button>@endif
    @endif
    <div class="dropdown qm-sel-only">
        <button class="btn-icon" data-bs-toggle="dropdown" title="Move to" aria-label="Move to"><i class="ri-folder-transfer-line"></i></button>
        <div class="dropdown-menu">
            <h6 class="dropdown-header">Move to</h6>
            <button class="dropdown-item" data-bulk="inbox">Inbox</button>
            @foreach($labels as $l)<button class="dropdown-item" data-bulk="move:{{ $l->id }}">{{ $l->name }}</button>@endforeach
            <button class="dropdown-item" data-bulk="spam">Spam</button>
            <button class="dropdown-item" data-bulk="trash">Bin</button>
        </div>
    </div>
    <div class="qm-pager">
        @if($threads->total())
            {{ $threads->firstItem() }}–{{ $threads->lastItem() }} of {{ number_format($threads->total()) }}
            <a class="btn-icon {{ $threads->onFirstPage() ? 'disabled opacity-25' : '' }}" href="{{ $threads->previousPageUrl() ?? '#' }}" aria-label="Newer"><i class="ri-arrow-left-s-line"></i></a>
            <a class="btn-icon {{ $threads->hasMorePages() ? '' : 'disabled opacity-25' }}" href="{{ $threads->nextPageUrl() ?? '#' }}" aria-label="Older"><i class="ri-arrow-right-s-line"></i></a>
        @endif
    </div>
</div>

@include('modules.mail._list')
@include('modules.mail._snooze')
@endsection

@push('scripts')
<script>
@verbatim
(function () {
    const B = JSON.parse(document.getElementById('qm').dataset.boot);
    const bar = document.getElementById('qmBar');
    const $$ = (s, el) => Array.from((el || document).querySelectorAll(s));
    let list = document.getElementById('qmList');
    let cursor = -1;

    const rows = () => $$('.qm-row', list);
    const selected = () => rows().filter(r => r.querySelector('[data-sel]').checked);
    function syncBar() {
        const n = selected().length, all = rows().length;
        bar.classList.toggle('qm-has-sel', n > 0);
        const box = document.getElementById('qmAll');
        box.checked = n > 0 && n === all; box.indeterminate = n > 0 && n < all;
        rows().forEach(r => r.classList.toggle('selected', r.querySelector('[data-sel]').checked));
    }
    document.getElementById('qmAll').addEventListener('change', e => { rows().forEach(r => r.querySelector('[data-sel]').checked = e.target.checked); syncBar(); });

    async function act(action, keys, extra) {
        const removes = ['archive', 'trash', 'spam', 'inbox', 'delete'].includes(action) || action.startsWith('move:') || action === 'snooze'
            || (action === 'unsnooze' && B.view === 'snoozed') || (action === 'unstar' && B.view === 'starred');
        if (action === 'delete' && !confirm('Delete ' + (keys.length === 1 ? 'this conversation' : keys.length + ' conversations') + ' for good? This cannot be undone.')) return;
        const hit = rows().filter(r => keys.includes(r.dataset.key));
        if (removes) hit.forEach(r => r.style.display = 'none');
        try {
            const d = await qmPost(B.urls.act, Object.assign({ action, threads: keys, view: B.view }, extra || {}));
            qmCounts(d.counts);
            if (removes) {
                hit.forEach(r => r.remove());
                const undo = { archive: 'inbox', trash: 'inbox', spam: 'inbox' }[action];
                qmToast(d.message, undo && B.view === 'inbox' ? async () => { await qmPost(B.urls.act, { action: undo, threads: keys }); refresh(true); } : null);
            } else {
                hit.forEach(r => {
                    if (action === 'read' || action === 'unread') r.classList.toggle('unread', action === 'unread');
                    if (action === 'star' || action === 'unstar') { const s = r.querySelector('[data-star]'); s.classList.toggle('on', action === 'star'); s.innerHTML = '<i class="' + (action === 'star' ? 'ri-star-fill' : 'ri-star-line') + '"></i>'; }
                    const t = r.querySelector('[data-act="read"],[data-act="unread"]');
                    if (t && (action === 'read' || action === 'unread')) { const u = action === 'unread'; t.dataset.act = u ? 'read' : 'unread'; t.innerHTML = '<i class="' + (u ? 'ri-mail-open-line' : 'ri-mail-unread-line') + '"></i>'; }
                });
                if (!['star', 'unstar'].includes(action)) qmToast(d.message);
            }
            syncBar();
        } catch (e) {
            hit.forEach(r => r.style.display = '');
            qmToast(e.message);
        }
    }
    window.qmListAct = act;

    // Snooze picker (shared with the conversation page).
    let snoozeKeys = [];
    function snooze(keys) { snoozeKeys = keys; bootstrap.Modal.getOrCreateInstance(document.getElementById('qmSnooze')).show(); }
    document.addEventListener('qm:snooze', e => { try { bootstrap.Modal.getOrCreateInstance(document.getElementById('qmSnooze')).hide(); } catch (x) {} act('snooze', snoozeKeys, { until: e.detail }); });

    function bind() {
        list.addEventListener('click', e => {
            const row = e.target.closest('.qm-row'); if (!row) return;
            if (e.target.closest('[data-sel]')) { syncBar(); return; }
            const star = e.target.closest('[data-star]');
            if (star) { act(star.classList.contains('on') ? 'unstar' : 'star', [row.dataset.key]); return; }
            const a = e.target.closest('[data-act]');
            if (a) { a.dataset.act === 'snooze' ? snooze([row.dataset.key]) : act(a.dataset.act, [row.dataset.key]); return; }
            if (e.ctrlKey || e.metaKey) window.open(row.dataset.href, '_blank'); else location.href = row.dataset.href;
        });
    }
    bind();
    bar.addEventListener('click', e => {
        const b = e.target.closest('[data-bulk]'); if (!b) return;
        const keys = selected().map(r => r.dataset.key); if (!keys.length) return;
        b.dataset.bulk === 'snooze' ? snooze(keys) : act(b.dataset.bulk, keys);
    });

    // New mail shows up without a reload, unless you are in the middle of choosing.
    async function refresh(force) {
        if (!force && (selected().length || document.getElementById('qmCompose').contains(document.activeElement))) return;
        try {
            const html = await (await fetch(location.href, { headers: { 'X-Live': '1', Accept: 'text/html' } })).text();
            const t = document.createElement('div'); t.innerHTML = html;
            const fresh = t.querySelector('#qmList');
            if (fresh) { list.replaceWith(fresh); list = fresh; bind(); cursor = -1; syncBar(); }
        } catch (e) {}
    }
    document.addEventListener('qm:new', () => refresh());
    document.addEventListener('qm:sent', () => { if (['sent', 'scheduled'].includes(B.view)) refresh(); });

    function focusRow(i) {
        const r = rows(); if (!r.length) return;
        cursor = Math.max(0, Math.min(r.length - 1, i));
        r.forEach((x, j) => x.style.boxShadow = j === cursor ? 'inset 4px 0 0 #4285f4' : '');
        r[cursor].scrollIntoView({ block: 'nearest' });
    }
    document.addEventListener('qm:key', ({ detail: e }) => {
        const r = rows(), cur = r[cursor];
        const keys = selected().length ? selected().map(x => x.dataset.key) : (cur ? [cur.dataset.key] : []);
        if (e.key === 'j') focusRow(cursor + 1);
        else if (e.key === 'k') focusRow(cursor - 1);
        else if (e.key === 'x' && cur) { const c = cur.querySelector('[data-sel]'); c.checked = !c.checked; syncBar(); }
        else if ((e.key === 'Enter' || e.key === 'o') && cur) location.href = cur.dataset.href;
        else if (!keys.length) return;
        else if (e.key === 'e' && B.view !== 'archive') act('archive', keys);
        else if (e.key === '#') act(['trash', 'junk'].includes(B.view) ? 'delete' : 'trash', keys);
        else if (e.key === '!') act('spam', keys);
        else if (e.key === 's' && cur) act(cur.querySelector('[data-star]').classList.contains('on') ? 'unstar' : 'star', [cur.dataset.key]);
        else if (e.key === 'I') act('read', keys);
        else if (e.key === 'U') act('unread', keys);
        else if (e.key === 'b') snooze(keys);
    });
})();
@endverbatim
</script>
@endpush
