@extends('layouts.master')

@section('content')
@php $editing = $page->exists; $home = $page->slug === 'home'; @endphp
<div class="main-content"><div class="page-content"><div class="container-fluid">
    <x-cb.hero :title="$editing ? $page->title : 'New page'" icon="ri-pages-line"
               subtitle="Add blocks, fill them in, drag to reorder. Save, then view the page." :back="route('website.pages.index')" back-label="Pages">
        @if($editing)
            <x-slot:actions><a class="cb-hero-btn" href="{{ $page->url() }}" target="_blank"><i class="ri-external-link-line me-1"></i>{{ $page->isPublished() ? 'View page' : 'Preview draft' }}</a></x-slot:actions>
        @endif
    </x-cb.hero>
    @include('partials.flash')
    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

    <form method="POST" action="{{ $editing ? route('website.pages.update', $page) : route('website.pages.store') }}" id="pageForm">
        @csrf @if($editing) @method('PUT') @endif
        <input type="hidden" name="blocks" id="blocksInput">
        <div class="row g-3">
            <div class="col-xl-8">
                <div id="blocks"></div>
                <div class="dropdown mt-2">
                    <button type="button" class="btn btn-soft-primary w-100 py-3 dropdown-toggle" data-bs-toggle="dropdown"><i class="ri-add-line me-1"></i>Add a block</button>
                    <div class="dropdown-menu w-100 p-2 ws-addmenu">
                        @foreach($schema as $type => $b)
                            <button type="button" class="dropdown-item rounded d-flex gap-2 align-items-start py-2" data-add="{{ $type }}">
                                <i class="{{ $b['icon'] }} fs-4 text-primary"></i><span><span class="fw-medium d-block">{{ $b['label'] }}</span><small class="text-muted text-wrap">{{ $b['hint'] }}</small></span>
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>
            <div class="col-xl-4">
                <div class="ws-sticky">
                    <x-cb.card title="Page" icon="ri-file-text-line">
                        <div class="mb-3"><label class="form-label">Title</label><input name="title" class="form-control" required maxlength="150" value="{{ old('title', $page->title) }}"></div>
                        @unless($home)
                            <div class="mb-3"><label class="form-label">Address</label>
                                <div class="input-group"><span class="input-group-text">{{ url('/') }}/</span><input name="slug" class="form-control" maxlength="80" value="{{ old('slug', $page->slug) }}" placeholder="about"></div>
                                <div class="form-text">Letters, numbers and dashes. Left empty, it is made from the title.</div></div>
                        @endunless
                        <div class="mb-3"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="2" maxlength="300">{{ old('description', $page->description) }}</textarea><div class="form-text">For search engines and shared links.</div></div>
                        @if($home)
                            <input type="hidden" name="status" value="published">
                            <p class="small text-muted">This is the front page at <code>/</code>. It is always published.</p>
                        @else
                            <div class="mb-3"><label class="form-label">Status</label>
                                <select name="status" class="form-select">@foreach(\App\Models\Website\Page::STATUSES as $k => $v)<option value="{{ $k }}" @selected(old('status', $page->status) === $k)>{{ $v }}</option>@endforeach</select></div>
                            <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="in_nav" value="1" id="inNav" @checked(old('in_nav', $page->in_nav))><label class="form-check-label" for="inNav">Show in the top menu</label></div>
                            <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" name="in_footer" value="1" id="inFoot" @checked(old('in_footer', $page->in_footer))><label class="form-check-label" for="inFoot">Show in the footer</label></div>
                            <div class="mb-3"><label class="form-label">Order in menus</label><input name="position" type="number" min="0" max="999" class="form-control" value="{{ old('position', $page->position ?? 0) }}"></div>
                        @endif
                        <button class="btn btn-primary w-100">{{ $editing ? 'Save page' : 'Create page' }}</button>
                    </x-cb.card>
                    @if($editing && !$home)
                        <div class="mt-2 text-end"><button form="deletePage" class="btn btn-link text-danger btn-sm">Delete page</button></div>
                    @endif
                    <x-cb.card title="Tips" icon="ri-lightbulb-line" class="mt-3">
                        <ul class="small text-muted ps-3 mb-0">
                            <li>Alternate backgrounds (Light, Grey, Dark) between blocks, as apple.com does.</li>
                            <li>Links can be pages on the site (<code>/contact</code>, <code>/courses</code>) or full addresses.</li>
                            <li>Text blocks take Markdown: <code>**bold**</code>, <code>- lists</code>, <code>[links](/contact)</code>.</li>
                            <li>Icons are Remix icon names, e.g. <code>ri-cloud-line</code>.</li>
                        </ul>
                    </x-cb.card>
                </div>
            </div>
        </div>
    </form>
    @if($editing && !$home)
        <form method="POST" action="{{ route('website.pages.destroy', $page) }}" id="deletePage" onsubmit="return confirm('Delete this page?')">@csrf @method('DELETE')</form>
    @endif
