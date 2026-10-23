{{-- The app launcher in the top bar: every module this person can open, one click away. --}}
@php $launch = \App\Support\Modules::available(); @endphp
@if(count($launch))
<div class="position-relative" id="al-wrapper">
    <button type="button" id="al-btn" class="btn btn-icon btn-topbar btn-ghost-dark rounded-circle" style="width:38px;height:38px;" aria-label="Apps" aria-haspopup="true" aria-expanded="false" title="Apps">
        <i class="ri-apps-2-line fs-20"></i>
    </button>
    <div id="al-menu" class="al-menu" hidden>
        <div class="al-grid">
            <a href="{{ route('dashboard') }}" class="al-app"><span class="al-icon" style="--c:#405189"><i class="ri-home-5-line"></i></span><span>Home</span></a>
            @foreach($launch as $m)
                <a href="{{ $m['url'] }}" class="al-app"><span class="al-icon" style="--c:{{ $m['color'] ?? '#405189' }}"><i class="{{ $m['icon'] }}"></i></span><span>{{ $m['name'] }}</span></a>
            @endforeach
            <a href="{{ route('notifications.index') }}" class="al-app"><span class="al-icon" style="--c:#ef4444"><i class="ri-notification-3-line"></i></span><span>Notifications</span></a>
            @if(Route::has('search'))<a href="{{ route('search') }}" class="al-app"><span class="al-icon" style="--c:#64748b"><i class="ri-search-line"></i></span><span>Search</span></a>@endif
        </div>
    </div>
</div>
<style>
    .al-menu { position:absolute; top:calc(100% + 8px); right:-90px; width:340px; max-width:94vw; background:var(--vz-dropdown-bg,#fff); border:1px solid var(--vz-border-color,#e9ebec); border-radius:14px; box-shadow:0 12px 30px rgba(0,0,0,.14); z-index:9999; padding:10px; max-height:70vh; overflow:auto; }
    .al-grid { display:grid; grid-template-columns:repeat(3, 1fr); gap:4px; }
    .al-app { display:flex; flex-direction:column; align-items:center; gap:6px; padding:12px 4px; border-radius:10px; color:inherit; text-align:center; font-size:12px; line-height:1.2; }
    .al-app:hover, .al-app:focus { background:var(--vz-light,#f3f6f9); color:inherit; }
    .al-icon { width:44px; height:44px; border-radius:12px; display:inline-flex; align-items:center; justify-content:center; font-size:22px; color:var(--c); background:color-mix(in srgb, var(--c) 14%, transparent); }
    @media (max-width: 575px) { .al-menu { position:fixed; top:64px; right:8px; left:8px; width:auto; } }
</style>
<script>
(function () {
    const btn = document.getElementById('al-btn'), menu = document.getElementById('al-menu');
    btn.addEventListener('click', e => { e.stopPropagation(); menu.hidden = !menu.hidden; btn.setAttribute('aria-expanded', String(!menu.hidden)); });
    document.addEventListener('click', e => { if (!menu.hidden && !document.getElementById('al-wrapper').contains(e.target)) { menu.hidden = true; btn.setAttribute('aria-expanded', 'false'); } });
    document.addEventListener('keydown', e => { if (e.key === 'Escape' && !menu.hidden) { menu.hidden = true; btn.focus(); } });
})();
</script>
@endif
