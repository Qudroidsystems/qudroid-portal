@php $connectUnread = auth()->check() ? app(\App\Services\Connect\ConnectService::class)->unreadTotal(auth()->user()) : 0; @endphp
<li class="nav-item">
    <a class="nav-link menu-link {{ request()->routeIs('connect.*') ? 'active' : '' }}" href="{{ route('connect.index') }}">
        <i class="{{ $module['icon'] }}"></i> <span>{{ $module['name'] }}</span>
        <span class="badge badge-pill bg-success ms-auto" id="connect-nav-badge" @if(!$connectUnread) style="display:none" @endif>{{ $connectUnread }}</span>
    </a>
</li>
