@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">

    <x-cb.hero title="Projects" icon="ri-layout-column-line" subtitle="Client work and internal projects, with boards, milestones and time.">
        <x-slot:actions>
            <a href="{{ route('projects.my-work') }}" class="cb-hero-btn"><i class="ri-user-follow-line"></i>My work</a>
            @can('Manage projects')<a href="{{ route('projects.create') }}" class="cb-hero-btn"><i class="ri-add-line"></i>New project</a>@endcan
        </x-slot:actions>
    </x-cb.hero>

    @include('partials.flash')

    <div class="row g-3 mb-3">
        <div class="col-md-4"><x-cb.stat label="My open tasks" :value="$myOpen" icon="ri-task-line" accent="teal" /></div>
        <div class="col-md-4"><x-cb.stat label="My overdue tasks" :value="$myLate" icon="ri-alarm-warning-line" accent="rose" /></div>
        <div class="col-md-4"><x-cb.stat label="My time this week" :value="\App\Models\Projects\TimeEntry::format($myWeek)" icon="ri-time-line" accent="amber" /></div>
    </div>

    <x-cb.card title="Projects" icon="ri-layout-column-line" :count="$projects->total()" :flush="true">
        <form class="cb-toolbar" method="GET" data-live data-live-auto>
            <div class="cb-search"><i class="ri-search-line"></i><input type="search" name="q" value="{{ request('q') }}" placeholder="Name, code or client" autocomplete="off"></div>
            <select name="status" class="cb-select">
                <option value="open" @selected($status === 'open')>Open projects</option>
                <option value="all" @selected($status === 'all')>All projects</option>
                @foreach(\App\Models\Projects\Project::STATUSES as $k => $v)<option value="{{ $k }}" @selected($status === $k)>{{ $v }}</option>@endforeach
            </select>
        </form>
        @if($projects->isEmpty())
            <div class="empty-state"><i class="ri-layout-column-line"></i><h6>No projects here yet</h6>
                @can('Manage projects')<a href="{{ route('projects.create') }}" class="btn btn-sm btn-primary mt-2">Create the first one</a>@endcan
            </div>
        @else
            <div class="table-responsive"><table class="cb-table stack">
                <thead><tr><th>Project</th><th>Client</th><th>Manager</th><th>Due</th><th>Status</th><th style="width:16%">Progress</th></tr></thead>
                <tbody>
                @foreach($projects as $p)
                    @php $pct = $p->tasks_count ? (int) round($p->done_count * 100 / $p->tasks_count) : 0; @endphp
                    <tr>
                        <td data-label="Project"><span class="d-inline-block rounded-circle me-1" style="width:9px;height:9px;background:{{ $p->color }}"></span><a href="{{ route('projects.show', $p) }}" class="fw-semibold">{{ $p->name }}</a><div class="small text-muted">{{ $p->code }}</div></td>
                        <td data-label="Client">{{ $p->client_name ?: '—' }}</td>
                        <td data-label="Manager">{{ $p->manager?->name ?? '—' }}</td>
                        <td data-label="Due" class="{{ $p->isOverdue() ? 'text-danger fw-semibold' : '' }}">{{ $p->due_on?->format('j M Y') ?? '—' }}</td>
                        <td data-label="Status">@include('modules.projects._pill', ['value' => $p->status])</td>
                        <td data-label="Progress">
                            <div class="progress-track"><div class="progress-fill" style="width: {{ $pct }}%"></div></div>
                            <div class="progress-meta"><span>{{ $p->done_count }} of {{ $p->tasks_count }} tasks</span><span>{{ $pct }}%</span></div>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
            <div class="p-3">{{ $projects->links() }}</div>
        @endif
    </x-cb.card>

</div></div></div>
@endsection
