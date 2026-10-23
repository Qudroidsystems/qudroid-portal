<li class="nav-item">
    <a class="nav-link menu-link {{ request()->routeIs('command-center') ? 'active' : '' }}" href="{{ route('command-center') }}">
        <i class="{{ $module['icon'] }}"></i> <span>{{ $module['name'] }}</span>
    </a>
</li>
