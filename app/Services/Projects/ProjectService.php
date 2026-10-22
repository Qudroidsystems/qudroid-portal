<?php

namespace App\Services\Projects;

use App\Models\Projects\Milestone;
use App\Models\Projects\Project;
use App\Models\Projects\ProjectMember;
use App\Models\Projects\Task;
use App\Models\Projects\TaskComment;
use App\Models\Projects\TimeEntry;
use App\Models\User;
use App\Services\Messaging\PortalNotifier;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Projects, their members and their tasks.
 *
 * Who may do what on a project:
 *  - Manage projects (permission) or project manager: everything.
 *  - member: add and change tasks, move them on the board, log time.
 *  - viewer: read and comment.
 *  - client: sees the project, milestones and the tasks marked
 *    "visible to client", and comments that are not internal.
 */
class ProjectService
{
    // ── Access ──────────────────────────────────────────────────────────

    /** @var array<string, ?string> */
    protected array $roles = [];

    public function role(Project $project, User $user): ?string
    {
        $key = $project->id . ':' . $user->id;
        if (!array_key_exists($key, $this->roles)) {
            $this->roles[$key] = ProjectMember::where('project_id', $project->id)->where('user_id', $user->id)->value('role');
        }

        return $this->roles[$key];
    }

    public function canView(Project $project, User $user): bool
    {
        return $user->can('Manage projects') || $this->role($project, $user) !== null;
    }

    public function canManage(Project $project, User $user): bool
    {
        return $user->can('Manage projects') || $this->role($project, $user) === 'manager';
    }

    public function canWork(Project $project, User $user): bool
    {
        return $this->canManage($project, $user) || $this->role($project, $user) === 'member';
    }

    /** Clients only see what has been shared with them. */
    public function isClientView(Project $project, User $user): bool
    {
        return !$user->can('Manage projects') && $this->role($project, $user) === 'client';
    }

    public function canSeeTask(Task $task, User $user): bool
    {
        $project = $task->project;

        return $this->canView($project, $user) && (!$this->isClientView($project, $user) || $task->client_visible);
    }

    // ── Projects ────────────────────────────────────────────────────────

    public function create(array $data, User $by): Project
    {
        $code = strtoupper(trim($data['code']));
        $this->check(!preg_match('/^[A-Z][A-Z0-9]{1,9}$/', $code), 'code', 'Use 2 to 10 letters or digits, starting with a letter.');
        $this->check(Project::where('code', $code)->exists(), 'code', 'Another project already uses this code.');

        return DB::transaction(function () use ($data, $code, $by) {
            $project = Project::create([
                'code' => $code, 'created_by' => $by->id, 'status' => $data['status'] ?? 'planning',
                'manager_id' => ($data['manager_id'] ?? null) ?: $by->id,
            ] + $this->fields($data));
            $this->setMember($project, $project->manager_id, 'manager');
            if ($project->client_id) {
                $this->setMember($project, $project->client_id, 'client');
            }

            return $project;
        });
    }

    public function update(Project $project, array $data): Project
    {
        $project->update($this->fields($data));
        if ($project->manager_id) $this->setMember($project, $project->manager_id, 'manager');
        if ($project->client_id && !$project->members()->where('user_id', $project->client_id)->exists()) {
            $this->setMember($project, $project->client_id, 'client');
        }

        return $project;
    }

    public function setStatus(Project $project, string $status): Project
    {
        $this->check(!isset(Project::STATUSES[$status]), 'status', 'Unknown status.');
        $project->update(['status' => $status, 'completed_at' => $status === 'completed' ? ($project->completed_at ?? now()) : null]);

        return $project;
    }

    public function setMember(Project $project, int $userId, string $role): ProjectMember
    {
        $this->check(!isset(ProjectMember::ROLES[$role]), 'role', 'Unknown role.');

        unset($this->roles[$project->id . ':' . $userId]);

        return ProjectMember::updateOrCreate(['project_id' => $project->id, 'user_id' => $userId], ['role' => $role]);
    }

