<li class="nav-item">
    <a class="nav-link menu-link {{ request()->routeIs('inventory.*') ? 'active' : 'collapsed' }}" href="#sidebarInventory" data-bs-toggle="collapse" role="button" aria-expanded="{{ request()->routeIs('inventory.*') ? 'true' : 'false' }}" aria-controls="sidebarInventory">
        <i class="{{ $module['icon'] }}"></i> <span>{{ $module['name'] }}</span>
    </a>
    <div class="collapse menu-dropdown {{ request()->routeIs('inventory.*') ? 'show' : '' }}" id="sidebarInventory">
        <ul class="nav nav-sm flex-column">
            <li class="nav-item"><a href="{{ route('inventory.index') }}" class="nav-link {{ request()->routeIs('inventory.index') ? 'active' : '' }}">Overview</a></li>
            <li class="nav-item"><a href="{{ route('inventory.items.index') }}" class="nav-link {{ request()->routeIs('inventory.items.*', 'inventory.movements') ? 'active' : '' }}">Stock</a></li>
            <li class="nav-item"><a href="{{ route('inventory.orders.index') }}" class="nav-link {{ request()->routeIs('inventory.orders.*') ? 'active' : '' }}">Purchase orders</a></li>
            <li class="nav-item"><a href="{{ route('inventory.suppliers.index') }}" class="nav-link {{ request()->routeIs('inventory.suppliers.*') ? 'active' : '' }}">Suppliers</a></li>
            <li class="nav-item"><a href="{{ route('inventory.assets.index') }}" class="nav-link {{ request()->routeIs('inventory.assets.*') ? 'active' : '' }}">Assets</a></li>
            @can('Manage inventory')
                <li class="nav-item"><a href="{{ route('inventory.settings') }}" class="nav-link {{ request()->routeIs('inventory.settings') ? 'active' : '' }}">Categories & locations</a></li>
            @endcan
        </ul>
    </div>
</li>
