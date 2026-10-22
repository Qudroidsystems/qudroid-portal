<div class="modal fade" id="driveRename" tabindex="-1" aria-labelledby="driveRenameTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
        <form method="POST" id="driveRenameForm">
            @csrf
            <div class="modal-header"><h5 class="modal-title" id="driveRenameTitle">Rename</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body"><input name="name" class="form-control" maxlength="150" required aria-label="New name"></div>
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save</button></div>
        </form>
    </div></div>
</div>
@push('scripts')
<script>
document.addEventListener('click', function (e) {
    const b = e.target.closest('[data-drive-rename]');
    if (!b) return;
    const form = document.getElementById('driveRenameForm');
    form.action = b.dataset.driveRename;
    form.querySelector('[name=name]').value = b.dataset.name;
    const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('driveRename'));
    modal.show();
    setTimeout(() => { const i = form.querySelector('[name=name]'); i.focus(); const dot = i.value.lastIndexOf('.'); i.setSelectionRange(0, dot > 0 ? dot : i.value.length); }, 300);
});
</script>
@endpush