    public function addMember(Project $project, User $user, string $role, User $by): ProjectMember
    {
        $this->check($role === 'client' && !$user->isClient(), 'role', 'Only client accounts can be added as clients.');
        $this->check($role !== 'client' && $user->isClient(), 'role', 'Add a client account with the Client role.');
        $member = $this->setMember($project, $user->id, $role);
        if ($user->id !== $by->id) {
            PortalNotifier::toUsers([$user->id], 'Added to project ' . $project->name, $by->name . ' added you as ' . strtolower(ProjectMember::ROLES[$role]) . '.',
                route('projects.show', $project), 'project');
        }

        return $member;
    }

    public function removeMember(Project $project, ProjectMember $member): void
    {
        $this->check($member->project_id !== $project->id, 'member', 'Not a member of this project.');
        $this->check($member->role === 'manager' && $project->members()->where('role', 'manager')->count() <= 1, 'member', 'A project needs at least one manager.');
        Task::where('project_id', $project->id)->where('assignee_id', $member->user_id)->where('status', '!=', 'done')->update(['assignee_id' => null]);
        unset($this->roles[$project->id . ':' . $member->user_id]);
        $member->delete();
        if ($project->manager_id === $member->user_id) {
            $project->update(['manager_id' => $project->members()->where('role', 'manager')->value('user_id')]);
        }
    }

    // ── Milestones ──────────────────────────────────────────────────────

    public function addMilestone(Project $project, array $data): Milestone
    {
        return $project->milestones()->create([
            'title' => trim($data['title']), 'description' => $data['description'] ?? null, 'due_on' => $data['due_on'] ?? null,
            'position' => (int) $project->milestones()->max('position') + 1,
        ]);
    }

    public function toggleMilestone(Milestone $milestone): Milestone
    {
        $milestone->update(['completed_at' => $milestone->completed_at ? null : now()]);

        return $milestone;
    }

    // ── Tasks ───────────────────────────────────────────────────────────

    public function createTask(Project $project, array $data, User $by): Task
    {
        $this->checkAssignee($project, $data['assignee_id'] ?? null);
        $this->checkMilestone($project, $data['milestone_id'] ?? null);

        $task = DB::transaction(function () use ($project, $data, $by) {
            $locked = Project::whereKey($project->id)->lockForUpdate()->first();
            $number = $locked->task_seq + 1;
            $locked->update(['task_seq' => $number]);
            $status = $data['status'] ?? 'todo';

            return Task::create([
                'project_id' => $project->id, 'number' => $number, 'reporter_id' => $by->id, 'status' => $status,
                'position' => (int) Task::where('project_id', $project->id)->where('status', $status)->max('position') + 1,
                'completed_at' => $status === 'done' ? now() : null,
            ] + $this->taskFields($data));
        });

        $task->setRelation('project', $project);
        if ($task->assignee_id && $task->assignee_id !== $by->id) {
            $this->notify([$task->assignee_id], $task, 'Task assigned: ' . $task->key_label, $by->name . ' assigned you "' . $task->title . '".');
        }

        return $task;
    }

    public function updateTask(Task $task, array $data, User $by): Task
    {
        $project = $task->project;
        $this->checkAssignee($project, $data['assignee_id'] ?? null);
        $this->checkMilestone($project, $data['milestone_id'] ?? null);

        $before = $task->assignee_id;
        $task->fill($this->taskFields($data));
        if (isset($data['status']) && $data['status'] !== $task->getOriginal('status')) {
            $this->applyStatus($task, $data['status'], $by);
        }
        $task->save();

        if ($task->assignee_id !== $before) {
            $this->event($task, $by, $task->assignee_id ? 'assigned this to ' . $task->assignee->name : 'removed the assignee');
            if ($task->assignee_id && $task->assignee_id !== $by->id) {
                $this->notify([$task->assignee_id], $task, 'Task assigned: ' . $task->key_label, $by->name . ' assigned you "' . $task->title . '".');
            }
        }

        return $task;
    }

    /**
     * Move a task on the board: into a column, and into a place in it.
     *
     * @param  int[]  $order  ids of the column's tasks in their new order (including this one)
     */
    public function move(Task $task, string $status, array $order, User $by): Task
    {
        $this->check(!isset(Task::STATUSES[$status]), 'status', 'Unknown column.');

        DB::transaction(function () use ($task, $status, $order, $by) {
            if ($status !== $task->status) {
                $this->applyStatus($task, $status, $by);
                $task->save();
            }
            $ids = Task::where('project_id', $task->project_id)->whereIn('id', $order)->pluck('id')->all();
            foreach (array_values(array_filter($order, fn ($id) => in_array((int) $id, $ids, true))) as $i => $id) {
                Task::whereKey($id)->update(['position' => $i + 1]);
            }
        });

        return $task->fresh();
    }

