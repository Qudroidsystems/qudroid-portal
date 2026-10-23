@extends('modules.mail.layout')

@section('mail')
@php
    $first = $messages->first();
    $last = $messages->last();
    $folders = $messages->map(fn ($m) => $m->folder)->filter()->unique('id');
    $starred = $messages->contains('flagged', true);
    $binView = $folders->every(fn ($f) => in_array($f->role, ['trash', 'junk'], true));
    $remote = !$images && $messages->contains(fn ($m) => $m->body_html && preg_match('/(<img[^>]+src\s*=\s*["\']?\s*(https?:)?\/\/|url\(\s*["\']?\s*(https?:)?\/\/)/i', $m->body_html));
    $me = mb_strtolower($account->email);
    $colors = ['#1a73e8', '#e8710a', '#188038', '#d93025', '#9334e6', '#12b5cb', '#c5221f', '#3c4043'];
    $back = route('mail.box', [$account, $view]);
@endphp
<div class="qm-bar" id="qmBar" data-key="{{ $thread }}" data-back="{{ $back }}">
    <a href="{{ $back }}" class="btn-icon" title="Back (u)" aria-label="Back to list" id="qmBack"><i class="ri-arrow-left-line"></i></a>
    @if($binView)
        <button class="btn-icon" data-tact="inbox" title="Move to inbox" aria-label="Move to inbox"><i class="ri-inbox-unarchive-line"></i></button>
        <button class="btn-icon" data-tact="delete" title="Delete for good" aria-label="Delete for good"><i class="ri-delete-bin-2-line"></i></button>
    @else
        <button class="btn-icon" data-tact="archive" title="Archive (e)" aria-label="Archive"><i class="ri-archive-line"></i></button>
        <button class="btn-icon" data-tact="spam" title="Report spam (!)" aria-label="Report spam"><i class="ri-spam-2-line"></i></button>
        <button class="btn-icon" data-tact="trash" title="Delete (#)" aria-label="Delete"><i class="ri-delete-bin-6-line"></i></button>
    @endif
    <button class="btn-icon" data-tact="unread" title="Mark as unread (U)" aria-label="Mark as unread"><i class="ri-mail-unread-line"></i></button>
    @unless($binView)<button class="btn-icon" data-tact="snooze" title="Snooze (b)" aria-label="Snooze"><i class="ri-time-line"></i></button>@endunless
    <div class="dropdown">
        <button class="btn-icon" data-bs-toggle="dropdown" title="Move to" aria-label="Move to"><i class="ri-folder-transfer-line"></i></button>
        <div class="dropdown-menu">
            <h6 class="dropdown-header">Move to</h6>
            <button class="dropdown-item" data-tact="inbox">Inbox</button>
            @foreach($labels as $l)<button class="dropdown-item" data-tact="move:{{ $l->id }}">{{ $l->name }}</button>@endforeach
        </div>
    </div>
    <button class="btn-icon" onclick="window.print()" title="Print" aria-label="Print"><i class="ri-printer-line"></i></button>
</div>

