<?php

use App\Models\Connect\Message;
use App\Models\Drive\File;
use App\Models\Drive\Folder;
use App\Models\Drive\Link;
use App\Models\Drive\Version;
use App\Models\Projects\Project;
use App\Models\Projects\ProjectMember;
use App\Models\User;
use App\Services\Connect\ConnectService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function drUser(string $role = 'Staff'): User
{
    $u = User::factory()->create(['user_type' => 'staff']);
    $u->assignRole($role);
    $u->forceFill(['must_change_password' => false])->save();

    return $u;
}

beforeEach(function () {
    Storage::fake('local');
    $this->seed(DatabaseSeeder::class);
    $this->ada = drUser();
    $this->bola = drUser();
});

test('people upload into folders, keep versions, and only owners and sharers can open files', function () {
    $this->actingAs($this->ada)->post(route('drive.folders.store'), ['name' => 'Designs'])->assertRedirect();
    $folder = Folder::firstOrFail();
    expect($folder->owner_id)->toBe($this->ada->id)->and($folder->project_id)->toBeNull();

    $this->actingAs($this->ada)->postJson(route('drive.upload'), [
        'files' => [UploadedFile::fake()->create('logo.png', 20, 'image/png'), UploadedFile::fake()->create('brief.pdf', 40, 'application/pdf')],
        'parent_id' => $folder->id,
    ])->assertOk()->assertJsonCount(2, 'files');
    $file = File::where('name', 'brief.pdf')->firstOrFail();
    Storage::disk('local')->assertExists($file->path);

    $this->actingAs($this->ada)->get(route('drive.folder', $folder))->assertOk()->assertSee('logo.png')->assertSee('brief.pdf');
    $this->actingAs($this->bola)->get(route('drive.folder', $folder))->assertForbidden();
    $this->actingAs($this->bola)->get(route('drive.file.download', $file))->assertForbidden();

    // a new version keeps the old one, and the old one can be made current again
    $old = $file->path;
    $this->actingAs($this->ada)->post(route('drive.file.versions.store', $file), ['file' => UploadedFile::fake()->create('brief-v2.pdf', 50, 'application/pdf')])->assertSessionHasNoErrors();
    $file->refresh();
    expect($file->version)->toBe(2)->and($file->name)->toBe('brief.pdf')->and(Version::where('file_id', $file->id)->value('path'))->toBe($old);
    $v1 = Version::where('file_id', $file->id)->firstOrFail();
    $this->actingAs($this->ada)->post(route('drive.file.versions.restore', [$file, $v1]))->assertSessionHasNoErrors();
    expect($file->refresh()->version)->toBe(3)->and($file->size)->toBe($v1->size)->and(Version::where('file_id', $file->id)->count())->toBe(2);

    // sharing the folder gives access to what is inside; viewers can't change things
    $this->actingAs($this->ada)->post(route('drive.share.store', ['type' => 'folder', 'id' => $folder->id]), ['users' => [$this->bola->id], 'role' => 'viewer'])->assertSessionHasNoErrors();
    expect($this->bola->notifications()->count())->toBe(1);
    $this->actingAs($this->bola)->get(route('drive.shared'))->assertOk()->assertSee('Designs');
    $this->actingAs($this->bola)->get(route('drive.file', $file))->assertOk()->assertSee('View only');
    $this->actingAs($this->bola)->get(route('drive.file.download', $file))->assertOk();
    $this->actingAs($this->bola)->post(route('drive.rename', ['type' => 'file', 'id' => $file->id]), ['name' => 'x.pdf'])->assertSessionHasErrors('item');
    $this->actingAs($this->bola)->postJson(route('drive.upload'), ['files' => [UploadedFile::fake()->create('n.txt', 1)], 'parent_id' => $folder->id])->assertUnprocessable();

    // as an editor they can add files; the owner hears about it
    $this->actingAs($this->ada)->post(route('drive.share.store', ['type' => 'folder', 'id' => $folder->id]), ['users' => [$this->bola->id], 'role' => 'editor']);
    $this->actingAs($this->bola)->postJson(route('drive.upload'), ['files' => [UploadedFile::fake()->create('notes.txt', 1, 'text/plain')], 'parent_id' => $folder->id])->assertOk();
    expect(File::where('name', 'notes.txt')->value('owner_id'))->toBe($this->bola->id)
        ->and($this->ada->notifications()->count())->toBe(1);

    // search finds things in shared folders
    $this->actingAs($this->bola)->get(route('drive.search', ['q' => 'logo']))->assertOk()->assertSee('logo.png');
});

