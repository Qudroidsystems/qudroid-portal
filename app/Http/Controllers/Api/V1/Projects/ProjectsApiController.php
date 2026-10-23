<?php

namespace App\Http\Controllers\Api\V1\Projects;

use App\Http\Controllers\Api\V1\ApiController;
use App\Models\Projects\Project;
use App\Models\Projects\Task;
use App\Models\Projects\TaskComment;
use App\Services\Projects\ProjectService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Projects and tasks for the app, with the same rules as the web pages:
 * Manage projects or project manager can do everything, members work on
 * tasks, viewers read and comment, clients see only client-visible tasks
 * and non-internal comments.
 */
class ProjectsApiController extends ApiController
{
    public function __construct(protected ProjectService $projects) {}

    /** ?status=open|all|{status}&q= */
    public function index(Request $request)
    {
        $user = $request->user();
        if (!$user->can('View projects') && !$user->can('Manage projects')) return $this->fail('You do not have access to projects.', 403, 'forbidden');
        $q = Project::visibleTo($user)->with('manager');
        $status = $request->get('status', 'open');
        if ($status === 'open') $q->open();
        elseif ($status !== 'all' && isset(Project::STATUSES[$status])) $q->where('status', $status);
        if ($s = trim((string) $request->get('q'))) $q->where(fn ($w) => $w->where('name', 'like', "%{$s}%")->orWhere('code', 'like', "%{$s}%")->orWhere('client_name', 'like', "%{$s}%"));
        $page = $q->orderByRaw("case status when 'active' then 0 when 'planning' then 1 when 'on_hold' then 2 else 3 end")->orderBy('due_on')->orderBy('name')
            ->paginate(min(50, max(5, (int) $request->get('per_page', 20))));

        $ids = collect($page->items())->pluck('id');
        $counts = Task::whereIn('project_id', $ids)->open()->selectRaw('project_id, count(*) as open, sum(case when due_on < ? then 1 else 0 end) as overdue, sum(case when assignee_id = ? then 1 else 0 end) as mine', [today()->toDateString(), $user->id])
            ->groupBy('project_id')->get()->keyBy('project_id');
        $all = Task::whereIn('project_id', $ids)->selectRaw('project_id, count(*) as total, sum(case when status = ? then 1 else 0 end) as done', ['done'])->groupBy('project_id')->get()->keyBy('project_id');

        return $this->paged($page, fn ($p) => $this->project($p, $user, $counts->get($p->id), $all->get($p->id)));
    }

    public function show(Request $request, Project $project)
    {
        $user = $request->user();
        if (!$this->projects->canView($project, $user)) return $this->fail('Project not found.', 404, 'not_found');
        $project->load('manager', 'milestones', 'members.user');
        $client = $this->projects->isClientView($project, $user);
        $s = $this->projects->summary($project);

        return $this->ok($this->project($project, $user) + [
            'description' => $project->description, 'client_name' => $project->client_name,
            'starts_on' => $project->starts_on?->toDateString(),
            'members' => $project->members->map(fn ($m) => ['id' => $m->user_id, 'name' => $m->user?->name, 'avatar' => $m->user?->avatar_url, 'role' => $m->role])->values(),
            'milestones' => $project->milestones->map(fn ($m) => ['id' => $m->id, 'title' => $m->title, 'due_on' => $m->due_on?->toDateString(), 'done' => (bool) $m->completed_at])->values(),
            'columns' => collect(Task::STATUSES)->map(fn ($label, $key) => ['key' => $key, 'label' => $label, 'count' => (int) ($s['counts'][$key] ?? 0)])->values(),
            'time_logged_minutes' => $client ? null : $s['minutes'],
            'can_work' => $this->projects->canWork($project, $user), 'can_manage' => $this->projects->canManage($project, $user),
        ]);
    }

