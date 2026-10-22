<?php

namespace App\Http\Controllers\Projects;

use App\Http\Controllers\Controller;
use App\Models\Projects\Milestone;
use App\Models\Projects\Project;
use App\Models\Projects\ProjectMember;
use App\Models\Projects\Task;
use App\Models\Projects\TaskComment;
use App\Models\Projects\TimeEntry;
use App\Models\User;
use App\Services\Projects\ProjectService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProjectController extends Controller
{
    public function __construct(protected ProjectService $projects) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $status = $request->query('status', 'open');
        $list = Project::visibleTo($user)->with('manager')
            ->withCount(['tasks', 'tasks as done_count' => fn ($q) => $q->where('status', 'done')])
            ->when($status === 'open', fn ($q) => $q->open())
            ->when(isset(Project::STATUSES[$status]), fn ($q) => $q->where('status', $status))
            ->when(trim((string) $request->query('q')), fn ($q, $s) => $q->where(fn ($w) => $w->where('name', 'like', "%{$s}%")->orWhere('code', 'like', "%{$s}%")->orWhere('client_name', 'like', "%{$s}%")))
            ->orderByRaw("case status when 'active' then 0 when 'planning' then 1 when 'on_hold' then 2 else 3 end")
            ->orderBy('due_on')->paginate(24)->withQueryString();

        return view('modules.projects.index', [
            'projects' => $list,
            'status'   => $status,
            'myOpen'   => Task::open()->where('assignee_id', $user->id)->count(),
            'myLate'   => Task::open()->where('assignee_id', $user->id)->whereDate('due_on', '<', today())->count(),
            'myWeek'   => (int) TimeEntry::where('user_id', $user->id)->where('date', '>=', today()->startOfWeek()->toDateString())->sum('minutes'),
        ]);
    }

    public function create()
    {
        return view('modules.projects.form', ['project' => new Project(['color' => '#0ea5e9', 'priority' => 'normal', 'status' => 'planning']), ...$this->pickers()]);
    }

    public function store(Request $request)
    {
        $project = $this->projects->create($this->validated($request, true), $request->user());

        return redirect()->route('projects.show', $project)->with('success', 'Project created. Add people and tasks next.');
    }

    public function show(Request $request, Project $project)
    {
        $user = $request->user();
        abort_unless($this->projects->canView($project, $user), 403);
        $client = $this->projects->isClientView($project, $user);

        $project->load('manager', 'client', 'milestones', 'members.user');
        $milestoneCounts = Task::where('project_id', $project->id)->whereNotNull('milestone_id')
            ->when($client, fn ($q) => $q->where('client_visible', true))
            ->selectRaw('milestone_id, count(*) as n, sum(case when status = ? then 1 else 0 end) as done', ['done'])
            ->groupBy('milestone_id')->get()->keyBy('milestone_id');

        $activity = TaskComment::with('user', 'task')
            ->whereHas('task', fn ($q) => $q->where('project_id', $project->id)->when($client, fn ($t) => $t->where('client_visible', true)))
            ->when($client, fn ($q) => $q->where('is_internal', false))
            ->latest()->limit(12)->get();

        return view('modules.projects.show', [
            'project'   => $project,
            'summary'   => $this->projects->summary($project),
            'milestoneCounts' => $milestoneCounts,
            'activity'  => $activity,
            'client'    => $client,
            'canManage' => $this->projects->canManage($project, $user),
            'canWork'   => $this->projects->canWork($project, $user),
            'addable'   => User::whereNotIn('id', $project->members->pluck('user_id'))->whereIn('user_type', [User::TYPE_STAFF, User::TYPE_CLIENT])->orderBy('name')->get(['id', 'name', 'user_type']),
            'upcoming'  => $client ? collect() : Task::with('assignee')->where('project_id', $project->id)->open()->whereNotNull('due_on')->orderBy('due_on')->limit(6)->get(),
        ]);
    }

    public function edit(Request $request, Project $project)
    {
        abort_unless($this->projects->canManage($project, $request->user()), 403);

        return view('modules.projects.form', ['project' => $project, ...$this->pickers()]);
    }

    public function update(Request $request, Project $project)
    {
        abort_unless($this->projects->canManage($project, $request->user()), 403);
        $this->projects->update($project, $this->validated($request, false));

        return redirect()->route('projects.show', $project)->with('success', 'Project updated.');
    }

    public function status(Request $request, Project $project)
    {
        abort_unless($this->projects->canManage($project, $request->user()), 403);
        $this->projects->setStatus($project, (string) $request->input('status'));

        return back()->with('success', 'Status changed to ' . strtolower($project->status_label) . '.');
    }

    public function destroy(Request $request, Project $project)
    {
        abort_unless($request->user()->can('Manage projects'), 403);
        abort_if($project->tasks()->exists() || $project->timeEntries()->exists(), 422, 'Only an empty project can be deleted. Cancel it instead.');
        $project->delete();

        return redirect()->route('projects.index')->with('success', 'Project deleted.');
    }

    // ── Members ─────────────────────────────────────────────────────────

    public function addMember(Request $request, Project $project)
    {
        abort_unless($this->projects->canManage($project, $request->user()), 403);
        $data = $request->validate(['user_id' => ['required', 'exists:users,id'], 'role' => ['required', Rule::in(array_keys(ProjectMember::ROLES))]]);
        $this->projects->addMember($project, User::findOrFail($data['user_id']), $data['role'], $request->user());

        return back()->with('success', 'Member added.');
    }

    public function updateMember(Request $request, Project $project, ProjectMember $member)
    {
        abort_unless($this->projects->canManage($project, $request->user()) && $member->project_id === $project->id, 403);
        $role = $request->validate(['role' => ['required', Rule::in(array_keys(ProjectMember::ROLES))]])['role'];
        if ($member->role === 'manager' && $role !== 'manager' && $project->members()->where('role', 'manager')->count() <= 1) {
            return back()->withErrors(['role' => 'A project needs at least one manager.']);
        }
        if (($role === 'client') !== $member->user->isClient()) {
            return back()->withErrors(['role' => 'Client accounts take the Client role, and only they do.']);
        }
        $this->projects->setMember($project, $member->user_id, $role);

        return back()->with('success', 'Role changed.');
    }

    public function removeMember(Request $request, Project $project, ProjectMember $member)
    {
        abort_unless($this->projects->canManage($project, $request->user()), 403);
        $this->projects->removeMember($project, $member);

        return back()->with('success', 'Removed from the project.');
    }

    // ── Milestones ──────────────────────────────────────────────────────

    public function addMilestone(Request $request, Project $project)
    {
        abort_unless($this->projects->canManage($project, $request->user()), 403);
        $this->projects->addMilestone($project, $request->validate([
            'title' => ['required', 'string', 'max:150'], 'due_on' => ['nullable', 'date'], 'description' => ['nullable', 'string', 'max:1000'],
        ]));

        return back()->with('success', 'Milestone added.');
    }

    public function toggleMilestone(Request $request, Project $project, Milestone $milestone)
    {
        abort_unless($this->projects->canManage($project, $request->user()) && $milestone->project_id === $project->id, 403);
        $this->projects->toggleMilestone($milestone);

        return back();
    }

    public function removeMilestone(Request $request, Project $project, Milestone $milestone)
    {
        abort_unless($this->projects->canManage($project, $request->user()) && $milestone->project_id === $project->id, 403);
        $milestone->delete();

        return back()->with('success', 'Milestone removed. Its tasks stay on the board.');
    }

    // ── Helpers ─────────────────────────────────────────────────────────

    protected function validated(Request $request, bool $creating): array
    {
        return $request->validate([
            'code'        => $creating ? ['required', 'string', 'max:10'] : ['prohibited'],
            'name'        => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:5000'],
            'client_name' => ['nullable', 'string', 'max:150'],
            'client_id'   => ['nullable', Rule::exists('users', 'id')->where('user_type', User::TYPE_CLIENT)],
            'manager_id'  => ['nullable', Rule::exists('users', 'id')->where('user_type', User::TYPE_STAFF)],
            'priority'    => ['required', Rule::in(array_keys(Project::PRIORITIES))],
            'status'      => $creating ? ['nullable', Rule::in(array_keys(Project::STATUSES))] : ['prohibited'],
            'starts_on'   => ['nullable', 'date'],
            'due_on'      => ['nullable', 'date', 'after_or_equal:starts_on'],
            'budget'      => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'color'       => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);
    }

    protected function pickers(): array
    {
        return [
            'staff'   => User::where('user_type', User::TYPE_STAFF)->orderBy('name')->get(['id', 'name']),
            'clients' => User::where('user_type', User::TYPE_CLIENT)->orderBy('name')->get(['id', 'name']),
        ];
    }
}
