@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">
    <x-cb.hero title="{{ $wf->exists ? $wf->name : 'New workflow' }}" icon="ri-flow-chart" subtitle="Click a box to change it. Steps run from top to bottom; drag to reorder." :back="route('workflows.index')" back-label="Workflows">
        @if($wf->exists)
            <x-slot:actions>
                <a class="cb-hero-btn" href="{{ route('workflows.runs', $wf) }}"><i class="ri-history-line me-1"></i>Runs ({{ $wf->runs_count }})</a>
            </x-slot:actions>
        @endif
    </x-cb.hero>
    @include('partials.flash')
    <div id="wfErrors" class="alert alert-danger d-none" role="alert"></div>

    <form id="wfForm" method="POST" action="{{ $wf->exists ? route('workflows.update', $wf) : route('workflows.store') }}" class="row g-3">
        @csrf
        @if($wf->exists) @method('PUT') @endif
        <input type="hidden" name="definition" id="wfDefinition">

        <div class="col-lg-7">
            <div class="cb-card">
                <div class="cb-card-body">
                    <div class="row g-2 align-items-center mb-3">
                        <div class="col-md-7">
                            <label for="wfName" class="visually-hidden">Name</label>
                            <input id="wfName" name="name" class="form-control form-control-lg fw-semibold" maxlength="150" required placeholder="Name this workflow" value="{{ old('name', $wf->name) }}">
                        </div>
                        <div class="col-md-5 d-flex gap-2 justify-content-md-end align-items-center flex-wrap">
                            <div class="form-check form-switch m-0">
                                <input class="form-check-input" type="checkbox" role="switch" name="is_active" value="1" id="wfActive" @checked($wf->is_active)>
                                <label class="form-check-label" for="wfActive">On</label>
                            </div>
                            <button type="button" class="btn btn-soft-info" id="wfTry"><i class="ri-play-circle-line me-1"></i>Try it</button>
                            <button type="submit" class="btn btn-primary" id="wfSave"><i class="ri-save-3-line me-1"></i>Save</button>
                        </div>
                        <div class="col-12">
                            <label for="wfDesc" class="visually-hidden">Description</label>
                            <input id="wfDesc" name="description" class="form-control form-control-sm" maxlength="500" placeholder="What it's for (optional)" value="{{ old('description', $wf->description) }}">
                        </div>
                    </div>
                    <div id="wfCanvas" class="wf-canvas" aria-label="Workflow steps"></div>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="wf-side">
                <div class="cb-card"><div class="cb-card-body" id="wfPanel" aria-live="polite"></div></div>
                <div class="cb-card mt-3 d-none" id="wfTryCard">
                    <div class="cb-card-header"><h5><i class="ri-play-circle-line"></i>Try it</h5><button type="button" class="btn-close" aria-label="Close" id="wfTryClose"></button></div>
                    <div class="cb-card-body" id="wfTryBody"></div>
                </div>
            </div>
        </div>
    </form>

    @if($wf->exists)
        <div class="d-flex gap-2 mt-3 flex-wrap">
            <form method="POST" action="{{ route('workflows.duplicate', $wf) }}" class="m-0">@csrf<button class="btn btn-sm btn-soft-secondary"><i class="ri-file-copy-line me-1"></i>Make a copy</button></form>
            <form method="POST" action="{{ route('workflows.destroy', $wf) }}" class="m-0" onsubmit="return confirm('Delete this workflow and its run history?')">@csrf @method('DELETE')<button class="btn btn-sm btn-soft-danger"><i class="ri-delete-bin-line me-1"></i>Delete</button></form>
        </div>
    @endif
</div></div></div>
@endsection