<div class="qm-thread">
    <div class="d-flex align-items-start gap-2">
        <h1 class="flex-grow-1">{{ $first->subject ?: '(no subject)' }}
            @foreach($folders as $f)<span class="badge bg-light text-body fw-normal ms-1 align-middle" style="font-size:.7rem">{{ $f->label() }}</span>@endforeach
        </h1>
        <button type="button" class="qm-star mt-2 {{ $starred ? 'on' : '' }}" id="qmThreadStar" aria-label="{{ $starred ? 'Starred' : 'Not starred' }}"><i class="{{ $starred ? 'ri-star-fill' : 'ri-star-line' }} fs-5"></i></button>
    </div>
    @if($remote)
        <div class="qm-images">Images in this conversation are hidden to protect your privacy. <a href="{{ request()->fullUrlWithQuery(['images' => 1]) }}">Show images</a></div>
    @endif

    @foreach($messages as $i => $m)
        @php
            $open = $loop->last || !$m->seen || $messages->count() <= 2;
            $initial = mb_strtoupper(mb_substr($m->from_name ?: ($m->from_email ?: '?'), 0, 1));
            $color = $colors[crc32((string) $m->from_email) % count($colors)];
            $toLine = collect([...($m->to ?? []), ...($m->cc ?? [])])->map(fn ($a) => mb_strtolower($a['email'] ?? '') === $me ? 'me' : (($a['name'] ?? '') ?: ($a['email'] ?? '')))->filter()->implode(', ');
            $files = $m->attachments->where('inline', false);
        @endphp
        <article class="qm-msg {{ $open ? '' : 'collapsed' }}" id="m{{ $m->id }}">
            <div class="qm-msg-head" data-toggle-msg>
                <div class="qm-ava" style="background: {{ $color }}" aria-hidden="true">{{ $initial }}</div>
                <div class="meta">
                    <div><strong>{{ $m->fromLabel() }}</strong> @if($m->from_name)<small class="text-muted">&lt;{{ $m->from_email }}&gt;</small>@endif</div>
                    <div class="to">to {{ $toLine ?: 'undisclosed recipients' }}
                        @if($m->bcc && mb_strtolower((string) $m->from_email) === $me)<span>, bcc: {{ \App\Models\Mail\MailMessage::names($m->bcc) }}</span>@endif
                    </div>
                    <div class="snip">{{ $m->snippet }}</div>
                </div>
                <div class="when">
                    @if($files->isNotEmpty())<i class="ri-attachment-2 me-1"></i>@endif
                    <span title="{{ $m->sent_at?->format('D, j M Y, g:i A') }}">{{ $m->sent_at?->format('j M Y, g:i A') }} @if($m->sent_at)({{ $m->sent_at->diffForHumans(short: true) }})@endif</span>
                </div>
                <div class="dropdown" onclick="event.stopPropagation()">
                    <button class="btn btn-sm btn-link text-muted p-0 px-1" data-bs-toggle="dropdown" aria-label="More for this message"><i class="ri-more-2-fill"></i></button>
                    <div class="dropdown-menu dropdown-menu-end">
                        <button class="dropdown-item" data-compose="reply" data-message="{{ $m->id }}"><i class="ri-reply-line me-2"></i>Reply</button>
                        <button class="dropdown-item" data-compose="reply_all" data-message="{{ $m->id }}"><i class="ri-reply-all-line me-2"></i>Reply all</button>
                        <button class="dropdown-item" data-compose="forward" data-message="{{ $m->id }}"><i class="ri-share-forward-line me-2"></i>Forward</button>
                        <div class="dropdown-divider"></div>
                        <button class="dropdown-item" data-mact="unread" data-id="{{ $m->id }}"><i class="ri-mail-unread-line me-2"></i>Mark unread from here</button>
                        <button class="dropdown-item" data-mact="trash" data-id="{{ $m->id }}"><i class="ri-delete-bin-6-line me-2"></i>Delete this message</button>
                    </div>
                </div>
            </div>
            <div class="qm-msg-body">
                <iframe title="Message from {{ $m->fromLabel() }}" sandbox="allow-same-origin allow-popups allow-popups-to-escape-sandbox" loading="lazy"
                        data-src="{{ route('mail.body', [$account, $m, 'images' => $images ? 1 : null]) }}" @if($open) src="{{ route('mail.body', [$account, $m, 'images' => $images ? 1 : null]) }}" @endif></iframe>
            </div>
            @if($files->isNotEmpty())
                <div class="qm-atts">
                    @foreach($files as $a)
                        <a class="qm-att" href="{{ route('mail.attachment', [$account, $a]) }}" title="Download {{ $a->filename }}">
                            <i class="{{ str_starts_with((string) $a->mime, 'image/') ? 'ri-image-line text-success' : (str_contains((string) $a->mime, 'pdf') ? 'ri-file-pdf-2-line text-danger' : 'ri-file-line') }}"></i>
                            <span>{{ $a->filename }}</span><small class="text-muted">{{ $a->sizeLabel() }}</small>
                        </a>
                    @endforeach
                </div>
            @endif
            @if($loop->last)
                <div class="qm-msg-acts">
                    <button class="btn btn-outline-secondary btn-sm px-3" data-compose="reply" data-message="{{ $m->id }}"><i class="ri-reply-line me-1"></i>Reply</button>
                    @if(count($m->to ?? []) + count($m->cc ?? []) > 1)<button class="btn btn-outline-secondary btn-sm px-3" data-compose="reply_all" data-message="{{ $m->id }}"><i class="ri-reply-all-line me-1"></i>Reply all</button>@endif
                    <button class="btn btn-outline-secondary btn-sm px-3" data-compose="forward" data-message="{{ $m->id }}"><i class="ri-share-forward-line me-1"></i>Forward</button>
                </div>
            @endif
        </article>
    @endforeach
</div>
@include('modules.mail._snooze')
@endsection

