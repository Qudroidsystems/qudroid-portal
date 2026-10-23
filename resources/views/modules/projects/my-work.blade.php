@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">

    <x-cb.hero title="My work" icon="ri-user-follow-line" subtitle="Tasks assigned to you on open projects, soonest first. Change a status right here." :back="route('projects.index')" back-label="Projects">
        <x-slot:actions>
            <a href="{{ route('projects.my-work', request()->boolean('done') ? [] : ['done' => 1]) }}" class="cb-hero-btn" data-live><i class="ri-checkbox-circle-line"></i>{{ request()->boolean('done') ? 'Hide done' : 'Show done' }}</a>
        </x-slot:actions>
    </x-cb.hero>

    @include('partials.flash')

    <div class="row g-3">
        <div class="col-xl-8">
            @php $any = false; @endphp
            @foreach($groups as $label => $tasks)
                @continue($tasks->isEmpty())
                @php $any = true; @endphp
                <x-cb.card :title="$label" :icon="$label === 'Overdue' ? 'ri-alarm-warning-line' : 'ri-task-line'" :count="$tasks->count()" :flush="true">
                    <div class="table-responsive"><table class="cb-table stack">
                        <tbody>
                        @foreach($tasks as $t)
                            <tr>
                                <td data-label="Task"><a href="{{ route('projects.tasks.show', [$t->project, $t->number]) }}" class="fw-semibold">{{ $t->title }}</a><div class="small text-muted">{{ $t->project->code }}-{{ $t->number }} · {{ $t->project->name }}</div></td>
                                <td data-label="Status"><form method="POST" action="{{ route('projects.tasks.update', [$t->project, $t->number]) }}" data-live data-live-auto>@csrf @method('PUT')
                                    <select name="status" class="form-select form-select-sm" style="min-width: 8rem" aria-label="Status">@foreach(\App\Models\Projects\Task::STATUSES as $k => $v)<option value="{{ $k }}" @selected($t->status === $k)>{{ $v }}</option>@endforeach</select></form></td>
                                <td data-label="Priority">@include('modules.projects._pill', ['kind' => 'priority', 'value' => $t->priority])</td>
                                <td data-label="Due" class="text-nowrap {{ $t->isOverdue() ? 'text-danger fw-semibold' : '' }}">{{ $t->due_on?->format('j M Y') ?? '—' }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table></div>
                </x-cb.card>
            @endforeach
            @unless($any)
                <x-cb.card><div class="empty-state"><i class="ri-cup-line"></i><h6>Nothing assigned to you right now</h6></div></x-cb.card>
            @endunless
        </div>
        <div class="col-xl-4">
            <x-cb.card title="My time this week" icon="ri-time-line" :flush="true">
                <div class="p-3 fs-4 fw-semibold">{{ \App\Models\Projects\TimeEntry::format($week->sum('minutes')) }} <span class="fs-6 text-muted fw-normal">since {{ $weekStart->format('D j M') }}</span></div>
                <ul class="list-group list-group-flush">
                    @foreach($week->groupBy('project_id') as $rows)
                        <li class="list-group-item d-flex justify-content-between"><a href="{{ route('projects.time', $rows->first()->project) }}">{{ $rows->first()->project->name }}</a><span>{{ \App\Models\Projects\TimeEntry::format($rows->sum('minutes')) }}</span></li>
                    @endforeach
                </ul>
            </x-cb.card>
        </div>
    </div>

</div></div></div>
@endsection