@push('styles')
<style>
    .wf-canvas { background-color: var(--vz-light); background-image: radial-gradient(var(--vz-border-color) 1px, transparent 1px); background-size: 18px 18px; border-radius: 12px; padding: 24px 16px; display: flex; flex-direction: column; align-items: center; min-height: 360px; }
    .wf-steps-list { display: flex; flex-direction: column; align-items: center; width: 100%; }
    .wf-item { display: flex; flex-direction: column; align-items: center; width: 100%; }
    .wf-node { width: 100%; max-width: 440px; background: var(--vz-card-bg, #fff); border: 2px solid var(--vz-border-color); border-radius: 12px; padding: 12px 14px; display: flex; gap: 12px; align-items: flex-start; cursor: pointer; text-align: left; color: var(--vz-body-color); box-shadow: 0 1px 2px rgba(0,0,0,.04); transition: border-color .15s, box-shadow .15s; position: relative; }
    .wf-node:hover { border-color: rgba(124, 58, 237, .5); }
    .wf-node.sel { border-color: #7c3aed; box-shadow: 0 0 0 4px rgba(124, 58, 237, .15); }
    .wf-node.empty { border-style: dashed; }
    .wf-node .wf-n-ico { width: 36px; height: 36px; border-radius: 9px; display: grid; place-items: center; font-size: 18px; flex: none; background: rgba(124, 58, 237, .1); color: #7c3aed; }
    .wf-node.k-trigger .wf-n-ico { background: #7c3aed; color: #fff; }
    .wf-node.k-cond .wf-n-ico, .wf-node.k-filter .wf-n-ico { background: rgba(245, 158, 11, .14); color: #b45309; }
    .wf-node.k-wait .wf-n-ico { background: rgba(100, 116, 139, .14); color: #475569; }
    .wf-node .wf-n-kind { font-size: 11px; text-transform: uppercase; letter-spacing: .04em; color: var(--vz-secondary-color); font-weight: 600; }
    .wf-node .wf-n-title { font-weight: 600; }
    .wf-node .wf-n-sum { font-size: 12.5px; color: var(--vz-secondary-color); overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; word-break: break-word; }
    .wf-node .wf-n-body { flex: 1; min-width: 0; }
    .wf-node .wf-n-warn { color: #dc2626; font-size: 12px; }
    .wf-grip { cursor: grab; color: var(--vz-secondary-color); padding: 2px; align-self: center; }
    .wf-num { position: absolute; left: -11px; top: -11px; width: 22px; height: 22px; border-radius: 50%; background: #7c3aed; color: #fff; font-size: 11px; display: grid; place-items: center; font-weight: 600; }
    .wf-line { width: 2px; height: 14px; background: var(--vz-border-color); }
    .wf-add { width: 28px; height: 28px; border-radius: 50%; border: 2px dashed var(--vz-border-color); background: var(--vz-card-bg, #fff); color: var(--vz-secondary-color); display: grid; place-items: center; padding: 0; line-height: 1; }
    .wf-add:hover, .wf-add:focus-visible { border-color: #7c3aed; color: #7c3aed; border-style: solid; }
    .wf-end { margin-top: 2px; font-size: 12px; color: var(--vz-secondary-color); background: var(--vz-card-bg, #fff); border: 1px solid var(--vz-border-color); border-radius: 20px; padding: 3px 12px; }
    .wf-menu { position: absolute; z-index: 1060; background: var(--vz-card-bg, #fff); border: 1px solid var(--vz-border-color); border-radius: 10px; box-shadow: 0 10px 30px rgba(0,0,0,.12); padding: 6px; width: 290px; }
    .wf-menu button { display: flex; gap: 10px; width: 100%; border: 0; background: none; text-align: left; padding: 8px; border-radius: 8px; color: var(--vz-body-color); }
    .wf-menu button:hover, .wf-menu button:focus-visible { background: var(--vz-light); }
    .wf-menu i { font-size: 18px; color: #7c3aed; }
    .wf-side { position: sticky; top: 86px; }
    @media (max-width: 991.98px) { .wf-side { position: static; } }
    .wf-tlist { max-height: 340px; overflow: auto; border: 1px solid var(--vz-border-color); border-radius: 8px; }
    .wf-tlist .wf-g { font-size: 11px; text-transform: uppercase; letter-spacing: .04em; font-weight: 600; color: var(--vz-secondary-color); padding: 8px 10px 4px; position: sticky; top: 0; background: var(--vz-card-bg, #fff); }
    .wf-tlist label { display: flex; gap: 8px; align-items: center; padding: 6px 10px; cursor: pointer; margin: 0; }
    .wf-tlist label:hover { background: var(--vz-light); }
    .wf-tlist input:checked + span { font-weight: 600; color: #7c3aed; }
    .wf-checks { max-height: 170px; overflow: auto; border: 1px solid var(--vz-border-color); border-radius: 8px; padding: 6px 10px; }
    .wf-rule { display: grid; grid-template-columns: 1fr 1fr 1fr auto; gap: 6px; margin-bottom: 6px; }
    @media (max-width: 575.98px) { .wf-rule { grid-template-columns: 1fr 1fr; } }
    .wf-ins { max-width: 150px; }
    .wf-try li { padding: 6px 0; border-bottom: 1px dashed var(--vz-border-color); }
    .wf-try li:last-child { border-bottom: 0; }
</style>
@endpush

@push('scripts')
<script src="{{ asset('vendor/sortablejs/Sortable.min.js') }}"></script>
<script>
    window.WF_CATALOG = @js($catalog);
    window.WF_DEF = @js($wf->definition);
    window.WF_URLS = @js(['preview' => route('workflows.preview')]);
    window.WF_OWNER = @js($wf->exists ? ($wf->creator?->name ?? 'whoever built it') : auth()->user()->name);
</script>
@verbatim
<script>
(function () {
    const CAT = window.WF_CATALOG;
    const $ = id => document.getElementById(id);
    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const token = document.querySelector('meta[name=csrf-token]').content;

    let def = window.WF_DEF || {};
    def.trigger = Object.assign({ key: '', config: {} }, def.trigger || {});
    if (Array.isArray(def.trigger.config)) def.trigger.config = {};
    def.conditions = Object.assign({ match: 'all', rules: [] }, def.conditions || {});
    def.steps = Array.isArray(def.steps) ? def.steps : [];
    def.steps.forEach(s => { if (!s.config || Array.isArray(s.config)) s.config = {}; });
    let sel = def.trigger.key ? (def.steps.length ? null : 'add') : 'trigger';
    let dirty = false;

    const trig = () => CAT.triggers[def.trigger.key] || null;
    const fields = () => (trig()?.fields || []);
    const people = () => (trig()?.people || []);
    const fieldLabel = k => fields().find(f => f.key === k)?.label || k;
    const OPEN = '{' + '{', CLOSE = '}' + '}';

    function touch() { dirty = true; renderCanvas(); }

    // ── Summaries on the canvas ─────────────────────────────────────
    function rulesText(c) {
        const rules = (c?.rules || []).filter(r => r.field);
        if (!rules.length) return '';
        return rules.map(r => {
            const f = fields().find(x => x.key === r.field);
            const v = ['empty', 'not_empty'].includes(r.op) ? '' : ' "' + (f?.options?.[r.value] ?? r.value ?? '') + '"';
            return (f?.label || r.field) + ' ' + (CAT.ops[r.op] || 'is') + v;
        }).join(c.match === 'any' ? ' or ' : ' and ');
    }
    function toText(to) {
        to = to || {};
        const p = (to.people || []).map(k => people().find(x => x.key === k)?.label || k);
        const u = (to.users || []).map(id => CAT.users.find(x => x.id == id)?.label).filter(Boolean);
        const r = (to.roles || []).map(n => 'everyone in ' + n);
        return [...p, ...u, ...r].join(', ');
    }
    function problem(s) {
        const c = s.config || {};
        const hasTo = t => t && ((t.people || []).length || (t.users || []).length || (t.roles || []).length);
        switch (s.type) {
            case 'notify': return !hasTo(c.to) ? 'Choose who to notify' : (!c.title && !c.message ? 'Write the notification' : '');
            case 'email': return !hasTo(c.to) && !c.emails ? 'Choose who to e-mail' : (!c.subject || !c.body ? 'Add a subject and message' : '');
            case 'chat': return !c.conversation_id ? 'Choose where to post' : (!c.message ? 'Write the message' : '');
            case 'task': return (c.project || 'record') === 'record' && !trig()?.project ? 'Choose a project' : '';
            case 'webhook': return !/^https?:\/\/./i.test(c.url || '') ? 'Enter the webhook address' : '';
            case 'filter': return !(c.rules || []).filter(r => r.field).length ? 'Add a rule' : '';
        }
        return '';
    }
    function summary(s) {
        const c = s.config || {};
        switch (s.type) {
            case 'notify': return (toText(c.to) ? 'To ' + toText(c.to) + ': ' : '') + (c.title || c.message || '');
            case 'email': return 'To ' + ([toText(c.to), c.emails].filter(Boolean).join(', ') || '…') + ': ' + (c.subject || '');
            case 'chat': return (CAT.chats.find(x => x.id == c.conversation_id)?.label || '…') + ': ' + (c.message || '');
            case 'task': return (c.project === 'record' || !c.project ? 'In the same project' : (CAT.projects.find(p => p.id == c.project)?.label || 'Project')) + ': ' + (c.title || 'Follow up');
            case 'webhook': try { return 'POST to ' + new URL(c.url).host; } catch (e) { return ''; }
            case 'wait': return (c.amount || 1) + ' ' + (c.unit || 'hours');
            case 'filter': return rulesText(c);
        }
        return '';
    }
    function triggerSummary() {
        const t = trig(); if (!t) return '';
        const c = def.trigger.config || {};
        if (def.trigger.key === 'schedule.daily') return 'At ' + (c.time || '08:00') + (c.weekdays_only ? ', Monday to Friday' : '');
        if (def.trigger.key === 'schedule.weekly') return 'On ' + (t.config.weekday[3][c.weekday || '1']) + ' at ' + (c.time || '08:00');
        if (def.trigger.key === 'schedule.monthly') return 'On day ' + (c.day || 1) + ' at ' + (c.time || '08:00');
        return CAT.groups[t.group]?.label || '';
    }

    // ── Canvas ──────────────────────────────────────────────────────
    function node(key, kind, icon, small, title, sum, extra = '') {
        const isSel = String(sel) === String(key);
        return '<button type="button" class="wf-node k-' + kind + (isSel ? ' sel' : '') + (title ? '' : ' empty') + '" data-sel="' + key + '" aria-pressed="' + isSel + '">'
            + extra + '<span class="wf-n-ico"><i class="' + icon + '"></i></span><span class="wf-n-body"><span class="wf-n-kind d-block">' + esc(small) + '</span>'
            + '<span class="wf-n-title d-block">' + esc(title || 'Choose…') + '</span>' + (sum ? '<span class="wf-n-sum">' + esc(sum) + '</span>' : '') + '</span></button>';
    }
    const line = () => '<div class="wf-line"></div>';
    const add = i => line() + '<button type="button" class="wf-add" data-add="' + i + '" aria-label="Add a step here" title="Add a step here"><i class="ri-add-line"></i></button>' + line();

    function renderCanvas() {
        const t = trig();
        let h = node('trigger', 'trigger', t?.icon || 'ri-flashlight-line', 'When', t?.label, triggerSummary());
        if (t && fields().length) {
            h += line() + node('conditions', 'cond', 'ri-filter-3-line', 'Only if', rulesText(def.conditions) || 'Every time', rulesText(def.conditions) ? '' : 'Click to add rules, such as "Priority is Urgent"');
        }
        h += '<div class="wf-steps-list" id="wfSteps">';
        def.steps.forEach((s, i) => {
            const st = CAT.steps[s.type] || { label: s.type, icon: 'ri-question-line' };
            const warn = problem(s);
            h += '<div class="wf-item" data-i="' + i + '">' + add(i)
                + node(i, s.type, st.icon, 'Then', st.label, summary(s),
                    '<span class="wf-num">' + (i + 1) + '</span>')
                    .replace('</span></button>', (warn ? '<span class="wf-n-warn d-block"><i class="ri-error-warning-line"></i> ' + esc(warn) + '</span>' : '') + '</span><span class="wf-grip" title="Drag to reorder" aria-hidden="true"><i class="ri-draggable"></i></span></button>')
                + '</div>';
        });
        h += '</div>' + add(def.steps.length) + '<div class="wf-end"><i class="ri-flag-line me-1"></i>Done</div>';
        $('wfCanvas').innerHTML = h;
        if (window.Sortable) Sortable.create($('wfSteps'), {
            handle: '.wf-grip', draggable: '.wf-item', animation: 150,
            onEnd: () => {
                const order = [...$('wfSteps').querySelectorAll('.wf-item')].map(el => +el.dataset.i);
                const before = typeof sel === 'number' ? def.steps[sel] : null;
                def.steps = order.map(i => def.steps[i]);
                if (before) sel = def.steps.indexOf(before);
                touch(); renderPanel();
            },
        });
    }

    $('wfCanvas').addEventListener('click', e => {
        const a = e.target.closest('[data-add]');
        if (a) return openMenu(a, +a.dataset.add);
        const n = e.target.closest('[data-sel]');
        if (n) { const v = n.dataset.sel; sel = /^\d+$/.test(v) ? +v : v; renderCanvas(); renderPanel(); if (window.innerWidth < 992) $('wfPanel').scrollIntoView({ behavior: 'smooth' }); }
    });

    // ── Add-step menu ───────────────────────────────────────────────
    let menu = null;
    function closeMenu() { menu?.remove(); menu = null; }
    function openMenu(btn, at) {
        closeMenu();
        menu = document.createElement('div');
        menu.className = 'wf-menu'; menu.setAttribute('role', 'menu');
        menu.innerHTML = Object.entries(CAT.steps).map(([k, s]) => '<button type="button" role="menuitem" data-type="' + k + '"><i class="' + s.icon + '"></i><span><span class="fw-semibold d-block">' + esc(s.label) + '</span><span class="small text-muted">' + esc(s.hint) + '</span></span></button>').join('');
        document.body.appendChild(menu);
        const r = btn.getBoundingClientRect();
        menu.style.top = (window.scrollY + r.bottom + 6) + 'px';
        menu.style.left = Math.max(8, Math.min(window.scrollX + r.left + r.width / 2 - 145, window.scrollX + document.documentElement.clientWidth - 300)) + 'px';
        menu.querySelector('button').focus();
        menu.addEventListener('click', e => {
            const b = e.target.closest('[data-type]'); if (!b) return;
            def.steps.splice(at, 0, { type: b.dataset.type, config: defaults(b.dataset.type) });
            sel = at; closeMenu(); touch(); renderPanel();
            $('wfPanel').querySelector('input, select, textarea')?.focus();
        });
        menu.addEventListener('keydown', e => {
            const items = [...menu.querySelectorAll('button')]; const i = items.indexOf(document.activeElement);
            if (e.key === 'ArrowDown') { e.preventDefault(); items[(i + 1) % items.length].focus(); }
            if (e.key === 'ArrowUp') { e.preventDefault(); items[(i - 1 + items.length) % items.length].focus(); }
            if (e.key === 'Escape') { closeMenu(); btn.focus(); }
        });
    }
    document.addEventListener('click', e => { if (menu && !menu.contains(e.target) && !e.target.closest('[data-add]')) closeMenu(); });

    function defaults(type) {
        const firstPerson = people()[0]?.key;
        switch (type) {
            case 'notify': return { to: { people: firstPerson ? [firstPerson] : [], users: [], roles: [] }, title: trig() ? OPEN + 'record.title' + CLOSE : '', message: '', link: '' };
            case 'email': return { to: { people: [], users: [], roles: [] }, emails: '', subject: '', body: '', include_link: false };
            case 'chat': return { conversation_id: CAT.chats[0]?.id || 0, message: '', include_link: !!trig()?.fields.length };
            case 'task': return { project: trig()?.project ? 'record' : '', title: '', description: '', assignee: '', priority: 'normal', due_days: '' };
            case 'webhook': return { url: '', secret: '' };
            case 'wait': return { amount: 1, unit: 'days' };
            case 'filter': return { match: 'all', rules: [{ field: fields()[0]?.key || '', op: 'is', value: '' }] };
        }
        return {};
    }

    // ── The editing panel ───────────────────────────────────────────
    const P = $('wfPanel');
    let uid = 0;
    const id = () => 'wf' + (++uid);

    function insertSelect(target) {
        const opts = [...fields().map(f => [f.key, f.label]), ['record.title', 'Record name'], ['record.link', 'Link to the record'], ['actor.name', 'Who did it'], ['today', "Today's date"]];
        if (!trig()?.fields.length) opts.splice(0, opts.length, ['actor.name', 'Who did it'], ['today', "Today's date"]);
        return '<select class="form-select form-select-sm wf-ins" data-insert="' + target + '" aria-label="Insert a field"><option value="">Insert field…</option>'
            + opts.map(([k, l]) => '<option value="' + k + '">' + esc(l) + '</option>').join('') + '</select>';
    }
    function text(label, path, value, { multi = false, ph = '', fieldsOn = true, rows = 4, max = 2000 } = {}) {
        const i = id();
        const ctl = multi
            ? '<textarea id="' + i + '" class="form-control" rows="' + rows + '" maxlength="' + max + '" data-path="' + path + '" placeholder="' + esc(ph) + '">' + esc(value) + '</textarea>'
            : '<input id="' + i + '" class="form-control" maxlength="' + max + '" data-path="' + path + '" placeholder="' + esc(ph) + '" value="' + esc(value) + '">';
        return '<div class="mb-3"><div class="d-flex justify-content-between align-items-end gap-2 mb-1"><label class="form-label m-0" for="' + i + '">' + esc(label) + '</label>'
            + (fieldsOn ? insertSelect(i) : '') + '</div>' + ctl + '</div>';
    }
    function select(label, path, value, options, help = '') {
        const i = id();
        return '<div class="mb-3"><label class="form-label" for="' + i + '">' + esc(label) + '</label><select id="' + i + '" class="form-select" data-path="' + path + '">'
            + options.map(([v, l]) => '<option value="' + esc(v) + '"' + (String(v) === String(value ?? '') ? ' selected' : '') + '>' + esc(l) + '</option>').join('')
            + '</select>' + (help ? '<div class="form-text">' + help + '</div>' : '') + '</div>';
    }
    function check(label, path, value) {
        const i = id();
        return '<div class="form-check mb-3"><input class="form-check-input" type="checkbox" id="' + i + '" data-path="' + path + '"' + (value ? ' checked' : '') + '><label class="form-check-label" for="' + i + '">' + esc(label) + '</label></div>';
    }
    function recipients(path, to) {
        to = to || {};
        const ppl = people();
        let h = '<fieldset class="mb-3"><legend class="form-label fs-6 mb-1">Send to</legend>';
        if (ppl.length) {
            h += '<div class="small text-muted mb-1">People on the record</div><div class="mb-2">' + ppl.map(p => {
                const i = id();
                return '<div class="form-check"><input class="form-check-input" type="checkbox" id="' + i + '" data-list="' + path + '.people" value="' + p.key + '"' + ((to.people || []).includes(p.key) ? ' checked' : '') + '><label class="form-check-label" for="' + i + '">' + esc(p.label) + '</label></div>';
            }).join('') + '</div>';
        }
        h += '<div class="row g-2"><div class="col-sm-6"><div class="small text-muted mb-1">Everyone in a role</div><div class="wf-checks">' + CAT.roles.map(r => {
            const i = id();
            return '<div class="form-check"><input class="form-check-input" type="checkbox" id="' + i + '" data-list="' + path + '.roles" value="' + esc(r) + '"' + ((to.roles || []).includes(r) ? ' checked' : '') + '><label class="form-check-label" for="' + i + '">' + esc(r) + '</label></div>';
        }).join('') + '</div></div>';
        const fi = id();
        h += '<div class="col-sm-6"><label class="small text-muted mb-1" for="' + fi + '">Particular people</label><input id="' + fi + '" class="form-control form-control-sm mb-1" placeholder="Find someone" data-filter>'
            + '<div class="wf-checks">' + CAT.users.map(u => {
                const i = id();
                return '<div class="form-check" data-name="' + esc(u.label.toLowerCase()) + '"><input class="form-check-input" type="checkbox" id="' + i + '" data-list="' + path + '.users" data-num="1" value="' + u.id + '"' + ((to.users || []).map(Number).includes(u.id) ? ' checked' : '') + '><label class="form-check-label" for="' + i + '">' + esc(u.label) + '</label></div>';
            }).join('') + '</div></div></div></fieldset>';
        return h;
    }
    function rulesEditor(path, c, intro) {
        const fs = fields();
        if (!fs.length) return '<p class="text-muted small">This trigger has no details to check. Pick a different trigger to add rules.</p>';
        let h = '<p class="small text-muted">' + intro + '</p>';
        h += '<div class="d-flex align-items-center gap-2 mb-2"><span class="small">Carry on when</span><select class="form-select form-select-sm w-auto" data-path="' + path + '.match" aria-label="All or any"><option value="all"' + (c.match !== 'any' ? ' selected' : '') + '>all of these are true</option><option value="any"' + (c.match === 'any' ? ' selected' : '') + '>any of these is true</option></select></div>';
        (c.rules || []).forEach((r, i) => {
            const f = fs.find(x => x.key === r.field);
            const noValue = ['empty', 'not_empty'].includes(r.op);
            const vp = path + '.rules.' + i + '.value';
            const value = noValue ? '<span></span>' : (f?.options
                ? '<select class="form-select form-select-sm" data-path="' + vp + '" aria-label="Value"><option value="">Choose…</option>' + Object.entries(f.options).map(([k, l]) => '<option value="' + esc(k) + '"' + (k === r.value ? ' selected' : '') + '>' + esc(l) + '</option>').join('') + '</select>'
                : '<input class="form-control form-control-sm" data-path="' + vp + '" value="' + esc(r.value) + '" aria-label="Value" placeholder="Value">');
            h += '<div class="wf-rule">'
                + '<select class="form-select form-select-sm" data-path="' + path + '.rules.' + i + '.field" data-rerender="1" aria-label="Field">' + fs.map(x => '<option value="' + x.key + '"' + (x.key === r.field ? ' selected' : '') + '>' + esc(x.label) + '</option>').join('') + '</select>'
                + '<select class="form-select form-select-sm" data-path="' + path + '.rules.' + i + '.op" data-rerender="1" aria-label="Test">' + Object.entries(CAT.ops).map(([k, l]) => '<option value="' + k + '"' + (k === (r.op || 'is') ? ' selected' : '') + '>' + esc(l) + '</option>').join('') + '</select>'
                + value + '<button type="button" class="btn btn-sm btn-soft-danger" data-del-rule="' + path + '|' + i + '" aria-label="Remove rule"><i class="ri-close-line"></i></button></div>';
        });
        h += '<button type="button" class="btn btn-sm btn-soft-primary" data-add-rule="' + path + '"><i class="ri-add-line me-1"></i>Add a rule</button>';
        return h;
    }

    function renderPanel() {
        const t = trig();
        if (sel === 'trigger') {
            let h = '<h5 class="mb-1">When this happens</h5><p class="small text-muted">What starts the workflow.</p><div class="wf-tlist mb-3" role="radiogroup" aria-label="Trigger">';
            for (const [g, gl] of Object.entries(CAT.groups)) {
                const items = Object.entries(CAT.triggers).filter(([, x]) => x.group === g);
                if (!items.length) continue;
                h += '<div class="wf-g"><i class="' + gl.icon + ' me-1"></i>' + esc(gl.label) + '</div>';
                h += items.map(([k, x]) => '<label><input type="radio" class="form-check-input m-0" name="wfTrigger" value="' + k + '"' + (k === def.trigger.key ? ' checked' : '') + '><span>' + esc(x.label) + '</span></label>').join('');
            }
            h += '</div>';
            if (t?.hint) h += '<div class="alert alert-info py-2 small">' + esc(t.hint) + '</div>';
            for (const [name, spec] of Object.entries(t?.config || {})) {
                const [label, type, dflt, a, b] = spec;
                const v = def.trigger.config[name] ?? dflt;
                const i = id();
                if (type === 'time') h += '<div class="mb-3"><label class="form-label" for="' + i + '">' + esc(label) + '</label><input type="time" id="' + i + '" class="form-control w-auto" data-path="trigger.config.' + name + '" value="' + esc(v) + '"></div>';
                if (type === 'bool') h += check(label, 'trigger.config.' + name, v);
                if (type === 'number') h += '<div class="mb-3"><label class="form-label" for="' + i + '">' + esc(label) + '</label><input type="number" id="' + i + '" class="form-control w-auto" min="' + a + '" max="' + b + '" data-path="trigger.config.' + name + '" value="' + esc(v) + '"><div class="form-text">Up to the 28th, so every month has it.</div></div>';
                if (type === 'select') h += select(label, 'trigger.config.' + name, v, Object.entries(a));
            }
            if (t && Object.keys(t.config).length) h += '<p class="small text-muted mb-0">Runs once in the two hours after that time (the portal checks every minute).</p>';
            P.innerHTML = h;
            return;
        }
        if (sel === 'conditions') {
            P.innerHTML = '<h5 class="mb-1">Only if</h5>' + rulesEditor('conditions', def.conditions, 'Leave this empty to run every time. Rules check the record at the moment it happens.');
            return;
        }
        if (sel === 'add' || sel === null || !def.steps[sel]) {
            P.innerHTML = '<h5 class="mb-1">' + (t ? 'Now add what happens' : 'Start with a trigger') + '</h5><p class="text-muted small mb-3">' + (t ? 'Press a + on the left, or pick a step here.' : 'Click the "When" box to choose what starts this workflow.') + '</p>'
                + (t ? Object.entries(CAT.steps).map(([k, s]) => '<button type="button" class="btn btn-soft-primary btn-sm me-1 mb-2" data-quick="' + k + '"><i class="' + s.icon + ' me-1"></i>' + esc(s.label) + '</button>').join('') : '');
            return;
        }
        const i = sel, s = def.steps[i], c = s.config, st = CAT.steps[s.type];
        const p = 'steps.' + i + '.config';
        let h = '<div class="d-flex justify-content-between align-items-start gap-2"><div><h5 class="mb-1"><i class="' + st.icon + ' me-1"></i>' + (i + 1) + '. ' + esc(st.label) + '</h5><p class="small text-muted">' + esc(st.hint) + '</p></div>'
            + '<div class="btn-group btn-group-sm flex-none"><button type="button" class="btn btn-soft-secondary" data-move="-1" aria-label="Move up"' + (i === 0 ? ' disabled' : '') + '><i class="ri-arrow-up-line"></i></button>'
            + '<button type="button" class="btn btn-soft-secondary" data-move="1" aria-label="Move down"' + (i === def.steps.length - 1 ? ' disabled' : '') + '><i class="ri-arrow-down-line"></i></button>'
            + '<button type="button" class="btn btn-soft-secondary" data-dup aria-label="Copy step"><i class="ri-file-copy-line"></i></button>'
            + '<button type="button" class="btn btn-soft-danger" data-del aria-label="Remove step"><i class="ri-delete-bin-line"></i></button></div></div>';
        switch (s.type) {
            case 'notify':
                h += recipients(p + '.to', c.to) + text('Title', p + '.title', c.title, { max: 150 }) + text('Message', p + '.message', c.message, { multi: true, max: 1000 })
                    + text('Link (optional)', p + '.link', c.link, { ph: 'Leave empty to link to the record', max: 500 });
                break;
            case 'email':
                h += recipients(p + '.to', c.to) + text('Other e-mail addresses', p + '.emails', c.emails, { ph: 'e.g. accounts@client.com, or a field', max: 1000 })
                    + text('Subject', p + '.subject', c.subject, { max: 200 }) + text('Message', p + '.body', c.body, { multi: true, rows: 6, max: 5000 })
                    + check('Add an "Open in the portal" button (only for people who can sign in)', p + '.include_link', c.include_link);
                break;
            case 'chat':
                h += select('Post in', p + '.conversation_id', c.conversation_id, [['', 'Choose a channel or group'], ...CAT.chats.map(x => [x.id, x.label])], 'Posted as ' + esc(window.WF_OWNER) + '. Groups listed are ones you are in.')
                    + text('Message', p + '.message', c.message, { multi: true, max: 4000 }) + check('Add a link to the record', p + '.include_link', c.include_link);
                break;
            case 'task':
                h += select('Project', p + '.project', c.project, [...(t?.project ? [['record', 'The same project as the record']] : [['', 'Choose a project']]), ...CAT.projects.map(x => [x.id, x.label])])
                    + text('Task title', p + '.title', c.title, { ph: 'e.g. Follow up with ' + OPEN + 'deal.company' + CLOSE, max: 200 })
                    + text('Description (optional)', p + '.description', c.description, { multi: true, rows: 3 })
                    + select('Assign to', p + '.assignee', c.assignee, [['', 'Nobody'], ...people().map(x => ['person:' + x.key, x.label]), ...CAT.users.map(u => ['user:' + u.id, u.label])], 'If they are not on the project, the task is added unassigned.')
                    + '<div class="row g-2"><div class="col-6">' + select('Priority', p + '.priority', c.priority, Object.entries(CAT.priorities)) + '</div>'
                    + '<div class="col-6"><div class="mb-3"><label class="form-label" for="wfDue">Due in (days)</label><input type="number" min="0" max="365" class="form-control" id="wfDue" data-path="' + p + '.due_days" value="' + esc(c.due_days) + '" placeholder="No due date"></div></div></div>';
                break;
            case 'webhook':
                h += text('Address', p + '.url', c.url, { ph: 'https://hooks.zapier.com/…', fieldsOn: false, max: 500 })
                    + text('Secret (optional)', p + '.secret', c.secret, { fieldsOn: false, max: 200, ph: 'Signs each call' })
                    + '<p class="small text-muted">Sends a JSON POST with the record\'s details. With a secret, the <code>X-Qudroid-Signature</code> header is <code>sha256=</code> plus the HMAC of the body.</p>';
                break;
            case 'wait':
                h += '<div class="row g-2 mb-3"><div class="col-5"><label class="form-label" for="wfAmt">Wait for</label><input type="number" min="1" max="365" class="form-control" id="wfAmt" data-path="' + p + '.amount" value="' + esc(c.amount) + '"></div><div class="col-7">'
                    + select(' ', p + '.unit', c.unit, Object.entries(CAT.units)) + '</div></div><p class="small text-muted">After the wait, the steps below see the record as it is then, so a rule like "Status is not Paid" checks the latest state.</p>';
                break;
            case 'filter':
                h += rulesEditor(p, c, 'If these are not true, the run stops here and the steps below are skipped.');
                break;
        }
        P.innerHTML = h;
    }

    // ── Editing ─────────────────────────────────────────────────────
    function setPath(path, value) {
        const keys = path.split('.');
        let o = def;
        keys.slice(0, -1).forEach(k => { o = o[/^\d+$/.test(k) ? +k : k]; });
        o[keys[keys.length - 1]] = value;
    }
    function getPath(path) { return path.split('.').reduce((o, k) => o?.[/^\d+$/.test(k) ? +k : k], def); }

    P.addEventListener('input', e => {
        const el = e.target;
        if (el.dataset.filter !== undefined) {
            const q = el.value.toLowerCase();
            el.nextElementSibling.querySelectorAll('[data-name]').forEach(d => d.classList.toggle('d-none', !d.dataset.name.includes(q)));
            return;
        }
        if (el.name === 'wfTrigger') {
            const before = def.trigger.key;
            def.trigger = { key: el.value, config: {} };
            const keys = fields().map(f => f.key);
            def.conditions.rules = def.conditions.rules.filter(r => keys.includes(r.field));
            def.steps.forEach(s => { if (s.type === 'filter') s.config.rules = (s.config.rules || []).filter(r => keys.includes(r.field)); });
            if (!before && !def.steps.length) sel = 'add';
            touch(); renderPanel();
            return;
        }
        if (el.dataset.insert !== undefined) return;
        if (el.dataset.list) {
            const list = getPath(el.dataset.list) || [];
            const v = el.dataset.num ? +el.value : el.value;
            const next = el.checked ? [...new Set([...list, v])] : list.filter(x => String(x) !== String(v));
            setPath(el.dataset.list, next);
            touch();
            return;
        }
        if (el.dataset.path) {
            setPath(el.dataset.path, el.type === 'checkbox' ? el.checked : el.value);
            touch();
            if (el.dataset.rerender) renderPanel();
        }
    });
    P.addEventListener('change', e => {
        const el = e.target;
        if (el.dataset.insert !== undefined && el.value) {
            const target = $(el.dataset.insert);
            const tag = OPEN + el.value + CLOSE;
            const at = target.selectionStart ?? target.value.length;
            target.value = target.value.slice(0, at) + tag + target.value.slice(target.selectionEnd ?? at);
            target.dispatchEvent(new Event('input', { bubbles: true }));
            target.focus(); target.selectionStart = target.selectionEnd = at + tag.length;
            el.value = '';
        }
    });
    P.addEventListener('click', e => {
        const b = e.target.closest('button'); if (!b) return;
        if (b.dataset.quick) { def.steps.push({ type: b.dataset.quick, config: defaults(b.dataset.quick) }); sel = def.steps.length - 1; touch(); renderPanel(); return; }
        if (b.dataset.addRule) {
            const c = getPath(b.dataset.addRule);
            c.rules = c.rules || [];
            c.rules.push({ field: fields()[0]?.key || '', op: 'is', value: '' });
            touch(); renderPanel(); return;
        }
        if (b.dataset.delRule) {
            const [path, i] = b.dataset.delRule.split('|');
            getPath(path).rules.splice(+i, 1);
            touch(); renderPanel(); return;
        }
        if (typeof sel !== 'number') return;
        if (b.dataset.move) {
            const j = sel + +b.dataset.move;
            [def.steps[sel], def.steps[j]] = [def.steps[j], def.steps[sel]];
            sel = j; touch(); renderPanel(); P.querySelector('[data-move="' + b.dataset.move + '"]:not([disabled])')?.focus(); return;
        }
        if (b.dataset.dup !== undefined) { def.steps.splice(sel + 1, 0, JSON.parse(JSON.stringify(def.steps[sel]))); sel++; touch(); renderPanel(); return; }
        if (b.dataset.del !== undefined) { def.steps.splice(sel, 1); sel = def.steps.length ? Math.min(sel, def.steps.length - 1) : 'add'; touch(); renderPanel(); }
    });

    // ── Save and try ────────────────────────────────────────────────
    function showErrors(list) {
        const box = $('wfErrors');
        if (!list.length) { box.classList.add('d-none'); return; }
        box.innerHTML = list.map(esc).join('<br>');
        box.classList.remove('d-none');
        box.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
    async function send(url, body) {
        const r = await fetch(url, { method: 'POST', body, headers: { Accept: 'application/json', 'X-CSRF-TOKEN': token }, credentials: 'same-origin' });
        const j = await r.json().catch(() => ({}));
        if (r.status === 422) { showErrors(Object.values(j.errors || { x: [j.message || 'Please check the workflow.'] }).flat()); return null; }
        if (!r.ok) { showErrors([j.message || 'Something went wrong. Please try again.']); return null; }
        showErrors([]);
        return j;
    }
    ['wfName', 'wfDesc', 'wfActive'].forEach(k => $(k).addEventListener('input', () => { dirty = true; }));
    $('wfForm').addEventListener('submit', async e => {
        e.preventDefault();
        $('wfDefinition').value = JSON.stringify(def);
        const btn = $('wfSave'); btn.disabled = true;
        try {
            const j = await send($('wfForm').action, new FormData($('wfForm')));
            if (!j) return;
            dirty = false;
            if (!location.pathname.endsWith('/' + j.id)) { location.href = j.edit; return; }
            window.QToast ? QToast(j.message) : (btn.innerHTML = '<i class="ri-check-line me-1"></i>Saved', setTimeout(() => btn.innerHTML = '<i class="ri-save-3-line me-1"></i>Save', 1800));
        } finally { btn.disabled = false; }
    });
    $('wfTry').addEventListener('click', async () => {
        const fd = new FormData(); fd.append('definition', JSON.stringify(def)); fd.append('name', $('wfName').value);
        const btn = $('wfTry'); btn.disabled = true;
        try {
            const j = await send(WF_URLS.preview, fd);
            if (!j) return;
            let h = '';
            if (j.needs_record) h += '<p class="text-muted">There is no record of this kind yet, so the details are blank.</p>';
            else if (j.record) h += '<p class="small text-muted mb-2">Using the newest record: <strong>' + esc(j.record) + '</strong></p>';
            if (j.conditions) h += '<p class="mb-2">' + (j.passes ? '<i class="ri-checkbox-circle-line text-success me-1"></i>Its rules pass' : '<i class="ri-close-circle-line text-warning me-1"></i>Its rules do not pass for this record, so it would not run') + ': ' + esc(j.conditions) + '.</p>';
            h += '<ol class="wf-try list-unstyled mb-2">' + j.steps.map((s, i) => '<li class="' + (s.ok ? '' : 'text-danger') + '"><i class="' + (CAT.steps[s.type]?.icon || '') + ' me-1"></i><strong>' + (i + 1) + '.</strong> ' + esc(s.note) + '</li>').join('') + '</ol>';
            h += '<p class="small text-muted mb-0">Nothing was sent or changed.</p>';
            $('wfTryBody').innerHTML = h;
            $('wfTryCard').classList.remove('d-none');
            $('wfTryCard').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        } finally { btn.disabled = false; }
    });
    $('wfTryClose').addEventListener('click', () => $('wfTryCard').classList.add('d-none'));
    window.addEventListener('beforeunload', e => { if (dirty) { e.preventDefault(); e.returnValue = ''; } });

    renderCanvas();
    renderPanel();
})();
</script>
@endverbatim
@endpush
