@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">

    <x-cb.hero :title="$project->name" icon="ri-layout-column-line" :subtitle="$project->code . ($project->client_name ? ' · ' . $project->client_name : ' · Internal') . ($project->due_on ? ' · due ' . $project->due_on->format('j M Y') : '')" :back="route('projects.index')" back-label="Projects">
        <x-slot:actions>
            @if($canManage)<a href="{{ route('projects.edit', $project) }}" class="cb-hero-btn"><i class="ri-edit-line"></i>Edit</a>@endif
            <a href="{{ route('projects.board', $project) }}" class="cb-hero-btn"><i class="ri-layout-column-line"></i>Board</a>
            @if(!$client && auth()->user()->can('Use connect') && \Illuminate\Support\Facades\Route::has('connect.project'))
                <a href="{{ route('connect.project', $project) }}" class="cb-hero-btn"><i class="ri-chat-smile-3-line"></i>Team chat</a>
            @endif
            @if(\Illuminate\Support\Facades\Route::has('connect.project-clients'))
                @php
                    $clientChat = \App\Models\Connect\Conversation::where('client_project_id', $project->id)->first();
                    $showClientChat = $client
                        ? $clientChat && $clientChat->members()->where('user_id', auth()->id())->exists()
                        : auth()->user()->can('Use connect') && ($clientChat || $project->members()->where('role', 'client')->exists());
                @endphp
                @if($showClientChat)
                    <a href="{{ route('connect.project-clients', $project) }}" class="cb-hero-btn"><i class="ri-earth-line"></i>{{ $client ? 'Chat with the team' : 'Client chat' }}</a>
                @endif
            @endif
            @if(!$client && auth()->user()->can('View meetings') && \Illuminate\Support\Facades\Route::has('meetings.create'))
                <a href="{{ route('meetings.create', ['project' => $project->id]) }}" class="cb-hero-btn"><i class="ri-video-chat-line"></i>Meeting</a>
            @endif
        </x-slot:actions>
    </x-cb.hero>

    @include('partials.flash')
    @include('modules.projects._tabs')

    <div class="row g-3 mb-1">
        <div class="col-xl-3 col-md-6"><x-cb.stat label="Done" :value="$summary['percent'] . '%'" icon="ri-checkbox-circle-line" accent="teal" :hint="($summary['counts']['done'] ?? 0) . ' of ' . $summary['total'] . ' tasks'" /></div>
        <div class="col-xl-3 col-md-6"><x-cb.stat label="In progress" :value="($summary['counts']['in_progress'] ?? 0) + ($summary['counts']['review'] ?? 0)" icon="ri-loader-4-line" accent="sky" :hint="($summary['counts']['review'] ?? 0) . ' in review'" /></div>
        <div class="col-xl-3 col-md-6"><x-cb.stat label="Overdue" :value="$summary['overdue']" icon="ri-alarm-warning-line" accent="rose" /></div>
        @unless($client)
            <div class="col-xl-3 col-md-6"><x-cb.stat label="Time logged" :value="\App\Models\Projects\TimeEntry::format($summary['minutes'])" icon="ri-time-line" accent="amber" :hint="\App\Models\Projects\TimeEntry::format($summary['billable']) . ' billable'" /></div>
        @endunless
    </div>

    <div class="row g-3">
        <div class="col-xl-8">
            <x-cb.card title="About" icon="ri-information-line">
                <div class="d-flex flex-wrap gap-2 mb-2">
                    @include('modules.projects._pill', ['value' => $project->status])
                    @include('modules.projects._pill', ['kind' => 'priority', 'value' => $project->priority])
                    @if($project->isOverdue())<span class="badge bg-danger-subtle text-danger">Past due date</span>@endif
                </div>
                @if($project->description)
                    <div class="text-body" style="white-space: pre-line">{{ $project->description }}</div>
                @else
                    <div class="text-muted">No description.</div>
                @endif
                <dl class="row mt-3 mb-0 small">
                    @if(!$client && $project->company && auth()->user()->can('View clients'))
                        <dt class="col-sm-3 text-muted fw-normal">Company</dt><dd class="col-sm-9"><a href="{{ route('crm.companies.show', $project->company) }}">{{ $project->company->name }}</a></dd>
                    @endif
                    <dt class="col-sm-3 text-muted fw-normal">Manager</dt><dd class="col-sm-9">{{ $project->manager?->name ?? '—' }}</dd>
                    <dt class="col-sm-3 text-muted fw-normal">Dates</dt><dd class="col-sm-9">{{ $project->starts_on?->format('j M Y') ?? '—' }} to {{ $project->due_on?->format('j M Y') ?? '—' }}</dd>
                    @if(!$client && $project->budget !== null)<dt class="col-sm-3 text-muted fw-normal">Budget</dt><dd class="col-sm-9">{{ \App\Support\Money::format($project->budget, $project->currency) }}</dd>@endif
                </dl>
                @if($canManage)
                    <form method="POST" action="{{ route('projects.status', $project) }}" class="d-flex gap-2 align-items-center mt-3" data-live data-live-auto>
                        @csrf
                        <label class="small text-muted">Status</label>
                        <select name="status" class="form-select form-select-sm" style="max-width: 12rem">
                            @foreach(\App\Models\Projects\Project::STATUSES as $k => $v)<option value="{{ $k }}" @selected($project->status === $k)>{{ $v }}</option>@endforeach
                        </select>
                    </form>
                @endif
            </x-cb.card>

            <x-cb.card title="Milestones" icon="ri-flag-line" :count="$project->milestones->count()" :flush="true">
                @if($project->milestones->isEmpty())
                    <div class="empty-state"><i class="ri-flag-line"></i><h6>No milestones</h6></div>
                @else
                    <div class="table-responsive"><table class="cb-table stack">
                        <tbody>
                        @foreach($project->milestones as $ms)
                            @php $c = $milestoneCounts->get($ms->id); $n = (int) ($c->n ?? 0); $d = (int) ($c->done ?? 0); @endphp
                            <tr>
                                <td data-label="Milestone">
                                    <span class="fw-semibold {{ $ms->completed_at ? 'text-decoration-line-through text-muted' : '' }}">{{ $ms->title }}</span>
                                    @if($ms->completed_at)<span class="badge bg-success-subtle text-success ms-1">Reached</span>@elseif($ms->isOverdue())<span class="badge bg-danger-subtle text-danger ms-1">Late</span>@endif
                                    @if($ms->description)<div class="small text-muted">{{ $ms->description }}</div>@endif
                                </td>
                                <td data-label="Due">{{ $ms->due_on?->format('j M Y') ?? '—' }}</td>
                                <td data-label="Tasks" style="min-width: 10rem">
                                    <div class="progress-track"><div class="progress-fill" style="width: {{ $n ? round($d * 100 / $n) : 0 }}%"></div></div>
                                    <div class="progress-meta"><a href="{{ route('projects.board', [$project, 'milestone' => $ms->id]) }}">{{ $d }} of {{ $n }} tasks</a></div>
                                </td>
                                @if($canManage)
                                    <td class="text-end text-nowrap">
                                        <form method="POST" action="{{ route('projects.milestones.toggle', [$project, $ms]) }}" class="d-inline" data-live>@csrf<button class="btn btn-sm btn-soft-success" title="{{ $ms->completed_at ? 'Reopen' : 'Mark reached' }}"><i class="{{ $ms->completed_at ? 'ri-arrow-go-back-line' : 'ri-check-line' }}"></i></button></form>
                                        <form method="POST" action="{{ route('projects.milestones.destroy', [$project, $ms]) }}" class="d-inline" data-confirm="Remove this milestone? Its tasks stay." data-live>@csrf @method('DELETE')<button class="btn btn-sm btn-ghost-danger"><i class="ri-delete-bin-line"></i></button></form>
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                        </tbody>
                    </table></div>
                @endif
                @if($canManage)
                    <form method="POST" action="{{ route('projects.milestones.store', $project) }}" class="d-flex flex-wrap gap-2 p-3 border-top" data-live>
                        @csrf
                        <input name="title" class="form-control form-control-sm" style="flex: 1 1 14rem" maxlength="150" required placeholder="New milestone, e.g. Design signed off">
                        <input type="date" name="due_on" class="form-control form-control-sm" style="max-width: 11rem">
                        <button class="btn btn-sm btn-soft-primary">Add</button>
                    </form>
                @endif
            </x-cb.card>

            <x-cb.card title="Recent activity" icon="ri-chat-history-line" :flush="true">
                @if($activity->isEmpty())
                    <div class="empty-state"><i class="ri-chat-history-line"></i><h6>Nothing yet</h6></div>
                @else
                    <ul class="list-group list-group-flush">
                        @foreach($activity as $a)
                            <li class="list-group-item small">
                                <span class="fw-semibold">{{ $a->user?->name ?? 'Someone' }}</span>
                                @if($a->is_event) {{ $a->body }} @else commented @endif
                                on <a href="{{ route('projects.tasks.show', [$project, $a->task->number]) }}">{{ $project->code }}-{{ $a->task->number }} {{ \Illuminate\Support\Str::limit($a->task->title, 50) }}</a>
                                @if(!$a->is_event)<div class="text-muted">{{ \Illuminate\Support\Str::limit($a->body, 140) }}</div>@endif
                                <span class="text-muted">· {{ $a->created_at->diffForHumans() }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-cb.card>
        </div>

        <div class="col-xl-4">
            @if($upcoming->isNotEmpty())
                <x-cb.card title="Coming up" icon="ri-calendar-event-line" :flush="true">
                    <ul class="list-group list-group-flush">
                        @foreach($upcoming as $t)
                            <li class="list-group-item small d-flex justify-content-between gap-2">
                                <span><a href="{{ route('projects.tasks.show', [$project, $t->number]) }}">{{ $project->code }}-{{ $t->number }}</a> {{ \Illuminate\Support\Str::limit($t->title, 40) }}<div class="text-muted">{{ $t->assignee?->name ?? 'Unassigned' }}</div></span>
                                <span class="{{ $t->isOverdue() ? 'text-danger fw-semibold' : 'text-muted' }} text-nowrap">{{ $t->due_on->format('j M') }}</span>
                            </li>
                        @endforeach
                    </ul>
                </x-cb.card>
            @endif

            <x-cb.card title="People" icon="ri-team-line" :count="$project->members->count()" :flush="true">
                <ul class="list-group list-group-flush">
                    @foreach($project->members->sortBy(fn ($m) => array_search($m->role, array_keys(\App\Models\Projects\ProjectMember::ROLES))) as $m)
                        <li class="list-group-item d-flex justify-content-between align-items-center gap-2">
                            <span>{{ $m->user->name }}@if(!$canManage)<span class="small text-muted"> · {{ \App\Models\Projects\ProjectMember::ROLES[$m->role] }}</span>@endif</span>
                            @if($canManage)
                                <span class="d-flex gap-1">
                                    <form method="POST" action="{{ route('projects.members.update', [$project, $m]) }}" data-live data-live-auto>
                                        @csrf @method('PUT')
                                        <select name="role" class="form-select form-select-sm">
                                            @foreach(\App\Models\Projects\ProjectMember::ROLES as $k => $v)
                                                @continue(($k === 'client') !== $m->user->isClient())
                                                <option value="{{ $k }}" @selected($m->role === $k)>{{ $v }}</option>
                                            @endforeach
                                        </select>
                                    </form>
                                    <form method="POST" action="{{ route('projects.members.destroy', [$project, $m]) }}" data-confirm="Remove {{ $m->user->name }} from the project? Their open tasks become unassigned." data-live>
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-ghost-danger"><i class="ri-close-line"></i></button>
                                    </form>
                                </span>
                            @endif
                        </li>
                    @endforeach
                </ul>
                @if($canManage && $addable->isNotEmpty())
                    <form method="POST" action="{{ route('projects.members.store', $project) }}" class="p-3 border-top" data-live>
                        @csrf
                        <select name="user_id" class="form-select form-select-sm mb-2" required>
                            <option value="">Add someone…</option>
                            <optgroup label="Staff">@foreach($addable->where('user_type', 'staff') as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach</optgroup>
                            <optgroup label="Clients">@foreach($addable->where('user_type', 'client') as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach</optgroup>
                        </select>
                        <div class="d-flex gap-2">
                            <select name="role" class="form-select form-select-sm">
                                @foreach(\App\Models\Projects\ProjectMember::ROLES as $k => $v)<option value="{{ $k }}" @selected($k === 'member')>{{ $v }}</option>@endforeach
                            </select>
                            <button class="btn btn-sm btn-soft-primary">Add</button>
                        </div>
                        <div class="form-text">Members work on tasks; viewers read and comment; clients see only tasks shared with them.</div>
                    </form>
                @endif
            </x-cb.card>
        </div>
    </div>

</div></div></div>
@endsection