    /** ?status=&assignee=me|{id}&q= */
    public function tasks(Request $request, Project $project)
    {
        $user = $request->user();
        if (!$this->projects->canView($project, $user)) return $this->fail('Project not found.', 404, 'not_found');
        $q = $project->tasks()->with('assignee');
        if ($this->projects->isClientView($project, $user)) $q->where('client_visible', true);
        $this->filter($q, $request);
        $tasks = $q->withCount(['comments' => fn ($c) => $c->where('is_event', false)])->orderBy('status')->orderBy('position')->limit(500)->get();
        $tasks->each->setRelation('project', $project);

        return $this->ok($tasks->map(fn ($t) => $this->task($t))->values());
    }

    /** ?scope=open|today|overdue|week — tasks assigned to me, across projects. */
    public function mine(Request $request)
    {
        $user = $request->user();
        $q = Task::with('project', 'assignee')->withCount(['comments' => fn ($c) => $c->where('is_event', false)])
            ->where('assignee_id', $user->id)->open()->whereHas('project', fn ($p) => $p->visibleTo($user));
        match ($request->get('scope', 'open')) {
            'today' => $q->whereDate('due_on', '<=', today()),
            'overdue' => $q->whereDate('due_on', '<', today()),
            'week' => $q->whereDate('due_on', '<=', today()->endOfWeek()),
            default => null,
        };
        $tasks = $q->orderByRaw('due_on is null')->orderBy('due_on')->orderByRaw("case priority when 'urgent' then 0 when 'high' then 1 when 'normal' then 2 else 3 end")->limit(300)->get();

        return $this->ok($tasks->map(fn ($t) => $this->task($t))->values(), null, 200, [
            'overdue' => $tasks->filter->isOverdue()->count(),
        ]);
    }

    public function showTask(Request $request, Task $task)
    {
        $user = $request->user();
        if (!$this->projects->canSeeTask($task, $user)) return $this->fail('Task not found.', 404, 'not_found');
        $client = $this->projects->isClientView($task->project, $user);
        $task->load(['assignee', 'reporter', 'milestone', 'comments' => fn ($q) => $q->with('user')->when($client, fn ($c) => $c->where('is_internal', false))->oldest('id')]);

        return $this->ok($this->task($task) + [
            'description' => $task->description,
            'reporter' => $task->reporter ? $this->person($task->reporter) : null,
            'milestone' => $task->milestone ? ['id' => $task->milestone->id, 'title' => $task->milestone->title] : null,
            'starts_on' => $task->starts_on?->toDateString(),
            'estimate_minutes' => $task->estimate_minutes,
            'client_visible' => $task->client_visible,
            'checklist' => array_values($task->checklist ?? []),
            'comments' => $task->comments->map(fn ($c) => $this->commentItem($c, $task))->values(),
            'time_logged_minutes' => $client ? null : (int) $task->timeEntries()->sum('minutes'),
            'can_edit' => $this->projects->canWork($task->project, $user),
        ]);
    }

    public function storeTask(Request $request, Project $project)
    {
        if (!$this->projects->canWork($project, $request->user())) return $this->fail('You can\'t add tasks to this project.', 403, 'forbidden');
        $task = $this->projects->createTask($project, $this->validated($request), $request->user());

        return $this->ok($this->task($task->load('assignee')), "Task {$task->key_label} added.", 201);
    }

    /** Any of {title, description, status, priority, assignee_id, due_on, starts_on, milestone_id, client_visible, checklist: [{text, done}]} */
    public function updateTask(Request $request, Task $task)
    {
        if (!$this->projects->canSeeTask($task, $request->user())) return $this->fail('Task not found.', 404, 'not_found');
        if (!$this->projects->canWork($task->project, $request->user())) return $this->fail('You can\'t change this task.', 403, 'forbidden');
        $data = $this->validated($request, true);
        if ($request->has('checklist')) {
            $data['checklist_text'] = collect($request->input('checklist', []))->pluck('text')->implode("\n");
            $data['checklist_old'] = collect($request->input('checklist', []))->map(fn ($i) => ['text' => trim((string) ($i['text'] ?? '')), 'done' => (bool) ($i['done'] ?? false)])->all();
        }
        $task = $this->projects->updateTask($task, $data, $request->user());

        return $this->ok($this->task($task->fresh(['project', 'assignee'])), 'Task saved.');
    }

