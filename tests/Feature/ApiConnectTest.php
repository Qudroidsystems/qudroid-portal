<?php

use App\Models\Connect\Message;
use App\Models\User;
use App\Services\Connect\ConnectService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function apiChatUser(string $name, string $role = 'Staff'): User
{
    $u = User::factory()->create(['user_type' => 'staff', 'name' => $name]);
    $u->assignRole($role);
    $u->forceFill(['must_change_password' => false])->save();

    return $u;
}

beforeEach(function () {
    Storage::fake('local');
    $this->seed(DatabaseSeeder::class);
    $this->ada = apiChatUser('Ada Obi');
    $this->ben = apiChatUser('Ben Eze');
    $this->cy = apiChatUser('Cy Uche');
    $this->svc = app(ConnectService::class);
});

test('the app creates groups, manages members, mutes and sends polls, GIFs, threads and files', function () {
    Sanctum::actingAs($this->ada);
    $g = $this->postJson('/api/v1/connect/conversations', ['kind' => 'group', 'name' => 'Launch', 'member_ids' => [$this->ben->id]])
        ->assertCreated()->assertJsonPath('data.kind', 'group')->json('data.id');
    $this->postJson("/api/v1/connect/conversations/{$g}/members", ['user_ids' => [$this->cy->id]])->assertOk()->assertJsonPath('data.added', 1);
    $this->getJson("/api/v1/connect/conversations/{$g}/members")->assertOk()->assertJsonCount(3, 'data');
    $this->deleteJson("/api/v1/connect/conversations/{$g}/members/{$this->cy->id}")->assertOk();
    $this->postJson("/api/v1/connect/conversations/{$g}/mute")->assertJsonPath('data.muted', true);
    $this->postJson("/api/v1/connect/conversations/{$g}/mute", ['muted' => false])->assertJsonPath('data.muted', false);

    // poll
    $poll = $this->postJson("/api/v1/connect/conversations/{$g}/messages", ['kind' => 'poll', 'body' => 'Lunch?', 'meta' => ['options' => ['Rice', 'Beans']]])
        ->assertCreated()->assertJsonPath('data.kind', 'poll')->assertJsonPath('data.meta.question', 'Lunch?')->json('data.id');
    Sanctum::actingAs($this->ben);
    $this->postJson("/api/v1/connect/messages/{$poll}/vote", ['options' => [1]])->assertOk()
        ->assertJsonPath('data.meta.options.1.count', 1)->assertJsonPath('data.meta.my_vote', [1]);
    Sanctum::actingAs($this->ada);
    $this->postJson("/api/v1/connect/messages/{$poll}/close-poll")->assertOk()->assertJsonPath('data.meta.closed', true);

    // GIF (only GIPHY links), thread reply, edit, pin, save
    $this->postJson("/api/v1/connect/conversations/{$g}/messages", ['kind' => 'gif', 'meta' => ['url' => 'https://evil.example/x.gif']])->assertUnprocessable();
    $this->postJson("/api/v1/connect/conversations/{$g}/messages", ['kind' => 'gif', 'meta' => ['url' => 'https://media.giphy.com/media/abc/giphy.gif', 'width' => 200, 'height' => 150]])
        ->assertCreated()->assertJsonPath('data.meta.width', 200);
    $root = $this->postJson("/api/v1/connect/conversations/{$g}/messages", ['body' => 'Plan'])->json('data.id');
    $this->postJson("/api/v1/connect/conversations/{$g}/messages", ['body' => 'Step one', 'parent_id' => $root])->assertCreated()->assertJsonPath('data.parent_id', $root);
    $this->getJson("/api/v1/connect/conversations/{$g}/messages/{$root}/thread")->assertOk()
        ->assertJsonPath('data.root.thread_count', 1)->assertJsonPath('data.replies.0.body', 'Step one');
    $this->putJson("/api/v1/connect/messages/{$root}", ['body' => 'The plan'])->assertOk()->assertJsonPath('data.body', 'The plan')->assertJsonPath('data.edited', true);
    $this->postJson("/api/v1/connect/messages/{$root}/pin")->assertJsonPath('data.pinned', true);
    $this->postJson("/api/v1/connect/messages/{$root}/save")->assertJsonPath('data.saved', true);
    $this->getJson('/api/v1/connect/saved')->assertOk()->assertJsonPath('data.0.id', $root)->assertJsonPath('data.0.saved', true);
    $this->getJson('/api/v1/connect/search?q=plan')->assertOk()->assertJsonPath('data.0.conversation.title', 'Launch');

    // several files: one message each, text with the first
    $this->post("/api/v1/connect/conversations/{$g}/messages", ['body' => 'Docs', 'files' => [UploadedFile::fake()->image('a.png'), UploadedFile::fake()->create('b.pdf', 10, 'application/pdf')]], ['Accept' => 'application/json'])
        ->assertCreated()->assertJsonPath('data.body', 'Docs')->assertJsonPath('data.attachments.0.name', 'a.png');
    expect(Message::where('conversation_id', $g)->whereNotNull('attachment_path')->count())->toBe(2);

    // Cy (removed) cannot read it; staff create channels
    Sanctum::actingAs($this->cy);
    $this->getJson("/api/v1/connect/conversations/{$g}/messages")->assertNotFound();
});

test('poll returns new and changed messages, typing, presence and unread counts across chats', function () {
    $d = $this->svc->direct($this->ada, $this->ben);
    $g = $this->svc->group($this->ada, 'Ops', [$this->ben->id]);
    $first = $this->svc->send($d, $this->ada, 'Hello');

    Sanctum::actingAs($this->ben);
    $cursor = $this->getJson('/api/v1/connect/poll')->assertOk()->assertJsonCount(0, 'data.messages')->json('data.cursor');
    expect($cursor)->toBeGreaterThanOrEqual($first->id);

    $this->svc->send($g, $this->ada, 'In the group');
    $this->svc->react($first, $this->ada, '👍');
    $this->svc->typing($g, $this->ada);

    $r = $this->getJson('/api/v1/connect/poll?since=' . $cursor)->assertOk();
    expect($r->json('data.messages.0.body'))->toBe('In the group')
        ->and($r->json('data.messages.0.conversation_id'))->toBe($g->id)
        ->and($r->json('data.changed.0.reactions.0.emoji'))->toBe('👍')
        ->and($r->json('data.typing.' . $g->id))->toBe(['Ada'])
        ->and($r->json('data.presence.' . $this->ada->id . '.state'))->toBeString()
        ->and($r->json('data.unread_total'))->toBe(2)
        ->and($r->json('data.cursor'))->toBeGreaterThan($cursor);

    $this->postJson("/api/v1/connect/conversations/{$g->id}/typing")->assertOk();
});