</div></div></div>
@include('modules.website._picker')
@endsection

@push('styles')
<style>
    .ws-sticky { position: sticky; top: 90px; }
    .ws-block { border: 1px solid var(--vz-border-color); border-radius: 12px; background: var(--vz-secondary-bg, #fff); margin-bottom: 12px; }
    .ws-block-head { display: flex; align-items: center; gap: 10px; padding: 10px 12px; cursor: pointer; user-select: none; }
    .ws-block-head .ws-handle { cursor: grab; color: var(--vz-secondary-color); font-size: 18px; }
    .ws-block-head i.ws-type { font-size: 20px; color: var(--vz-primary); }
    .ws-block-head .ws-sum { color: var(--vz-secondary-color); font-size: 13px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; flex: 1; min-width: 0; }
    .ws-block-body { padding: 4px 16px 16px; border-top: 1px solid var(--vz-border-color); }
    .ws-block.is-closed .ws-block-body { display: none; }
    .ws-item { border: 1px dashed var(--vz-border-color); border-radius: 10px; padding: 10px; margin-bottom: 8px; position: relative; }
    .ws-item-tools { position: absolute; top: 6px; right: 6px; display: flex; gap: 2px; }
    .ws-thumb { height: 38px; width: 60px; object-fit: cover; border-radius: 6px; background: var(--vz-light); }
    .ws-addmenu { max-height: 60vh; overflow: auto; display: grid !important; grid-template-columns: 1fr 1fr; gap: 2px; }
    .ws-addmenu:not(.show) { display: none !important; }
    .sortable-ghost { opacity: .4; }
</style>
@endpush

@push('scripts')
<script src="{{ asset('vendor/sortablejs/Sortable.min.js') }}"></script>
<script>
(function () {
    const SCHEMA = @json($schema);
    let blocks = @json(old('blocks') ? json_decode(old('blocks'), true) : ($page->blocks ?? []));
    if (!Array.isArray(blocks)) blocks = [];
    const host = document.getElementById('blocks');
    const open = new WeakSet();
    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    const imgUrl = v => !v ? '' : (/^(https?:)?\/\//.test(v) || v.startsWith('/') ? v : @json(asset('storage')) + '/' + v);

    function blank(fields) {
        const o = {};
        Object.entries(fields).forEach(([k, f]) => {
            o[k] = f.type === 'list' ? [] : f.type === 'toggle' ? false : f.type === 'select' ? Object.keys(f.options || {})[0] || '' : '';
        });
        return o;
    }

    function input(f, value, onChange) {
        const wrap = document.createElement('div');
        wrap.className = 'mb-2';
        const id = 'f' + Math.random().toString(36).slice(2);
        if (f.type === 'toggle') {
            wrap.innerHTML = '<div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="' + id + '"><label class="form-check-label" for="' + id + '">' + esc(f.label) + '</label></div>';
            const el = wrap.querySelector('input'); el.checked = !!value; el.onchange = () => onChange(el.checked);
            return wrap;
        }
        wrap.innerHTML = '<label class="form-label small mb-1" for="' + id + '">' + esc(f.label) + '</label>';
        let el;
        if (f.type === 'select') {
            el = document.createElement('select'); el.className = 'form-select form-select-sm';
            const opts = f.options || {};
            if (!Object.keys(opts).length) el.innerHTML = '<option value="">No forms yet</option>';
            Object.entries(opts).forEach(([k, v]) => { const o = new Option(v, k); el.add(o); });
            el.value = value ?? '';
            if (el.value !== String(value ?? '') && el.options.length) { el.selectedIndex = 0; setTimeout(() => onChange(el.value)); }
            el.onchange = () => onChange(el.value);
        } else if (f.type === 'textarea' || f.type === 'markdown') {
            el = document.createElement('textarea'); el.className = 'form-control form-control-sm'; el.rows = f.type === 'markdown' ? 6 : 2;
            el.value = value ?? ''; el.oninput = () => onChange(el.value);
        } else {
            el = document.createElement('input'); el.className = 'form-control form-control-sm'; el.value = value ?? '';
            if (f.type === 'url') el.placeholder = '/contact or https://…';
            el.oninput = () => onChange(el.value);
        }
        el.id = id;
        if (f.type === 'image') {
            const g = document.createElement('div'); g.className = 'd-flex gap-2 align-items-center';
            const th = document.createElement('img'); th.className = 'ws-thumb'; th.alt = '';
            const set = v => { th.src = imgUrl(v); th.style.visibility = v ? 'visible' : 'hidden'; };
            set(value);
            el.placeholder = 'Choose or paste an image address';
            el.oninput = () => { onChange(el.value); set(el.value); };
            const b = document.createElement('button'); b.type = 'button'; b.className = 'btn btn-sm btn-soft-primary text-nowrap'; b.innerHTML = '<i class="ri-image-add-line"></i> Choose';
            b.onclick = () => wsPick(m => { el.value = m.path; onChange(m.path); set(m.path); });
            g.append(th, el, b); wrap.appendChild(g);
        } else {
            wrap.appendChild(el);
        }
        return wrap;
    }

    function listEditor(f, items, onChange) {
        const wrap = document.createElement('div');
        wrap.className = 'mb-2';
        wrap.innerHTML = '<label class="form-label small mb-1">' + esc(f.label) + '</label>';
        const box = document.createElement('div');
        wrap.appendChild(box);
        const draw = () => {
            box.innerHTML = '';
            items.forEach((item, i) => {
                const card = document.createElement('div'); card.className = 'ws-item';
                const tools = document.createElement('div'); tools.className = 'ws-item-tools';
                [['ri-arrow-up-s-line', 'Move up', () => { if (i > 0) { [items[i - 1], items[i]] = [items[i], items[i - 1]]; draw(); onChange(items); } }],
                 ['ri-arrow-down-s-line', 'Move down', () => { if (i < items.length - 1) { [items[i + 1], items[i]] = [items[i], items[i + 1]]; draw(); onChange(items); } }],
                 ['ri-delete-bin-line', 'Remove', () => { items.splice(i, 1); draw(); onChange(items); }]].forEach(([ic, t, fn]) => {
                    const b = document.createElement('button'); b.type = 'button'; b.className = 'btn btn-sm btn-ghost-secondary px-1 py-0'; b.title = t; b.innerHTML = '<i class="' + ic + '"></i>'; b.onclick = fn; tools.appendChild(b);
                });
                card.appendChild(tools);
                const row = document.createElement('div'); row.className = 'row g-2 pe-5';
                Object.entries(f.fields).forEach(([k, sub]) => {
                    const col = document.createElement('div'); col.className = (sub.type === 'textarea' || sub.type === 'image') ? 'col-12' : 'col-md-6';
                    col.appendChild(input(sub, item[k], v => { item[k] = v; onChange(items); }));
                    row.appendChild(col);
                });
                card.appendChild(row);
                box.appendChild(card);
            });
        };
        draw();
        const add = document.createElement('button'); add.type = 'button'; add.className = 'btn btn-sm btn-soft-secondary';
        add.innerHTML = '<i class="ri-add-line"></i> Add'; add.onclick = () => { items.push(blank(f.fields)); draw(); onChange(items); };
        wrap.appendChild(add);
        return wrap;
    }

    function summary(b) {
        return b.title || b.heading || b.eyebrow || (b.items && b.items.length ? b.items.length + ' items' : '') || '';
    }

    function render() {
        host.innerHTML = '';
        if (!blocks.length) host.innerHTML = '<div class="text-center text-muted border rounded-3 p-5">This page is empty. Add a block below.</div>';
        blocks.forEach((b, i) => {
            const s = SCHEMA[b.type];
            if (!s) return;
            const el = document.createElement('div');
            el.className = 'ws-block' + (open.has(b) ? '' : ' is-closed');
            el.dataset.i = i;
            const head = document.createElement('div'); head.className = 'ws-block-head';
            head.innerHTML = '<i class="ri-draggable ws-handle" title="Drag to move"></i><i class="ws-type ' + esc(s.icon) + '"></i><span class="fw-medium">' + esc(s.label) + '</span><span class="ws-sum">' + esc(summary(b)) + '</span>';
            const tools = document.createElement('div'); tools.className = 'd-flex gap-1';
            [['ri-arrow-up-line', 'Move up', () => move(i, -1)], ['ri-arrow-down-line', 'Move down', () => move(i, 1)],
             ['ri-file-copy-line', 'Duplicate', () => { const c = JSON.parse(JSON.stringify(b)); blocks.splice(i + 1, 0, c); open.add(c); render(); }],
             ['ri-delete-bin-line', 'Delete block', () => { if (confirm('Delete this ' + s.label + ' block?')) { blocks.splice(i, 1); render(); } }]].forEach(([ic, t, fn]) => {
                const btn = document.createElement('button'); btn.type = 'button'; btn.className = 'btn btn-sm btn-ghost-secondary px-2'; btn.title = t;
                btn.innerHTML = '<i class="' + ic + '"></i>'; btn.onclick = e => { e.stopPropagation(); fn(); }; tools.appendChild(btn);
            });
            head.appendChild(tools);
            head.onclick = () => { open.has(b) ? open.delete(b) : open.add(b); el.classList.toggle('is-closed'); };
            const body = document.createElement('div'); body.className = 'ws-block-body';
            body.innerHTML = '<p class="small text-muted my-2">' + esc(s.hint) + '</p>';
            const row = document.createElement('div'); row.className = 'row g-2';
            Object.entries(s.fields).forEach(([k, f]) => {
                const col = document.createElement('div');
                col.className = ['list', 'markdown', 'textarea', 'image'].includes(f.type) ? 'col-12' : 'col-md-6';
                if (f.type === 'list') {
                    if (!Array.isArray(b[k])) b[k] = [];
                    col.appendChild(listEditor(f, b[k], v => { b[k] = v; head.querySelector('.ws-sum').textContent = summary(b); }));
                } else {
                    col.appendChild(input(f, b[k], v => { b[k] = v; head.querySelector('.ws-sum').textContent = summary(b); }));
                }
                row.appendChild(col);
            });
            body.appendChild(row);
            el.append(head, body);
            host.appendChild(el);
        });
    }

    function move(i, d) {
        const j = i + d;
        if (j < 0 || j >= blocks.length) return;
        [blocks[i], blocks[j]] = [blocks[j], blocks[i]];
        render();
    }

    document.querySelectorAll('[data-add]').forEach(btn => btn.addEventListener('click', () => {
        const type = btn.dataset.add, b = Object.assign({ type }, blank(SCHEMA[type].fields));
        blocks.push(b); open.add(b); render();
        host.lastElementChild?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }));

    Sortable.create(host, { handle: '.ws-handle', animation: 150, onEnd: e => {
        if (e.oldIndex === e.newIndex) return;
        const [b] = blocks.splice(e.oldIndex, 1); blocks.splice(e.newIndex, 0, b); render();
    } });

    document.getElementById('pageForm').addEventListener('submit', () => {
        document.getElementById('blocksInput').value = JSON.stringify(blocks);
    });
    if (blocks.length === 1) open.add(blocks[0]);
    render();
})();
</script>
@endpush
