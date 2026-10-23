<li class="nav-item">
    <a class="nav-link menu-link {{ request()->routeIs('workflows.*') ? 'active' : '' }}" href="{{ route('workflows.index') }}">
        <i class="{{ $module['icon'] }}"></i> <span>{{ $module['name'] }}</span>
    </a>
</li>
