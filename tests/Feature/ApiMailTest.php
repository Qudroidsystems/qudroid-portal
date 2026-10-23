<?php

use App\Models\Mail\MailAccount;
use App\Models\Mail\MailMessage;
use App\Models\User;
use App\Services\Mail\FakeGateway;
use App\Services\Mail\Mailboxes;
use App\Services\Mail\MailSync;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function apiMailUser(string $name, string $role = 'Staff'): User
{
    $u = User::factory()->create(['user_type' => 'staff', 'name' => $name]);
    $u->assignRole($role);
    $u->forceFill(['must_change_password' => false])->save();

    return $u;
}

function apiMailBox(User $u, string $email): MailAccount
{
    $a = MailAccount::create(['email' => $email, 'name' => $u->name, 'kind' => 'personal', 'imap_host' => 'imap.test', 'smtp_host' => 'smtp.test', 'username' => $email, 'password' => 'pw']);
    $a->users()->attach($u->id, ['role' => 'owner', 'is_default' => true]);
    FakeGateway::deliver($email, "Subject: x\r\n\r\n", 'Drafts');
    unset(FakeGateway::$boxes[$email]['Drafts']['messages'][1]);

    return $a;
}

beforeEach(function () {
    Mailboxes::fake();
    Storage::fake('local');
    $this->seed(DatabaseSeeder::class);
    $this->ada = apiMailUser('Ada Obi');
    $this->ben = apiMailUser('Ben Eze');
    $this->box = apiMailBox($this->ada, 'ada@qudroid.test');
    apiMailBox($this->ben, 'ben@qudroid.test');
});

test('the app lists mailboxes and conversations, reads one and replies', function () {
    $html = '<p>Hello <b>Ada</b></p><img src="https://t.test/p.gif"><script>x()</script>';
    FakeGateway::deliver('ada@qudroid.test', "From: Ben Eze <ben@qudroid.test>\r\nTo: ada@qudroid.test\r\nSubject: Report\r\nMessage-ID: <r1@qudroid.test>\r\nMIME-Version: 1.0\r\nContent-Type: text/html; charset=utf-8\r\n\r\n{$html}\r\n");
    app(MailSync::class)->sync($this->box);
    Sanctum::actingAs($this->ada);

    $this->getJson('/api/v1/mail/accounts')->assertOk()->assertJsonPath('data.0.email', 'ada@qudroid.test')->assertJsonPath('data.0.counts.inbox', 1)
        ->assertJsonPath('data.0.is_default', true);
    $key = $this->getJson("/api/v1/mail/{$this->box->id}/threads?view=inbox")->assertOk()->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.subject', 'Report')->assertJsonPath('data.0.unread', true)->json('data.0.key');
    $this->getJson("/api/v1/mail/{$this->box->id}/threads?view=nope")->assertStatus(422);

    $t = $this->getJson("/api/v1/mail/{$this->box->id}/threads/{$key}")->assertOk()->json('data');
    expect($t['messages'][0]['body_html'])->toContain('<b>Ada</b>')->not->toContain('<script')
        ->and($t['messages'][0]['has_remote_images'])->toBeTrue()
        ->and(MailMessage::first()->seen)->toBeTrue();

    $pre = $this->getJson("/api/v1/mail/{$this->box->id}/compose?mode=reply&message={$t['messages'][0]['id']}")->assertOk()->json('data');
    expect($pre['to'])->toBe('Ben Eze <ben@qudroid.test>');
    $this->post("/api/v1/mail/{$this->box->id}/send", ['mode' => 'reply', 'source_message_id' => $pre['source_message_id'], 'to' => $pre['to'],
        'subject' => $pre['subject'], 'body_html' => '<p>Thanks!</p>', 'files' => [UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')]], ['Accept' => 'application/json'])
        ->assertOk()->assertJsonPath('message', 'Message sent.')->assertJsonPath('data.scheduled', false);
    expect(Mailboxes::$sent[0]['raw'])->toContain('In-Reply-To: <r1@qudroid.test>')->toContain('a.pdf');

    $this->postJson("/api/v1/mail/{$this->box->id}/act", ['action' => 'archive', 'threads' => [$key], 'view' => 'inbox'])->assertOk()
        ->assertJsonPath('message', 'Conversation archived.')->assertJsonPath('data.counts.inbox', 0);
    $this->getJson("/api/v1/mail/{$this->box->id}/poll")->assertOk()->assertJsonStructure(['data' => ['counts', 'latest', 'status']]);
});

test('the app saves drafts, schedules mail and can only reach its own mailboxes', function () {
    Sanctum::actingAs($this->ada);
    $d = $this->postJson("/api/v1/mail/{$this->box->id}/drafts", ['to' => 'x@ext.test', 'subject' => 'Later', 'body_html' => '<p>Hi</p>'])->assertOk()->json('data.draft_id');
    $this->getJson("/api/v1/mail/{$this->box->id}/drafts")->assertOk()->assertJsonPath('data.0.subject', 'Later');
    $this->postJson("/api/v1/mail/{$this->box->id}/send", ['draft_id' => $d, 'to' => 'x@ext.test', 'subject' => 'Later', 'body_html' => '<p>Hi</p>',
        'send_at' => now()->addDay()->toIso8601String()])->assertOk()->assertJsonPath('data.scheduled', true);
    $this->getJson("/api/v1/mail/{$this->box->id}/drafts?status=scheduled")->assertOk()->assertJsonPath('meta.total', 1);
    $this->deleteJson("/api/v1/mail/{$this->box->id}/drafts/{$d}")->assertOk();
    $this->postJson("/api/v1/mail/{$this->box->id}/send", ['to' => 'bad', 'body_html' => 'x'])->assertStatus(422)->assertJsonPath('success', false);

    $benBox = MailAccount::where('email', 'ben@qudroid.test')->first();
    $this->getJson("/api/v1/mail/{$benBox->id}/threads")->assertForbidden()->assertJsonPath('success', false);
    $this->getJson('/api/v1/mail/accounts')->assertJsonCount(1, 'data');

    Sanctum::actingAs(apiMailUser('Sam Student', 'Student'));
    $this->getJson('/api/v1/mail/accounts')->assertForbidden();
});
