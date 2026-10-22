@php
    $section = $section ?? null;
    $pct = $quota ? min(100, round($usage / $quota * 100)) : 0;
    $links = [
        'mine' => ['drive.index', 'ri-hard-drive-2-line', 'My Drive'],
        'shared' => ['drive.shared', 'ri-share-line', 'Shared with me'],
        'recent' => ['drive.recent', 'ri-time-line', 'Recent'],
        'starred' => ['drive.starred', 'ri-star-line', 'Starred'],
        'chats' => ['drive.chats', 'ri-chat-3-line', 'From chats'],
        'trash' => ['drive.trash', 'ri-delete-bin-6-line', 'Trash'],
    ];
@endphp
<div class="cb-card drive-side">
    <div class="cb-card-body">
        <form action="{{ route('drive.search') }}" class="mb-3">
            <div class="input-group input-group-sm">
                <span class="input-group-text"><i class="ri-search-line"></i></span>
                <input name="q" value="{{ $term ?? '' }}" class="form-control" placeholder="Search files and folders" aria-label="Search Drive">
            </div>
        </form>
        <nav class="nav flex-column drive-nav">
            @foreach($links as $key => [$route, $ico, $label])
                <a href="{{ route($route) }}" class="nav-link {{ $section === $key ? 'active' : '' }}"><i class="{{ $ico }}"></i>{{ $label }}</a>
            @endforeach
        </nav>
        @if($projects->isNotEmpty())
            <div class="drive-side-head">Project files</div>
            <nav class="nav flex-column drive-nav">
                @foreach($projects->take(12) as $p)
                    <a href="{{ route('drive.project', $p) }}" class="nav-link text-truncate {{ (isset($project) && $project?->id === $p->id) ? 'active' : '' }}"><i class="ri-folder-chart-line"></i>{{ $p->name }}</a>
                @endforeach
            </nav>
        @endif
        <div class="drive-side-head">Storage</div>
        <div class="progress" style="height:6px" role="progressbar" aria-label="Storage used" aria-valuenow="{{ $pct }}" aria-valuemin="0" aria-valuemax="100">
            <div class="progress-bar {{ $pct >= 90 ? 'bg-danger' : ($pct >= 75 ? 'bg-warning' : '') }}" style="width: {{ $pct }}%"></div>
        </div>
        <div class="small text-muted mt-1">{{ \App\Models\Drive\File::humanSize($usage) }} of {{ \App\Models\Drive\File::humanSize($quota) }} used</div>
    </div>
</div>
