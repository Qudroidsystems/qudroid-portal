@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">
    @include('modules.connect.notes._head', ['subtitle' => 'Editing a note in ' . $conversation->titleFor($me) . '. Changes save as you type.'])

    <div class="nt-conflict cb-banner warning d-none" id="ntConflict" role="alert">
        <i class="ri-error-warning-line"></i>
        <div class="flex-grow-1"><span id="ntConflictText">Someone else saved this note while you were editing.</span>
            <div class="mt-2 d-flex gap-2 flex-wrap">
                <button type="button" class="btn btn-sm btn-light" id="ntTakeTheirs">Load their version</button>
                <button type="button" class="btn btn-sm btn-warning" id="ntKeepMine">Keep mine (replace theirs)</button>
            </div>
        </div>
    </div>

    <x-cb.card>
        <form method="POST" action="{{ route('connect.notes.update', $note) }}" id="ntForm">
            @csrf @method('PUT')
            <input type="hidden" name="version" id="ntVersion" value="{{ $note->version }}">
            <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
                <input name="title" id="ntTitleIn" class="form-control form-control-lg flex-grow-1 nt-title-in" maxlength="150" required value="{{ $note->title }}" aria-label="Title">
                <span class="small text-muted text-nowrap" id="ntStatus" aria-live="polite">Saved</span>
                <a href="{{ route('connect.notes.show', $note) }}" class="btn btn-sm btn-primary" id="ntDone">Done</a>
            </div>
            <div class="small text-primary mb-2" id="ntOthers" aria-live="polite">{{ $editing ? implode(', ', $editing) . ' also has this open.' : '' }}</div>

            <div class="nt-toolbar" role="toolbar" aria-label="Formatting">
                <button type="button" data-md="bold" title="Bold (Ctrl+B)" aria-label="Bold"><i class="ri-bold"></i></button>
                <button type="button" data-md="italic" title="Italic (Ctrl+I)" aria-label="Italic"><i class="ri-italic"></i></button>
                <button type="button" data-md="strike" title="Strikethrough" aria-label="Strikethrough"><i class="ri-strikethrough"></i></button>
                <span class="nt-sep"></span>
                <button type="button" data-md="h2" title="Heading" aria-label="Heading"><i class="ri-h-2"></i></button>
                <button type="button" data-md="h3" title="Subheading" aria-label="Subheading"><i class="ri-h-3"></i></button>
                <span class="nt-sep"></span>
                <button type="button" data-md="ul" title="Bulleted list" aria-label="Bulleted list"><i class="ri-list-unordered"></i></button>
                <button type="button" data-md="ol" title="Numbered list" aria-label="Numbered list"><i class="ri-list-ordered"></i></button>
                <button type="button" data-md="task" title="Checklist" aria-label="Checklist"><i class="ri-checkbox-line"></i></button>
                <button type="button" data-md="quote" title="Quote" aria-label="Quote"><i class="ri-double-quotes-l"></i></button>
                <button type="button" data-md="code" title="Code" aria-label="Code"><i class="ri-code-line"></i></button>
                <button type="button" data-md="link" title="Link (Ctrl+K)" aria-label="Link"><i class="ri-link"></i></button>
                <button type="button" data-md="table" title="Table" aria-label="Table"><i class="ri-table-line"></i></button>
                <button type="button" data-md="hr" title="Divider" aria-label="Divider"><i class="ri-separator"></i></button>
                <span class="ms-auto d-flex gap-1 nt-views">
                    <button type="button" data-view="write" class="active">Write</button>
                    <button type="button" data-view="split" class="d-none d-lg-inline-block">Side by side</button>
                    <button type="button" data-view="preview">Preview</button>
                </span>
            </div>
            <div class="nt-panes" data-view="write" id="ntPanes">
                <textarea name="body" id="ntBodyIn" class="form-control nt-text" spellcheck="true" aria-label="Note text (Markdown)">{{ $note->body }}</textarea>
                <div class="md-body nt-preview" id="ntPreview" aria-label="Preview"></div>
            </div>
            <div class="small text-muted mt-2">Formatting uses Markdown: **bold**, *italic*, # heading, - list, - [ ] checklist, [text](link).</div>
            <noscript><button class="btn btn-primary mt-2">Save</button></noscript>
        </form>
    </x-cb.card>
</div></div></div>
@endsection

