<?php

use App\Models\Projects\Project;
use App\Models\Projects\ProjectMember;
use App\Models\Projects\Task;
use App\Models\Projects\TaskComment;
use App\Models\Projects\TimeEntry;
use App\Models\User;
use App\Services\Projects\ProjectService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function projectUser(string $role, string $type = 'staff'): User
{
    $u = User::factory()->create(['user_type' => $type]);
    $u->assignRole($role);
    $u->forceFill(['must_change_password' => false])->save();

    return $u;
}

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->pm = projectUser('Project Manager');
    $this->dev = projectUser('Staff');
    $this->outsider = projectUser('Staff');
    $this->client = projectUser('Client', 'client');
});

function makeProject($test): Project
{
    $test->actingAs($test->pm)->post(route('projects.store'), [
        'code' => 'web', 'name' => 'Website rebuild', 'priority' => 'high', 'client_name' => 'Acme Ltd', 'client_id' => $test->client->id,
        'due_on' => today()->addMonth()->toDateString(), 'color' => '#0ea5e9',
    ])->assertRedirect();
    $p = Project::where('code', 'WEB')->firstOrFail();
    $test->actingAs($test->pm)->post(route('projects.members.store', $p), ['user_id' => $test->dev->id, 'role' => 'member'])->assertSessionHasNoErrors();

    return $p;
}

test('a project manager creates a project; the creator manages it and the client is added', function () {
    $p = makeProject($this);
    expect($p->manager_id)->toBe($this->pm->id)
        ->and(ProjectMember::where('project_id', $p->id)->pluck('role', 'user_id')->all())
        ->toEqual([$this->pm->id => 'manager', $this->client->id => 'client', $this->dev->id => 'member']);

    // Codes are unique and well formed.
    $this->actingAs($this->pm)->post(route('projects.store'), ['code' => 'WEB', 'name' => 'X', 'priority' => 'normal'])->assertSessionHasErrors('code');
    $this->actingAs($this->pm)->post(route('projects.store'), ['code' => '1A', 'name' => 'X', 'priority' => 'normal'])->assertSessionHasErrors('code');

    // Ordinary staff cannot create projects, and only see projects they belong to.
    $this->actingAs($this->dev)->get(route('projects.create'))->assertForbidden();
    $this->actingAs($this->dev)->get(route('projects.index'))->assertOk()->assertSee('Website rebuild');
    $this->actingAs($this->outsider)->get(route('projects.index'))->assertOk()->assertDontSee('Website rebuild');
    $this->actingAs($this->outsider)->get(route('projects.show', $p))->assertForbidden();

    // A client account cannot be added as a member, nor staff as client.
    $this->actingAs($this->pm)->post(route('projects.members.store', $p), ['user_id' => $this->outsider->id, 'role' => 'client'])->assertSessionHasErrors('role');
});

test('tasks are numbered per project, moved on the board and logged as history', function () {
    $p = makeProject($this);
    $this->actingAs($this->dev)->post(route('projects.tasks.store', $p), ['title' => 'Design home page', 'assignee_id' => $this->dev->id, 'stay' => 1])->assertSessionHasNoErrors();
    $this->actingAs($this->dev)->post(route('projects.tasks.store', $p), ['title' => 'Build contact form', 'status' => 'todo'])->assertRedirect(route('projects.tasks.show', [$p, 2]));
    expect(Task::where('project_id', $p->id)->orderBy('number')->pluck('number')->all())->toBe([1, 2]);

    // Assignee must be a working member.
    $this->actingAs($this->dev)->post(route('projects.tasks.store', $p), ['title' => 'X', 'assignee_id' => $this->client->id])->assertSessionHasErrors('assignee_id');

    $t1 = Task::where('project_id', $p->id)->where('number', 1)->first();
    $t2 = Task::where('project_id', $p->id)->where('number', 2)->first();
    $this->actingAs($this->dev)->postJson(route('projects.tasks.move', [$p, 2]), ['status' => 'in_progress', 'order' => [$t2->id]])->assertOk();
    $this->actingAs($this->dev)->postJson(route('projects.tasks.move', [$p, 1]), ['status' => 'in_progress', 'order' => [$t1->id, $t2->id]])->assertOk();
    expect($t1->fresh()->status)->toBe('in_progress')->and($t1->fresh()->position)->toBe(1)->and($t2->fresh()->position)->toBe(2);

    $this->actingAs($this->dev)->postJson(route('projects.tasks.move', [$p, 1]), ['status' => 'done', 'order' => [$t1->id]])->assertOk();
    expect($t1->fresh()->completed_at)->not->toBeNull()
        ->and(TaskComment::where('task_id', $t1->id)->where('is_event', true)->count())->toBe(2);

    // Outsiders and clients cannot move tasks.
    $this->actingAs($this->client)->postJson(route('projects.tasks.move', [$p, 2]), ['status' => 'done'])->assertForbidden();
    $this->actingAs($this->outsider)->postJson(route('projects.tasks.move', [$p, 2]), ['status' => 'done'])->assertForbidden();

    $this->actingAs($this->dev)->get(route('projects.board', $p))->assertOk()->assertSee('Design home page')->assertSee('Sortable.min.js', false);
    $this->actingAs($this->dev)->get(route('projects.board', [$p, 'view' => 'list', 'q' => 'WEB-2']))->assertOk()->assertSee('Build contact form')->assertDontSee('Design home page');
});