    public function toggleChecklist(Task $task, int $index): Task
    {
        $items = $task->checklist ?? [];
        $this->check(!isset($items[$index]), 'checklist', 'No such item.');
        $items[$index]['done'] = !($items[$index]['done'] ?? false);
        $task->update(['checklist' => $items]);

        return $task;
    }

    public function comment(Task $task, User $by, string $body, bool $internal, ?UploadedFile $file = null): TaskComment
    {
        $body = trim($body);
        $this->check($body === '' && !$file, 'body', 'Write a comment or attach a file.');
        $internal = $internal && !$this->isClientView($task->project, $by);

        $comment = $task->comments()->create([
            'user_id' => $by->id, 'body' => $body, 'is_internal' => $internal,
            'attachment_path' => $file?->store('projects/' . $task->project_id, 'local'),
            'attachment_name' => $file ? mb_substr($file->getClientOriginalName(), 0, 255) : null,
        ]);

        // Tell the assignee, the reporter and anyone who has commented, except the author.
        $people = collect([$task->assignee_id, $task->reporter_id])
            ->merge($task->comments()->where('is_event', false)->pluck('user_id'))
            ->filter()->unique()->reject(fn ($id) => $id === $by->id);
        if ($internal) {
            $clients = ProjectMember::where('project_id', $task->project_id)->where('role', 'client')->pluck('user_id');
            $people = $people->diff($clients);
        }
        $this->notify($people->values()->all(), $task, 'Comment on ' . $task->key_label, $by->name . ': ' . \Illuminate\Support\Str::limit($body ?: 'attached ' . $comment->attachment_name, 120));

        return $comment;
    }

    public function deleteTask(Task $task): void
    {
        foreach ($task->comments()->whereNotNull('attachment_path')->pluck('attachment_path') as $path) {
            \Illuminate\Support\Facades\Storage::disk('local')->delete($path);
        }
        $task->delete();
    }

    // ── Time ────────────────────────────────────────────────────────────

    public function logTime(Project $project, User $by, array $data): TimeEntry
    {
        $minutes = $this->minutes($data['hours'] ?? 0, $data['mins'] ?? 0);
        $this->check($minutes <= 0, 'hours', 'Enter the time spent.');
        $this->check($minutes > 24 * 60, 'hours', 'That is more than a day.');
        $date = Carbon::parse($data['date'] ?? today());
        $this->check($date->gt(today()), 'date', 'Time cannot be logged for a future date.');
        $taskId = $data['task_id'] ?? null;
        $this->check($taskId && !Task::where('project_id', $project->id)->whereKey($taskId)->exists(), 'task_id', 'That task is not in this project.');

        return TimeEntry::create([
            'project_id' => $project->id, 'task_id' => $taskId ?: null, 'user_id' => $by->id, 'date' => $date->toDateString(),
            'minutes' => $minutes, 'note' => $data['note'] ?? null, 'billable' => (bool) ($data['billable'] ?? true),
        ]);
    }

    // ── Summary ─────────────────────────────────────────────────────────

    /** Counts per status, % done, time logged and overdue tasks. */
    public function summary(Project $project): array
    {
        $counts = Task::where('project_id', $project->id)->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');
        $total = (int) $counts->sum();

        return [
            'counts'   => $counts,
            'total'    => $total,
            'percent'  => $total ? (int) round(($counts['done'] ?? 0) * 100 / $total) : 0,
            'overdue'  => Task::where('project_id', $project->id)->open()->whereDate('due_on', '<', today())->count(),
            'minutes'  => (int) TimeEntry::where('project_id', $project->id)->sum('minutes'),
            'billable' => (int) TimeEntry::where('project_id', $project->id)->where('billable', true)->sum('minutes'),
        ];
    }

    // ── Helpers ─────────────────────────────────────────────────────────

