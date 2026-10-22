<li class="nav-item">
    <a class="nav-link menu-link {{ request()->routeIs('workspace.*') ? 'active' : '' }}" href="{{ route('workspace.index') }}">
        <i class="{{ $module['icon'] }}"></i> <span>{{ $module['name'] }}</span>
    </a>
</li>
