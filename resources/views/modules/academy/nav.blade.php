@php $manage = auth()->user()->can('Manage academy'); @endphp
<li class="nav-item">
    <a class="nav-link menu-link {{ request()->routeIs('academy.*') ? 'active' : 'collapsed' }}" href="#sidebarAcademy" data-bs-toggle="collapse" role="button" aria-expanded="{{ request()->routeIs('academy.*') ? 'true' : 'false' }}" aria-controls="sidebarAcademy">
        <i class="{{ $module['icon'] }}"></i> <span>{{ $module['name'] }}</span>
    </a>
    <div class="collapse menu-dropdown {{ request()->routeIs('academy.*') ? 'show' : '' }}" id="sidebarAcademy">
        <ul class="nav nav-sm flex-column">
            @if($manage)
                <li class="nav-item"><a href="{{ route('academy.index') }}" class="nav-link {{ request()->routeIs('academy.index') ? 'active' : '' }}">Overview</a></li>
                <li class="nav-item"><a href="{{ route('academy.applications.index') }}" class="nav-link {{ request()->routeIs('academy.applications.*') ? 'active' : '' }}">Applications</a></li>
                <li class="nav-item"><a href="{{ route('academy.students.index') }}" class="nav-link {{ request()->routeIs('academy.students.*') ? 'active' : '' }}">Students</a></li>
                <li class="nav-item"><a href="{{ route('academy.enrollments.index') }}" class="nav-link {{ request()->routeIs('academy.enrollments.*') ? 'active' : '' }}">Enrollments</a></li>
            @endif
            <li class="nav-item"><a href="{{ route('academy.courses.index') }}" class="nav-link {{ request()->routeIs('academy.courses.*', 'academy.cohorts.*') ? 'active' : '' }}">Courses</a></li>
            @if($manage)
                <li class="nav-item"><a href="{{ route('academy.programmes.index') }}" class="nav-link {{ request()->routeIs('academy.programmes.*') ? 'active' : '' }}">Programmes</a></li>
            @endif
            <li class="nav-item"><a href="{{ route('academy.my') }}" class="nav-link {{ request()->routeIs('academy.my') ? 'active' : '' }}">My academy</a></li>
        </ul>
    </div>
</li>
