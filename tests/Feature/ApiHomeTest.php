<?php

use App\Models\Projects\Project;
use App\Models\User;
use App\Services\Projects\ProjectService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->admin = User::factory()->create(['user_type' => 'staff', 'name' => 'Ada Admin']);
    $this->admin->assignRole('Super Administrator');
    $this->admin->forceFill(['must_change_password' => false])->save();
});

test('auth/me carries modules, permissions and presence; home gives the blocks the person can see', function () {
    Sanctum::actingAs($this->admin);
    $this->getJson('/api/v1/auth/me')->assertOk()
        ->assertJsonPath('data.permissions', ['*'])
        ->assertJsonStructure(['data' => ['modules', 'roles', 'presence' => ['state', 'label', 'line'], 'business_unit']]);

    $this->actingAs($this->admin)->post(route('projects.store'), ['code' => 'APP', 'name' => 'App build', 'priority' => 'normal']);
    $p = Project::where('code', 'APP')->first();
    app(ProjectService::class)->createTask($p, ['title' => 'Design home', 'assignee_id' => $this->admin->id, 'due_on' => today()->subDay()->toDateString()], $this->admin);

    Sanctum::actingAs($this->admin);
    $home = $this->getJson('/api/v1/home')->assertOk()->json('data');
    expect($home)->toHaveKeys(['unread_notifications', 'unread_messages', 'next_meetings', 'my_tasks_due', 'pending_approvals'])
        ->and($home['my_tasks_due'][0]['key'])->toBe('APP-1')->and($home['my_tasks_due'][0]['overdue'])->toBeTrue()
        ->and($home)->not->toHaveKey('my_courses');

    // a student sees none of the staff blocks
    $student = User::factory()->create(['user_type' => 'student']);
    $student->assignRole('Student');
    Sanctum::actingAs($student);
    expect($this->getJson('/api/v1/home')->assertOk()->json('data'))->not->toHaveKeys(['my_tasks_due', 'pending_approvals']);
});

test('search groups results by type with app links', function () {
    Sanctum::actingAs($this->admin);
    $this->getJson('/api/v1/search?q=a')->assertStatus(422);
    $res = $this->getJson('/api/v1/search?q=Ada')->assertOk()->json('data');
    $people = collect($res['groups'])->firstWhere('type', 'people');
    expect($people['results'][0]['title'])->toBe('Ada Admin')->and($people['results'][0]['deep_link'])->toStartWith('user/');
});
