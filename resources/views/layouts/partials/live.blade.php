{{--
    Live forms: any form or link marked data-live is sent in the background
    and the page content is swapped in place, so the sidebar, calls and
    sounds keep running and the page does not flash. Controllers stay as
    they are (redirect back with a flash); the flash becomes a toast.

    <form data-live>                     post in the background, swap the page
    <form method="GET" data-live>        filters: swap and update the address bar
    <a data-live>                        same for a link
    data-confirm="Question?"             ask first
    data-live-also="/url"                after success also refresh regions from this page
    data-live-close                      fire live:close (closes the task drawer)

    A region is an element with id and data-live-region; the whole page
    region is .main-content. Scripts listen for "live:swapped" to rebind.
--}}
<div id="live-toasts" aria-live="polite"></div>
<style>
    #live-toasts { position: fixed; right: 16px; bottom: 16px; z-index: 2000; display: flex; flex-direction: column; gap: 8px; max-width: min(380px, calc(100vw - 32px)); }
    .live-toast { display: flex; gap: 10px; align-items: flex-start; padding: 10px 14px; border-radius: 10px; background: #1f2937; color: #fff; box-shadow: 0 8px 24px rgba(0,0,0,.18); font-size: 14px; animation: live-in .18s ease-out; }
    .live-toast.danger { background: #b42318; }
    .live-toast i { font-size: 18px; line-height: 1; margin-top: 1px; }
    @keyframes live-in { from { transform: translateY(8px); opacity: 0; } to { transform: none; opacity: 1; } }
    .live-busy { opacity: .6; pointer-events: none; transition: opacity .15s; }
    #live-bar { position: fixed; top: 0; left: 0; height: 2px; width: 0; background: var(--cb-teal, #0ab39c); z-index: 2001; transition: width .3s ease, opacity .3s; opacity: 0; }
</style>
<div id="live-bar"></div>
<script>
(function () {
    const parser = new DOMParser();
    const bar = document.getElementById('live-bar');
    let pending = 0;

    function busy(on) {
        pending += on ? 1 : -1;
        if (pending > 0) { bar.style.opacity = 1; bar.style.width = '70%'; }
        else { bar.style.width = '100%'; setTimeout(() => { if (!pending) { bar.style.opacity = 0; bar.style.width = 0; } }, 250); }
    }

    function toast(text, kind) {
        if (!text) return;
        const el = document.createElement('div');
        el.className = 'live-toast' + (kind === 'danger' ? ' danger' : '');
        el.innerHTML = '<i class="' + (kind === 'danger' ? 'ri-error-warning-line' : 'ri-checkbox-circle-line') + '"></i><div></div>';
        el.lastChild.textContent = text;
        document.getElementById('live-toasts').appendChild(el);
        setTimeout(() => el.remove(), kind === 'danger' ? 7000 : 3500);
    }

    // A toast that should survive a full page load (e.g. after a delete).
    try {
        const carried = sessionStorage.getItem('live:toast');
        if (carried) { sessionStorage.removeItem('live:toast'); const t = JSON.parse(carried); setTimeout(() => toast(t.text, t.kind), 50); }
    } catch (e) {}

    function samePage(url) {
        const a = new URL(url, location.href);
        return a.origin === location.origin && a.pathname === location.pathname;
    }

    // Banners from partials.flash become toasts; the banners are removed so they don't stack.
    function flashes(doc) {
        const out = [];
        doc.querySelectorAll('.cb-banner.success, .cb-banner.danger').forEach(b => {
            if (b.closest('[data-live-keep-banner]') || b.querySelector('code')) return;
            out.push({ text: b.textContent.trim().replace(/\s+/g, ' '), kind: b.classList.contains('danger') ? 'danger' : 'success' });
            if (b.classList.contains('success')) b.remove();
        });
        return out;
    }

    function keepState(root) {
        const a = document.activeElement;
        const focus = a && root.contains(a) && (a.id || a.name) ? { id: a.id, name: a.name, form: a.form?.getAttribute('action'), status: a.form?.querySelector('[name=status]')?.value } : null;
        const scroll = [...root.querySelectorAll('[data-live-scroll]')].map(el => [el.dataset.liveScroll, el.scrollLeft, el.scrollTop]);
        return function restore() {
            scroll.forEach(([k, l, t]) => { const el = root.querySelector('[data-live-scroll="' + k + '"]'); if (el) { el.scrollLeft = l; el.scrollTop = t; } });
            if (!focus) return;
            let el = focus.id ? document.getElementById(focus.id) : null;
            if (!el) el = [...root.querySelectorAll('[name="' + CSS.escape(focus.name) + '"]')].find(i => i.form?.getAttribute('action') === focus.form && (!focus.status || i.form.querySelector('[name=status]')?.value === focus.status));
            el?.focus();
        };
    }

    // Swap regions from a fetched page. Returns how many were swapped.
    function swap(doc, url) {
        let n = 0;
        const page = document.querySelector('.main-content'), next = doc.querySelector('.main-content');
        if (samePage(url) && page && next) {
            const restore = keepState(page);
            page.innerHTML = next.innerHTML;
            restore();
            n++;
        } else {
            doc.querySelectorAll('[data-live-region][id]').forEach(r => {
                const here = document.getElementById(r.id);
                if (!here) return;
                const restore = keepState(here);
                here.innerHTML = r.innerHTML;
                Object.assign(here.dataset, r.dataset);
                restore();
                n++;
            });
        }
        if (n) {
            const title = doc.querySelector('title');
            if (title && samePage(url)) document.title = title.textContent;
            document.dispatchEvent(new CustomEvent('live:swapped', { detail: { url } }));
        }
        return n;
    }

    async function load(url, options) {
        const res = await fetch(url, Object.assign({ headers: { 'Accept': 'text/html', 'X-Live': '1' }, credentials: 'same-origin' }, options || {}));
        if (res.status === 419) { toast('Your session expired. Reloading the page.', 'danger'); setTimeout(() => location.reload(), 1200); throw new Error('419'); }
        if (res.redirected && /\/login(\?|$)/.test(new URL(res.url).pathname)) { location.href = res.url; throw new Error('login'); }
        const html = await res.text();
        return { res, doc: parser.parseFromString(html, 'text/html') };
    }

    async function refresh(url) {
        const { res, doc } = await load(url);
        if (res.ok) swap(doc, res.url);
        return res.ok;
    }

    async function submit(form, submitter) {
        const method = (form.getAttribute('method') || 'GET').toUpperCase();
        const action = form.getAttribute('action') || location.href;
        const data = new FormData(form);
        if (submitter && submitter.name) data.append(submitter.name, submitter.value);
        const region = form.closest('[data-live-region], .main-content') || form;
        region.classList.add('live-busy');
        busy(true);
        try {
            if (method === 'GET') {
                const url = new URL(action, location.href);
                url.search = new URLSearchParams([...data.entries()].filter(([, v]) => v !== '')).toString();
                const { res, doc } = await load(url);
                if (!res.ok) throw new Error(res.status);
                swap(doc, res.url);
                if (samePage(res.url)) history.replaceState({ live: true }, '', res.url);
                return;
            }
            const { res, doc } = await load(action, { method: 'POST', body: data });
            if (res.status === 403 || res.status === 404) { toast(res.status === 403 ? "You don't have permission to do that." : 'That item no longer exists.', 'danger'); return; }
            if (!res.ok) { toast('Something went wrong. Please try again.', 'danger'); return; }
            const notes = flashes(doc);
            const swapped = swap(doc, res.url);
            if (form.dataset.liveAlso) await refresh(form.dataset.liveAlso);
            if ('liveClose' in form.dataset) document.dispatchEvent(new CustomEvent('live:close'));
            if (!swapped && !form.dataset.liveAlso && !('liveClose' in form.dataset)) {
                // The action led somewhere else (e.g. a delete went back to the list).
                try { if (notes[0]) sessionStorage.setItem('live:toast', JSON.stringify(notes[0])); } catch (e) {}
                location.href = res.url;
                return;
            }
            notes.forEach(t => toast(t.text, t.kind));
            if (swapped && form.isConnected && !form.closest('.main-content') && method === 'POST') form.reset();
        } catch (e) {
            if (e.message !== '419' && e.message !== 'login') toast('Could not reach the server. Check your connection.', 'danger');
        } finally {
            region.classList.remove('live-busy');
            busy(false);
        }
    }

    document.addEventListener('submit', function (e) {
        const form = e.target;
        if (e.defaultPrevented || !form.matches('form[data-live]')) return;
        e.preventDefault();
        if (form.dataset.confirm && !confirm(form.dataset.confirm)) return;
        submit(form, e.submitter);
    });

    document.addEventListener('click', function (e) {
        const a = e.target.closest('a[data-live]');
        if (!a || e.defaultPrevented || e.metaKey || e.ctrlKey || e.shiftKey || e.button !== 0) return;
        if (!samePage(a.href)) return;
        e.preventDefault();
        busy(true);
        load(a.href).then(({ res, doc }) => { if (res.ok) { swap(doc, res.url); history.pushState({ live: true }, '', res.url); } else location.href = a.href; })
            .catch(() => location.href = a.href).finally(() => busy(false));
    });

    // Filters: selects and search boxes in a GET live form apply themselves.
    let typing;
    document.addEventListener('change', function (e) {
        const f = e.target.form;
        if (f && f.matches('form[data-live][data-live-auto]') && e.target.matches('select, input[type=checkbox], input[type=date]')) f.requestSubmit();
    });
    document.addEventListener('input', function (e) {
        const f = e.target.form;
        if (!f || !f.matches('form[data-live][data-live-auto]') || !e.target.matches('input[type=search], input[name=q]')) return;
        clearTimeout(typing);
        typing = setTimeout(() => f.requestSubmit(), 350);
    });

    window.addEventListener('popstate', function (e) {
        if (e.state && e.state.live) refresh(location.href).catch(() => location.reload());
    });

    window.Live = { toast, refresh, load, swap, submit };
})();
</script>
