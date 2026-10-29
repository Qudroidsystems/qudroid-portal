@php $on = request()->routeIs('website.*'); @endphp
<li class="nav-item">
    <a class="nav-link menu-link {{ $on ? 'active' : 'collapsed' }}" href="#sidebarWebsite" data-bs-toggle="collapse" role="button" aria-expanded="{{ $on ? 'true' : 'false' }}" aria-controls="sidebarWebsite">
        <i class="{{ $module['icon'] }}"></i> <span>{{ $module['name'] }}</span>
    </a>
    <div class="collapse menu-dropdown {{ $on ? 'show' : '' }}" id="sidebarWebsite">
        <ul class="nav nav-sm flex-column">
            <li class="nav-item"><a href="{{ route('website.index') }}" class="nav-link {{ request()->routeIs('website.index') ? 'active' : '' }}">Overview</a></li>
            <li class="nav-item"><a href="{{ route('website.pages.index') }}" class="nav-link {{ request()->routeIs('website.pages.*') ? 'active' : '' }}">Pages</a></li>
            <li class="nav-item"><a href="{{ route('website.products.index') }}" class="nav-link {{ request()->routeIs('website.products.*') ? 'active' : '' }}">Products</a></li>
            <li class="nav-item"><a href="{{ route('website.works.index') }}" class="nav-link {{ request()->routeIs('website.works.*') ? 'active' : '' }}">Our work</a></li>
            <li class="nav-item"><a href="{{ route('website.forms.index') }}" class="nav-link {{ request()->routeIs('website.forms.*') ? 'active' : '' }}">Forms and entries</a></li>
            <li class="nav-item"><a href="{{ route('website.media.index') }}" class="nav-link {{ request()->routeIs('website.media.*') ? 'active' : '' }}">Pictures</a></li>
            <li class="nav-item"><a href="{{ route('website.settings') }}" class="nav-link {{ request()->routeIs('website.settings') ? 'active' : '' }}">Settings</a></li>
            <li class="nav-item"><a href="{{ route('site.home') }}" target="_blank" class="nav-link">View website <i class="ri-external-link-line"></i></a></li>
        </ul>
    </div>
</li>
