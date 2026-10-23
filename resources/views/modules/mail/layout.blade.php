@extends('layouts.master')
@section('title', 'Qmail')

{{-- Qmail frame: account switcher, folders on the left, the page on the right, and the compose window. --}}
@php
    $views = [
        'inbox' => ['ri-inbox-line', 'Inbox'],
        'starred' => ['ri-star-line', 'Starred'],
        'snoozed' => ['ri-time-line', 'Snoozed'],
        'sent' => ['ri-send-plane-line', 'Sent'],
        'drafts' => ['ri-draft-line', 'Drafts'],
        'scheduled' => ['ri-calendar-schedule-line', 'Scheduled'],
        'archive' => ['ri-mail-open-line', 'All mail'],
        'junk' => ['ri-spam-2-line', 'Spam'],
        'trash' => ['ri-delete-bin-6-line', 'Bin'],
    ];
    $boot = [
        'account' => $account->id, 'email' => $account->email, 'view' => $view,
        'urls' => [
            'compose' => route('mail.compose', $account), 'draft' => route('mail.drafts.save', $account), 'send' => route('mail.send', $account),
            'discard' => route('mail.drafts.discard', [$account, '__ID__']), 'contacts' => route('mail.contacts', $account),
            'act' => route('mail.act', $account), 'poll' => route('mail.poll', $account), 'box' => route('mail.box', [$account, $view === 'settings' ? 'inbox' : $view]),
        ],
        'maxMb' => (int) config('qudroid.mail.max_attachment_mb', 25),
    ];
@endphp

