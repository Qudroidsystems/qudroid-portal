<?php

use App\Models\Connect\Conversation;
use App\Models\Connect\Message;
use App\Models\Connect\Participant;
use App\Models\User;
use App\Services\Connect\ConnectService;
use App\Services\Projects\ProjectService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function chatUser(string $role = 'Staff', string $type = 'staff', string $name = null): User
{
    $u = User::factory()->create(['user_type' => $type] + ($name ? ['name' => $name] : []));
    $u->assignRole($role);
    $u->forceFill(['must_change_password' => false])->save();

    return $u;
}

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->ada = chatUser(name: 'Ada Obi');
    $this->ben = chatUser(name: 'Ben Eze');
    $this->cy = chatUser(name: 'Cy Uche');
    $this->client = chatUser('Client', 'client');
    $this->svc = app(ConnectService::class);
});

test('a direct chat is created once, delivers messages and counts unread', function () {
    $this->actingAs($this->ada)->get(route('connect.direct', $this->ben))->assertRedirect();
    $this->actingAs($this->ben)->get(route('connect.direct', $this->ada))->assertRedirect();
    expect(Conversation::where('type', 'direct')->count())->toBe(1);
    $c = Conversation::first();

    $this->actingAs($this->ada)->postJson(route('connect.send', $c), ['body' => 'Hello Ben'])->assertOk();
    $this->actingAs($this->ada)->postJson(route('connect.send', $c), ['body' => 'Are you there?'])->assertOk();
    expect($this->svc->unreadTotal($this->ben))->toBe(2)->and($this->svc->unreadTotal($this->ada))->toBe(0)
        // One notification until Ben reads.
        ->and($this->ben->unreadNotifications()->count())->toBe(1);

    $this->actingAs($this->ben)->get(route('connect.show', $c))->assertOk()->assertSee('Hello Ben')->assertSee('Are you there?');
    expect($this->svc->unreadTotal($this->ben))->toBe(0)->and($this->ben->unreadNotifications()->count())->toBe(0);

    $this->actingAs($this->ada)->postJson(route('connect.send', $c), ['body' => 'Third'])->assertOk();
    expect($this->ben->fresh()->unreadNotifications()->count())->toBe(1);

    // Outsiders cannot read or post; clients only get chats shared with them (none here).
    $this->actingAs($this->cy)->get(route('connect.show', $c))->assertNotFound();
    $this->actingAs($this->cy)->postJson(route('connect.send', $c), ['body' => 'Hi'])->assertStatus(422);
    $this->actingAs($this->client)->get(route('connect.index'))->assertOk()->assertDontSee('Ben');
    $this->actingAs($this->client)->get(route('connect.show', $c))->assertNotFound();
    $this->actingAs($this->ada)->get(route('connect.direct', $this->ada))->assertSessionHasErrors('user');
});

test('polling returns new messages and the inbox', function () {
    $c = $this->svc->direct($this->ada, $this->ben);
    $first = $this->svc->send($c, $this->ada, 'One');
    $this->svc->send($c, $this->ada, 'Two');

    $r = $this->actingAs($this->ben)->getJson(route('connect.poll', $c) . '?inbox=1&after=' . $first->id)->assertOk();
    expect($r->json('html'))->toContain('Two')->not->toContain('>One<')
        ->and($r->json('inbox'))->toContain('Ada Obi')
        ->and($r->json('total'))->toBe(0);

    // An edit shows up in "changed".
    $this->actingAs($this->ada)->putJson(route('connect.messages.edit', $first), ['body' => 'One (fixed)'])->assertOk();
    $r = $this->actingAs($this->ben)->getJson(route('connect.poll', $c) . '?after=' . Message::max('id'));
    expect(collect($r->json('changed'))->implode(''))->toContain('One (fixed)');

    // Only the author edits; the author or an admin deletes.
    $this->actingAs($this->ben)->putJson(route('connect.messages.edit', $first), ['body' => 'Hacked'])->assertStatus(422);
    $this->actingAs($this->ben)->deleteJson(route('connect.messages.destroy', $first))->assertStatus(422);
    $this->actingAs($this->ada)->deleteJson(route('connect.messages.destroy', $first))->assertOk();
    expect($first->fresh()->isDeleted())->toBeTrue()->and($first->fresh()->body)->toBeNull();
});

