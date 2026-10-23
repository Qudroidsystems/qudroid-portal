<li class="nav-item">
    <a class="nav-link menu-link {{ request()->routeIs('mail.*') ? 'active' : '' }}" href="{{ route('mail.index') }}">
        <i class="{{ $module['icon'] }}"></i> <span>{{ $module['name'] }}</span>
    </a>
</li>
