<li class="nav-item">
    <a class="nav-link menu-link {{ request()->routeIs('meetings.*') ? 'active' : 'collapsed' }}" href="#sidebarMeetings" data-bs-toggle="collapse" role="button" aria-expanded="{{ request()->routeIs('meetings.*') ? 'true' : 'false' }}" aria-controls="sidebarMeetings">
        <i class="{{ $module['icon'] }}"></i> <span>{{ $module['name'] }}</span>
    </a>
    <div class="collapse menu-dropdown {{ request()->routeIs('meetings.*') ? 'show' : '' }}" id="sidebarMeetings">
        <ul class="nav nav-sm flex-column">
            <li class="nav-item"><a href="{{ route('meetings.calendar') }}" class="nav-link {{ request()->routeIs('meetings.calendar') ? 'active' : '' }}">Calendar</a></li>
            <li class="nav-item"><a href="{{ route('meetings.index') }}" class="nav-link {{ request()->routeIs('meetings.index', 'meetings.show', 'meetings.edit') ? 'active' : '' }}">Meetings</a></li>
            <li class="nav-item"><a href="{{ route('meetings.create') }}" class="nav-link {{ request()->routeIs('meetings.create') ? 'active' : '' }}">Schedule</a></li>
            @can('Manage meetings')
                <li class="nav-item"><a href="{{ route('meetings.rooms') }}" class="nav-link {{ request()->routeIs('meetings.rooms') ? 'active' : '' }}">Rooms</a></li>
            @endcan
        </ul>
    </div>
</li>