@section('content')
<div class="main-content"><div class="page-content qm-page"><div class="container-fluid">
    @include('partials.flash')
    <div class="qm" id="qm" data-boot='@json($boot)'>
        <aside class="qm-side">
            <button type="button" class="btn btn-primary qm-compose-btn" data-compose="new"><i class="ri-pencil-line me-2"></i>Compose</button>

            @if($accounts->count() > 1)
                <div class="dropdown qm-acct">
                    <button class="btn btn-light btn-sm w-100 text-start dropdown-toggle text-truncate" data-bs-toggle="dropdown" aria-label="Switch mailbox">
                        <i class="ri-at-line me-1"></i>{{ $account->email }}
                    </button>
                    <div class="dropdown-menu w-100">
                        @foreach($accounts as $a)
                            <a class="dropdown-item text-truncate {{ $a->id === $account->id ? 'active' : '' }}" href="{{ route('mail.box', [$a, 'inbox']) }}">
                                {{ $a->email }}@if($a->kind === 'shared') <span class="badge bg-info-subtle text-info ms-1">Shared</span>@endif
                            </a>
                        @endforeach
                    </div>
                </div>
            @else
                <div class="qm-acct-one text-truncate" title="{{ $account->email }}"><i class="ri-at-line me-1"></i>{{ $account->email }}</div>
            @endif

            <nav class="qm-nav" aria-label="Mail folders">
                @foreach($views as $key => [$ico, $label])
                    @php $n = $counts[$key] ?? 0; @endphp
                    <a href="{{ route('mail.box', [$account, $key]) }}" class="{{ $view === $key ? 'active' : '' }} {{ $n && in_array($key, ['inbox', 'junk'], true) ? 'has-new' : '' }}" data-view="{{ $key }}">
                        <i class="{{ $ico }}"></i><span>{{ $label }}</span><b class="qm-count" data-count="{{ $key }}">{{ $n ?: '' }}</b>
                    </a>
                @endforeach
            </nav>
            @if($labels->isNotEmpty())
                <div class="qm-side-head">Labels</div>
                <nav class="qm-nav">
                    @foreach($labels as $l)
                        @php $n = $counts[(string) $l->id] ?? 0; @endphp
                        <a href="{{ route('mail.box', [$account, $l->id]) }}" class="{{ (string) $view === (string) $l->id ? 'active' : '' }} {{ $n ? 'has-new' : '' }}">
                            <i class="ri-price-tag-3-line"></i><span class="text-truncate">{{ $l->name }}</span><b class="qm-count" data-count="{{ $l->id }}">{{ $n ?: '' }}</b>
                        </a>
                    @endforeach
                </nav>
            @endif
            <div class="qm-side-foot">
                <a href="{{ route('mail.settings', $account) }}" class="{{ $view === 'settings' ? 'active' : '' }}"><i class="ri-settings-3-line"></i>Settings</a>
                @can('Manage mail')<a href="{{ route('mail.admin.index') }}"><i class="ri-shield-user-line"></i>Mailboxes</a>@endcan
                <span class="qm-status" id="qmStatus">
                    @if($account->status === 'error')<i class="ri-error-warning-line text-danger"></i> {{ $account->last_error }}
                    @elseif($account->last_synced_at) Checked {{ $account->last_synced_at->diffForHumans() }}
                    @else <span class="spinner-border spinner-border-sm"></span> Loading your mail…
                    @endif
                </span>
            </div>
        </aside>

        <section class="qm-main">
            @if($view !== 'settings')
                <form class="qm-search" action="{{ route('mail.box', [$account, in_array($view, ['drafts', 'scheduled'], true) ? 'inbox' : $view]) }}" role="search">
                    <i class="ri-search-line"></i>
                    <input name="q" value="{{ $q }}" placeholder="Search mail  (from:ada has:attachment is:unread)" aria-label="Search mail" id="qmSearch" autocomplete="off">
                    @if($q !== '')<a href="{{ route('mail.box', [$account, $view]) }}" class="qm-search-clear" aria-label="Clear search"><i class="ri-close-line"></i></a>@endif
                </form>
            @endif
            @yield('mail')
        </section>
    </div>

    {{-- Compose window (Gmail-style, bottom right). --}}
    <div class="qm-compose shadow-lg" id="qmCompose" hidden role="dialog" aria-label="New message">
        <div class="qm-compose-head">
            <span id="qmComposeTitle">New message</span>
            <span class="ms-auto d-flex gap-1">
                <button type="button" class="btn btn-sm btn-link text-white p-0 px-1" data-cmd="min" aria-label="Minimise"><i class="ri-subtract-line"></i></button>
                <button type="button" class="btn btn-sm btn-link text-white p-0 px-1" data-cmd="max" aria-label="Full screen"><i class="ri-fullscreen-line"></i></button>
                <button type="button" class="btn btn-sm btn-link text-white p-0 px-1" data-cmd="close" aria-label="Save and close"><i class="ri-close-line"></i></button>
            </span>
        </div>
        <form class="qm-compose-body" id="qmComposeForm" autocomplete="off">
            <div class="qm-field"><label for="qmTo">To</label><input id="qmTo" name="to" class="qm-addr" multiple>
                <button type="button" class="btn btn-link btn-sm p-0" data-cmd="ccbcc">Cc Bcc</button></div>
            <div class="qm-field" data-ccbcc hidden><label for="qmCc">Cc</label><input id="qmCc" name="cc" class="qm-addr"></div>
            <div class="qm-field" data-ccbcc hidden><label for="qmBcc">Bcc</label><input id="qmBcc" name="bcc" class="qm-addr"></div>
            <div class="qm-field"><input id="qmSubject" name="subject" placeholder="Subject" aria-label="Subject"></div>
            <div class="qm-editor" id="qmEditor" contenteditable="true" role="textbox" aria-multiline="true" aria-label="Message"></div>
            <div class="qm-files" id="qmFiles"></div>
            <div class="qm-suggest dropdown-menu" id="qmSuggest"></div>
            <div class="qm-compose-foot">
                <div class="btn-group">
                    <button type="submit" class="btn btn-primary btn-sm px-3" id="qmSend">Send</button>
                    <button type="button" class="btn btn-primary btn-sm dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-label="Schedule send"></button>
                    <div class="dropdown-menu p-2" style="min-width:240px">
                        <div class="small fw-semibold mb-1">Schedule send</div>
                        <button type="button" class="dropdown-item" data-schedule="tomorrow8">Tomorrow morning, 8:00 AM</button>
                        <button type="button" class="dropdown-item" data-schedule="tomorrow13">Tomorrow afternoon, 1:00 PM</button>
                        <button type="button" class="dropdown-item" data-schedule="monday8">Monday morning, 8:00 AM</button>
                        <div class="dropdown-divider"></div>
                        <label class="small text-muted" for="qmSendAt">Pick date and time</label>
                        <div class="d-flex gap-1"><input type="datetime-local" class="form-control form-control-sm" id="qmSendAt"><button type="button" class="btn btn-sm btn-soft-primary" data-schedule="pick">Set</button></div>
                    </div>
                </div>
                <div class="qm-tools">
                    <button type="button" data-fmt="bold" aria-label="Bold"><i class="ri-bold"></i></button>
                    <button type="button" data-fmt="italic" aria-label="Italic"><i class="ri-italic"></i></button>
                    <button type="button" data-fmt="underline" aria-label="Underline"><i class="ri-underline"></i></button>
                    <button type="button" data-fmt="insertUnorderedList" aria-label="Bulleted list"><i class="ri-list-unordered"></i></button>
                    <button type="button" data-fmt="insertOrderedList" aria-label="Numbered list"><i class="ri-list-ordered"></i></button>
                    <button type="button" data-fmt="formatBlock:blockquote" aria-label="Quote"><i class="ri-double-quotes-l"></i></button>
                    <button type="button" data-fmt="link" aria-label="Insert link"><i class="ri-link"></i></button>
                    <button type="button" data-fmt="removeFormat" aria-label="Clear formatting"><i class="ri-format-clear"></i></button>
                    <label class="mb-0" aria-label="Attach files" title="Attach files"><i class="ri-attachment-2"></i><input type="file" multiple hidden id="qmAttach"></label>
                </div>
                <span class="qm-saved small text-muted ms-auto" id="qmSaved"></span>
                <button type="button" class="btn btn-sm btn-link text-muted" data-cmd="discard" aria-label="Discard draft"><i class="ri-delete-bin-line"></i></button>
            </div>
        </form>
    </div>
    <div class="qm-toast" id="qmToast" role="status" aria-live="polite" hidden></div>
