<li class="nav-item">
    <a class="nav-link menu-link {{ request()->routeIs('crm.*') ? 'active' : 'collapsed' }}" href="#sidebarCrm" data-bs-toggle="collapse" role="button" aria-expanded="{{ request()->routeIs('crm.*') ? 'true' : 'false' }}" aria-controls="sidebarCrm">
        <i class="{{ $module['icon'] }}"></i> <span>{{ $module['name'] }}</span>
    </a>
    <div class="collapse menu-dropdown {{ request()->routeIs('crm.*') ? 'show' : '' }}" id="sidebarCrm">
        <ul class="nav nav-sm flex-column">
            <li class="nav-item"><a href="{{ route('crm.index') }}" class="nav-link {{ request()->routeIs('crm.index') ? 'active' : '' }}">Overview</a></li>
            <li class="nav-item"><a href="{{ route('crm.deals.index') }}" class="nav-link {{ request()->routeIs('crm.deals.*') ? 'active' : '' }}">Pipeline</a></li>
            <li class="nav-item"><a href="{{ route('crm.companies.index') }}" class="nav-link {{ request()->routeIs('crm.companies.*') ? 'active' : '' }}">Companies</a></li>
            <li class="nav-item"><a href="{{ route('crm.contacts.index') }}" class="nav-link {{ request()->routeIs('crm.contacts.*') ? 'active' : '' }}">Contacts</a></li>
            <li class="nav-item"><a href="{{ route('crm.follow-ups') }}" class="nav-link {{ request()->routeIs('crm.follow-ups') ? 'active' : '' }}">Follow-ups</a></li>
        </ul>
    </div>
</li>