test('groups, replies, reactions and files', function () {
    Storage::fake('local');
    $this->actingAs($this->ada)->post(route('connect.groups.store'), ['name' => 'Design team', 'users' => [$this->ben->id, $this->client->id]])->assertRedirect();
    $g = Conversation::where('type', 'group')->first();
    // The client cannot use Connect, so is not added.
    expect($g->members()->pluck('user_id')->sort()->values()->all())->toBe(collect([$this->ada->id, $this->ben->id])->sort()->values()->all())
        ->and($this->svc->isAdmin($g, $this->ada))->toBeTrue();

    $q = $this->svc->send($g, $this->ben, 'Which logo?');
    $this->actingAs($this->ada)->post(route('connect.send', $g), ['body' => 'This one', 'reply_to_id' => $q->id, 'attachment' => UploadedFile::fake()->image('logo.png')])->assertRedirect();
    $a = Message::latest('id')->first();
    expect($a->reply_to_id)->toBe($q->id)->and($a->isImage())->toBeTrue();
    $this->actingAs($this->ben)->get(route('connect.messages.file', $a))->assertOk();
    $this->actingAs($this->cy)->get(route('connect.messages.file', $a))->assertNotFound();

    $this->actingAs($this->ben)->postJson(route('connect.messages.react', $a), ['emoji' => '👍'])->assertOk();
    $this->actingAs($this->ben)->postJson(route('connect.messages.react', $a), ['emoji' => '💩'])->assertStatus(422);
    expect($a->reactions()->count())->toBe(1);
    $this->actingAs($this->ben)->postJson(route('connect.messages.react', $a), ['emoji' => '👍']);
    expect($a->reactions()->count())->toBe(0);

    // Members: only admins add or remove; leaving hands admin on.
    $this->actingAs($this->ben)->post(route('connect.members.add', $g), ['users' => [$this->cy->id]])->assertSessionHasErrors('users');
    $this->actingAs($this->ada)->post(route('connect.members.add', $g), ['users' => [$this->cy->id]])->assertSessionHasNoErrors();
    $this->actingAs($this->ada)->post(route('connect.leave', $g))->assertRedirect(route('connect.index'));
    expect($g->members()->where('role', 'admin')->count())->toBe(1)->and($this->svc->participant($g, $this->ada))->toBeNull();
    $this->actingAs($this->ada)->get(route('connect.show', $g))->assertNotFound();
});

test('channels are open to browse and join; only members post and channels do not notify', function () {
    $this->actingAs($this->ada)->post(route('connect.channels.store'), ['name' => 'General Chat!'])->assertRedirect();
    $ch = Conversation::where('type', 'channel')->first();
    expect($ch->name)->toBe('general-chat');
    $this->actingAs($this->ada)->post(route('connect.channels.store'), ['name' => 'general chat'])->assertSessionHasErrors('name');

    $student = chatUser('Student', 'student');
    $this->actingAs($student)->post(route('connect.channels.store'), ['name' => 'students'])->assertSessionHasErrors('name');

    $this->actingAs($this->ben)->get(route('connect.show', $ch))->assertOk()->assertSee('Join #general-chat');
    $this->actingAs($this->ben)->postJson(route('connect.send', $ch), ['body' => 'hi'])->assertStatus(422);
    $this->actingAs($this->ben)->post(route('connect.join', $ch))->assertRedirect();
    $this->actingAs($this->ben)->postJson(route('connect.send', $ch), ['body' => 'Hello all'])->assertOk();
    expect($this->ada->unreadNotifications()->count())->toBe(0)
        ->and($this->svc->unreadTotal($this->ada))->toBe(1);
});

test('a project gets a team chat that follows its members', function () {
    $pm = chatUser('Project Manager');
    $this->actingAs($pm)->post(route('projects.store'), ['code' => 'WEB', 'name' => 'Website', 'priority' => 'normal', 'client_id' => $this->client->id]);
    $project = \App\Models\Projects\Project::first();
    app(ProjectService::class)->setMember($project, $this->ben->id, 'member');

    $this->actingAs($pm)->get(route('projects.show', $project))->assertSee('Team chat');
    $this->actingAs($this->ben)->get(route('connect.project', $project))->assertRedirect();
    $c = Conversation::where('project_id', $project->id)->first();
    expect($c->members()->pluck('user_id')->sort()->values()->all())->toBe(collect([$pm->id, $this->ben->id])->sort()->values()->all());

    $this->actingAs($this->client)->get(route('connect.project', $project))->assertForbidden();

    $member = $project->members()->where('user_id', $this->ben->id)->first();
    app(ProjectService::class)->removeMember($project, $member);
    $this->actingAs($pm)->get(route('connect.project', $project));
    expect($this->svc->participant($c, $this->ben))->toBeNull();
});

test('the mobile API lists conversations and sends messages', function () {
    $c = $this->svc->direct($this->ada, $this->ben);
    $this->svc->send($c, $this->ada, 'From the web');

    Sanctum::actingAs($this->ben);
    $this->getJson('/api/v1/connect/conversations')->assertOk()
        ->assertJsonPath('data.0.title', 'Ada Obi')->assertJsonPath('data.0.unread', 1)->assertJsonPath('meta.unread', 1);
    $this->getJson("/api/v1/connect/conversations/{$c->id}/messages")->assertOk()->assertJsonPath('data.0.body', 'From the web');
    $this->postJson("/api/v1/connect/conversations/{$c->id}/messages", ['body' => 'From the app'])->assertCreated()->assertJsonPath('data.mine', true);
    $this->postJson('/api/v1/connect/direct', ['user_id' => $this->cy->id])->assertOk()->assertJsonPath('data.title', 'Cy Uche');
    $this->getJson('/api/v1/connect/people?q=Cy')->assertOk()->assertJsonPath('data.0.name', 'Cy Uche');

    Sanctum::actingAs($this->client);
    $this->getJson('/api/v1/connect/conversations')->assertOk()->assertJsonCount(0, 'data');
    $this->getJson('/api/v1/connect/people')->assertOk()->assertJsonCount(0, 'data');
});