    protected function fields(array $data): array
    {
        return array_filter([
            'name'        => isset($data['name']) ? trim($data['name']) : null,
            'description' => $data['description'] ?? null,
            'client_name' => $data['client_name'] ?? null,
            'client_id'   => $data['client_id'] ?? null,
            'company_id'  => $data['company_id'] ?? null,
            'priority'    => $data['priority'] ?? 'normal',
            'starts_on'   => $data['starts_on'] ?? null,
            'due_on'      => $data['due_on'] ?? null,
            'budget'      => $data['budget'] ?? null,
            'currency'    => strtoupper($data['currency'] ?? config('qudroid.academy.currency', 'NGN')),
            'color'       => $data['color'] ?? null,
            'manager_id'  => $data['manager_id'] ?? null,
        ], fn ($v, $k) => $v !== null || in_array($k, ['description', 'client_name', 'client_id', 'company_id', 'starts_on', 'due_on', 'budget'], true), ARRAY_FILTER_USE_BOTH);
    }

    protected function taskFields(array $data): array
    {
        $out = [];
        foreach (['title', 'description', 'priority', 'assignee_id', 'milestone_id', 'starts_on', 'due_on'] as $k) {
            if (array_key_exists($k, $data)) $out[$k] = is_string($data[$k]) ? (trim($data[$k]) === '' ? null : trim($data[$k])) : $data[$k];
        }
        if (array_key_exists('estimate_hours', $data)) {
            $m = $this->minutes($data['estimate_hours'] ?? 0, 0);
            $out['estimate_minutes'] = $m ?: null;
        }
        if (array_key_exists('client_visible', $data)) $out['client_visible'] = (bool) $data['client_visible'];
        if (array_key_exists('checklist_text', $data)) {
            $old = collect($data['checklist_old'] ?? [])->keyBy('text');
            $out['checklist'] = collect(preg_split('/\r?\n/', (string) $data['checklist_text']))
                ->map(fn ($l) => trim($l))->filter()->unique()->take(50)
                ->map(fn ($t) => ['text' => mb_substr($t, 0, 200), 'done' => (bool) ($old[$t]['done'] ?? false)])->values()->all() ?: null;
        }
        if (array_key_exists('priority', $out) && !isset(Project::PRIORITIES[$out['priority']])) {
            unset($out['priority']);
        }

        return $out;
    }

    protected function applyStatus(Task $task, string $status, User $by): void
    {
        $this->check(!isset(Task::STATUSES[$status]), 'status', 'Unknown status.');
        $from = $task->status;
        $task->status = $status;
        $task->completed_at = $status === 'done' ? ($task->completed_at ?? now()) : null;
        if ($from !== $status) {
            $this->event($task, $by, 'moved this from ' . (Task::STATUSES[$from] ?? $from) . ' to ' . Task::STATUSES[$status]);
            if ($status === 'review' && $task->reporter_id && $task->reporter_id !== $by->id) {
                $this->notify([$task->reporter_id], $task, $task->key_label . ' is ready for review', $by->name . ' moved "' . $task->title . '" to review.');
            }
        }
    }

    protected function event(Task $task, User $by, string $text): void
    {
        $task->comments()->create(['user_id' => $by->id, 'body' => $text, 'is_event' => true]);
    }

    protected function notify(array $userIds, Task $task, string $title, string $body): void
    {
        if ($userIds) {
            PortalNotifier::toUsers($userIds, $title, $body, route('projects.tasks.show', [$task->project, $task->number]), 'project');
        }
    }

    protected function checkAssignee(Project $project, $userId): void
    {
        if (!$userId) return;
        $role = ProjectMember::where('project_id', $project->id)->where('user_id', $userId)->value('role');
        $this->check(!in_array($role, ['manager', 'member'], true), 'assignee_id', 'Assign the task to a manager or member of this project.');
    }

    protected function checkMilestone(Project $project, $milestoneId): void
    {
        $this->check($milestoneId && !Milestone::where('project_id', $project->id)->whereKey($milestoneId)->exists(), 'milestone_id', 'That milestone is not in this project.');
    }

    protected function minutes($hours, $mins): int
    {
        return (int) round(((float) $hours) * 60) + (int) $mins;
    }

    protected function check(bool $failed, string $field, string $message): void
    {
        if ($failed) throw ValidationException::withMessages([$field => $message]);
    }
}
