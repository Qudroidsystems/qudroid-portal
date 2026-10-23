@extends('layouts.master')

@php
    $initials = fn ($name) => $name ? collect(explode(' ', $name))->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode('') : '—';
    $statusDot = ['todo' => '#94a3b8', 'in_progress' => '#0ea5e9', 'review' => '#f59e0b', 'done' => '#22c55e'];
@endphp

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">

    <x-cb.hero :title="$project->name" icon="ri-layout-column-line" :subtitle="$list ? 'All tasks. Change status, priority, assignee or due date right in the table.' : ($canWork ? 'Drag cards between columns. Click a card to open it here.' : 'Click a card to see its details.')" :back="route('projects.show', $project)" :back-label="$project->name" />

    @include('partials.flash')
    @include('modules.projects._tabs')

    <form class="cb-toolbar cb-card mb-3 pj-filters" method="GET" data-live data-live-auto>
        @if($list)<input type="hidden" name="view" value="list">@endif
        <div class="cb-search"><i class="ri-search-line"></i><input type="search" name="q" value="{{ request('q') }}" placeholder="Title or {{ $project->code }}-12" autocomplete="off"></div>
        @unless($client)
            <select name="assignee" class="cb-select" aria-label="Assignee">
                <option value="">Anyone</option>
                <option value="me" @selected(request('assignee') === 'me')>Assigned to me</option>
                @foreach($people as $p)<option value="{{ $p->id }}" @selected(request('assignee') === (string) $p->id)>{{ $p->name }}</option>@endforeach
            </select>
        @endunless
        <select name="priority" class="cb-select" aria-label="Priority">
            <option value="">Any priority</option>
            @foreach(\App\Models\Projects\Project::PRIORITIES as $k => $v)<option value="{{ $k }}" @selected(request('priority') === $k)>{{ $v }}</option>@endforeach
        </select>
        <select name="due" class="cb-select" aria-label="Due date">
            <option value="">Any due date</option>
            <option value="overdue" @selected(request('due') === 'overdue')>Overdue</option>
            <option value="week" @selected(request('due') === 'week')>Due in the next 7 days</option>
            <option value="none" @selected(request('due') === 'none')>No due date</option>
        </select>
        @if($project->milestones->isNotEmpty())
            <select name="milestone" class="cb-select" aria-label="Milestone">
                <option value="">Any milestone</option>
                @foreach($project->milestones as $m)<option value="{{ $m->id }}" @selected((int) request('milestone') === $m->id)>{{ $m->title }}</option>@endforeach
            </select>
        @endif
        @if($filtered)
            <a href="{{ route('projects.board', $list ? [$project, 'view' => 'list'] : $project) }}" class="btn btn-sm btn-ghost-secondary" data-live><i class="ri-close-line"></i> Clear</a>
        @endif
        <span class="ms-auto small text-muted pj-live-note" title="The board refreshes by itself when others change it"><i class="ri-refresh-line"></i> Live</span>
    </form>

    @if($list)
        <x-cb.card :flush="true">
            @if($tasks->isEmpty())
                <div class="empty-state"><i class="ri-list-check-2"></i><h6>No tasks{{ $filtered ? ' match these filters' : '' }}</h6></div>
            @else
                <div class="table-responsive"><table class="cb-table stack pj-table">
                    <thead><tr><th>Task</th><th>Status</th><th>Priority</th><th>Assignee</th><th>Milestone</th><th>Due</th></tr></thead>
                    <tbody>
                    @foreach($tasks->sortBy(fn ($t) => [array_search($t->status, array_keys(\App\Models\Projects\Task::STATUSES)), $t->position]) as $t)
                        @php $upd = route('projects.tasks.update', [$project, $t->number]); @endphp
                        <tr>
                            <td data-label="Task"><a href="{{ route('projects.tasks.show', [$project, $t->number]) }}" class="fw-semibold pj-open" data-task="{{ $t->number }}">{{ $project->code }}-{{ $t->number }}</a> {{ $t->title }}</td>
                            @if($canWork)
                                <td data-label="Status"><form method="POST" action="{{ $upd }}" data-live data-live-auto>@csrf @method('PUT')
                                    <select name="status" class="form-select form-select-sm pj-inline" aria-label="Status">@foreach(\App\Models\Projects\Task::STATUSES as $k => $v)<option value="{{ $k }}" @selected($t->status === $k)>{{ $v }}</option>@endforeach</select></form></td>
                                <td data-label="Priority"><form method="POST" action="{{ $upd }}" data-live data-live-auto>@csrf @method('PUT')
                                    <select name="priority" class="form-select form-select-sm pj-inline" aria-label="Priority">@foreach(\App\Models\Projects\Project::PRIORITIES as $k => $v)<option value="{{ $k }}" @selected($t->priority === $k)>{{ $v }}</option>@endforeach</select></form></td>
                                <td data-label="Assignee"><form method="POST" action="{{ $upd }}" data-live data-live-auto>@csrf @method('PUT')
                                    <select name="assignee_id" class="form-select form-select-sm pj-inline" aria-label="Assignee"><option value="">Unassigned</option>@foreach($people as $p)<option value="{{ $p->id }}" @selected($t->assignee_id === $p->id)>{{ $p->name }}</option>@endforeach</select></form></td>
                                <td data-label="Milestone">{{ $t->milestone?->title ?? '—' }}</td>
                                <td data-label="Due"><form method="POST" action="{{ $upd }}" data-live data-live-auto>@csrf @method('PUT')
                                    <input type="date" name="due_on" class="form-control form-control-sm pj-inline {{ $t->isOverdue() ? 'text-danger fw-semibold' : '' }}" value="{{ $t->due_on?->toDateString() }}" aria-label="Due date"></form></td>
                            @else
                                <td data-label="Status">@include('modules.projects._pill', ['value' => $t->status])</td>
                                <td data-label="Priority">@include('modules.projects._pill', ['kind' => 'priority', 'value' => $t->priority])</td>
                                <td data-label="Assignee">{{ $t->assignee?->name ?? '—' }}</td>
                                <td data-label="Milestone">{{ $t->milestone?->title ?? '—' }}</td>
                                <td data-label="Due" class="{{ $t->isOverdue() ? 'text-danger fw-semibold' : '' }}">{{ $t->due_on?->format('j M Y') ?? '—' }}</td>
                            @endif
                        </tr>
                    @endforeach
                    </tbody>
                </table></div>
            @endif
        </x-cb.card>
    @else
        <div class="pj-board" data-live-scroll="board" data-can-work="{{ $canWork ? 1 : 0 }}">
            @foreach(\App\Models\Projects\Task::STATUSES as $status => $label)
                <section class="pj-col" aria-label="{{ $label }}">
                    <div class="pj-col-head">
                        <span><i class="pj-dot" style="background: {{ $statusDot[$status] }}"></i>{{ $label }}</span>
                        <span class="cb-count">{{ $columns[$status]->count() }}</span>
                    </div>
                    <div class="pj-list" data-status="{{ $status }}">
                        @foreach($columns[$status] as $t)
                            @php [$cd, $ct] = $t->checklistProgress(); @endphp
                            <a href="{{ route('projects.tasks.show', [$project, $t->number]) }}" class="pj-card pj-open pj-p-{{ $t->priority }} {{ $status === 'done' ? 'is-done' : '' }}" data-id="{{ $t->id }}" data-number="{{ $t->number }}" data-task="{{ $t->number }}">
                                <div class="d-flex justify-content-between gap-2 small text-muted">
                                    <span>{{ $project->code }}-{{ $t->number }}</span>
                                    @if($t->priority !== 'normal')@include('modules.projects._pill', ['kind' => 'priority', 'value' => $t->priority])@endif
                                </div>
                                <div class="pj-title">{{ $t->title }}</div>
                                @if($ct)
                                    <div class="pj-progress" title="{{ $cd }} of {{ $ct }} checklist items done"><span style="width: {{ round($cd / $ct * 100) }}%"></span></div>
                                @endif
                                <div class="d-flex flex-wrap gap-2 small text-muted align-items-center">
                                    @if($t->due_on)<span class="{{ $t->isOverdue() ? 'text-danger fw-semibold' : '' }}"><i class="ri-calendar-line"></i> {{ $t->due_on->format('j M') }}</span>@endif
                                    @if($ct)<span><i class="ri-checkbox-line"></i> {{ $cd }}/{{ $ct }}</span>@endif
                                    @if($t->comments_count)<span><i class="ri-chat-3-line"></i> {{ $t->comments_count }}</span>@endif
                                    @if($t->milestone)<span><i class="ri-flag-line"></i> {{ \Illuminate\Support\Str::limit($t->milestone->title, 18) }}</span>@endif
                                    @if(!$client && $t->client_visible)<span title="Visible to the client"><i class="ri-eye-line"></i></span>@endif
                                    <span class="ms-auto pj-avatar {{ $t->assignee ? '' : 'none' }}" title="{{ $t->assignee?->name ?? 'Unassigned' }}" style="{{ $t->assignee ? 'background: hsl(' . ($t->assignee_id * 47 % 360) . ' 55% 42%)' : '' }}">{{ $initials($t->assignee?->name) }}</span>
                                </div>
                            </a>
                        @endforeach
                        <div class="pj-empty">{{ $canWork ? 'Drop tasks here' : 'Nothing here' }}</div>
                    </div>
                    @if($canWork)
                        <form method="POST" action="{{ route('projects.tasks.store', $project) }}" class="pj-add" data-live>
                            @csrf
                            <input type="hidden" name="status" value="{{ $status }}">
                            <input type="hidden" name="stay" value="1">
                            @if(request('milestone'))<input type="hidden" name="milestone_id" value="{{ (int) request('milestone') }}">@endif
                            @if(request('assignee') === 'me')<input type="hidden" name="assignee_id" value="{{ auth()->id() }}">@endif
                            <input name="title" class="form-control form-control-sm" maxlength="200" required placeholder="+ Add a task" autocomplete="off" aria-label="New task in {{ $label }}">
                        </form>
                    @endif
                </section>
            @endforeach
        </div>
    @endif

