<div class="d-flex flex-wrap gap-2 mb-3">
    <a href="{{ route('projects.show', $project) }}" class="btn btn-sm {{ request()->routeIs('projects.show') ? 'btn-primary' : 'btn-soft-secondary' }}"><i class="ri-dashboard-line"></i> Overview</a>
    <a href="{{ route('projects.board', $project) }}" class="btn btn-sm {{ request()->routeIs('projects.board') && request('view') !== 'list' ? 'btn-primary' : 'btn-soft-secondary' }}"><i class="ri-layout-column-line"></i> Board</a>
    <a href="{{ route('projects.board', [$project, 'view' => 'list']) }}" class="btn btn-sm {{ request()->routeIs('projects.board') && request('view') === 'list' ? 'btn-primary' : 'btn-soft-secondary' }}"><i class="ri-list-check-2"></i> List</a>
    @unless($client ?? false)
        <a href="{{ route('projects.time', $project) }}" class="btn btn-sm {{ request()->routeIs('projects.time') ? 'btn-primary' : 'btn-soft-secondary' }}"><i class="ri-time-line"></i> Time</a>
        @if(\Illuminate\Support\Facades\Route::has('drive.project') && auth()->user()->can('Use connect'))
            <a href="{{ route('drive.project', $project) }}" class="btn btn-sm btn-soft-secondary"><i class="ri-folder-cloud-line"></i> Files</a>
        @endif
    @endunless
</div>