@push('styles')
@include('modules.connect.notes._styles')
<style>
    .nt-title-in { font-weight: 700; border-color: transparent; padding-left: 0; }
    .nt-title-in:focus { border-color: var(--cb-border); padding-left: .75rem; }
    .nt-toolbar { display: flex; flex-wrap: wrap; gap: 2px; align-items: center; border: 1px solid var(--cb-border); border-bottom: 0; border-radius: 10px 10px 0 0; padding: 4px; background: var(--cb-surface-2); position: sticky; top: 70px; z-index: 2; }
    .nt-toolbar button { border: 0; background: none; padding: 4px 8px; border-radius: 6px; color: var(--cb-text); font-size: 15px; }
    .nt-toolbar button:hover, .nt-toolbar button.active { background: var(--cb-surface); color: var(--cb-heading); }
    .nt-views button { font-size: 12px; font-weight: 600; }
    .nt-sep { width: 1px; height: 18px; background: var(--cb-border); margin: 0 4px; }
    .nt-panes { display: grid; grid-template-columns: 1fr; border: 1px solid var(--cb-border); border-radius: 0 0 10px 10px; min-height: 60vh; }
    .nt-panes[data-view=split] { grid-template-columns: 1fr 1fr; }
    .nt-panes[data-view=write] .nt-preview, .nt-panes[data-view=preview] .nt-text { display: none; }
    .nt-text { border: 0; border-radius: 0 0 10px 10px; min-height: 60vh; resize: vertical; font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 14px; line-height: 1.6; padding: 14px; }
    .nt-panes[data-view=split] .nt-text { border-right: 1px solid var(--cb-border); border-radius: 0 0 0 10px; }
    .nt-preview { padding: 14px; overflow: auto; }
</style>
@endpush

