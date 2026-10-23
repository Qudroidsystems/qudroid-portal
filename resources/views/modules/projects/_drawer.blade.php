{{-- Side panel that opens a task over the board or list without leaving the page. --}}
@include('modules.projects._task-styles')
<div class="tk-drawer-back" id="tk-back" hidden></div>
<aside class="tk-drawer" id="tk-drawer" aria-hidden="true" aria-labelledby="tk-drawer-title" role="dialog">
    <header class="tk-drawer-head">
        <div class="fw-semibold text-truncate" id="tk-drawer-title">Task</div>
        <div class="d-flex gap-1 ms-auto">
            <a href="#" class="btn btn-sm btn-ghost-secondary" id="tk-full" title="Open the full page"><i class="ri-external-link-line"></i></a>
            <button type="button" class="btn btn-sm btn-ghost-secondary" id="tk-close" title="Close (Esc)" aria-label="Close"><i class="ri-close-line fs-5"></i></button>
        </div>
    </header>
    <div class="tk-drawer-body"><div id="task-panel" data-live-region></div></div>
</aside>
<style>
    .tk-drawer-back { position: fixed; inset: 0; background: rgba(15, 35, 66, .28); z-index: 1040; }
    .tk-drawer { position: fixed; top: 0; right: 0; bottom: 0; width: min(780px, 100vw); background: var(--cb-page, #f1f5f9); z-index: 1045; display: flex; flex-direction: column; box-shadow: -12px 0 32px rgba(15, 35, 66, .18); transform: translateX(105%); transition: transform .22s ease; }
    .tk-drawer.open { transform: none; }
    .tk-drawer-head { display: flex; align-items: center; gap: 8px; padding: 12px 16px; background: var(--cb-surface, #fff); border-bottom: 1px solid var(--cb-border, #e2e8f0); color: var(--cb-heading); }
    .tk-drawer-body { flex: 1; overflow-y: auto; padding: 16px; }
    .tk-skeleton { height: 14px; border-radius: 6px; background: var(--cb-border); margin-bottom: 10px; animation: tk-pulse 1.2s ease-in-out infinite; }
    @keyframes tk-pulse { 50% { opacity: .5; } }
    body.tk-open { overflow: hidden; }
</style>
<script>
(function () {
    const drawer = document.getElementById('tk-drawer'), back = document.getElementById('tk-back');
    const title = document.getElementById('tk-drawer-title'), full = document.getElementById('tk-full');
    let current = null, dirty = false, seq = 0;

    function setUrl(number) {
        const u = new URL(location.href);
        if (number) u.searchParams.set('task', number); else u.searchParams.delete('task');
        history.replaceState(history.state, '', u);
    }

    function prepare() {
        const panel = drawer.querySelector('#task-panel');
        if (!panel) return;
        if (panel.dataset.taskTitle) title.textContent = panel.dataset.taskTitle;
        panel.querySelectorAll('form[data-live]').forEach(f => {
            if (f.hasAttribute('data-live-delete')) f.dataset.liveClose = '';
            else f.dataset.liveAlso = current;
        });
    }

    async function open(url, number) {
        current = url;
        const mine = ++seq;
        full.href = url;
        title.textContent = 'Loading…';
        drawer.querySelector('#task-panel').innerHTML = '<div class="tk-skeleton" style="width:60%"></div><div class="tk-skeleton"></div><div class="tk-skeleton" style="width:80%"></div>';
        back.hidden = false;
        drawer.classList.add('open');
        drawer.setAttribute('aria-hidden', 'false');
        document.body.classList.add('tk-open');
        document.querySelectorAll('.pj-active').forEach(c => c.classList.remove('pj-active'));
        document.querySelector('.pj-card[data-task="' + number + '"]')?.classList.add('pj-active');
        setUrl(number);
        try {
            const { res, doc } = await Live.load(url);
            if (mine !== seq) return;
            if (!res.ok) { Live.toast(res.status === 404 ? 'That task no longer exists.' : 'Could not open the task.', 'danger'); close(); return; }
            const panel = doc.getElementById('task-panel');
            drawer.querySelector('#task-panel').innerHTML = panel ? panel.innerHTML : '';
            if (panel) drawer.querySelector('#task-panel').dataset.taskTitle = panel.dataset.taskTitle;
            prepare();
            drawer.querySelector('.tk-drawer-body').scrollTop = 0;
        } catch (e) { Live.toast('Could not reach the server.', 'danger'); close(); }
    }

    function close() {
        if (!drawer.classList.contains('open')) return;
        drawer.classList.remove('open');
        drawer.setAttribute('aria-hidden', 'true');
        back.hidden = true;
        document.body.classList.remove('tk-open');
        document.querySelectorAll('.pj-active').forEach(c => c.classList.remove('pj-active'));
        setUrl(null);
        current = null;
        seq++;
        if (dirty) { dirty = false; Live.refresh(location.href); }
    }

    document.addEventListener('click', function (e) {
        const a = e.target.closest('a.pj-open');
        if (!a || e.metaKey || e.ctrlKey || e.shiftKey || e.button !== 0) return;
        e.preventDefault();
        if (Date.now() - (+document.body.dataset.pjDragAt || 0) < 400) return;
        open(a.href, a.dataset.task);
    });
    drawer.addEventListener('submit', () => { dirty = true; });
    document.addEventListener('live:swapped', () => { if (current) prepare(); });
    document.addEventListener('live:close', () => { dirty = false; close(); });
    back.addEventListener('click', close);
    document.getElementById('tk-close').addEventListener('click', close);
    document.addEventListener('keydown', e => { if (e.key === 'Escape' && drawer.classList.contains('open') && !e.target.matches('input, textarea, select')) close(); });

    const start = new URL(location.href).searchParams.get('task');
    if (start && /^\d+$/.test(start)) {
        const a = document.querySelector('a.pj-open[data-task="' + start + '"]');
        open(a ? a.href : @json(route('projects.tasks.show', [$project, '__N__'])).replace('__N__', start), start);
    }
})();
</script>