test('project members share a project space by role; clients and outsiders do not', function () {
    $pm = drUser('Project Manager');
    $project = Project::create(['name' => 'Acme app', 'code' => 'ACME', 'status' => 'active', 'created_by' => $pm->id]);
    ProjectMember::create(['project_id' => $project->id, 'user_id' => $this->ada->id, 'role' => 'member']);
    ProjectMember::create(['project_id' => $project->id, 'user_id' => $this->bola->id, 'role' => 'viewer']);
    $outsider = drUser();

    $this->actingAs($this->ada)->post(route('drive.folders.store'), ['name' => 'Specs', 'project_id' => $project->id])->assertRedirect();
    $specs = Folder::firstOrFail();
    expect($specs->project_id)->toBe($project->id);
    $this->actingAs($this->ada)->postJson(route('drive.upload'), ['files' => [UploadedFile::fake()->create('api.md', 2, 'text/markdown')], 'parent_id' => $specs->id])->assertOk();
    $file = File::firstOrFail();
    expect($file->project_id)->toBe($project->id);

    $this->actingAs($this->bola)->get(route('drive.project', $project))->assertOk()->assertSee('Specs');
    $this->actingAs($this->bola)->post(route('drive.folders.store'), ['name' => 'Nope', 'project_id' => $project->id])->assertSessionHasErrors('folder');
    $this->actingAs($outsider)->get(route('drive.project', $project))->assertForbidden();
    $this->actingAs($outsider)->get(route('drive.file', $file))->assertForbidden();

    // members trash; only managers (or the owner) delete for good
    $this->actingAs($this->ada)->post(route('drive.trash.item', ['type' => 'folder', 'id' => $specs->id]))->assertRedirect();
    $this->actingAs($this->bola)->get(route('drive.project', $project))->assertViewHas('folders', fn ($f) => $f->isEmpty());
    $this->actingAs($this->bola)->delete(route('drive.destroy', ['type' => 'folder', 'id' => $specs->id]))->assertSessionHasErrors('item');
    $this->actingAs($pm)->get(route('drive.trash'))->assertOk()->assertSee('Specs');
    $path = $file->path;
    $this->actingAs($pm)->delete(route('drive.destroy', ['type' => 'folder', 'id' => $specs->id]))->assertRedirect(route('drive.trash'));
    expect(Folder::count())->toBe(0)->and(File::count())->toBe(0);
    Storage::disk('local')->assertMissing($path);
});

test('moving a folder carries its contents into the new space, and never into itself', function () {
    $pm = drUser('Project Manager');
    $project = Project::create(['name' => 'Site', 'code' => 'SITE', 'status' => 'active', 'created_by' => $pm->id]);
    ProjectMember::create(['project_id' => $project->id, 'user_id' => $this->ada->id, 'role' => 'member']);

    $this->actingAs($this->ada);
    $this->post(route('drive.folders.store'), ['name' => 'Outer']);
    $outer = Folder::where('name', 'Outer')->firstOrFail();
    $this->post(route('drive.folders.store'), ['name' => 'Inner', 'parent_id' => $outer->id]);
    $inner = Folder::where('name', 'Inner')->firstOrFail();
    $this->postJson(route('drive.upload'), ['files' => [UploadedFile::fake()->create('a.txt', 1)], 'parent_id' => $inner->id])->assertOk();

    $this->post(route('drive.move.store', ['type' => 'folder', 'id' => $outer->id]), ['to' => 'folder:' . $inner->id])->assertSessionHasErrors('target');
    $this->post(route('drive.move.store', ['type' => 'folder', 'id' => $outer->id]), ['to' => 'project:' . $project->id])->assertSessionHasNoErrors();
    expect($inner->refresh()->project_id)->toBe($project->id)->and(File::firstOrFail()->project_id)->toBe($project->id);
    $this->get(route('drive.move', ['type' => 'folder', 'id' => $outer->id]))->assertOk()->assertSee('Site');
});