test('task details, checklist and assignment notify the right people', function () {
    $p = makeProject($this);
    $task = app(ProjectService::class)->createTask($p, ['title' => 'Write copy'], $this->pm);

    $this->actingAs($this->pm)->put(route('projects.tasks.update', [$p, 1]), [
        'title' => 'Write copy', 'status' => 'todo', 'priority' => 'urgent', 'assignee_id' => $this->dev->id,
        'checklist_text' => "Home\nAbout\n\nHome", 'estimate_hours' => 1.5, 'client_visible' => 0, 'due_on' => today()->subDay()->toDateString(),
    ])->assertSessionHasNoErrors();
    $task->refresh();
    expect($task->assignee_id)->toBe($this->dev->id)->and($task->priority)->toBe('urgent')
        ->and($task->estimate_minutes)->toBe(90)->and($task->checklist)->toHaveCount(2)->and($task->isOverdue())->toBeTrue()
        ->and($this->dev->notifications()->count())->toBeGreaterThan(0);

    $this->actingAs($this->dev)->post(route('projects.tasks.checklist', [$p, 1]), ['index' => 1])->assertRedirect();
    expect($task->fresh()->checklistProgress())->toBe([1, 2]);

    // Ticked items stay ticked when the checklist text is saved again.
    $this->actingAs($this->dev)->put(route('projects.tasks.update', [$p, 1]), ['title' => 'Write copy', 'checklist_text' => "Home\nAbout\nContact"]);
    expect(collect($task->fresh()->checklist)->where('done', true)->pluck('text')->all())->toBe(['About']);

    $this->actingAs($this->dev)->get(route('projects.my-work'))->assertOk()->assertSee('Write copy')->assertSee('Overdue');
});

test('clients only see shared tasks and public comments', function () {
    Storage::fake('local');
    $p = makeProject($this);
    $svc = app(ProjectService::class);
    $shared = $svc->createTask($p, ['title' => 'Shared milestone review', 'client_visible' => true], $this->pm);
    $hidden = $svc->createTask($p, ['title' => 'Internal refactor'], $this->pm);

    $this->actingAs($this->pm)->post(route('projects.tasks.comment', [$p, $shared->number]), ['body' => 'Secret pricing note', 'internal' => 1]);
    $this->actingAs($this->pm)->post(route('projects.tasks.comment', [$p, $shared->number]), ['body' => 'Draft attached', 'attachment' => UploadedFile::fake()->create('draft.pdf', 10, 'application/pdf')]);

    $this->actingAs($this->client)->get(route('projects.board', $p))->assertOk()->assertSee('Shared milestone review')->assertDontSee('Internal refactor');
    $this->actingAs($this->client)->get(route('projects.tasks.show', [$p, $hidden->number]))->assertNotFound();
    $this->actingAs($this->client)->get(route('projects.tasks.show', [$p, $shared->number]))->assertOk()->assertSee('Draft attached')->assertDontSee('Secret pricing note');
    $this->actingAs($this->client)->get(route('projects.show', $p))->assertOk()->assertDontSee('Time logged');

    $internal = TaskComment::where('body', 'Secret pricing note')->first();
    expect($this->client->notifications()->where('data', 'like', '%Secret pricing%')->count())->toBe(0);

    // A client can comment, but never internally.
    $this->actingAs($this->client)->post(route('projects.tasks.comment', [$p, $shared->number]), ['body' => 'Looks good', 'internal' => 1]);
    expect(TaskComment::where('body', 'Looks good')->first()->is_internal)->toBeFalse();

    $file = TaskComment::whereNotNull('attachment_path')->first();
    $this->actingAs($this->client)->get(route('projects.tasks.attachment', [$p, $shared->number, $file]))->assertOk();
    $this->actingAs($this->client)->get(route('projects.time', $p))->assertForbidden();
});

