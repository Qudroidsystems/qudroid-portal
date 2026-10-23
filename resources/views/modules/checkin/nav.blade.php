<li class="nav-item">
    <a class="nav-link menu-link {{ request()->routeIs('checkin.*') ? 'active' : '' }}" href="{{ route('checkin.index') }}">
        <i class="{{ $module['icon'] }}"></i> <span>{{ $module['name'] }}</span>
    </a>
</li>
