<?php

use App\Models\Connect\Message;
use App\Models\Connect\Note;
use App\Models\User;
use App\Services\Connect\ConnectService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function noteUser(string $name): User
{
    $u = User::factory()->create(['user_type' => 'staff', 'name' => $name]);
    $u->assignRole('Staff');
    $u->forceFill(['must_change_password' => false])->save();

    return $u;
}

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->ada = noteUser('Ada Obi');
    $this->ben = noteUser('Ben Eze');
    $this->out = noteUser('Dee Out');
    $this->svc = app(ConnectService::class);
    $this->group = $this->svc->group($this->ada, 'Design', [$this->ben->id]);
});

test('people in a chat write shared notes; saves never overwrite each other silently', function () {
    $this->actingAs($this->ada)->post(route('connect.notes.store', $this->group), ['title' => 'Launch plan', 'body' => "## Goals\n- [ ] Ship"])->assertRedirect();
    $note = Note::first();
    expect(Message::where('conversation_id', $this->group->id)->latest('id')->value('body'))->toBe('Ada Obi added a note: Launch plan');

    // shown as safe HTML: raw HTML and javascript: links are dropped
    $this->actingAs($this->ada)->putJson(route('connect.notes.update', $note), ['title' => 'Launch plan', 'body' => "## Goals\n<script>alert(1)</script>\n[x](javascript:alert(1)) **bold**", 'version' => 1])
        ->assertOk()->assertJsonPath('version', 2);
    $this->actingAs($this->ben)->get(route('connect.notes.show', $note))->assertOk()
        ->assertSee('<h2>Goals</h2>', false)->assertSee('<strong>bold</strong>', false)->assertDontSee('<script>alert', false)->assertDontSee('javascript:alert', false);

    // Ben edits from version 2, Ada saves from the old version 1: conflict, nothing lost
    $this->actingAs($this->ben)->putJson(route('connect.notes.update', $note), ['title' => 'Launch plan', 'body' => 'Ben text', 'version' => 2])->assertOk()->assertJsonPath('version', 3);
    $this->actingAs($this->ada)->putJson(route('connect.notes.update', $note), ['title' => 'Launch plan', 'body' => 'Ada text', 'version' => 2])
        ->assertStatus(409)->assertJsonPath('conflict', true)->assertJsonPath('body', 'Ben text')->assertJsonPath('version', 3);
    expect($note->fresh()->body)->toBe('Ben text');

    // outsiders can't see or edit
    $this->actingAs($this->out)->get(route('connect.notes.show', $note))->assertNotFound();
    $this->actingAs($this->out)->putJson(route('connect.notes.update', $note), ['title' => 'x', 'body' => 'x', 'version' => 3])->assertStatus(422);

    // state: version, and who is editing
    $this->actingAs($this->ben)->getJson(route('connect.notes.state', [$note, 'editing' => 1, 'version' => 3]))->assertOk();
    $this->actingAs($this->ada)->getJson(route('connect.notes.state', [$note, 'version' => 1]))->assertJsonPath('editing', ['Ben'])->assertJsonPath('version', 3)->assertJsonPath('title', 'Launch plan');
});

test('history keeps versions, restores one, and only the author or an admin deletes', function () {
    $note = app(\App\Services\Connect\NoteService::class)->create($this->group, $this->ada, 'Checklist', 'one');
    $this->actingAs($this->ben)->putJson(route('connect.notes.update', $note), ['title' => 'Checklist', 'body' => 'two', 'version' => 1])->assertOk();
    $this->actingAs($this->ada)->putJson(route('connect.notes.update', $note), ['title' => 'Checklist', 'body' => 'three', 'version' => 2])->assertOk();
    expect($note->revisions()->count())->toBe(3);

    $v1 = $note->revisions()->where('version', 1)->first();
    $this->actingAs($this->ben)->get(route('connect.notes.history', [$note, 'v' => 1]))->assertOk()->assertSee('Restore this version');
    $this->actingAs($this->ben)->post(route('connect.notes.restore', [$note, $v1]))->assertRedirect();
    expect($note->fresh()->body)->toBe('one')->and($note->fresh()->version)->toBe(4);

    $this->actingAs($this->ben)->delete(route('connect.notes.destroy', $note))->assertSessionHasErrors();   // Ben is not the author or an admin
    expect(Note::count())->toBe(1);
    $this->actingAs($this->ada)->delete(route('connect.notes.destroy', $note))->assertRedirect(route('connect.notes.index', $this->group));
    expect(Note::count())->toBe(0);
});

test('the files tab lists what was shared in the chat', function () {
    Storage::fake('local');
    $this->svc->send($this->group, $this->ben, 'Here', UploadedFile::fake()->create('brief.pdf', 20, 'application/pdf'));
    $this->actingAs($this->ada)->get(route('connect.notes.index', [$this->group, 'tab' => 'files']))->assertOk()->assertSee('brief.pdf');
    $this->actingAs($this->ada)->postJson(route('connect.notes.preview'), ['body' => '**hi**'])->assertJsonPath('html', "<p><strong>hi</strong></p>\n");
});