    /** Board drag: {status, position} — position counts from 1 inside the column. */
    public function move(Request $request, Task $task)
    {
        if (!$this->projects->canSeeTask($task, $request->user())) return $this->fail('Task not found.', 404, 'not_found');
        if (!$this->projects->canWork($task->project, $request->user())) return $this->fail('You can\'t move this task.', 403, 'forbidden');
        $d = $request->validate(['status' => ['required', Rule::in(array_keys(Task::STATUSES))], 'position' => ['nullable', 'integer', 'min:1']]);
        $order = Task::where('project_id', $task->project_id)->where('status', $d['status'])->whereKeyNot($task->id)->orderBy('position')->pluck('id')->all();
        $at = min(count($order), max(0, (int) ($d['position'] ?? count($order) + 1) - 1));
        array_splice($order, $at, 0, [$task->id]);
        $task = $this->projects->move($task, $d['status'], $order, $request->user());

        return $this->ok($this->task($task->load('project', 'assignee')));
    }

    /** multipart {body, internal?, file?} */
    public function comment(Request $request, Task $task)
    {
        if (!$this->projects->canSeeTask($task, $request->user())) return $this->fail('Task not found.', 404, 'not_found');
        $d = $request->validate([
            'body' => ['nullable', 'string', 'max:5000'], 'internal' => ['nullable', 'boolean'],
            'file' => ['nullable', 'file', 'max:20480', 'mimes:pdf,jpg,jpeg,png,gif,webp,heic,doc,docx,xls,xlsx,ppt,pptx,txt,csv,zip'],
        ]);
        $c = $this->projects->comment($task, $request->user(), (string) ($d['body'] ?? ''), (bool) ($d['internal'] ?? false), $request->file('file'));

        return $this->ok($this->commentItem($c->load('user'), $task), null, 201);
    }

    public function attachment(Request $request, TaskComment $comment)
    {
        $task = $comment->task;
        abort_unless($task && $this->projects->canSeeTask($task, $request->user()), 404);
        abort_if($comment->is_internal && $this->projects->isClientView($task->project, $request->user()), 404);
        abort_unless($comment->attachment_path && Storage::disk('local')->exists($comment->attachment_path), 404);

        return Storage::disk('local')->download($comment->attachment_path, $comment->attachment_name);
    }

    /** {minutes, date, note?, billable?} */
    public function logTime(Request $request, Task $task)
    {
        if (!$this->projects->canSeeTask($task, $request->user())) return $this->fail('Task not found.', 404, 'not_found');
        if (!$this->projects->canWork($task->project, $request->user())) return $this->fail('You can\'t log time here.', 403, 'forbidden');
        $d = $request->validate(['minutes' => ['required', 'integer', 'min:1', 'max:1440'], 'date' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:255'], 'billable' => ['nullable', 'boolean']]);
        $e = $this->projects->logTime($task->project, $request->user(), ['mins' => $d['minutes'], 'hours' => 0, 'date' => $d['date'], 'task_id' => $task->id,
            'note' => $d['note'] ?? null, 'billable' => $d['billable'] ?? true]);

        return $this->ok(['id' => $e->id, 'minutes' => $e->minutes, 'date' => $e->date?->toDateString(),
            'task_minutes' => (int) $task->timeEntries()->sum('minutes')], 'Time logged.', 201);
    }

    protected function validated(Request $request, bool $partial = false): array
    {
        $req = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'title' => [$req, 'string', 'max:200'], 'description' => ['nullable', 'string', 'max:20000'],
            'status' => ['sometimes', Rule::in(array_keys(Task::STATUSES))], 'priority' => ['sometimes', Rule::in(array_keys(Project::PRIORITIES))],
            'assignee_id' => ['nullable', 'integer'], 'milestone_id' => ['nullable', 'integer'],
            'starts_on' => ['nullable', 'date'], 'due_on' => ['nullable', 'date'], 'client_visible' => ['sometimes', 'boolean'],
            'checklist' => ['sometimes', 'array', 'max:50'], 'checklist.*.text' => ['required_with:checklist', 'string', 'max:200'], 'checklist.*.done' => ['sometimes', 'boolean'],
        ]);
    }

