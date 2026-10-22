@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">

    <x-cb.hero :title="$project->name" icon="ri-layout-column-line" :subtitle="$list ? 'All tasks' : 'Drag cards between columns to change their status.'" :back="route('projects.show', $project)" :back-label="$project->name" />

    @include('partials.flash')
    @include('modules.projects._tabs')

    <form class="cb-toolbar cb-card mb-3" method="GET">
        @if($list)<input type="hidden" name="view" value="list">@endif
        <div class="cb-search"><i class="ri-search-line"></i><input name="q" value="{{ request('q') }}" placeholder="Title or {{ $project->code }}-12"></div>
        @unless($client)
            <select name="assignee" class="cb-select" onchange="this.form.submit()">
                <option value="">Anyone</option>
                <option value="me" @selected(request('assignee') === 'me')>Assigned to me</option>
                @foreach($people as $p)<option value="{{ $p->id }}" @selected(request('assignee') === (string) $p->id)>{{ $p->name }}</option>@endforeach
            </select>
        @endunless
        @if($project->milestones->isNotEmpty())
            <select name="milestone" class="cb-select" onchange="this.form.submit()">
                <option value="">Any milestone</option>
                @foreach($project->milestones as $m)<option value="{{ $m->id }}" @selected((int) request('milestone') === $m->id)>{{ $m->title }}</option>@endforeach
            </select>
        @endif
    </form>

    @if($list)
        <x-cb.card :flush="true">
            @if($tasks->isEmpty())
                <div class="empty-state"><i class="ri-list-check-2"></i><h6>No tasks</h6></div>
            @else
                <div class="table-responsive"><table class="cb-table stack">
                    <thead><tr><th>Task</th><th>Status</th><th>Priority</th><th>Assignee</th><th>Milestone</th><th>Due</th></tr></thead>
                    <tbody>
                    @foreach($tasks->sortBy(fn ($t) => [array_search($t->status, array_keys(\App\Models\Projects\Task::STATUSES)), $t->position]) as $t)
                        <tr>
                            <td data-label="Task"><a href="{{ route('projects.tasks.show', [$project, $t->number]) }}" class="fw-semibold">{{ $project->code }}-{{ $t->number }}</a> {{ $t->title }}</td>
                            <td data-label="Status">@include('modules.projects._pill', ['value' => $t->status])</td>
                            <td data-label="Priority">@include('modules.projects._pill', ['kind' => 'priority', 'value' => $t->priority])</td>
                            <td data-label="Assignee">{{ $t->assignee?->name ?? '—' }}</td>
                            <td data-label="Milestone">{{ $t->milestone?->title ?? '—' }}</td>
                            <td data-label="Due" class="{{ $t->isOverdue() ? 'text-danger fw-semibold' : '' }}">{{ $t->due_on?->format('j M Y') ?? '—' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table></div>
            @endif
        </x-cb.card>
    @else
        <div class="pj-board">
            @foreach(\App\Models\Projects\Task::STATUSES as $status => $label)
                <div class="pj-col">
                    <div class="pj-col-head"><span>{{ $label }}</span><span class="cb-count">{{ $columns[$status]->count() }}</span></div>
                    <div class="pj-list" data-status="{{ $status }}">
                        @foreach($columns[$status] as $t)
                            @php [$cd, $ct] = $t->checklistProgress(); @endphp
                            <a href="{{ route('projects.tasks.show', [$project, $t->number]) }}" class="pj-card" data-id="{{ $t->id }}" data-number="{{ $t->number }}">
                                <div class="d-flex justify-content-between gap-2 small text-muted">
                                    <span>{{ $project->code }}-{{ $t->number }}</span>
                                    @if($t->priority !== 'normal')@include('modules.projects._pill', ['kind' => 'priority', 'value' => $t->priority])@endif
                                </div>
                                <div class="pj-title">{{ $t->title }}</div>
                                <div class="d-flex flex-wrap gap-2 small text-muted align-items-center">
                                    @if($t->due_on)<span class="{{ $t->isOverdue() ? 'text-danger fw-semibold' : '' }}"><i class="ri-calendar-line"></i> {{ $t->due_on->format('j M') }}</span>@endif
                                    @if($ct)<span><i class="ri-checkbox-line"></i> {{ $cd }}/{{ $ct }}</span>@endif
                                    @if($t->comments_count)<span><i class="ri-chat-3-line"></i> {{ $t->comments_count }}</span>@endif
                                    @if($t->milestone)<span><i class="ri-flag-line"></i> {{ \Illuminate\Support\Str::limit($t->milestone->title, 18) }}</span>@endif
                                    @if(!$client && $t->client_visible)<span title="Visible to the client"><i class="ri-eye-line"></i></span>@endif
                                    <span class="ms-auto pj-avatar" title="{{ $t->assignee?->name ?? 'Unassigned' }}">{{ $t->assignee ? collect(explode(' ', $t->assignee->name))->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode('') : '—' }}</span>
                                </div>
                            </a>
                        @endforeach
                    </div>
                    @if($canWork)
                        <form method="POST" action="{{ route('projects.tasks.store', $project) }}" class="pj-add">
                            @csrf
                            <input type="hidden" name="status" value="{{ $status }}">
                            <input type="hidden" name="stay" value="1">
                            @if(request('milestone'))<input type="hidden" name="milestone_id" value="{{ (int) request('milestone') }}">@endif
                            <input name="title" class="form-control form-control-sm" maxlength="200" required placeholder="+ Add a task">
                        </form>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

</div></div></div>
@endsection

@push('styles')
<style>
    .pj-board { display: grid; grid-template-columns: repeat(4, minmax(240px, 1fr)); gap: 14px; overflow-x: auto; padding-bottom: 8px; }
    .pj-col { background: var(--cb-surface-2); border: 1px solid var(--cb-border); border-radius: var(--cb-radius); padding: 10px; display: flex; flex-direction: column; min-height: 200px; }
    .pj-col-head { display: flex; justify-content: space-between; align-items: center; font-weight: 600; color: var(--cb-heading); padding: 4px 4px 10px; }
    .pj-list { flex: 1; display: flex; flex-direction: column; gap: 8px; min-height: 40px; }
    .pj-card { display: block; background: var(--cb-surface); border: 1px solid var(--cb-border); border-radius: var(--cb-radius-sm); padding: 10px 12px; color: var(--cb-text); text-decoration: none; cursor: grab; }
    .pj-card:hover { border-color: var(--cb-teal); color: var(--cb-text); }
    .pj-title { font-weight: 600; color: var(--cb-heading); margin: 4px 0 6px; line-height: 1.35; }
    .pj-avatar { display: inline-flex; align-items: center; justify-content: center; width: 24px; height: 24px; border-radius: 50%; background: var(--cb-teal); color: #fff; font-size: 10px; font-weight: 700; }
    .pj-add { margin-top: 8px; }
    .pj-ghost { opacity: .4; }
    @media (max-width: 991px) { .pj-board { grid-template-columns: repeat(4, 260px); } }
</style>
@endpush

@if(!$list && $canWork)
@push('scripts')
<script src="{{ asset('theme/layouts/assets/libs/sortablejs/Sortable.min.js') }}"></script>
<script>
(function () {
    const moveUrl = @json(route('projects.tasks.move', [$project, '__N__']));
    const token = document.querySelector('meta[name="csrf-token"]')?.content;
    document.querySelectorAll('.pj-list').forEach(function (list) {
        Sortable.create(list, {
            group: 'tasks', animation: 150, ghostClass: 'pj-ghost',
            onEnd: function (e) {
                const to = e.to, card = e.item;
                const order = [...to.querySelectorAll('.pj-card')].map(c => +c.dataset.id);
                fetch(moveUrl.replace('__N__', card.dataset.number), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token },
                    body: JSON.stringify({ status: to.dataset.status, order: order }),
                }).then(r => { if (!r.ok) throw r; })
                  .then(() => document.querySelectorAll('.pj-col').forEach(col => col.querySelector('.cb-count').textContent = col.querySelectorAll('.pj-card').length))
                  .catch(() => { alert('Could not move the task. The page will reload.'); location.reload(); });
            },
        });
    });
})();
</script>
@endpush
@endif
