@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">

    <x-cb.hero :title="'Time on ' . $project->name" icon="ri-time-line" :subtitle="$from->format('j M Y') . ' to ' . $to->format('j M Y') . ' · ' . \App\Models\Projects\TimeEntry::format($entries->sum('minutes'))" :back="route('projects.show', $project)" :back-label="$project->name" />

    @include('partials.flash')
    @include('modules.projects._tabs')

    <div class="row g-3">
        <div class="col-xl-8">
            <x-cb.card title="Entries" icon="ri-time-line" :count="$entries->count()" :flush="true">
                <form class="cb-toolbar" method="GET">
                    <label class="small text-muted">From</label><input type="date" name="from" value="{{ $from->toDateString() }}" class="cb-select">
                    <label class="small text-muted">To</label><input type="date" name="to" value="{{ $to->toDateString() }}" class="cb-select">
                    <button class="btn btn-sm btn-soft-primary">Show</button>
                </form>
                @if($entries->isEmpty())
                    <div class="empty-state"><i class="ri-time-line"></i><h6>No time logged in this period</h6></div>
                @else
                    <div class="table-responsive"><table class="cb-table stack">
                        <thead><tr><th>Date</th><th>Person</th><th>Task</th><th>Note</th><th class="num">Time</th><th></th></tr></thead>
                        <tbody>
                        @foreach($entries as $e)
                            <tr>
                                <td data-label="Date">{{ $e->date->format('j M Y') }}</td>
                                <td data-label="Person">{{ $e->user->name }}</td>
                                <td data-label="Task">@if($e->task)<a href="{{ route('projects.tasks.show', [$project, $e->task->number]) }}">{{ $project->code }}-{{ $e->task->number }}</a> {{ \Illuminate\Support\Str::limit($e->task->title, 40) }}@else — @endif</td>
                                <td data-label="Note" class="small">{{ $e->note }}@unless($e->billable) <span class="badge bg-light text-muted">not billable</span>@endunless</td>
                                <td data-label="Time" class="num">{{ \App\Models\Projects\TimeEntry::format($e->minutes) }}</td>
                                <td class="text-end">
                                    @if($e->user_id === auth()->id() || $canManage)
                                        <form method="POST" action="{{ route('projects.time.destroy', [$project, $e]) }}" onsubmit="return confirm('Remove this entry?')">@csrf @method('DELETE')<button class="btn btn-sm btn-ghost-danger"><i class="ri-delete-bin-line"></i></button></form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table></div>
                @endif
            </x-cb.card>
        </div>
        <div class="col-xl-4">
            <x-cb.card title="By person" icon="ri-team-line" :flush="true">
                <ul class="list-group list-group-flush">
                    @forelse($byPerson as $row)
                        <li class="list-group-item d-flex justify-content-between"><span>{{ $row['user']->name }}</span><span>{{ \App\Models\Projects\TimeEntry::format($row['minutes']) }} <span class="text-muted small">({{ \App\Models\Projects\TimeEntry::format($row['billable']) }} billable)</span></span></li>
                    @empty
                        <li class="list-group-item text-muted small">Nobody yet.</li>
                    @endforelse
                </ul>
            </x-cb.card>
            @if($canWork)
                <x-cb.card title="Log time" icon="ri-add-line">
                    <form method="POST" action="{{ route('projects.time.store', $project) }}">
                        @csrf
                        <select name="task_id" class="form-select form-select-sm mb-2">
                            <option value="">General project work</option>
                            @foreach($tasks as $t)<option value="{{ $t->id }}">{{ $project->code }}-{{ $t->number }} {{ \Illuminate\Support\Str::limit($t->title, 40) }}</option>@endforeach
                        </select>
                        <div class="row g-2 mb-2">
                            <div class="col-5"><input type="date" name="date" class="form-control form-control-sm" value="{{ today()->toDateString() }}" max="{{ today()->toDateString() }}" required></div>
                            <div class="col-3"><input type="number" name="hours" min="0" max="24" step="0.25" class="form-control form-control-sm" placeholder="h"></div>
                            <div class="col-4"><input type="number" name="mins" min="0" max="59" class="form-control form-control-sm" placeholder="min"></div>
                        </div>
                        <input name="note" class="form-control form-control-sm mb-2" maxlength="255" placeholder="What you did (optional)">
                        <input type="hidden" name="billable" value="0">
                        <div class="d-flex align-items-center gap-2">
                            <div class="form-check mb-0"><input type="checkbox" name="billable" value="1" class="form-check-input" id="bill2" checked><label class="form-check-label small" for="bill2">Billable</label></div>
                            <button class="btn btn-sm btn-primary ms-auto">Log time</button>
                        </div>
                    </form>
                </x-cb.card>
            @endif
        </div>
    </div>

</div></div></div>
@endsection
