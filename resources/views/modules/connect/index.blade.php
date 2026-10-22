@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">

    @include('partials.flash')

    <div class="cx-shell {{ $conversation ? 'has-open' : '' }}">
        {{-- ── Conversation list ───────────────────────────────────────── --}}
        <aside class="cx-side">
            <div class="cx-side-head">
                <h5 class="mb-0"><i class="ri-chat-smile-3-line"></i> Connect</h5>
                <div class="d-flex gap-1">
                    <button class="btn btn-sm btn-soft-primary" data-bs-toggle="modal" data-bs-target="#cxNewChat" title="New message"><i class="ri-edit-box-line"></i></button>
                    <button class="btn btn-sm btn-soft-secondary" data-bs-toggle="modal" data-bs-target="#cxNewGroup" title="New group"><i class="ri-group-line"></i></button>
                    <button class="btn btn-sm btn-soft-secondary" data-bs-toggle="modal" data-bs-target="#cxChannels" title="Channels"><i class="ri-hashtag"></i></button>
                </div>
            </div>
            <div class="cx-list" id="cxInbox">
                @include('modules.connect._inbox', ['active' => $conversation])
            </div>
        </aside>

        {{-- ── Open conversation ───────────────────────────────────────── --}}
        <section class="cx-main">
            @if(!$conversation)
                <div class="cx-empty">
                    <i class="ri-chat-smile-3-line"></i>
                    <h5>Qudroid Connect</h5>
                    <p class="text-muted">Pick a conversation, or start a new one.</p>
                </div>
            @else
                <div class="cx-head">
                    <a href="{{ route('connect.index') }}" class="btn btn-sm btn-ghost-secondary d-lg-none"><i class="ri-arrow-left-line"></i></a>
                    <div class="flex-grow-1 min-w-0">
                        <div class="fw-semibold text-truncate">{{ $conversation->titleFor($me) }}</div>
                        <div class="small text-muted text-truncate">
                            @if($conversation->isDirect())
                                {{ $conversation->otherUser($me)?->email }}
                            @else
                                {{ $conversation->members->count() }} {{ \Illuminate\Support\Str::plural('member', $conversation->members->count()) }}
                                @if($conversation->description) · {{ $conversation->description }}@endif
                                @if($conversation->project) · <a href="{{ route('projects.show', $conversation->project) }}">project</a>@endif
                            @endif
                        </div>
                    </div>
                    @if($participant)
                        <form method="POST" action="{{ route('connect.mute', $conversation) }}">
                            @csrf <input type="hidden" name="muted" value="{{ $participant->muted ? 0 : 1 }}">
                            <button class="btn btn-sm btn-ghost-secondary" title="{{ $participant->muted ? 'Unmute' : 'Mute' }}"><i class="{{ $participant->muted ? 'ri-notification-off-line' : 'ri-notification-3-line' }}"></i></button>
                        </form>
                    @endif
                    @unless($conversation->isDirect())
                        <button class="btn btn-sm btn-ghost-secondary" data-bs-toggle="modal" data-bs-target="#cxMembers" title="People"><i class="ri-team-line"></i></button>
                    @endunless
                </div>

                <div class="cx-scroll" id="cxScroll">
                    @if($messages->count() >= 50)
                        <div class="text-center my-2"><button class="btn btn-sm btn-soft-secondary" id="cxOlder">Load earlier messages</button></div>
                    @endif
                    <div id="cxMessages">
                        @foreach($messages as $m)
                            @include('modules.connect._message', ['m' => $m])
                        @endforeach
                    </div>
                </div>

                @if($participant)
                    <form class="cx-composer" id="cxComposer" method="POST" action="{{ route('connect.send', $conversation) }}" enctype="multipart/form-data">
                        @csrf
                        <input type="hidden" name="reply_to_id" id="cxReplyTo">
                        <div class="cx-replying d-none" id="cxReplying"><span></span><button type="button" class="btn btn-sm btn-ghost-secondary" id="cxReplyCancel"><i class="ri-close-line"></i></button></div>
                        <div class="cx-file-chosen d-none" id="cxFileChosen"><i class="ri-attachment-2"></i> <span></span><button type="button" class="btn btn-sm btn-ghost-secondary" id="cxFileCancel"><i class="ri-close-line"></i></button></div>
                        <div class="d-flex gap-2 align-items-end">
                            <label class="btn btn-ghost-secondary mb-0" title="Attach a file"><i class="ri-attachment-2 fs-5"></i><input type="file" name="attachment" id="cxFile" hidden></label>
                            <textarea name="body" id="cxBody" rows="1" class="form-control" maxlength="5000" placeholder="Write a message. Enter sends, Shift+Enter for a new line"></textarea>
                            <button class="btn btn-primary" id="cxSend"><i class="ri-send-plane-2-fill"></i></button>
                        </div>
                    </form>
                @elseif($conversation->type === 'channel')
                    <div class="cx-composer text-center">
                        <form method="POST" action="{{ route('connect.join', $conversation) }}">@csrf<button class="btn btn-primary">Join #{{ $conversation->name }}</button></form>
                    </div>
                @endif
            @endif
        </section>
    </div>

    {{-- ── New direct message ─────────────────────────────────────────── --}}
    <div class="modal fade" id="cxNewChat" tabindex="-1"><div class="modal-dialog modal-dialog-scrollable"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title">New message</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <input class="form-control mb-2 cx-filter" data-target="#cxPeopleList" placeholder="Search people">
            <div class="list-group" id="cxPeopleList">
                @foreach($people as $p)
                    <a href="{{ route('connect.direct', $p) }}" class="list-group-item list-group-item-action d-flex gap-2 align-items-center" data-search="{{ strtolower($p->name . ' ' . $p->email) }}">
                        <img src="{{ $p->avatar_url }}" class="rounded-circle" width="28" height="28" alt="">
                        <span>{{ $p->name }}<small class="d-block text-muted">{{ $p->user_type_label }}</small></span>
                    </a>
                @endforeach
            </div>
        </div>
    </div></div></div>

    {{-- ── New group ──────────────────────────────────────────────────── --}}
    <div class="modal fade" id="cxNewGroup" tabindex="-1"><div class="modal-dialog modal-dialog-scrollable"><div class="modal-content">
        <form method="POST" action="{{ route('connect.groups.store') }}" class="d-contents">
            @csrf
            <div class="modal-header"><h5 class="modal-title">New group</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <input name="name" class="form-control mb-2" maxlength="100" required placeholder="Group name">
                <input class="form-control form-control-sm mb-2 cx-filter" data-target="#cxGroupPeople" placeholder="Search people">
                <div id="cxGroupPeople" style="max-height: 50vh; overflow:auto">
                    @foreach($people as $p)
                        <label class="d-flex gap-2 align-items-center py-1" data-search="{{ strtolower($p->name . ' ' . $p->email) }}"><input type="checkbox" name="users[]" value="{{ $p->id }}" class="form-check-input mt-0"> {{ $p->name }}</label>
                    @endforeach
                </div>
            </div>
            <div class="modal-footer"><button class="btn btn-primary">Create group</button></div>
        </form>
    </div></div></div>

    {{-- ── Channels ───────────────────────────────────────────────────── --}}
    <div class="modal fade" id="cxChannels" tabindex="-1"><div class="modal-dialog modal-dialog-scrollable"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title">Channels</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <div class="list-group mb-3">
                @forelse($channels as $ch)
                    <a href="{{ route('connect.show', $ch) }}" class="list-group-item list-group-item-action"><strong>#{{ $ch->name }}</strong> <span class="text-muted small">· {{ $ch->members_count }} {{ \Illuminate\Support\Str::plural('member', $ch->members_count) }}</span>@if($ch->description)<div class="small text-muted">{{ $ch->description }}</div>@endif</a>
                @empty
                    <div class="text-muted small">No channels yet.</div>
                @endforelse
            </div>
            @if($me->isStaff())
                <form method="POST" action="{{ route('connect.channels.store') }}" class="border-top pt-3">
                    @csrf
                    <label class="form-label">New channel</label>
                    <div class="input-group mb-2"><span class="input-group-text">#</span><input name="name" class="form-control" maxlength="60" required placeholder="general"></div>
                    <input name="description" class="form-control mb-2" maxlength="255" placeholder="What it's for (optional)">
                    <button class="btn btn-primary btn-sm">Create channel</button>
                </form>
            @endif
        </div>
    </div></div></div>

    {{-- ── Members ────────────────────────────────────────────────────── --}}
    @if($conversation && !$conversation->isDirect())
        <div class="modal fade" id="cxMembers" tabindex="-1"><div class="modal-dialog modal-dialog-scrollable"><div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">People in {{ $conversation->titleFor($me) }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <ul class="list-group mb-3">
                    @foreach($conversation->members->sortBy('user.name') as $p)
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><img src="{{ $p->user->avatar_url }}" class="rounded-circle me-2" width="24" height="24" alt="">{{ $p->user->name }}@if($p->role === 'admin') <span class="badge bg-light text-muted">admin</span>@endif</span>
                            @if($isAdmin && $p->user_id !== $me->id && !$conversation->project_id)
                                <form method="POST" action="{{ route('connect.members.remove', [$conversation, $p->user]) }}" onsubmit="return confirm('Remove {{ e($p->user->name) }}?')">@csrf @method('DELETE')<button class="btn btn-sm btn-ghost-danger"><i class="ri-close-line"></i></button></form>
                            @endif
                        </li>
                    @endforeach
                </ul>
                @if($participant && !$conversation->project_id && ($isAdmin || $conversation->type === 'channel'))
                    <form method="POST" action="{{ route('connect.members.add', $conversation) }}" class="mb-3">
                        @csrf
                        <label class="form-label">Add people</label>
                        <select name="users[]" class="form-select mb-2" multiple size="6">
                            @foreach($people->whereNotIn('id', $conversation->members->pluck('user_id')) as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach
                        </select>
                        <button class="btn btn-sm btn-primary">Add</button>
                    </form>
                @endif
                @if($participant && !$conversation->project_id)
                    <form method="POST" action="{{ route('connect.leave', $conversation) }}" onsubmit="return confirm('Leave this conversation?')">@csrf<button class="btn btn-sm btn-soft-danger">Leave</button></form>
                @endif
                @if($conversation->project_id)<div class="small text-muted">This chat follows the project team. Change people on the project.</div>@endif
            </div>
        </div></div></div>
    @endif

</div></div></div>
@endsection

@push('styles')
<style>
    .cx-shell { display: grid; grid-template-columns: 320px 1fr; height: calc(100vh - 170px); min-height: 480px; background: var(--cb-surface); border: 1px solid var(--cb-border); border-radius: var(--cb-radius); overflow: hidden; }
    .cx-side { border-right: 1px solid var(--cb-border); display: flex; flex-direction: column; min-height: 0; }
    .cx-side-head { display: flex; justify-content: space-between; align-items: center; padding: 14px; border-bottom: 1px solid var(--cb-border); }
    .cx-list { overflow-y: auto; flex: 1; }
    .cx-item { display: flex; gap: 10px; padding: 10px 14px; color: var(--cb-text); text-decoration: none; border-bottom: 1px solid var(--cb-border); }
    .cx-item:hover, .cx-item.active { background: var(--cb-hover); color: var(--cb-text); }
    .cx-item.unread .cx-title, .cx-item.unread .cx-preview { font-weight: 700; color: var(--cb-heading); }
    .cx-avatar { flex: 0 0 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; background: var(--cb-surface-2); color: var(--cb-teal); font-size: 18px; overflow: hidden; }
    .cx-avatar img { width: 100%; height: 100%; object-fit: cover; }
    .cx-meta { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 2px; }
    .cx-title { color: var(--cb-heading); font-weight: 600; }
    .cx-time, .cx-preview { font-size: 12px; color: var(--cb-muted); }
    .cx-main { display: flex; flex-direction: column; min-height: 0; min-width: 0; }
    .cx-empty { margin: auto; text-align: center; }
    .cx-empty i { font-size: 56px; color: var(--cb-teal); }
    .cx-head { display: flex; gap: 8px; align-items: center; padding: 12px 16px; border-bottom: 1px solid var(--cb-border); }
    .min-w-0 { min-width: 0; }
    .cx-scroll { flex: 1; overflow-y: auto; padding: 16px; background: var(--cb-surface-2); }
    .cx-system { text-align: center; font-size: 12px; color: var(--cb-muted); margin: 8px 0; }
    .cx-msg { display: flex; gap: 8px; margin: 6px 0; align-items: flex-end; }
    .cx-msg.mine { flex-direction: row-reverse; }
    .cx-msg-avatar { width: 28px; height: 28px; border-radius: 50%; object-fit: cover; }
    .cx-bubble-wrap { max-width: min(72%, 640px); display: flex; flex-direction: column; }
    .cx-msg.mine .cx-bubble-wrap { align-items: flex-end; }
    .cx-author { font-size: 12px; color: var(--cb-muted); margin: 0 0 2px 4px; }
    .cx-bubble { background: var(--cb-surface); border: 1px solid var(--cb-border); border-radius: 14px; padding: 8px 12px 6px; position: relative; word-wrap: break-word; overflow-wrap: anywhere; }
    .cx-msg.mine .cx-bubble { background: #d9f4ef; border-color: #b6e6dc; }
    [data-bs-theme="dark"] .cx-msg.mine .cx-bubble { background: #134e48; border-color: #1b6b62; }
    .cx-bubble.deleted { font-style: italic; color: var(--cb-muted); }
    .cx-stamp { display: block; text-align: right; font-size: 10px; color: var(--cb-muted); margin-top: 2px; }
    .cx-quote { border-left: 3px solid var(--cb-teal); padding: 2px 8px; margin-bottom: 4px; font-size: 12px; color: var(--cb-muted); background: rgba(13,148,136,.06); border-radius: 4px; }
    .cx-image { max-width: 260px; max-height: 260px; border-radius: 10px; display: block; margin-bottom: 4px; }
    .cx-file { display: flex; gap: 8px; align-items: center; padding: 6px 8px; border-radius: 8px; background: rgba(0,0,0,.04); margin-bottom: 4px; }
    .cx-file i { font-size: 22px; }
    .cx-reactions { display: flex; gap: 4px; margin-top: 2px; flex-wrap: wrap; }
    .cx-react { border: 1px solid var(--cb-border); background: var(--cb-surface); border-radius: 12px; font-size: 12px; padding: 0 6px; }
    .cx-react.me { border-color: var(--cb-teal); background: rgba(13,148,136,.1); }
    .cx-actions { opacity: 0; transition: opacity .15s; }
    .cx-msg:hover .cx-actions, .cx-actions:focus-within { opacity: 1; }
    .cx-composer { border-top: 1px solid var(--cb-border); padding: 10px 12px; background: var(--cb-surface); }
    .cx-composer textarea { resize: none; max-height: 160px; }
    .cx-replying, .cx-file-chosen { display: flex; justify-content: space-between; align-items: center; font-size: 12px; color: var(--cb-muted); border-left: 3px solid var(--cb-teal); padding: 2px 8px; margin-bottom: 6px; }
    .d-contents { display: contents; }
    @media (max-width: 991px) {
        .cx-shell { grid-template-columns: 1fr; height: calc(100vh - 140px); }
        .cx-shell.has-open .cx-side { display: none; }
        .cx-shell:not(.has-open) .cx-main { display: none; }
        .cx-actions { opacity: 1; }
    }
</style>
@endpush

@push('scripts')
<script>
(function () {
    const token = document.querySelector('meta[name="csrf-token"]')?.content;
    const headers = { 'Accept': 'application/json', 'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest' };

    // Filter lists in the modals.
    document.querySelectorAll('.cx-filter').forEach(input => input.addEventListener('input', () => {
        const q = input.value.trim().toLowerCase();
        document.querySelectorAll(input.dataset.target + ' [data-search]').forEach(el => el.style.display = !q || el.dataset.search.includes(q) ? '' : 'none');
    }));

    const pollBase = @json(route('connect.poll', $conversation ? [$conversation] : []));
    const badge = document.getElementById('connect-nav-badge');
    const inbox = document.getElementById('cxInbox');
    const scroll = document.getElementById('cxScroll');
    const list = document.getElementById('cxMessages');
    let lastId = list ? Math.max(0, ...[...list.querySelectorAll('[data-id]')].map(e => +e.dataset.id)) : 0;
    let busy = false;

    const nearBottom = () => !scroll || scroll.scrollHeight - scroll.scrollTop - scroll.clientHeight < 120;
    const toBottom = () => { if (scroll) scroll.scrollTop = scroll.scrollHeight; };
    toBottom();

    function poll(force) {
        if (busy || (!force && document.hidden)) return;
        busy = true;
        const url = pollBase + '?inbox=1&after=' + lastId + '&since=10';
        fetch(url, { headers }).then(r => r.ok ? r.json() : null).then(data => {
            if (!data) return;
            if (badge) { badge.textContent = data.total; badge.style.display = data.total ? '' : 'none'; }
            if (data.inbox && inbox) inbox.innerHTML = data.inbox;
            if (list && data.html) {
                const stick = nearBottom();
                const tmp = document.createElement('div'); tmp.innerHTML = data.html;
                tmp.querySelectorAll(':scope > [data-id]').forEach(el => { if (!list.querySelector('[data-id="' + el.dataset.id + '"]')) list.appendChild(el); });
                if (stick) toBottom();
            }
            if (list && data.changed) {
                Object.entries(data.changed).forEach(([id, html]) => {
                    const old = list.querySelector('[data-id="' + id + '"]');
                    if (old) { const t = document.createElement('div'); t.innerHTML = html; old.replaceWith(t.firstElementChild); }
                });
            }
            if (data.last_id) lastId = Math.max(lastId, data.last_id);
        }).catch(() => {}).finally(() => busy = false);
    }
    setInterval(poll, 4000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) poll(true); });

    const form = document.getElementById('cxComposer');
    if (!form) return;
    const body = document.getElementById('cxBody');
    const file = document.getElementById('cxFile');
    const replyTo = document.getElementById('cxReplyTo');
    const replying = document.getElementById('cxReplying');
    const chosen = document.getElementById('cxFileChosen');

    const grow = () => { body.style.height = 'auto'; body.style.height = Math.min(160, body.scrollHeight) + 'px'; };
    body.addEventListener('input', grow);
    body.addEventListener('keydown', e => { if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) { e.preventDefault(); form.requestSubmit(); } });
    file.addEventListener('change', () => { chosen.classList.toggle('d-none', !file.files.length); chosen.querySelector('span').textContent = file.files[0]?.name || ''; });
    document.getElementById('cxFileCancel').addEventListener('click', () => { file.value = ''; chosen.classList.add('d-none'); });
    document.getElementById('cxReplyCancel').addEventListener('click', () => { replyTo.value = ''; replying.classList.add('d-none'); });

    form.addEventListener('submit', e => {
        e.preventDefault();
        if (!body.value.trim() && !file.files.length) return;
        const fd = new FormData(form);
        const send = document.getElementById('cxSend');
        send.disabled = true;
        fetch(form.action, { method: 'POST', headers, body: fd })
            .then(r => r.ok ? r.json() : r.json().then(j => Promise.reject(j)))
            .then(() => {
                body.value = ''; grow(); file.value = ''; chosen.classList.add('d-none'); replyTo.value = ''; replying.classList.add('d-none');
                poll(true); setTimeout(toBottom, 300);
            })
            .catch(j => alert((j && j.message) || 'The message was not sent. Please try again.'))
            .finally(() => { send.disabled = false; body.focus(); });
    });

    // Message actions (delegated, so new messages work too).
    const msgUrl = @json(url('connect/m')) + '/';
    list.addEventListener('click', e => {
        const msg = e.target.closest('.cx-msg'); if (!msg) return;
        const id = msg.dataset.id;
        const react = e.target.closest('.cx-react');
        if (react) {
            fetch(msgUrl + id + '/react', { method: 'POST', headers: { ...headers, 'Content-Type': 'application/json' }, body: JSON.stringify({ emoji: react.dataset.emoji }) }).then(() => poll(true));
        } else if (e.target.closest('.cx-reply')) {
            const b = e.target.closest('.cx-reply');
            replyTo.value = id; replying.classList.remove('d-none');
            replying.querySelector('span').textContent = 'Replying to ' + (b.dataset.name || '') + ': ' + b.dataset.preview;
            body.focus();
        } else if (e.target.closest('.cx-edit')) {
            const text = prompt('Edit message', msg.dataset.body || '');
            if (text !== null) fetch(msgUrl + id, { method: 'PUT', headers: { ...headers, 'Content-Type': 'application/json' }, body: JSON.stringify({ body: text }) }).then(() => poll(true));
        } else if (e.target.closest('.cx-delete')) {
            if (confirm('Delete this message for everyone?')) fetch(msgUrl + id, { method: 'DELETE', headers }).then(() => poll(true));
        }
    });

    const older = document.getElementById('cxOlder');
    if (older) older.addEventListener('click', () => {
        const first = list.querySelector('[data-id]');
        fetch(@json($conversation ? route('connect.older', $conversation) : '') + '?before=' + first.dataset.id, { headers }).then(r => r.json()).then(data => {
            const h = scroll.scrollHeight;
            list.insertAdjacentHTML('afterbegin', data.html);
            scroll.scrollTop = scroll.scrollHeight - h;
            if (!data.more) older.remove();
        });
    });
})();
</script>
@endpush