test('links let anyone view and download without signing in, until turned off', function () {
    $this->actingAs($this->ada);
    $this->post(route('drive.folders.store'), ['name' => 'Handover']);
    $folder = Folder::firstOrFail();
    $this->postJson(route('drive.upload'), ['files' => [UploadedFile::fake()->create('final.pdf', 10, 'application/pdf')], 'parent_id' => $folder->id])->assertOk();
    $this->postJson(route('drive.upload'), ['files' => [UploadedFile::fake()->create('private.txt', 1)]])->assertOk();
    $file = File::where('name', 'final.pdf')->firstOrFail();
    $private = File::where('name', 'private.txt')->firstOrFail();

    $this->post(route('drive.link.store', ['type' => 'folder', 'id' => $folder->id]), ['days' => 7])->assertSessionHasNoErrors();
    $link = Link::firstOrFail();
    auth()->logout();

    $this->get(route('drive.public', $link->token))->assertOk()->assertSee('final.pdf');
    $this->get(route('drive.public.file', [$link->token, $file]))->assertOk()->assertDownload('final.pdf');
    $this->get(route('drive.public.file', [$link->token, $private]))->assertNotFound();
    $this->get(route('drive.public.zip', $link->token))->assertOk();
    expect($link->refresh()->downloads)->toBe(2);

    $this->actingAs($this->ada)->delete(route('drive.link.destroy', ['type' => 'folder', 'id' => $folder->id]));
    auth()->logout();
    $this->get(route('drive.public', $link->token))->assertNotFound();
});

test('chat files show in Drive, can be saved, and Drive files can be sent to a chat', function () {
    $chat = app(ConnectService::class)->direct($this->ada, $this->bola);
    app(ConnectService::class)->send($chat, $this->bola, 'Here you go', UploadedFile::fake()->create('report.xlsx', 12));
    $message = Message::whereNotNull('attachment_path')->firstOrFail();

    $this->actingAs($this->ada)->get(route('drive.chats'))->assertOk()->assertSee('report.xlsx');
    $this->actingAs($this->ada)->post(route('drive.chats.save', $message))->assertSessionHasNoErrors();
    $saved = File::where('name', 'report.xlsx')->firstOrFail();
    expect($saved->owner_id)->toBe($this->ada->id)->and($saved->path)->not->toBe($message->attachment_path);

    $outsider = drUser();
    $this->actingAs($outsider)->post(route('drive.chats.save', $message))->assertSessionHasErrors('message');

    // sending it back gives Bola access and posts a link
    $this->actingAs($this->ada)->post(route('drive.send', ['type' => 'file', 'id' => $saved->id]), ['conversation_id' => $chat->id, 'note' => 'Updated'])
        ->assertRedirect(route('connect.show', $chat));
    expect(Message::latest('id')->first()->body)->toContain('report.xlsx')->toContain(route('drive.file', $saved));
    $this->actingAs($this->bola)->get(route('drive.file', $saved))->assertOk();
});

test('uploads stop at the storage quota, and the trash frees space', function () {
    config(['qudroid.drive.quota_mb' => 1]);
    $this->actingAs($this->ada);
    $this->postJson(route('drive.upload'), ['files' => [UploadedFile::fake()->create('a.bin', 700)]])->assertOk();
    $this->postJson(route('drive.upload'), ['files' => [UploadedFile::fake()->create('b.bin', 700)]])->assertUnprocessable()->assertJsonValidationErrors('file');

    $a = File::firstOrFail();
    $this->post(route('drive.trash.item', ['type' => 'file', 'id' => $a->id]));
    $this->get(route('drive.index'))->assertViewHas('files', fn ($f) => $f->isEmpty());
    $this->post(route('drive.restore', ['type' => 'file', 'id' => $a->id]))->assertSessionHasNoErrors();
    $this->get(route('drive.index'))->assertSee('a.bin');
    $this->post(route('drive.trash.item', ['type' => 'file', 'id' => $a->id]));
    $this->post(route('drive.trash.empty'))->assertSessionHasNoErrors();
    expect(File::count())->toBe(0);
    $this->postJson(route('drive.upload'), ['files' => [UploadedFile::fake()->create('b.bin', 700)]])->assertOk();

    // the other pages render
    foreach (['drive.recent', 'drive.starred', 'drive.shared', 'drive.trash', 'drive.chats'] as $r) $this->get(route($r))->assertOk();
    $this->get(route('drive.index', ['view' => 'grid']))->assertOk()->assertSee('b.bin');
    $b = File::firstOrFail();
    $this->get(route('drive.share', ['type' => 'file', 'id' => $b->id]))->assertOk()->assertSee($this->bola->name);
    $this->postJson(route('drive.upload'), ['files' => [UploadedFile::fake()->createWithContent('readme.txt', 'Hello <b>team</b>')]])->assertOk();
    $this->get(route('drive.file', File::where('name', 'readme.txt')->firstOrFail()))->assertOk()->assertSee('Hello &lt;b&gt;team&lt;/b&gt;', false);
});
