<?php

namespace App\Http\Controllers\Projects;

use App\Http\Controllers\Controller;
use App\Models\Projects\Project;
use App\Models\Projects\ProjectMember;
use App\Models\Projects\Task;
use App\Models\Projects\TaskComment;
use App\Models\Projects\TimeEntry;
use App\Services\Projects\ProjectService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class TaskController extends Controller
{
    public function __construct(protected ProjectService $projects) {}

    /** The board (default) or a list of the project's tasks. */
    public function board(Request $request, Project $project)
    {
        $user = $request->user();
        abort_unless($this->projects->canView($project, $user), 403);
        $client = $this->projects->isClientView($project, $user);

        $tasks = Task::with('assignee', 'milestone')->withCount(['comments' => fn ($q) => $q->where('is_event', false)->when($client, fn ($c) => $c->where('is_internal', false))])
            ->where('project_id', $project->id)
            ->when($client, fn ($q) => $q->where('client_visible', true))
            ->when($request->query('assignee') === 'me', fn ($q) => $q->where('assignee_id', $user->id))
            ->when(ctype_digit((string) $request->query('assignee')), fn ($q) => $q->where('assignee_id', (int) $request->query('assignee')))
            ->when($request->integer('milestone'), fn ($q, $m) => $q->where('milestone_id', $m))
            ->when(isset(Project::PRIORITIES[$request->query('priority')]), fn ($q) => $q->where('priority', $request->query('priority')))
            ->when($request->query('due') === 'overdue', fn ($q) => $q->where('status', '!=', 'done')->whereDate('due_on', '<', today()))
            ->when($request->query('due') === 'week', fn ($q) => $q->where('status', '!=', 'done')->whereDate('due_on', '>=', today())->whereDate('due_on', '<=', today()->addDays(7)))
            ->when($request->query('due') === 'none', fn ($q) => $q->whereNull('due_on'))
            ->when(trim((string) $request->query('q')), function ($q, $s) use ($project) {
                $n = preg_replace('/^' . preg_quote($project->code, '/') . '-/i', '', $s);
                $q->where(fn ($w) => $w->where('title', 'like', "%{$s}%")->when(ctype_digit($n), fn ($x) => $x->orWhere('number', (int) $n)));
            })
            ->orderBy('position')->orderBy('id')->get();

        return view('modules.projects.board', [
            'project'  => $project->load('milestones'),
            'columns'  => collect(Task::STATUSES)->map(fn ($label, $key) => $tasks->where('status', $key)->values()),
            'tasks'    => $tasks,
            'list'     => $request->query('view') === 'list',
            'client'   => $client,
            'canWork'  => $this->projects->canWork($project, $user),
            'people'   => $this->assignable($project),
            'filtered' => $request->hasAny(['q', 'assignee', 'milestone', 'priority', 'due']) && collect($request->only(['q', 'assignee', 'milestone', 'priority', 'due']))->filter()->isNotEmpty(),
        ]);
    }

    public function store(Request $request, Project $project)
    {
        abort_unless($this->projects->canWork($project, $request->user()), 403);
        $task = $this->projects->createTask($project, $this->validated($request), $request->user());

        return $request->boolean('stay')
            ? back()->with('success', "Task {$task->key_label} added.")
            : redirect()->route('projects.tasks.show', [$project, $task->number])->with('success', "Task {$task->key_label} added.");
    }

    public function show(Request $request, Project $project, int $number)
    {
        $user = $request->user();
        $task = $this->find($project, $number);
        abort_unless($this->projects->canSeeTask($task, $user), 404);
        $client = $this->projects->isClientView($project, $user);
        $task->load(['assignee', 'reporter', 'milestone', 'comments' => fn ($q) => $q->with('user')->when($client, fn ($c) => $c->where('is_internal', false))]);

        return view('modules.projects.task', [
            'project' => $project->load('milestones'),
            'task'    => $task,
            'client'  => $client,
            'canWork' => $this->projects->canWork($project, $user),
            'canManage' => $this->projects->canManage($project, $user),
            'people'  => $this->assignable($project),
            'time'    => $client ? collect() : $task->timeEntries()->with('user')->latest('date')->get(),
        ]);
    }

    public function update(Request $request, Project $project, int $number)
    {
        abort_unless($this->projects->canWork($project, $request->user()), 403);
        $task = $this->find($project, $number);
        $data = $this->validated($request, true);
        $data['checklist_old'] = $task->checklist;
        $this->projects->updateTask($task, $data, $request->user());

        return back()->with('success', 'Task saved.');
    }

    /** Drag and drop on the board. */
    public function move(Request $request, Project $project, int $number)
    {
        abort_unless($this->projects->canWork($project, $request->user()), 403);
        $data = $request->validate(['status' => ['required', Rule::in(array_keys(Task::STATUSES))], 'order' => ['array'], 'order.*' => ['integer']]);
        $task = $this->projects->move($this->find($project, $number), $data['status'], $data['order'] ?? [], $request->user());

        return response()->json(['ok' => true, 'status' => $task->status]);
    }

    public function checklist(Request $request, Project $project, int $number)
    {
        abort_unless($this->projects->canWork($project, $request->user()), 403);
        $this->projects->toggleChecklist($this->find($project, $number), $request->integer('index'));

        return back();
    }

    public function destroy(Request $request, Project $project, int $number)
    {
        abort_unless($this->projects->canManage($project, $request->user()), 403);
        $task = $this->find($project, $number);
        $this->projects->deleteTask($task);

        return redirect()->route('projects.board', $project)->with('success', "Task {$task->key_label} deleted.");
    }

    public function comment(Request $request, Project $project, int $number)
    {
        $task = $this->find($project, $number);
        abort_unless($this->projects->canSeeTask($task, $request->user()), 404);
        $data = $request->validate([
            'body'       => ['nullable', 'string', 'max:5000'],
            'internal'   => ['nullable', 'boolean'],
            'attachment' => ['nullable', 'file', 'max:20480', 'mimes:pdf,jpg,jpeg,png,gif,webp,heic,doc,docx,xls,xlsx,ppt,pptx,txt,csv,zip'],
        ]);
        $this->projects->comment($task, $request->user(), (string) ($data['body'] ?? ''), (bool) ($data['internal'] ?? false), $request->file('attachment'));

        return redirect(route('projects.tasks.show', [$project, $number]) . '#comments');
    }

    public function attachment(Request $request, Project $project, int $number, TaskComment $comment)
    {
        $task = $this->find($project, $number);
        abort_unless($comment->task_id === $task->id && $this->projects->canSeeTask($task, $request->user()), 404);
        abort_if($comment->is_internal && $this->projects->isClientView($project, $request->user()), 404);
        abort_unless($comment->attachment_path && Storage::disk('local')->exists($comment->attachment_path), 404);

        return Storage::disk('local')->download($comment->attachment_path, $comment->attachment_name);
    }

    // ── Time ────────────────────────────────────────────────────────────

    public function timesheet(Request $request, Project $project)
    {
        $user = $request->user();
        abort_unless($this->projects->canWork($project, $user) || ($this->projects->canView($project, $user) && !$this->projects->isClientView($project, $user)), 403);
        $from = $request->filled('from') ? \Illuminate\Support\Carbon::parse($request->query('from')) : today()->startOfMonth();
        $to = $request->filled('to') ? \Illuminate\Support\Carbon::parse($request->query('to')) : today();

        $entries = TimeEntry::with('user', 'task')->where('project_id', $project->id)
            ->whereDate('date', '>=', $from)->whereDate('date', '<=', $to)
            ->when($request->integer('user'), fn ($q, $u) => $q->where('user_id', $u))
            ->latest('date')->latest('id')->get();

        return view('modules.projects.time', [
            'project' => $project, 'entries' => $entries, 'from' => $from, 'to' => $to,
            'byPerson' => $entries->groupBy('user_id')->map(fn ($g) => ['user' => $g->first()->user, 'minutes' => $g->sum('minutes'), 'billable' => $g->where('billable', true)->sum('minutes')]),
            'canWork' => $this->projects->canWork($project, $user),
            'canManage' => $this->projects->canManage($project, $user),
            'tasks' => Task::where('project_id', $project->id)->open()->orderBy('number')->get(['id', 'number', 'title']),
        ]);
    }

    public function logTime(Request $request, Project $project)
    {
        abort_unless($this->projects->canWork($project, $request->user()), 403);
        $this->projects->logTime($project, $request->user(), $request->validate([
            'date'     => ['required', 'date'],
            'hours'    => ['nullable', 'numeric', 'min:0', 'max:24'],
            'mins'     => ['nullable', 'integer', 'min:0', 'max:59'],
            'task_id'  => ['nullable', 'integer'],
            'note'     => ['nullable', 'string', 'max:255'],
            'billable' => ['nullable', 'boolean'],
        ]));

        return back()->with('success', 'Time logged.');
    }

    public function deleteTime(Request $request, Project $project, TimeEntry $entry)
    {
        abort_unless($entry->project_id === $project->id && ($entry->user_id === $request->user()->id || $this->projects->canManage($project, $request->user())), 403);
        $entry->delete();

        return back()->with('success', 'Time entry removed.');
    }

    // ── Helpers ─────────────────────────────────────────────────────────

    protected function find(Project $project, int $number): Task
    {
        return Task::where('project_id', $project->id)->where('number', $number)->firstOrFail()->setRelation('project', $project);
    }

    protected function assignable(Project $project)
    {
        return ProjectMember::with('user:id,name')->where('project_id', $project->id)->whereIn('role', ['manager', 'member'])->get()->pluck('user')->sortBy('name')->values();
    }

    protected function validated(Request $request, bool $updating = false): array
    {
        return $request->validate([
            'title'          => [...($updating ? ['sometimes'] : []), 'required', 'string', 'max:200'],
            'description'    => ['nullable', 'string', 'max:20000'],
            'status'         => ['nullable', Rule::in(array_keys(Task::STATUSES))],
            'priority'       => ['nullable', Rule::in(array_keys(Project::PRIORITIES))],
            'assignee_id'    => ['nullable', 'integer'],
            'milestone_id'   => ['nullable', 'integer'],
            'starts_on'      => ['nullable', 'date'],
            'due_on'         => ['nullable', 'date'],
            'estimate_hours' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'client_visible' => ['nullable', 'boolean'],
            'checklist_text' => ['nullable', 'string', 'max:10000'],
        ]);
    }
}