</div></div></div>
@endsection

@push('styles')
<style>
    .pj-board { display: grid; grid-template-columns: repeat(4, minmax(250px, 1fr)); gap: 14px; overflow-x: auto; padding-bottom: 8px; align-items: start; }
    .pj-col { background: var(--cb-surface-2); border: 1px solid var(--cb-border); border-radius: var(--cb-radius); padding: 10px; display: flex; flex-direction: column; min-height: 220px; }
    .pj-col-head { display: flex; justify-content: space-between; align-items: center; font-weight: 600; color: var(--cb-heading); padding: 4px 4px 10px; }
    .pj-dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; margin-right: 8px; vertical-align: middle; }
    .pj-list { flex: 1; display: flex; flex-direction: column; gap: 8px; min-height: 60px; border-radius: var(--cb-radius-sm); transition: background .15s; }
    .pj-list.pj-over { background: color-mix(in srgb, var(--cb-teal) 8%, transparent); }
    .pj-empty { display: none; text-align: center; color: var(--cb-muted); font-size: 12px; padding: 18px 8px; border: 1px dashed var(--cb-border); border-radius: var(--cb-radius-sm); }
    .pj-list:not(:has(.pj-card)) .pj-empty { display: block; }
    .pj-card { display: block; background: var(--cb-surface); border: 1px solid var(--cb-border); border-left: 3px solid transparent; border-radius: var(--cb-radius-sm); padding: 10px 12px; color: var(--cb-text); text-decoration: none; cursor: grab; transition: box-shadow .15s, border-color .15s, transform .15s; }
    .pj-card:hover { border-color: var(--cb-teal); color: var(--cb-text); box-shadow: 0 2px 8px rgba(15, 35, 66, .08); }
    .pj-card.pj-p-urgent { border-left-color: var(--cb-rose); }
    .pj-card.pj-p-high { border-left-color: var(--cb-amber); }
    .pj-card.is-done .pj-title { color: var(--cb-muted); text-decoration: line-through; }
    .pj-card.pj-active { border-color: var(--cb-teal); box-shadow: 0 0 0 2px color-mix(in srgb, var(--cb-teal) 30%, transparent); }
    .pj-card.pj-moved { animation: pj-flash 1s ease-out; }
    @keyframes pj-flash { from { background: color-mix(in srgb, var(--cb-teal) 18%, var(--cb-surface)); } to { background: var(--cb-surface); } }
    .pj-title { font-weight: 600; color: var(--cb-heading); margin: 4px 0 6px; line-height: 1.35; }
    .pj-progress { height: 4px; border-radius: 4px; background: var(--cb-border); overflow: hidden; margin-bottom: 6px; }
    .pj-progress span { display: block; height: 100%; background: var(--cb-green); border-radius: 4px; }
    .pj-avatar { display: inline-flex; align-items: center; justify-content: center; width: 24px; height: 24px; border-radius: 50%; background: var(--cb-teal); color: #fff; font-size: 10px; font-weight: 700; }
    .pj-avatar.none { background: var(--cb-border); color: var(--cb-muted); }
    .pj-add { margin-top: 8px; }
    .pj-add input { background: transparent; border-style: dashed; }
    .pj-add input:focus { background: var(--cb-surface); border-style: solid; }
    .pj-ghost { opacity: .35; }
    .pj-chosen { cursor: grabbing; }
    .pj-drag { transform: rotate(2deg); box-shadow: 0 10px 24px rgba(15, 35, 66, .18) !important; }
    .pj-inline { min-width: 8rem; border-color: transparent; background-color: transparent; }
    .pj-inline:hover, .pj-inline:focus { border-color: var(--cb-border); background-color: var(--cb-surface); }
    .pj-live-note { white-space: nowrap; }
    @media (max-width: 991px) { .pj-board { grid-template-columns: repeat(4, 82vw); scroll-snap-type: x mandatory; } .pj-col { scroll-snap-align: start; } }
</style>
@endpush

@push('scripts')
@include('modules.projects._drawer')
<script src="{{ asset('vendor/sortablejs/Sortable.min.js') }}"></script>
<script>
(function () {
    const moveUrl = @json(route('projects.tasks.move', [$project, '__N__']));
    const token = document.querySelector('meta[name="csrf-token"]')?.content;
    let dragging = false, lastHtml = null, sorts = [];

    function counts() {
        document.querySelectorAll('.pj-col').forEach(col => col.querySelector('.cb-count').textContent = col.querySelectorAll('.pj-card').length);
    }

    function bind() {
        sorts.forEach(s => s.destroy());
        sorts = [];
        const board = document.querySelector('.pj-board');
        if (!board || board.dataset.canWork !== '1' || !window.Sortable) return;
        board.querySelectorAll('.pj-list').forEach(function (list) {
            sorts.push(Sortable.create(list, {
                group: 'tasks', animation: 160, draggable: '.pj-card',
                ghostClass: 'pj-ghost', chosenClass: 'pj-chosen', dragClass: 'pj-drag',
                delay: 180, delayOnTouchOnly: true, forceFallback: false,
                onStart: () => { dragging = true; },
                onMove: (e) => { document.querySelectorAll('.pj-list').forEach(l => l.classList.toggle('pj-over', l === e.to)); },
                onEnd: function (e) {
                    dragging = false;
                    document.body.dataset.pjDragAt = Date.now();
                    document.querySelectorAll('.pj-over').forEach(l => l.classList.remove('pj-over'));
                    const to = e.to, card = e.item;
                    if (e.from === to && e.oldIndex === e.newIndex) return;
                    counts();
                    card.classList.toggle('is-done', to.dataset.status === 'done');
                    card.classList.remove('pj-moved'); void card.offsetWidth; card.classList.add('pj-moved');
                    fetch(moveUrl.replace('__N__', card.dataset.number), {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token },
                        body: JSON.stringify({ status: to.dataset.status, order: [...to.querySelectorAll('.pj-card')].map(c => +c.dataset.id) }),
                    }).then(r => { if (!r.ok) throw r; return r.json(); })
                      .then(() => { if (e.from !== to) Live.toast(card.querySelector('.text-muted span')?.textContent + ' moved to ' + to.closest('.pj-col').querySelector('.pj-col-head span').textContent.trim()); })
                      .catch(() => { Live.toast('Could not move the task. The board has been refreshed.', 'danger'); Live.refresh(location.href); });
                },
            }));
        });
    }

    bind();
    document.addEventListener('live:swapped', bind);

    // Pick up changes made by others without reloading the page.
    setInterval(async function () {
        if (dragging || document.hidden || document.querySelector('.live-busy')) return;
        const a = document.activeElement;
        if (a && a.closest('.main-content') && a.matches('input, textarea')) return;
        try {
            const { res, doc } = await Live.load(location.href);
            if (!res.ok) return;
            const html = doc.querySelector('.pj-board, .pj-table')?.outerHTML || '';
            if (lastHtml !== null && html !== lastHtml) Live.swap(doc, res.url);
            lastHtml = html;
        } catch (e) {}
    }, 20000);
})();
</script>
@endpush
