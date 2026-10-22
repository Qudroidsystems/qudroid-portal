@push('styles')
<style>
    .drive-nav .nav-link { color: inherit; padding: .4rem .6rem; border-radius: 8px; display: flex; align-items: center; gap: .55rem; font-size: .875rem; }
    .drive-nav .nav-link i { font-size: 1.05rem; color: var(--cb-muted, #878a99); }
    .drive-nav .nav-link:hover { background: rgba(64, 81, 137, .06); }
    .drive-nav .nav-link.active { background: rgba(64, 81, 137, .1); font-weight: 600; }
    .drive-nav .nav-link.active i { color: var(--vz-primary, #405189); }
    .drive-side-head { font-size: .7rem; text-transform: uppercase; letter-spacing: .06em; color: var(--cb-muted, #878a99); margin: 1rem 0 .4rem .2rem; font-weight: 600; }
    .drive-table td, .drive-table th { vertical-align: middle; }
    .drive-table .drive-name { display: flex; align-items: center; gap: .6rem; min-width: 0; }
    .drive-table .drive-name a { color: inherit; font-weight: 500; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .drive-table .drive-name i.ico { font-size: 1.35rem; flex-shrink: 0; }
    .drive-star { color: #f7b84b; }
    .drive-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: .75rem; padding: 1rem; }
    .drive-tile { border: 1px solid var(--cb-border, #e9ebec); border-radius: 10px; padding: .75rem; position: relative; min-width: 0; }
    .drive-tile:hover { border-color: var(--vz-primary, #405189); }
    .drive-tile .thumb { height: 96px; display: flex; align-items: center; justify-content: center; border-radius: 8px; background: rgba(64, 81, 137, .04); margin-bottom: .5rem; overflow: hidden; }
    .drive-tile .thumb img { max-width: 100%; max-height: 100%; object-fit: cover; }
    .drive-tile .thumb i { font-size: 2.5rem; }
    .drive-tile .name { font-size: .85rem; font-weight: 500; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; display: block; color: inherit; }
    .drive-tile .dropdown { position: absolute; top: .35rem; right: .35rem; }
    .drive-drop { border: 2px dashed transparent; border-radius: 12px; transition: border-color .15s, background .15s; }
    .drive-drop.over { border-color: var(--vz-primary, #405189); background: rgba(64, 81, 137, .04); }
    .drive-preview { background: #f3f3f9; border-radius: 10px; min-height: 240px; display: flex; align-items: center; justify-content: center; overflow: hidden; }
    .drive-preview img, .drive-preview video { max-width: 100%; max-height: 75vh; }
    .drive-preview iframe { width: 100%; height: 75vh; border: 0; }
    .drive-preview pre { width: 100%; max-height: 70vh; overflow: auto; margin: 0; padding: 1rem; background: #fff; font-size: .8rem; white-space: pre-wrap; }
    [data-bs-theme="dark"] .drive-preview { background: rgba(255, 255, 255, .04); }
    [data-bs-theme="dark"] .drive-preview pre { background: transparent; }
    @media (max-width: 991.98px) { .drive-side { margin-bottom: 1rem; } }
</style>
@endpush
