{{-- The task itself: used on the task page and in the board's side panel. --}}
<div id="task-panel" data-live-region data-task-url="{{ route('projects.tasks.show', [$project, $task->number]) }}" data-task-title="{{ $project->code }}-{{ $task->number }} · {{ $task->title }}">
    <div class="tk-grid">
        <div class="tk-main">
            <x-cb.card>
                <div class="d-flex flex-wrap gap-2 mb-3">
                    @include('modules.projects._pill', ['value' => $task->status])
                    @include('modules.projects._pill', ['kind' => 'priority', 'value' => $task->priority])
                    @if($task->isOverdue())<span class="badge bg-danger-subtle text-danger">Overdue</span>@endif
                    @if(!$client && $task->client_visible)<span class="badge bg-light text-muted"><i class="ri-eye-line"></i> Visible to client</span>@endif
                </div>
                @if($task->description)
                    <div style="white-space: pre-line">{{ $task->description }}</div>
                @else
                    <div class="text-muted">No description.</div>
                @endif

                @if($task->checklist)
                    @php [$cd, $ct] = $task->checklistProgress(); @endphp
                    <h6 class="mt-4 mb-2">Checklist <span class="text-muted small">{{ $cd }}/{{ $ct }}</span></h6>
                    <ul class="list-unstyled mb-0">
                        @foreach($task->checklist as $i => $item)
                            <li class="d-flex align-items-center gap-2 py-1">
                                @if($canWork)
                                    <form method="POST" action="{{ route('projects.tasks.checklist', [$project, $task->number]) }}" data-live>
                                        @csrf <input type="hidden" name="index" value="{{ $i }}">
                                        <button class="btn btn-sm p-0 border-0 bg-transparent fs-5 {{ ($item['done'] ?? false) ? 'text-success' : 'text-muted' }}" aria-label="Tick"><i class="{{ ($item['done'] ?? false) ? 'ri-checkbox-line' : 'ri-checkbox-blank-line' }}"></i></button>
                                    </form>
                                @else
                                    <i class="fs-5 {{ ($item['done'] ?? false) ? 'ri-checkbox-line text-success' : 'ri-checkbox-blank-line text-muted' }}"></i>
                                @endif
                                <span class="{{ ($item['done'] ?? false) ? 'text-decoration-line-through text-muted' : '' }}">{{ $item['text'] }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-cb.card>

            <x-cb.card title="Comments and history" icon="ri-chat-3-line" id="comments">
                @forelse($task->comments as $c)
                    @if($c->is_event)
                        <div class="small text-muted py-1"><i class="ri-history-line"></i> {{ $c->user?->name ?? 'Someone' }} {{ $c->body }} · {{ $c->created_at->diffForHumans() }}</div>
                    @else
                        <div class="py-2 border-bottom {{ $c->is_internal ? 'bg-warning-subtle px-2 rounded' : '' }}">
                            <div class="small"><span class="fw-semibold">{{ $c->user?->name ?? 'Someone' }}</span> <span class="text-muted">· {{ $c->created_at->format('j M Y H:i') }}</span>
                                @if($c->is_internal)<span class="badge bg-warning-subtle text-warning ms-1">Internal</span>@endif
                            </div>
                            @if($c->body)<div style="white-space: pre-line">{{ $c->body }}</div>@endif
                            @if($c->attachment_path)
                                <a href="{{ route('projects.tasks.attachment', [$project, $task->number, $c]) }}" class="small"><i class="ri-attachment-2"></i> {{ $c->attachment_name }}</a>
                            @endif
                        </div>
                    @endif
                @empty
                    <div class="text-muted small">No comments yet.</div>
                @endforelse

                <form method="POST" action="{{ route('projects.tasks.comment', [$project, $task->number]) }}" enctype="multipart/form-data" class="mt-3" data-live>
                    @csrf
                    <textarea name="body" rows="3" class="form-control mb-2" maxlength="5000" placeholder="Write a comment"></textarea>
                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        <input type="file" name="attachment" class="form-control form-control-sm" style="max-width: 18rem">
                        @unless($client)
                            <div class="form-check mb-0"><input type="checkbox" name="internal" value="1" class="form-check-input" id="tk-internal"><label class="form-check-label small" for="tk-internal">Internal (hidden from the client)</label></div>
                        @endunless
                        <button class="btn btn-primary btn-sm ms-auto">Comment</button>
                    </div>
                </form>
            </x-cb.card>
        </div>

        <div class="tk-side">
            @if($canWork)
                <x-cb.card title="Details" icon="ri-settings-3-line">
                    <form method="POST" action="{{ route('projects.tasks.update', [$project, $task->number]) }}" data-live>
                        @csrf @method('PUT')
                        <label class="form-label">Title</label>
                        <input name="title" class="form-control mb-2" value="{{ old('title', $task->title) }}" maxlength="200" required>
                        <div class="row g-2 mb-2">
                            <div class="col-6"><label class="form-label">Status</label>
                                <select name="status" class="form-select">@foreach(\App\Models\Projects\Task::STATUSES as $k => $v)<option value="{{ $k }}" @selected($task->status === $k)>{{ $v }}</option>@endforeach</select></div>
                            <div class="col-6"><label class="form-label">Priority</label>
                                <select name="priority" class="form-select">@foreach(\App\Models\Projects\Project::PRIORITIES as $k => $v)<option value="{{ $k }}" @selected($task->priority === $k)>{{ $v }}</option>@endforeach</select></div>
                        </div>
                        <label class="form-label">Assignee</label>
                        <select name="assignee_id" class="form-select mb-2">
                            <option value="">Unassigned</option>
                            @foreach($people as $p)<option value="{{ $p->id }}" @selected($task->assignee_id === $p->id)>{{ $p->name }}</option>@endforeach
                        </select>
                        <label class="form-label">Milestone</label>
                        <select name="milestone_id" class="form-select mb-2">
                            <option value="">None</option>
                            @foreach($project->milestones as $m)<option value="{{ $m->id }}" @selected($task->milestone_id === $m->id)>{{ $m->title }}</option>@endforeach
                        </select>
                        <div class="row g-2 mb-2">
                            <div class="col-6"><label class="form-label">Starts</label><input type="date" name="starts_on" class="form-control" value="{{ $task->starts_on?->toDateString() }}"></div>
                            <div class="col-6"><label class="form-label">Due</label><input type="date" name="due_on" class="form-control" value="{{ $task->due_on?->toDateString() }}"></div>
                        </div>
                        <label class="form-label">Estimate (hours)</label>
                        <input type="number" step="0.25" min="0" name="estimate_hours" class="form-control mb-2" value="{{ $task->estimate_minutes ? round($task->estimate_minutes / 60, 2) : '' }}">
                        <label class="form-label">Description</label>
                        <textarea name="description" rows="5" class="form-control mb-2" maxlength="20000">{{ old('description', $task->description) }}</textarea>
                        <label class="form-label">Checklist <span class="text-muted small">(one item per line)</span></label>
                        <textarea name="checklist_text" rows="4" class="form-control mb-2" maxlength="10000">{{ collect($task->checklist ?? [])->pluck('text')->implode("\n") }}</textarea>
                        <input type="hidden" name="client_visible" value="0">
                        <div class="form-check mb-3"><input type="checkbox" name="client_visible" value="1" class="form-check-input" id="tk-cv" @checked($task->client_visible)><label class="form-check-label" for="tk-cv">Visible to the client</label></div>
                        <button class="btn btn-primary w-100">Save</button>
                    </form>
                    <div class="small text-muted mt-3">Reported by {{ $task->reporter?->name ?? '—' }} on {{ $task->created_at->format('j M Y') }}@if($task->completed_at) · done {{ $task->completed_at->format('j M Y') }}@endif</div>
                </x-cb.card>

                <x-cb.card title="Time" icon="ri-time-line">
                    @php $spent = $time->sum('minutes'); @endphp
                    <div class="mb-2"><span class="fs-5 fw-semibold">{{ \App\Models\Projects\TimeEntry::format($spent) }}</span>
                        @if($task->estimate_minutes)<span class="text-muted"> of {{ \App\Models\Projects\TimeEntry::format($task->estimate_minutes) }} estimated</span>@endif
                    </div>
                    <form method="POST" action="{{ route('projects.time.store', $project) }}" class="mb-2" data-live>
                        @csrf
                        <input type="hidden" name="task_id" value="{{ $task->id }}">
                        <div class="row g-2 mb-2">
                            <div class="col-5"><input type="date" name="date" class="form-control form-control-sm" value="{{ today()->toDateString() }}" max="{{ today()->toDateString() }}" required></div>
                            <div class="col-3"><input type="number" name="hours" min="0" max="24" step="0.25" class="form-control form-control-sm" placeholder="h"></div>
                            <div class="col-4"><input type="number" name="mins" min="0" max="59" class="form-control form-control-sm" placeholder="min"></div>
                        </div>
                        <input name="note" class="form-control form-control-sm mb-2" maxlength="255" placeholder="What you did (optional)">
                        <input type="hidden" name="billable" value="0">
                        <div class="d-flex align-items-center gap-2">
                            <div class="form-check mb-0"><input type="checkbox" name="billable" value="1" class="form-check-input" id="tk-bill" checked><label class="form-check-label small" for="tk-bill">Billable</label></div>
                            <button class="btn btn-sm btn-soft-primary ms-auto">Log time</button>
                        </div>
                    </form>
                    @foreach($time as $e)
                        <div class="small d-flex justify-content-between border-top py-1"><span>{{ $e->date->format('j M') }} · {{ $e->user->name }}@if($e->note)<span class="text-muted"> · {{ $e->note }}</span>@endif</span><span class="text-nowrap">{{ \App\Models\Projects\TimeEntry::format($e->minutes) }}</span></div>
                    @endforeach
                </x-cb.card>

                @if($canManage)
                    <form method="POST" action="{{ route('projects.tasks.destroy', [$project, $task->number]) }}" data-live data-live-delete data-confirm="Delete this task, its comments and files? Logged time stays on the project.">
                        @csrf @method('DELETE')
                        <button class="btn btn-sm btn-ghost-danger"><i class="ri-delete-bin-line"></i> Delete task</button>
                    </form>
                @endif
            @else
                <x-cb.card title="Details" icon="ri-information-line">
                    <dl class="row mb-0 small">
                        <dt class="col-5 text-muted fw-normal">Assignee</dt><dd class="col-7">{{ $task->assignee?->name ?? 'Unassigned' }}</dd>
                        <dt class="col-5 text-muted fw-normal">Milestone</dt><dd class="col-7">{{ $task->milestone?->title ?? '—' }}</dd>
                        <dt class="col-5 text-muted fw-normal">Due</dt><dd class="col-7">{{ $task->due_on?->format('j M Y') ?? '—' }}</dd>
                        @if($task->completed_at)<dt class="col-5 text-muted fw-normal">Done</dt><dd class="col-7">{{ $task->completed_at->format('j M Y') }}</dd>@endif
                    </dl>
                </x-cb.card>
            @endif
        </div>
    </div>
</div>
