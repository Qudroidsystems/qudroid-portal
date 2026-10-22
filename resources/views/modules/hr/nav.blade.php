<li class="nav-item">
    <a class="nav-link menu-link {{ request()->routeIs('hr.*') ? 'active' : 'collapsed' }}" href="#sidebarHr" data-bs-toggle="collapse" role="button" aria-expanded="{{ request()->routeIs('hr.*') ? 'true' : 'false' }}" aria-controls="sidebarHr">
        <i class="{{ $module['icon'] }}"></i> <span>{{ $module['name'] }}</span>
    </a>
    <div class="collapse menu-dropdown {{ request()->routeIs('hr.*') ? 'show' : '' }}" id="sidebarHr">
        <ul class="nav nav-sm flex-column">
            <li class="nav-item"><a href="{{ route('hr.index') }}" class="nav-link {{ request()->routeIs('hr.index') ? 'active' : '' }}">Overview</a></li>
            <li class="nav-item"><a href="{{ route('hr.employees.index') }}" class="nav-link {{ request()->routeIs('hr.employees.*') ? 'active' : '' }}">Employees</a></li>
            <li class="nav-item"><a href="{{ route('hr.departments.index') }}" class="nav-link {{ request()->routeIs('hr.departments.*') ? 'active' : '' }}">Departments</a></li>
            <li class="nav-item"><a href="{{ route('hr.leave.index') }}" class="nav-link {{ request()->routeIs('hr.leave.*') ? 'active' : '' }}">Leave</a></li>
            @canany(['Manage payroll', 'Approve finance'])
                <li class="nav-item"><a href="{{ route('hr.payroll.index') }}" class="nav-link {{ request()->routeIs('hr.payroll.*') ? 'active' : '' }}">Payroll</a></li>
            @endcanany
        </ul>
    </div>
</li>
