<?php

use App\Models\Connect\Conversation;
use App\Models\Projects\Project;
use App\Models\User;
use App\Services\Connect\ConnectService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function guestUser(string $name, string $role = 'Staff', string $type = 'staff'): User
{
    $u = User::factory()->create(['user_type' => $type, 'name' => $name]);
    $u->assignRole($role);
    $u->forceFill(['must_change_password' => false])->save();

    return $u;
}

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->pm = guestUser('Pat Manager', 'Project Manager');
    $this->dev = guestUser('Dev Staff');
    $this->other = guestUser('Other Staff');
    $this->client = guestUser('Cleo Client', 'Client', 'client');
    $this->client2 = guestUser('Carl Client', 'Client', 'client');
    $this->svc = app(ConnectService::class);
});

test('a project client chat brings the team and the client together, and the client sees only that', function () {
    $this->actingAs($this->pm)->post(route('projects.store'), ['code' => 'web', 'name' => 'Website', 'priority' => 'high', 'client_id' => $this->client->id])->assertRedirect();
    $p = Project::where('code', 'WEB')->firstOrFail();
    $this->actingAs($this->pm)->post(route('projects.members.store', $p), ['user_id' => $this->dev->id, 'role' => 'member']);
    $channel = $this->svc->channel($this->pm, 'general', null);

    // Before the team starts it, the client has nothing to open.
    $this->actingAs($this->client)->get(route('projects.show', $p))->assertOk()->assertDontSee('Chat with the team');
    $this->actingAs($this->client)->get(route('connect.project-clients', $p))->assertNotFound();

    $this->actingAs($this->pm)->get(route('projects.show', $p))->assertSee('Client chat');
    $this->actingAs($this->pm)->get(route('connect.project-clients', $p))->assertRedirect();
    $c = Conversation::where('client_project_id', $p->id)->firstOrFail();
    expect($c->guests_allowed)->toBeTrue()
        ->and($c->members()->pluck('user_id')->sort()->values()->all())->toEqual(collect([$this->pm->id, $this->dev->id, $this->client->id])->sort()->values()->all());

    // The client can use Connect for this chat only.
    $this->actingAs($this->client)->get(route('connect.index'))->assertOk()->assertSee('Website · client chat')->assertDontSee('#general');
    $this->actingAs($this->client)->get(route('connect.show', $c))->assertOk();
    $this->actingAs($this->client)->post(route('connect.send', $c), ['body' => 'Hello team'])->assertSessionHasNoErrors();
    $this->actingAs($this->pm)->get(route('connect.show', $c))->assertOk()->assertSee('Shared with clients')->assertSee('Client');
    $this->actingAs($this->client)->get(route('connect.show', $channel))->assertNotFound();
    $this->actingAs($this->client)->post(route('connect.join', $channel))->assertSessionHasErrors('conversation');
    $this->actingAs($this->client)->post(route('connect.groups.store'), ['name' => 'Mine', 'users' => [$this->dev->id]])->assertSessionHasErrors('name');
    $this->actingAs($this->client)->post(route('connect.channels.store'), ['name' => 'mine'])->assertSessionHasErrors('name');
    $this->actingAs($this->client)->post(route('connect.members.add', $c), ['users' => [$this->other->id]])->assertSessionHasErrors('users');
    $this->actingAs($this->client)->get(route('drive.index'))->assertForbidden();

    // Direct chats: only with people they share a chat with, never with other clients.
    $this->actingAs($this->client)->get(route('connect.direct', $this->dev))->assertRedirect();
    expect(Conversation::where('direct_key', Conversation::directKey($this->client->id, $this->dev->id))->value('guests_allowed'))->toBeTrue();
    $this->actingAs($this->client)->get(route('connect.direct', $this->other))->assertSessionHasErrors('user');
    $this->actingAs($this->client)->get(route('connect.project-clients', $p))->assertRedirect(route('connect.show', $c));

    // Staff can still start a direct chat with any client.
    $this->actingAs($this->other)->get(route('connect.direct', $this->client2))->assertRedirect();
});

test('a group admin shares a group with clients before adding them, and cannot unshare while they are in it', function () {
    $g = $this->svc->group($this->pm, 'Launch', [$this->dev->id]);

    // Not shared: clients are not addable.
    $this->actingAs($this->pm)->post(route('connect.members.add', $g), ['users' => [$this->client->id]]);
    expect($this->svc->participant($g, $this->client))->toBeNull();

    $this->actingAs($this->dev)->post(route('connect.guests', $g), ['on' => 1])->assertSessionHasErrors('guests');
    $this->actingAs($this->pm)->post(route('connect.guests', $g), ['on' => 1])->assertSessionHasNoErrors();
    $this->actingAs($this->pm)->get(route('connect.show', $g))->assertSee('Cleo Client');
    $this->actingAs($this->pm)->post(route('connect.members.add', $g), ['users' => [$this->client->id]])->assertSessionHasNoErrors();
    expect($this->svc->participant($g->fresh(), $this->client))->not->toBeNull();

    $this->actingAs($this->pm)->post(route('connect.guests', $g), ['on' => 0])->assertSessionHasErrors('guests');
    $this->actingAs($this->pm)->delete(route('connect.members.remove', [$g, $this->client]));
    $this->actingAs($this->pm)->post(route('connect.guests', $g), ['on' => 0])->assertSessionHasNoErrors();
    expect($g->fresh()->guests_allowed)->toBeFalse();

    // Channels and project team chats are never shared.
    $ch = $this->svc->channel($this->pm, 'news', null);
    $this->actingAs($this->pm)->post(route('connect.guests', $ch), ['on' => 1])->assertSessionHasErrors('guests');

    // The guest list in the people picker shows only clients they share chats with.
    expect($this->svc->people(except: $this->client)->pluck('id')->all())->toBe([]);
});
