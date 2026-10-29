{{-- The picture picker. Any [data-ws-pick] button opens it; window.wsPick(cb) does too. --}}
<div class="modal fade" id="wsPicker" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content">
        <div class="modal-header">
            <h5 class="modal-title">Choose a picture</h5>
            <label class="btn btn-sm btn-primary ms-auto me-2 mb-0"><i class="ri-upload-2-line me-1"></i>Upload new<input type="file" accept="image/*" multiple class="d-none" id="wsPickerUpload"></label>
            <button type="button" class="btn-close ms-0" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body"><div class="ws-grid" id="wsPickerGrid"><div class="text-muted">Loading…</div></div></div>
    </div></div>
</div>
@push('styles')
<style>
    .ws-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 10px; }
    .ws-grid button { border: 2px solid transparent; border-radius: 10px; padding: 0; background: var(--vz-light); aspect-ratio: 1; overflow: hidden; }
    .ws-grid button:hover, .ws-grid button:focus { border-color: var(--vz-primary); }
    .ws-grid img { width: 100%; height: 100%; object-fit: cover; }
    .ws-image-preview img { max-width: 100%; max-height: 180px; border-radius: 10px; background: var(--vz-light); }
</style>
@endpush
@push('scripts')
<script>
(function () {
    const modalEl = document.getElementById('wsPicker');
    const grid = document.getElementById('wsPickerGrid');
    const token = document.querySelector('meta[name="csrf-token"]')?.content;
    let done = null, modal = null;
    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

    function draw(items) {
        grid.innerHTML = items.length ? '' : '<div class="text-muted">No pictures yet. Upload one.</div>';
        items.forEach(m => {
            const b = document.createElement('button');
            b.type = 'button'; b.title = m.name;
            b.innerHTML = '<img loading="lazy" src="' + esc(m.url) + '" alt="' + esc(m.name) + '">';
            b.onclick = () => { done && done(m); modal.hide(); };
            grid.appendChild(b);
        });
    }
    function load() {
        fetch(@json(route('website.media.list')), { headers: { Accept: 'application/json' } }).then(r => r.json()).then(j => draw(j.data || []));
    }
    window.wsPick = function (cb) {
        done = cb;
        modal = modal || bootstrap.Modal.getOrCreateInstance(modalEl);
        load(); modal.show();
    };
    document.getElementById('wsPickerUpload').addEventListener('change', function () {
        const fd = new FormData();
        [...this.files].forEach(f => fd.append('files[]', f));
        grid.innerHTML = '<div class="text-muted">Uploading…</div>';
        fetch(@json(route('website.media.store')), { method: 'POST', body: fd, headers: { 'X-CSRF-TOKEN': token, Accept: 'application/json' } })
            .then(r => r.ok ? r.json() : r.json().then(j => Promise.reject(j)))
            .then(() => load())
            .catch(j => { grid.innerHTML = '<div class="text-danger">' + esc(j?.message || 'Upload failed.') + '</div>'; });
        this.value = '';
    });

    // Picture fields on product / work forms.
    document.querySelectorAll('[data-ws-image]').forEach(box => {
        const val = box.querySelector('[data-ws-image-value]'), file = box.querySelector('[data-ws-image-file]');
        const prev = box.querySelector('.ws-image-preview'), img = prev.querySelector('img');
        const clear = box.querySelector('[data-ws-image-clear]'), remove = box.querySelector('[data-ws-image-remove]');
        const show = src => { img.src = src || ''; prev.classList.toggle('d-none', !src); clear.classList.toggle('d-none', !src); };
        box.querySelector('[data-ws-pick]').onclick = () => wsPick(m => { val.value = m.path; file.value = ''; remove.value = '0'; show(m.url); });
        file.onchange = () => { if (file.files[0]) { remove.value = '0'; show(URL.createObjectURL(file.files[0])); } };
        clear.onclick = () => { val.value = ''; file.value = ''; remove.value = '1'; show(''); };
    });
})();
</script>
@endpush
