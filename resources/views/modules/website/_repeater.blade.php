{{-- Rows that can be added and removed: [data-repeater] holds rows and a <template>; names use __i__ for the row number. --}}
@once
@push('scripts')
<script>
document.querySelectorAll('[data-repeater]').forEach(box => {
    const list = box.querySelector('[data-rows]'), tpl = box.querySelector('template');
    let n = list.children.length + 1000;
    box.querySelector('[data-add-row]').addEventListener('click', () => {
        list.insertAdjacentHTML('beforeend', tpl.innerHTML.replaceAll('__i__', n++));
    });
    list.addEventListener('click', e => {
        const b = e.target.closest('[data-remove-row],[data-up-row]');
        if (!b) return;
        const row = b.closest('[data-row]');
        if (b.matches('[data-remove-row]')) row.remove();
        else if (row.previousElementSibling) row.parentNode.insertBefore(row, row.previousElementSibling);
    });
});
</script>
@endpush
@endonce