    protected function filter($q, Request $request): void
    {
        if (($st = $request->get('status')) && isset(Task::STATUSES[$st])) $q->where('status', $st);
        elseif ($st === 'open') $q->open();
        $a = $request->get('assignee');
        if ($a === 'me') $q->where('assignee_id', $request->user()->id);
        elseif ($a === 'none') $q->whereNull('assignee_id');
        elseif (ctype_digit((string) $a)) $q->where('assignee_id', (int) $a);
        if ($s = trim((string) $request->get('q'))) $q->where(fn ($w) => $w->where('title', 'like', "%{$s}%")->orWhere('number', ltrim(strrchr('-' . $s, '-'), '-')));
    }

    protected function project(Project $p, $user, $open = null, $all = null): array
    {
        return [
            'id' => $p->id, 'code' => $p->code, 'name' => $p->name, 'status' => $p->status, 'status_label' => $p->status_label,
            'priority' => $p->priority, 'color' => $p->color, 'due_on' => $p->due_on?->toDateString(), 'overdue' => $p->isOverdue(),
            'progress' => $all && $all->total ? (int) round($all->done * 100 / $all->total) : ($all ? 0 : $this->projects->summary($p)['percent']),
            'my_role' => $user->can('Manage projects') && !$this->projects->role($p, $user) ? 'admin' : $this->projects->role($p, $user),
            'manager' => $p->manager ? ['id' => $p->manager->id, 'name' => $p->manager->name] : null,
            'counts' => $open !== null || $all !== null
                ? ['open' => (int) ($open->open ?? 0), 'overdue' => (int) ($open->overdue ?? 0), 'mine' => (int) ($open->mine ?? 0)]
                : null,
            'web_url' => route('projects.show', $p),
        ];
    }

    protected function task(Task $t): array
    {
        [$done, $total] = $t->checklistProgress();

        return [
            'id' => $t->id, 'key' => $t->key_label, 'number' => $t->number, 'title' => $t->title, 'status' => $t->status, 'status_label' => $t->status_label,
            'priority' => $t->priority, 'assignee' => $t->assignee ? $this->person($t->assignee) : null,
            'due_on' => $t->due_on?->toDateString(), 'overdue' => $t->isOverdue(),
            'comments_count' => $t->comments_count ?? $t->comments()->where('is_event', false)->count(),
            'checklist_done' => $done, 'checklist_total' => $total, 'position' => $t->position,
            'project' => ['id' => $t->project->id, 'code' => $t->project->code, 'name' => $t->project->name, 'color' => $t->project->color],
            'web_url' => route('projects.tasks.show', [$t->project, $t->number]),
        ];
    }

    protected function commentItem(TaskComment $c, Task $t): array
    {
        return [
            'id' => $c->id, 'user' => $c->user ? $this->person($c->user) : null, 'body' => $c->body, 'is_event' => $c->is_event, 'is_internal' => $c->is_internal,
            'attachment' => $c->attachment_path ? ['name' => $c->attachment_name, 'url' => route('api.v1.tasks.comments.file', $c)] : null,
            'at' => $c->created_at?->toIso8601String(),
        ];
    }

    protected function person($u): array
    {
        return ['id' => $u->id, 'name' => $u->name, 'avatar' => $u->avatar_url];
    }
}
