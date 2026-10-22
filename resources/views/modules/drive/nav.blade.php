<li class="nav-item">
    <a class="nav-link menu-link {{ request()->routeIs('drive.*') ? 'active' : '' }}" href="{{ route('drive.index') }}">
        <i class="{{ $module['icon'] }}"></i> <span>{{ $module['name'] }}</span>
    </a>
</li>