@push('scripts')
<script>
@verbatim
(function () {
    const B = JSON.parse(document.getElementById('qm').dataset.boot);
    const bar = document.getElementById('qmBar');
    const key = bar.dataset.key, back = bar.dataset.back;

    // Fit each message frame to its content.
    function fit(f) {
        try { const d = f.contentDocument; if (d && d.body) f.style.height = Math.max(40, d.documentElement.scrollHeight) + 'px'; } catch (e) {}
    }
    function ready(f) {
        fit(f); setTimeout(() => fit(f), 400); setTimeout(() => fit(f), 1500);
        try {
            f.contentDocument.querySelectorAll('img').forEach(i => i.addEventListener('load', () => fit(f)));
            // Shortcuts keep working after clicking inside a message.
            f.contentDocument.addEventListener('keydown', ev => document.dispatchEvent(new KeyboardEvent('keydown', { key: ev.key, ctrlKey: ev.ctrlKey, metaKey: ev.metaKey, altKey: ev.altKey })));
        } catch (e) {}
    }
    document.querySelectorAll('.qm-msg-body iframe').forEach(f => {
        f.addEventListener('load', () => ready(f));
        // It may have finished loading before this script ran.
        try { if (f.getAttribute('src') && f.contentDocument.readyState === 'complete' && f.contentDocument.URL !== 'about:blank') ready(f); } catch (e) {}
    });
    window.addEventListener('resize', () => document.querySelectorAll('.qm-msg-body iframe').forEach(fit));
    document.querySelectorAll('[data-toggle-msg]').forEach(h => h.addEventListener('click', e => {
        if (e.target.closest('a, button')) return;
        const art = h.closest('.qm-msg');
        art.classList.toggle('collapsed');
        const f = art.querySelector('iframe');
        if (!art.classList.contains('collapsed') && !f.getAttribute('src')) f.src = f.dataset.src; else fit(f);
    }));
    const lastOpen = document.querySelectorAll('.qm-msg:not(.collapsed)');
    if (document.querySelectorAll('.qm-msg').length > 2 && lastOpen.length) lastOpen[0].scrollIntoView({ block: 'start' });

    async function act(action, extra, body) {
        if (action === 'delete' && !confirm('Delete this conversation for good? This cannot be undone.')) return;
        try {
            const d = await qmPost(B.urls.act, Object.assign(body || { threads: [key] }, { action, view: B.view }, extra || {}));
            if (['archive', 'trash', 'spam', 'inbox', 'delete', 'unread', 'snooze'].includes(action) || action.startsWith('move:')) {
                sessionStorage.setItem('qm-toast', d.message);
                location.href = back;
            } else qmToast(d.message);
        } catch (e) { qmToast(e.message); }
    }
    bar.addEventListener('click', e => {
        const b = e.target.closest('[data-tact]'); if (!b) return;
        if (b.dataset.tact === 'snooze') bootstrap.Modal.getOrCreateInstance(document.getElementById('qmSnooze')).show();
        else act(b.dataset.tact);
    });
    document.addEventListener('qm:snooze', e => act('snooze', { until: e.detail }));
    document.querySelectorAll('[data-mact]').forEach(b => b.addEventListener('click', () => act(b.dataset.mact, {}, { messages: [Number(b.dataset.id)] })));
    const star = document.getElementById('qmThreadStar');
    star.addEventListener('click', async () => {
        const on = !star.classList.contains('on');
        try { await qmPost(B.urls.act, { action: on ? 'star' : 'unstar', threads: [key] }); star.classList.toggle('on', on); star.innerHTML = '<i class="' + (on ? 'ri-star-fill' : 'ri-star-line') + ' fs-5"></i>'; }
        catch (e) { qmToast(e.message); }
    });
    document.addEventListener('qm:sent', () => setTimeout(() => location.reload(), 600));
    document.addEventListener('qm:key', ({ detail: e }) => {
        const last = document.querySelector('.qm-msg:last-of-type [data-compose="reply"]')?.dataset.message;
        if (e.key === 'u') location.href = back;
        else if (e.key === 'e' && !B.view.match(/^(trash|junk)$/)) act('archive');
        else if (e.key === '#') act(['trash', 'junk'].includes(B.view) ? 'delete' : 'trash');
        else if (e.key === '!') act('spam');
        else if (e.key === 'U') act('unread');
        else if (e.key === 'b') bootstrap.Modal.getOrCreateInstance(document.getElementById('qmSnooze')).show();
        else if (e.key === 'r' && last) qmCompose({ mode: 'reply', message: last });
        else if (e.key === 'a' && last) qmCompose({ mode: 'reply_all', message: last });
        else if (e.key === 'f' && last) qmCompose({ mode: 'forward', message: last });
    });
})();
@endverbatim
</script>
@endpush
