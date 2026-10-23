{{-- Snooze until… (fires qm:snooze with an ISO time). --}}
<div class="modal fade" id="qmSnooze" tabindex="-1" aria-labelledby="qmSnoozeTitle" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered"><div class="modal-content">
        <div class="modal-header py-2"><h6 class="modal-title" id="qmSnoozeTitle">Snooze until…</h6><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
        <div class="list-group list-group-flush" id="qmSnoozeList">
            <button type="button" class="list-group-item list-group-item-action" data-snooze="later">Later today <small class="text-muted float-end" data-hint="later"></small></button>
            <button type="button" class="list-group-item list-group-item-action" data-snooze="tomorrow">Tomorrow <small class="text-muted float-end" data-hint="tomorrow"></small></button>
            <button type="button" class="list-group-item list-group-item-action" data-snooze="weekend">This weekend <small class="text-muted float-end" data-hint="weekend"></small></button>
            <button type="button" class="list-group-item list-group-item-action" data-snooze="week">Next week <small class="text-muted float-end" data-hint="week"></small></button>
        </div>
        <div class="modal-body border-top">
            <label for="qmSnoozeAt" class="form-label small mb-1">Pick date and time</label>
            <div class="d-flex gap-1"><input type="datetime-local" id="qmSnoozeAt" class="form-control form-control-sm"><button type="button" class="btn btn-sm btn-primary" data-snooze="pick">Save</button></div>
        </div>
    </div></div>
</div>
@push('scripts')
<script>
@verbatim
(function () {
    const when = k => {
        const d = new Date();
        if (k === 'later') { d.setHours(d.getHours() + 3, 0, 0, 0); }
        else if (k === 'tomorrow') { d.setDate(d.getDate() + 1); d.setHours(8, 0, 0, 0); }
        else if (k === 'weekend') { d.setDate(d.getDate() + ((6 - d.getDay() + 7) % 7 || 7)); d.setHours(8, 0, 0, 0); }
        else if (k === 'week') { d.setDate(d.getDate() + ((8 - d.getDay()) % 7 || 7)); d.setHours(8, 0, 0, 0); }
        return d;
    };
    const fmt = d => d.toLocaleString([], { weekday: 'short', hour: 'numeric', minute: '2-digit' });
    document.querySelectorAll('#qmSnooze [data-hint]').forEach(el => el.textContent = fmt(when(el.dataset.hint)));
    document.getElementById('qmSnooze').addEventListener('click', e => {
        const b = e.target.closest('[data-snooze]'); if (!b) return;
        let d;
        if (b.dataset.snooze === 'pick') { const v = document.getElementById('qmSnoozeAt').value; if (!v) return; d = new Date(v); }
        else d = when(b.dataset.snooze);
        if (d <= new Date()) { qmToast('Pick a time in the future.'); return; }
        document.dispatchEvent(new CustomEvent('qm:snooze', { detail: d.toISOString() }));
    });
})();
@endverbatim
</script>
@endpush