test('time is logged against a project and shown on the timesheet', function () {
    $p = makeProject($this);
    $task = app(ProjectService::class)->createTask($p, ['title' => 'Build API'], $this->pm);

    $this->actingAs($this->dev)->post(route('projects.time.store', $p), ['date' => today()->toDateString(), 'hours' => 1.5, 'mins' => 15, 'task_id' => $task->id, 'billable' => 1])->assertSessionHasNoErrors();
    $this->actingAs($this->dev)->post(route('projects.time.store', $p), ['date' => today()->addDay()->toDateString(), 'hours' => 1])->assertSessionHasErrors('date');
    $this->actingAs($this->dev)->post(route('projects.time.store', $p), ['date' => today()->toDateString()])->assertSessionHasErrors('hours');
    expect(TimeEntry::sum('minutes'))->toBe(105);

    $this->actingAs($this->pm)->get(route('projects.time', $p))->assertOk()->assertSee('1h 45m');
    $this->actingAs($this->pm)->get(route('projects.show', $p))->assertOk()->assertSee('1h 45m');

    $entry = TimeEntry::first();
    $other = projectUser('Staff');
    app(ProjectService::class)->setMember($p, $other->id, 'member');
    $this->actingAs($other)->delete(route('projects.time.destroy', [$p, $entry]))->assertForbidden();
    $this->actingAs($this->pm)->delete(route('projects.time.destroy', [$p, $entry]))->assertRedirect();
    expect(TimeEntry::count())->toBe(0);
});

test('milestones, status and members are managed by the project manager only', function () {
    $p = makeProject($this);
    $this->actingAs($this->dev)->post(route('projects.milestones.store', $p), ['title' => 'Launch'])->assertForbidden();
    $this->actingAs($this->pm)->post(route('projects.milestones.store', $p), ['title' => 'Launch', 'due_on' => today()->addWeek()->toDateString()])->assertSessionHasNoErrors();
    $ms = $p->milestones()->first();
    $this->actingAs($this->pm)->post(route('projects.milestones.toggle', [$p, $ms]));
    expect($ms->fresh()->completed_at)->not->toBeNull();

    $this->actingAs($this->pm)->post(route('projects.status', $p), ['status' => 'completed']);
    expect($p->fresh()->status)->toBe('completed')->and($p->fresh()->completed_at)->not->toBeNull();

    // The last manager cannot be removed or demoted.
    $mgr = ProjectMember::where('project_id', $p->id)->where('role', 'manager')->first();
    $this->actingAs($this->pm)->delete(route('projects.members.destroy', [$p, $mgr]))->assertSessionHasErrors();
    $this->actingAs($this->pm)->put(route('projects.members.update', [$p, $mgr]), ['role' => 'member'])->assertSessionHasErrors('role');

    // Removing a member unassigns their open tasks.
    $task = app(ProjectService::class)->createTask($p, ['title' => 'T', 'assignee_id' => $this->dev->id], $this->pm);
    $devMember = ProjectMember::where('project_id', $p->id)->where('user_id', $this->dev->id)->first();
    $this->actingAs($this->pm)->delete(route('projects.members.destroy', [$p, $devMember]))->assertSessionHasNoErrors();
    expect($task->fresh()->assignee_id)->toBeNull();
    $this->actingAs($this->dev)->get(route('projects.show', $p))->assertForbidden();
});

test('project pages load', function () {
    $p = makeProject($this);
    app(ProjectService::class)->createTask($p, ['title' => 'Kickoff', 'assignee_id' => $this->dev->id, 'due_on' => today()->addDay()->toDateString()], $this->pm);
    $this->actingAs($this->pm)->get(route('projects.create'))->assertOk();
    $this->actingAs($this->pm)->get(route('projects.edit', $p))->assertOk()->assertSee('Website rebuild');
    $this->actingAs($this->dev)->get(route('projects.edit', $p))->assertForbidden();
    $this->actingAs($this->pm)->get(route('projects.show', $p))->assertOk()->assertSee('Coming up')->assertSee('Kickoff');
    $this->actingAs($this->pm)->get(route('projects.tasks.show', [$p, 1]))->assertOk()->assertSee('Log time');
    $this->actingAs($this->client)->get(route('projects.index'))->assertOk()->assertSee('Website rebuild');
    $this->actingAs($this->pm)->put(route('projects.update', $p), ['name' => 'Website v2', 'priority' => 'normal'])->assertRedirect();
    expect($p->fresh()->name)->toBe('Website v2')->and($p->fresh()->manager_id)->toBe($this->pm->id);
});