@push('scripts')
<script>
(function () {
    const form = document.getElementById('ntForm'), title = document.getElementById('ntTitleIn'), text = document.getElementById('ntBodyIn');
    const versionIn = document.getElementById('ntVersion'), status = document.getElementById('ntStatus'), panes = document.getElementById('ntPanes');
    const token = document.querySelector('meta[name=csrf-token]').content;
    const H = { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token };
    const stateUrl = @json(route('connect.notes.state', $note)), previewUrl = @json(route('connect.notes.preview'));
    let saved = { title: title.value, body: text.value }, timer = null, saving = false, conflict = null;
    const dirty = () => title.value !== saved.title || text.value !== saved.body;
    const setStatus = (t, cls) => { status.textContent = t; status.className = 'small text-nowrap ' + (cls || 'text-muted'); };

    function save(force) {
        if (saving || conflict || (!dirty() && !force)) return Promise.resolve();
        saving = true; setStatus('Saving…');
        const body = { title: title.value.trim() || 'Untitled', body: text.value, version: +versionIn.value };
        return fetch(form.action, { method: 'PUT', headers: H, body: JSON.stringify(body) }).then(async r => {
            const j = await r.json().catch(() => ({}));
            if (r.status === 409) { conflict = j; showConflict(j); setStatus('Not saved', 'text-danger'); return; }
            if (!r.ok) { setStatus(j.message || 'Not saved', 'text-danger'); return; }
            versionIn.value = j.version; saved = { title: body.title, body: body.body }; setStatus('Saved ' + j.saved, 'text-success');
            if (dirty()) schedule();
        }).catch(() => setStatus('Offline. Will retry…', 'text-danger')).finally(() => { saving = false; });
    }
    const schedule = () => { clearTimeout(timer); setStatus('Editing…'); timer = setTimeout(save, 1200); };
    [title, text].forEach(el => el.addEventListener('input', () => { schedule(); if (panes.dataset.view !== 'write') preview(); }));
    setInterval(() => { if (dirty() && !saving) save(); }, 15000);

    function showConflict(j) {
        document.getElementById('ntConflictText').textContent = j.message + ' Choose which version to keep.';
        document.getElementById('ntConflict').classList.remove('d-none');
    }
    document.getElementById('ntTakeTheirs').onclick = () => {
        title.value = conflict.title; text.value = conflict.body || ''; versionIn.value = conflict.version;
        saved = { title: title.value, body: text.value }; conflict = null;
        document.getElementById('ntConflict').classList.add('d-none'); setStatus('Loaded their version'); preview();
    };
    document.getElementById('ntKeepMine').onclick = () => {
        versionIn.value = conflict.version; conflict = null;
        document.getElementById('ntConflict').classList.add('d-none'); save(true);
    };

    // Who else has it open; pick up their saves when I have nothing unsaved.
    function heartbeat() {
        fetch(stateUrl + '?editing=1&version=' + versionIn.value, { headers: { 'Accept': 'application/json' } }).then(r => r.ok ? r.json() : null).then(s => {
            if (!s) return;
            document.getElementById('ntOthers').textContent = s.editing.length ? s.editing.join(', ') + (s.editing.length === 1 ? ' also has' : ' also have') + ' this open.' : '';
            if (s.version > +versionIn.value && s.body !== undefined && !dirty() && !saving) {
                title.value = s.title; text.value = s.body || ''; versionIn.value = s.version; saved = { title: title.value, body: text.value };
                setStatus('Updated by ' + (s.by || 'someone')); if (panes.dataset.view !== 'write') preview();
            }
        }).catch(() => {});
    }
    setInterval(() => { if (!document.hidden) heartbeat(); }, 8000);

    // Preview
    let pv = null, pvSeq = 0;
    function preview() {
        clearTimeout(pv);
        pv = setTimeout(() => {
            const mine = ++pvSeq;
            fetch(previewUrl, { method: 'POST', headers: H, body: JSON.stringify({ body: text.value }) }).then(r => r.json()).then(j => {
                if (mine === pvSeq) document.getElementById('ntPreview').innerHTML = j.html || '<p class="text-muted">Nothing to preview yet.</p>';
            }).catch(() => {});
        }, 300);
    }
    document.querySelectorAll('.nt-views button').forEach(b => b.addEventListener('click', () => {
        panes.dataset.view = b.dataset.view;
        document.querySelectorAll('.nt-views button').forEach(x => x.classList.toggle('active', x === b));
        if (b.dataset.view !== 'write') preview();
    }));

    // Toolbar: wrap the selection or prefix the lines.
    function wrap(before, after, placeholder) {
        const a = text.selectionStart, b = text.selectionEnd, sel = text.value.slice(a, b) || placeholder;
        text.setRangeText(before + sel + after, a, b, 'end');
        text.setSelectionRange(a + before.length, a + before.length + sel.length);
        text.focus(); text.dispatchEvent(new Event('input'));
    }
    function lines(prefix) {
        const v = text.value, a = v.lastIndexOf('\n', text.selectionStart - 1) + 1, e = text.selectionEnd;
        const end = v.indexOf('\n', e) === -1 ? v.length : v.indexOf('\n', e);
        let n = 0;
        const out = v.slice(a, end).split('\n').map(l => (typeof prefix === 'function' ? prefix(++n) : prefix) + l).join('\n');
        text.setRangeText(out, a, end, 'end'); text.focus(); text.dispatchEvent(new Event('input'));
    }
    const actions = {
        bold: () => wrap('**', '**', 'bold text'), italic: () => wrap('*', '*', 'italic text'), strike: () => wrap('~~', '~~', 'text'),
        h2: () => lines('## '), h3: () => lines('### '), ul: () => lines('- '), ol: () => lines(n => n + '. '), task: () => lines('- [ ] '), quote: () => lines('> '),
        code: () => text.value.slice(text.selectionStart, text.selectionEnd).includes('\n') ? wrap('```\n', '\n```', 'code') : wrap('`', '`', 'code'),
        link: () => { const u = prompt('Link address (https://…)', 'https://'); if (u) wrap('[', '](' + u + ')', 'link text'); },
        table: () => wrap('\n| Column | Column |\n| --- | --- |\n| ', ' |  |\n', 'Cell'), hr: () => wrap('\n\n---\n\n', '', ''),
    };
    document.querySelectorAll('[data-md]').forEach(b => b.addEventListener('click', () => actions[b.dataset.md]()));
    text.addEventListener('keydown', e => {
        if ((e.ctrlKey || e.metaKey) && ['b', 'i', 'k'].includes(e.key.toLowerCase())) { e.preventDefault(); actions[{ b: 'bold', i: 'italic', k: 'link' }[e.key.toLowerCase()]](); }
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') { e.preventDefault(); save(true); }
        // Enter in a list continues it
        if (e.key === 'Enter' && !e.shiftKey) {
            const v = text.value, a = v.lastIndexOf('\n', text.selectionStart - 1) + 1, line = v.slice(a, text.selectionStart);
            const m = line.match(/^(\s*)(- \[[ x]\] |- |\* |(\d+)\. |> )/);
            if (m) {
                e.preventDefault();
                if (line.trim() === m[2].trim()) { text.setRangeText('', a, text.selectionStart, 'end'); text.dispatchEvent(new Event('input')); return; }
                const next = m[3] ? (+m[3] + 1) + '. ' : m[2].replace('[x]', '[ ]');
                text.setRangeText('\n' + m[1] + next, text.selectionStart, text.selectionEnd, 'end'); text.dispatchEvent(new Event('input'));
            }
        }
    });

    document.getElementById('ntDone').addEventListener('click', e => { if (dirty()) { e.preventDefault(); save(true).then(() => { if (!conflict) location.href = e.currentTarget?.href || @json(route('connect.notes.show', $note)); }); } });
    window.addEventListener('beforeunload', e => { if (dirty()) { e.preventDefault(); e.returnValue = ''; } });
    text.focus();
})();
</script>
@endpush
