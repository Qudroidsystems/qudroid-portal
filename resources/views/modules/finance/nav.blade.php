<li class="nav-item">
    <a class="nav-link menu-link {{ request()->routeIs('finance.*') ? 'active' : 'collapsed' }}" href="#sidebarFinance" data-bs-toggle="collapse" role="button" aria-expanded="{{ request()->routeIs('finance.*') ? 'true' : 'false' }}" aria-controls="sidebarFinance">
        <i class="{{ $module['icon'] }}"></i> <span>{{ $module['name'] }}</span>
    </a>
    <div class="collapse menu-dropdown {{ request()->routeIs('finance.*') ? 'show' : '' }}" id="sidebarFinance">
        <ul class="nav nav-sm flex-column">
            <li class="nav-item"><a href="{{ route('finance.index') }}" class="nav-link {{ request()->routeIs('finance.index') ? 'active' : '' }}">Overview</a></li>
            <li class="nav-item"><a href="{{ route('finance.invoices.index') }}" class="nav-link {{ request()->routeIs('finance.invoices.*') ? 'active' : '' }}">Invoices</a></li>
            <li class="nav-item"><a href="{{ route('finance.payments.index') }}" class="nav-link {{ request()->routeIs('finance.payments.*') ? 'active' : '' }}">Payments</a></li>
        </ul>
    </div>
</li>