</div></div></div>
@endsection

@push('styles')
<style>
    .qm-page { padding-bottom: 0; }
    .qm-compose[hidden], .qm-toast[hidden], .qm-compose [hidden] { display: none !important; }
    .qm { display: grid; grid-template-columns: 236px 1fr; gap: 1rem; min-height: calc(100vh - 150px); }
    .qm-side { display: flex; flex-direction: column; gap: .5rem; }
    .qm-compose-btn { border-radius: 16px; padding: .85rem 1.2rem; font-weight: 600; align-self: flex-start; box-shadow: 0 1px 3px rgba(0,0,0,.15); }
    .qm-acct-one { font-size: .8rem; color: var(--vz-secondary-color, #878a99); padding: 0 .4rem; }
    .qm-nav a { display: flex; align-items: center; gap: .75rem; padding: .38rem .9rem; border-radius: 0 18px 18px 0; color: inherit; font-size: .875rem; margin-left: -.5rem; }
    .qm-nav a i { font-size: 1.05rem; color: var(--vz-secondary-color, #878a99); }
    .qm-nav a:hover { background: rgba(64,81,137,.06); }
    .qm-nav a.active { background: rgba(234,67,53,.12); font-weight: 700; color: #c5221f; }
    .qm-nav a.active i { color: #c5221f; }
    .qm-nav a.has-new span { font-weight: 700; }
    .qm-nav a span { flex: 1; min-width: 0; }
    .qm-count { font-size: .75rem; font-weight: 700; }
    .qm-side-head { font-size: .7rem; text-transform: uppercase; letter-spacing: .06em; color: var(--vz-secondary-color, #878a99); margin: .75rem 0 .2rem .4rem; font-weight: 600; }
    .qm-side-foot { margin-top: auto; display: flex; flex-direction: column; gap: .25rem; font-size: .8rem; padding: .5rem .4rem; }
    .qm-side-foot a { color: inherit; display: flex; gap: .5rem; align-items: center; }
    .qm-side-foot a.active { font-weight: 700; }
    .qm-status { color: var(--vz-secondary-color, #878a99); font-size: .75rem; margin-top: .25rem; }
    .qm-main { background: var(--vz-secondary-bg, #fff); border-radius: 16px; min-width: 0; display: flex; flex-direction: column; box-shadow: 0 1px 2px rgba(56,65,74,.08); }
    .qm-search { display: flex; align-items: center; gap: .6rem; margin: .75rem; padding: .55rem 1rem; border-radius: 24px; background: var(--vz-light, #f3f6f9); }
    .qm-search input { border: 0; background: transparent; flex: 1; outline: none; color: inherit; }
    .qm-search:focus-within { background: var(--vz-secondary-bg, #fff); box-shadow: 0 1px 4px rgba(0,0,0,.18); }
    .qm-search-clear { color: inherit; }
    .qm-bar { display: flex; align-items: center; gap: .15rem; padding: .25rem .75rem; border-bottom: 1px solid var(--vz-border-color, #e9ebec); min-height: 44px; flex-wrap: wrap; }
    .qm-bar .btn-icon { border: 0; background: none; width: 36px; height: 36px; border-radius: 50%; color: var(--vz-secondary-color, #5f6368); display: inline-flex; align-items: center; justify-content: center; font-size: 1.1rem; }
    .qm-bar .btn-icon:hover { background: rgba(0,0,0,.06); color: inherit; }
    .qm-bar .qm-pager { margin-left: auto; font-size: .78rem; color: var(--vz-secondary-color, #5f6368); display: flex; align-items: center; gap: .25rem; }
    .qm-sel-only { display: none !important; }
    .qm-has-sel .qm-sel-only { display: inline-flex !important; }
    .qm-has-sel .qm-nosel-only { display: none !important; }
    .qm-list { list-style: none; margin: 0; padding: 0; }
    .qm-row { display: grid; grid-template-columns: 28px 28px minmax(120px, 200px) 1fr auto; align-items: center; gap: .4rem; padding: 0 1rem 0 .75rem; height: 42px; border-bottom: 1px solid var(--vz-border-color, #f1f3f4); cursor: pointer; font-size: .875rem; position: relative; background: rgba(242,245,252,.6); }
    .qm-row.unread { background: var(--vz-secondary-bg, #fff); font-weight: 700; }
    .qm-row:hover { box-shadow: inset 1px 0 0 #dadce0, inset -1px 0 0 #dadce0, 0 1px 2px rgba(60,64,67,.3); z-index: 1; }
    .qm-row.selected { background: #c2dbff; }
    .qm-row .who { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .qm-row .who small { font-weight: 400; color: var(--vz-secondary-color, #5f6368); }
    .qm-row .what { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; min-width: 0; }
    .qm-row .what .snip { font-weight: 400; color: var(--vz-secondary-color, #5f6368); }
    .qm-row .when { font-size: .78rem; white-space: nowrap; text-align: right; }
    .qm-row .row-acts { display: none; position: absolute; right: .75rem; background: inherit; gap: .1rem; }
    .qm-row:hover .row-acts { display: flex; }
    .qm-row:hover .when { visibility: hidden; }
    .qm-row .row-acts button { border: 0; background: none; width: 32px; height: 32px; border-radius: 50%; color: var(--vz-secondary-color, #5f6368); }
    .qm-row .row-acts button:hover { background: rgba(0,0,0,.08); }
    .qm-star { border: 0; background: none; color: #c4c7c5; padding: 0; font-size: 1.05rem; line-height: 1; }
    .qm-star.on { color: #f4b400; }
    .qm-empty { text-align: center; padding: 4rem 1rem; color: var(--vz-secondary-color, #5f6368); }
    .qm-empty i { font-size: 3rem; display: block; margin-bottom: .5rem; opacity: .5; }
    .qm-thread { padding: .5rem 1.5rem 2rem 1.5rem; }
    .qm-thread h1 { font-size: 1.35rem; font-weight: 400; margin: .5rem 0 1rem 3.25rem; }
    .qm-msg { border-bottom: 1px solid var(--vz-border-color, #e9ebec); padding: .75rem 0; }
    .qm-msg-head { display: flex; gap: .75rem; align-items: flex-start; cursor: pointer; }
    .qm-ava { width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 600; flex-shrink: 0; }
    .qm-msg-head .meta { flex: 1; min-width: 0; }
    .qm-msg-head .meta .to { font-size: .78rem; color: var(--vz-secondary-color, #5f6368); }
    .qm-msg-head .when { font-size: .78rem; color: var(--vz-secondary-color, #5f6368); white-space: nowrap; }
    .qm-msg .snip { color: var(--vz-secondary-color, #5f6368); font-size: .85rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .qm-msg.collapsed .qm-msg-body, .qm-msg.collapsed .qm-msg-acts, .qm-msg:not(.collapsed) .snip { display: none; }
    .qm-msg-body { margin: .5rem 0 0 3.25rem; }
    .qm-msg-body iframe { width: 100%; border: 0; min-height: 60px; display: block; }
    .qm-atts { display: flex; flex-wrap: wrap; gap: .5rem; margin: .75rem 0 0 3.25rem; }
    .qm-att { border: 1px solid var(--vz-border-color, #dadce0); border-radius: 8px; padding: .45rem .7rem; font-size: .8rem; display: flex; align-items: center; gap: .45rem; color: inherit; max-width: 240px; }
    .qm-att:hover { background: rgba(0,0,0,.04); }
    .qm-att span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .qm-msg-acts { margin: 1rem 0 0 3.25rem; display: flex; gap: .5rem; flex-wrap: wrap; }
    .qm-msg-acts .btn { border-radius: 18px; }
    .qm-images { margin: 0 0 .5rem 3.25rem; font-size: .8rem; background: var(--vz-light, #f3f6f9); padding: .4rem .75rem; border-radius: 6px; }
    .qm-compose { position: fixed; right: 24px; bottom: 0; width: 540px; max-width: calc(100vw - 16px); background: var(--vz-secondary-bg, #fff); border-radius: 10px 10px 0 0; z-index: 1060; display: flex; flex-direction: column; max-height: 82vh; }
    .qm-compose.max { right: 50%; transform: translateX(50%); bottom: 5vh; width: min(960px, 92vw); height: 88vh; max-height: none; border-radius: 10px; }
    .qm-compose.min .qm-compose-body { display: none; }
    .qm-compose.min { width: 280px; }
    .qm-compose-head { background: #404040; color: #fff; padding: .55rem .9rem; border-radius: 10px 10px 0 0; display: flex; align-items: center; font-size: .875rem; font-weight: 600; cursor: pointer; }
    .qm-compose-body { display: flex; flex-direction: column; flex: 1; min-height: 0; position: relative; }
    .qm-field { display: flex; align-items: center; gap: .5rem; border-bottom: 1px solid var(--vz-border-color, #eceff1); padding: .3rem .9rem; font-size: .875rem; }
    .qm-field label { color: var(--vz-secondary-color, #5f6368); margin: 0; min-width: 28px; }
    .qm-field input { border: 0; outline: none; flex: 1; background: transparent; color: inherit; padding: .2rem 0; min-width: 0; }
    .qm-editor { flex: 1; min-height: 220px; overflow-y: auto; padding: .75rem .9rem; outline: none; font-size: .9rem; }
    .qm-editor blockquote { margin: 0 0 0 .6em; padding-left: .8em; border-left: 2px solid #dadce0; color: #5f6368; }
    .qm-files { display: flex; flex-wrap: wrap; gap: .4rem; padding: 0 .9rem; }
    .qm-files .qm-att { padding: .3rem .55rem; }
    .qm-files .qm-att button { border: 0; background: none; padding: 0; color: inherit; }
    .qm-compose-foot { display: flex; align-items: center; gap: .5rem; padding: .6rem .9rem; flex-wrap: wrap; }
    .qm-tools { display: flex; gap: .1rem; }
    .qm-tools button, .qm-tools label { border: 0; background: none; width: 30px; height: 30px; border-radius: 4px; color: var(--vz-secondary-color, #5f6368); display: inline-flex; align-items: center; justify-content: center; cursor: pointer; }
    .qm-tools button:hover, .qm-tools label:hover { background: rgba(0,0,0,.06); }
    .qm-suggest { position: absolute; left: 3rem; top: 2.3rem; display: none; max-height: 240px; overflow-y: auto; z-index: 5; }
    .qm-suggest.show { display: block; }
    .qm-toast { position: fixed; left: 24px; bottom: 24px; background: #323232; color: #fff; padding: .75rem 1rem; border-radius: 6px; z-index: 1070; font-size: .875rem; display: flex; gap: 1rem; align-items: center; box-shadow: 0 3px 8px rgba(0,0,0,.3); }
    .qm-toast button { background: none; border: 0; color: #8ab4f8; font-weight: 600; }
    @media (max-width: 991.98px) {
        .qm { grid-template-columns: 1fr; }
        .qm-side { flex-direction: row; flex-wrap: wrap; align-items: center; }
        .qm-side .qm-nav { display: flex; overflow-x: auto; width: 100%; gap: .25rem; }
        .qm-nav a { border-radius: 18px; margin: 0; white-space: nowrap; }
        .qm-side-head, .qm-side-foot { display: none; }
        .qm-row { grid-template-columns: 24px 24px 1fr auto; height: auto; padding: .5rem .75rem; }
        .qm-row .what { grid-column: 3 / 5; }
        .qm-thread { padding: .5rem .75rem 1.5rem; }
        .qm-thread h1, .qm-msg-body, .qm-atts, .qm-msg-acts, .qm-images { margin-left: 0; }
        .qm-compose { right: 0; left: 0; width: 100%; max-width: none; max-height: 100vh; height: 100vh; border-radius: 0; }
    }
    [data-bs-theme="dark"] .qm-row { background: rgba(255,255,255,.02); }
    [data-bs-theme="dark"] .qm-row.unread { background: transparent; }
    [data-bs-theme="dark"] .qm-row.selected { background: rgba(138,180,248,.18); }
    [data-bs-theme="dark"] .qm-nav a.active { color: #f28b82; background: rgba(242,139,130,.15); }
    [data-bs-theme="dark"] .qm-nav a.active i { color: #f28b82; }
</style>
@endpush

@push('scripts')
<script>
@verbatim
(function () {
    const root = document.getElementById('qm');
    if (!root) return;
    const B = JSON.parse(root.dataset.boot);
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const $ = (s, el) => (el || document).querySelector(s);
    const $$ = (s, el) => Array.from((el || document).querySelectorAll(s));

    // ── Toast (with undo) ──────────────────────────────────────────
    const toast = $('#qmToast');
    let toastTimer;
    window.qmToast = function (text, undo) {
        toast.innerHTML = '';
        const s = document.createElement('span'); s.textContent = text; toast.appendChild(s);
        if (undo) { const b = document.createElement('button'); b.textContent = 'Undo'; b.onclick = () => { toast.hidden = true; undo(); }; toast.appendChild(b); }
        toast.hidden = false;
        clearTimeout(toastTimer); toastTimer = setTimeout(() => { toast.hidden = true; }, 6000);
    };

    try { const t = sessionStorage.getItem('qm-toast'); if (t) { sessionStorage.removeItem('qm-toast'); setTimeout(() => qmToast(t), 50); } } catch (e) {}

    window.qmPost = async function (url, body, method) {
        const isForm = body instanceof FormData;
        const r = await fetch(url, { method: method || 'POST', headers: Object.assign({ 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, isForm ? {} : { 'Content-Type': 'application/json' }), body: body === undefined ? undefined : (isForm ? body : JSON.stringify(body)) });
        let data = {};
        try { data = await r.json(); } catch (e) {}
        if (!r.ok) {
            const msg = data.errors ? Object.values(data.errors)[0][0] : (data.message || 'Something went wrong.');
            throw new Error(msg);
        }
        return data;
    };

    window.qmCounts = function (counts) {
        if (!counts) return;
        $$('[data-count]').forEach(el => {
            const n = counts[el.dataset.count];
            if (n === undefined) return;
            el.textContent = n ? n : '';
            el.closest('a')?.classList.toggle('has-new', !!n && ['inbox', 'junk'].includes(el.dataset.count) || (!!n && /^\d+$/.test(el.dataset.count)));
        });
        const n = counts.inbox || 0;
        document.title = (n ? '(' + n + ') ' : '') + 'Inbox - ' + B.email + ' - Qmail';
    };

    // ── Compose ────────────────────────────────────────────────────
    const box = $('#qmCompose'), form = $('#qmComposeForm'), editor = $('#qmEditor'), filesEl = $('#qmFiles'), saved = $('#qmSaved');
    let state = null, dirty = false, saving = null, saveTimer = null, pending = [];

    function fileChip(name, size, onRemove) {
        const a = document.createElement('span'); a.className = 'qm-att';
        const i = document.createElement('i'); i.className = 'ri-attachment-2'; a.appendChild(i);
        const s = document.createElement('span'); s.textContent = name + (size ? ' (' + (size > 1048576 ? (size / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(size / 1024)) + ' KB') + ')' : ''); a.appendChild(s);
        const b = document.createElement('button'); b.type = 'button'; b.innerHTML = '<i class="ri-close-line"></i>'; b.setAttribute('aria-label', 'Remove ' + name); b.onclick = onRemove; a.appendChild(b);
        return a;
    }
    function drawFiles() {
        filesEl.innerHTML = '';
        (state.attachments || []).forEach(f => filesEl.appendChild(fileChip(f.name, f.size, () => { state.remove = (state.remove || []).concat([f.i]); state.attachments = state.attachments.filter(x => x !== f); drawFiles(); touch(); })));
        pending.forEach(f => filesEl.appendChild(fileChip(f.name, f.size, () => { pending = pending.filter(x => x !== f); drawFiles(); })));
    }

    async function openCompose(params) {
        if (state && dirty) await saveDraft();
        const url = new URL(B.urls.compose, location.origin);
        Object.entries(params || {}).forEach(([k, v]) => v !== undefined && v !== null && url.searchParams.set(k, v));
        let d;
        try { d = await (await fetch(url, { headers: { Accept: 'application/json' } })).json(); } catch (e) { qmToast('Could not open the compose window.'); return; }
        state = { draft_id: d.draft_id, mode: d.mode || 'new', source_message_id: d.source_message_id || null, attachments: d.attachments || [], remove: [] };
        pending = []; dirty = false;
        form.to.value = d.to || ''; form.cc.value = d.cc || ''; form.bcc.value = d.bcc || ''; form.subject.value = d.subject || '';
        $$('[data-ccbcc]').forEach(el => el.hidden = !(d.cc || d.bcc));
        editor.innerHTML = d.body_html || '<p><br></p>';
        $('#qmComposeTitle').textContent = { reply: 'Reply', reply_all: 'Reply all', forward: 'Forward' }[state.mode] || (d.draft_id ? 'Draft' : 'New message');
        saved.textContent = '';
        drawFiles();
        box.hidden = false; box.classList.remove('min');
        (state.mode === 'new' && !d.to ? form.to : (state.mode === 'forward' ? form.to : editor)).focus();
        if (state.mode !== 'new' && state.mode !== 'forward') { const r = document.createRange(); r.setStart(editor, 0); r.collapse(true); const s = getSelection(); s.removeAllRanges(); s.addRange(r); }
    }
    window.qmCompose = openCompose;

    function payload(extra) {
        const fd = new FormData();
        if (state.draft_id) fd.append('draft_id', state.draft_id);
        fd.append('mode', state.mode);
        if (state.source_message_id) fd.append('source_message_id', state.source_message_id);
        ['to', 'cc', 'bcc', 'subject'].forEach(k => fd.append(k, form[k].value));
        fd.append('body_html', editor.innerHTML);
        (state.remove || []).forEach(i => fd.append('remove_files[]', i));
        pending.forEach(f => fd.append('files[]', f));
        Object.entries(extra || {}).forEach(([k, v]) => fd.append(k, v));
        return fd;
    }
    function touch() { dirty = true; saved.textContent = ''; clearTimeout(saveTimer); saveTimer = setTimeout(saveDraft, 2500); }
    async function saveDraft() {
        if (!state || !dirty) return;
        if (saving) return saving;
        clearTimeout(saveTimer);
        dirty = false; saved.textContent = 'Saving…';
        const sentFiles = pending.slice();
        saving = qmPost(B.urls.draft, payload()).then(d => {
            state.draft_id = d.draft_id; state.attachments = d.attachments; state.remove = [];
            pending = pending.filter(f => !sentFiles.includes(f)); drawFiles();
            saved.textContent = 'Saved ' + d.saved_at;
            qmCounts && fetch(B.urls.poll, { headers: { Accept: 'application/json' } }).then(r => r.json()).then(p => qmCounts(p.counts)).catch(() => {});
        }).catch(e => { dirty = true; saved.textContent = 'Not saved: ' + e.message; }).finally(() => { saving = null; });
        return saving;
    }
    async function send(sendAt) {
        if (saving) await saving;
        clearTimeout(saveTimer);
        const btn = $('#qmSend'); btn.disabled = true; btn.textContent = sendAt ? 'Scheduling…' : 'Sending…';
        try {
            const d = await qmPost(B.urls.send, payload(sendAt ? { send_at: sendAt } : {}));
            box.hidden = true; state = null; dirty = false;
            qmToast(d.message);
            if (d.counts) qmCounts(d.counts);
            document.dispatchEvent(new CustomEvent('qm:sent'));
        } catch (e) {
            qmToast(e.message);
        } finally { btn.disabled = false; btn.textContent = 'Send'; }
    }
    form.addEventListener('submit', e => { e.preventDefault(); send(); });
    form.addEventListener('input', e => { if (e.target.id !== 'qmSendAt') touch(); });
    editor.addEventListener('input', touch);
    form.addEventListener('keydown', e => { if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') { e.preventDefault(); send(); } });
    $('#qmAttach').addEventListener('change', e => {
        const max = B.maxMb * 1048576;
        const all = Array.from(e.target.files);
        const total = all.reduce((n, f) => n + f.size, 0) + pending.reduce((n, f) => n + f.size, 0) + (state.attachments || []).reduce((n, f) => n + f.size, 0);
        if (total > max) { qmToast('Attachments can add up to ' + B.maxMb + ' MB. Share bigger files from Drive instead.'); e.target.value = ''; return; }
        pending = pending.concat(all); e.target.value = ''; drawFiles(); touch();
    });
    $$('[data-fmt]').forEach(b => b.addEventListener('mousedown', e => {
        e.preventDefault();
        const [cmd, arg] = b.dataset.fmt.split(':');
        if (cmd === 'link') {
            const url = prompt('Link address (https://…)');
            if (url && /^(https?:|mailto:)/i.test(url.trim())) document.execCommand('createLink', false, url.trim());
        } else document.execCommand(cmd, false, arg || null);
        touch();
    }));
    $$('[data-schedule]').forEach(b => b.addEventListener('click', () => {
        const d = new Date(), k = b.dataset.schedule;
        if (k === 'pick') {
            const v = $('#qmSendAt').value; if (!v) { qmToast('Pick a date and time.'); return; }
            return send(new Date(v).toISOString());
        }
        if (k === 'monday8') { d.setDate(d.getDate() + ((8 - d.getDay()) % 7 || 7)); d.setHours(8, 0, 0, 0); }
        else { d.setDate(d.getDate() + 1); d.setHours(k === 'tomorrow13' ? 13 : 8, 0, 0, 0); }
        send(d.toISOString());
    }));
    box.addEventListener('click', async e => {
        const c = e.target.closest('[data-cmd]')?.dataset.cmd;
        if (!c) { if (e.target.closest('.qm-compose-head') && box.classList.contains('min')) box.classList.remove('min'); return; }
        if (c === 'min') box.classList.toggle('min');
        if (c === 'max') box.classList.toggle('max');
        if (c === 'ccbcc') $$('[data-ccbcc]').forEach(el => el.hidden = !el.hidden);
        if (c === 'close') { await saveDraft(); box.hidden = true; if (state && state.draft_id) qmToast('Saved to drafts.'); state = null; }
        if (c === 'discard') {
            if (state && state.draft_id) { try { await qmPost(B.urls.discard.replace('__ID__', state.draft_id), undefined, 'DELETE'); } catch (e) {} }
            clearTimeout(saveTimer); box.hidden = true; state = null; dirty = false; qmToast('Draft discarded.');
            document.dispatchEvent(new CustomEvent('qm:sent'));
        }
    });
    window.addEventListener('beforeunload', e => { if (state && dirty) { saveDraft(); e.preventDefault(); e.returnValue = ''; } });

    // Address suggestions: staff, mailboxes and people who wrote to you.
    const sug = $('#qmSuggest'); let sugFor = null, sugTimer;
    $$('.qm-addr').forEach(inp => {
        inp.addEventListener('input', () => {
            clearTimeout(sugTimer);
            const term = inp.value.split(',').pop().trim();
            if (term.length < 2) { sug.classList.remove('show'); return; }
            sugTimer = setTimeout(async () => {
                const r = await fetch(B.urls.contacts + '?q=' + encodeURIComponent(term), { headers: { Accept: 'application/json' } }).then(r => r.json()).catch(() => ({ contacts: [] }));
                sug.innerHTML = '';
                (r.contacts || []).forEach(c => {
                    const b = document.createElement('button'); b.type = 'button'; b.className = 'dropdown-item small';
                    b.textContent = c.name ? c.name + ' <' + c.email + '>' : c.email;
                    b.onmousedown = ev => { ev.preventDefault(); const parts = inp.value.split(','); parts.pop(); parts.push(' ' + (c.name ? '"' + c.name.replace(/"/g, '') + '" <' + c.email + '>' : c.email)); inp.value = parts.join(',').replace(/^\s+/, '') + ', '; sug.classList.remove('show'); touch(); inp.focus(); };
                    sug.appendChild(b);
                });
                sugFor = inp;
                sug.style.top = (inp.offsetTop + inp.offsetHeight + 4) + 'px';
                sug.classList.toggle('show', sug.children.length > 0);
            }, 180);
        });
        inp.addEventListener('blur', () => setTimeout(() => sug.classList.remove('show'), 150));
    });

    document.addEventListener('click', e => {
        const b = e.target.closest('[data-compose]');
        if (!b) return;
        e.preventDefault();
        openCompose({ mode: b.dataset.compose, message: b.dataset.message, draft: b.dataset.draft, to: b.dataset.to });
    });

    // ── Keyboard shortcuts (Gmail's) ───────────────────────────────
    document.addEventListener('keydown', e => {
        if (e.ctrlKey || e.metaKey || e.altKey || (e.target.closest && e.target.closest('input, textarea, [contenteditable="true"], select'))) return;
        if (e.key === 'c') { e.preventDefault(); openCompose({ mode: 'new' }); }
        else if (e.key === '/') { e.preventDefault(); $('#qmSearch')?.focus(); }
        else document.dispatchEvent(new CustomEvent('qm:key', { detail: e }));
    });

    // ── Staying current: Reverb poke when available, polling otherwise ──
    let latest = null;
    async function poll() {
        try {
            const p = await (await fetch(B.urls.poll, { headers: { Accept: 'application/json' } })).json();
            qmCounts(p.counts);
            const st = $('#qmStatus');
            if (st) st.textContent = p.status === 'error' ? '⚠ ' + (p.error || 'Could not check mail.') : (p.synced_at ? 'Checked just now' : 'Loading your mail…');
            if (latest !== null && p.latest > latest) document.dispatchEvent(new CustomEvent('qm:new'));
            latest = p.latest;
        } catch (e) {}
    }
    let last = Date.now();
    const tick = () => { last = Date.now(); poll(); };
    tick();
    if (window.QRealtime) window.QRealtime.on('user.' + window.QRealtime.me, 'mail', () => setTimeout(tick, 300));
    // Every 30 seconds, or every 90 while instant updates are connected.
    setInterval(() => { if (!document.hidden && Date.now() - last >= (window.QRealtime && window.QRealtime.connected ? 90000 : 30000)) tick(); }, 15000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) tick(); });
})();
@endverbatim
</script>
@endpush
