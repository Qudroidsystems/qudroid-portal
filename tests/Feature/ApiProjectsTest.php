<?php

use App\Models\Projects\Project;
use App\Models\Projects\Task;
use App\Models\User;
use App\Services\Projects\ProjectService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function apUser(string $role, string $type = 'staff'): User
{
    $u = User::factory()->create(['user_type' => $type]);
    $u->assignRole($role);
    $u->forceFill(['must_change_password' => false])->save();

    return $u;
}

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->pm = apUser('Project Manager');
    $this->dev = apUser('Staff');
    $this->outsider = apUser('Staff');
    $this->client = apUser('Client', 'client');
    $svc = app(ProjectService::class);
    $this->project = $svc->create(['code' => 'WEB', 'name' => 'Website rebuild', 'client_id' => $this->client->id, 'color' => '#0ea5e9', 'due_on' => today()->addMonth()->toDateString()], $this->pm);
    $svc->addMember($this->project, $this->dev, 'member', $this->pm);
});

test('projects and tasks follow the same access rules as the web', function () {
    Sanctum::actingAs($this->pm);
    $t1 = $this->postJson(route('api.v1.projects.tasks.store', $this->project->id), ['title' => 'Home page', 'assignee_id' => $this->dev->id, 'due_on' => today()->subDay()->toDateString(), 'client_visible' => true])
        ->assertCreated()->assertJsonPath('data.key', 'WEB-1')->json('data');
    $this->postJson(route('api.v1.projects.tasks.store', $this->project->id), ['title' => 'Internal budget'])->assertCreated();
    $this->postJson(route('api.v1.projects.tasks.store', $this->project->id), ['title' => 'x', 'assignee_id' => $this->outsider->id])->assertStatus(422);

    $this->getJson(route('api.v1.projects.index'))->assertOk()->assertJsonPath('data.0.code', 'WEB')->assertJsonPath('data.0.counts.open', 2)
        ->assertJsonPath('data.0.counts.overdue', 1)->assertJsonPath('data.0.my_role', 'manager');
    $this->getJson(route('api.v1.projects.show', $this->project->id))->assertOk()->assertJsonPath('data.columns.0.key', 'todo')->assertJsonCount(3, 'data.members');

    Sanctum::actingAs($this->dev);
    $this->getJson(route('api.v1.tasks.mine', ['scope' => 'overdue']))->assertOk()->assertJsonPath('data.0.id', $t1['id'])->assertJsonPath('meta.overdue', 1);
    $this->getJson(route('api.v1.projects.tasks', [$this->project->id, 'assignee' => 'me']))->assertJsonCount(1, 'data');

    // the client sees only shared tasks, and can't change them
    Sanctum::actingAs($this->client);
    $this->getJson(route('api.v1.projects.tasks', $this->project->id))->assertJsonCount(1, 'data');
    $this->putJson(route('api.v1.tasks.update', $t1['id']), ['title' => 'Mine now'])->assertForbidden();
    $hidden = Task::where('title', 'Internal budget')->first();
    $this->getJson(route('api.v1.tasks.show', $hidden->id))->assertNotFound();

    Sanctum::actingAs($this->outsider);
    $this->getJson(route('api.v1.projects.show', $this->project->id))->assertNotFound();
    $this->getJson(route('api.v1.projects.index'))->assertJsonCount(0, 'data');
});

test('tasks can be edited, moved on the board, commented on and timed', function () {
    Storage::fake('local');
    $svc = app(ProjectService::class);
    $a = $svc->createTask($this->project, ['title' => 'A'], $this->pm);
    $b = $svc->createTask($this->project, ['title' => 'B'], $this->pm);
    $c = $svc->createTask($this->project, ['title' => 'C', 'status' => 'in_progress'], $this->pm);
    Sanctum::actingAs($this->dev);

    $this->putJson(route('api.v1.tasks.update', $a->id), ['priority' => 'high', 'checklist' => [['text' => 'Design', 'done' => true], ['text' => 'Build']]])
        ->assertOk()->assertJsonPath('data.checklist_done', 1)->assertJsonPath('data.checklist_total', 2);

    // drag B to the top of In progress
    $this->postJson(route('api.v1.tasks.move', $b->id), ['status' => 'in_progress', 'position' => 1])->assertOk()->assertJsonPath('data.status', 'in_progress');
    expect(Task::where('status', 'in_progress')->orderBy('position')->pluck('title')->all())->toBe(['B', 'C']);

    $this->post(route('api.v1.tasks.comments', $a->id), ['body' => 'Mock-ups attached', 'file' => UploadedFile::fake()->create('mock.pdf', 10, 'application/pdf')], ['Accept' => 'application/json'])
        ->assertCreated()->assertJsonPath('data.attachment.name', 'mock.pdf');
    $this->postJson(route('api.v1.tasks.comments', $a->id), ['body' => 'Internal note', 'internal' => true])->assertCreated();
    $this->postJson(route('api.v1.tasks.time', $a->id), ['minutes' => 90, 'date' => today()->toDateString()])->assertCreated()->assertJsonPath('data.task_minutes', 90);

    $show = $this->getJson(route('api.v1.tasks.show', $a->id))->assertOk()->json('data');
    $real = collect($show['comments'])->where('is_event', false)->values();
    expect($real)->toHaveCount(2)->and($show['time_logged_minutes'])->toBe(90)->and($show['checklist'][0])->toBe(['text' => 'Design', 'done' => true]);
    $this->get($real[0]['attachment']['url'])->assertOk();

    // the client doesn't see internal comments (or the time)
    $svc->updateTask($a, ['client_visible' => true], $this->pm);
    Sanctum::actingAs($this->client);
    $show = $this->getJson(route('api.v1.tasks.show', $a->id))->json('data');
    expect(collect($show['comments'])->pluck('body'))->not->toContain('Internal note')->and($show['time_logged_minutes'])->toBeNull();
});
