<li class="nav-item">
    <a class="nav-link menu-link {{ request()->routeIs('projects.*') ? 'active' : 'collapsed' }}" href="#sidebarProjects" data-bs-toggle="collapse" role="button" aria-expanded="{{ request()->routeIs('projects.*') ? 'true' : 'false' }}" aria-controls="sidebarProjects">
        <i class="{{ $module['icon'] }}"></i> <span>{{ $module['name'] }}</span>
    </a>
    <div class="collapse menu-dropdown {{ request()->routeIs('projects.*') ? 'show' : '' }}" id="sidebarProjects">
        <ul class="nav nav-sm flex-column">
            <li class="nav-item"><a href="{{ route('projects.my-work') }}" class="nav-link {{ request()->routeIs('projects.my-work') ? 'active' : '' }}">My work</a></li>
            <li class="nav-item"><a href="{{ route('projects.index') }}" class="nav-link {{ request()->routeIs('projects.index', 'projects.show', 'projects.board', 'projects.tasks.*', 'projects.time') ? 'active' : '' }}">Projects</a></li>
            @can('Manage projects')
                <li class="nav-item"><a href="{{ route('projects.create') }}" class="nav-link {{ request()->routeIs('projects.create') ? 'active' : '' }}">New project</a></li>
            @endcan
        </ul>
    </div>
</li>
