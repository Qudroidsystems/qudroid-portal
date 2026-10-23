<?php

use App\Models\Drive\File;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function apiDriveUser(string $name): User
{
    $u = User::factory()->create(['user_type' => 'staff', 'name' => $name]);
    $u->assignRole('Staff');
    $u->forceFill(['must_change_password' => false])->save();

    return $u;
}

beforeEach(function () {
    Storage::fake('local');
    $this->seed(DatabaseSeeder::class);
    $this->ada = apiDriveUser('Ada Obi');
    $this->ben = apiDriveUser('Ben Eze');
});

test('the app browses, uploads, renames, moves, shares, links and trashes Drive items', function () {
    Sanctum::actingAs($this->ada);
    $folder = $this->postJson('/api/v1/drive/folders', ['name' => 'Designs'])->assertCreated()->assertJsonPath('data.type', 'folder')->json('data.id');
    $this->post('/api/v1/drive/files', ['folder_id' => $folder, 'files' => [UploadedFile::fake()->image('logo.png'), UploadedFile::fake()->createWithContent('notes.txt', 'hello')]], ['Accept' => 'application/json'])
        ->assertCreated()->assertJsonCount(2, 'data');
    $logo = File::where('name', 'logo.png')->value('id');
    $notes = File::where('name', 'notes.txt')->value('id');

    $this->getJson('/api/v1/drive')->assertOk()->assertJsonPath('data.folders.0.name', 'Designs')->assertJsonStructure(['meta' => ['usage', 'quota']]);
    $this->getJson("/api/v1/drive?folder_id={$folder}")->assertOk()->assertJsonCount(2, 'data.files')
        ->assertJsonPath('data.trail.0.name', 'Designs')->assertJsonPath('data.can_add', true)->assertJsonPath('data.files.0.thumb_url', fn ($v) => $v !== null);
    $this->getJson('/api/v1/drive?q=note')->assertOk()->assertJsonPath('data.files.0.name', 'notes.txt');
    $this->get("/api/v1/drive/files/{$notes}/download")->assertOk();

    // rename and move to My Drive
    $this->putJson("/api/v1/drive/items/file/{$notes}", ['name' => 'readme.txt', 'to' => 'mine'])->assertOk()->assertJsonPath('data.name', 'readme.txt')->assertJsonPath('data.folder_id', null);

    // Ben sees nothing until it is shared
    Sanctum::actingAs($this->ben);
    $this->getJson("/api/v1/drive/files/{$logo}")->assertNotFound();
    Sanctum::actingAs($this->ada);
    $this->postJson("/api/v1/drive/items/folder/{$folder}/share", ['user_ids' => [$this->ben->id], 'permission' => 'viewer'])->assertOk()->assertJsonPath('data.shared_with', ['Ben Eze']);
    Sanctum::actingAs($this->ben);
    $this->getJson('/api/v1/drive/shared-with-me')->assertOk()->assertJsonPath('data.folders.0.name', 'Designs');
    $this->getJson("/api/v1/drive/files/{$logo}")->assertOk()->assertJsonPath('data.level', 'viewer');
    $this->putJson("/api/v1/drive/items/file/{$logo}", ['name' => 'mine.png'])->assertUnprocessable();
    $this->postJson("/api/v1/drive/items/file/{$logo}/star")->assertJsonPath('data.starred', true);
    $this->getJson('/api/v1/drive/starred')->assertJsonPath('data.files.0.id', $logo);

    // public link, then trash and restore
    Sanctum::actingAs($this->ada);
    $url = $this->postJson("/api/v1/drive/items/file/{$logo}/link", ['days' => 7])->assertCreated()->json('data.url');
    expect($url)->toContain('/s/');
    $this->getJson("/api/v1/drive/files/{$logo}")->assertJsonPath('data.link.url', $url);
    $this->deleteJson("/api/v1/drive/items/file/{$logo}/link")->assertOk();
    $this->deleteJson("/api/v1/drive/items/file/{$logo}")->assertOk();
    $this->getJson('/api/v1/drive/trash')->assertJsonPath('data.files.0.id', $logo);
    $this->postJson("/api/v1/drive/items/file/{$logo}/restore")->assertOk()->assertJsonPath('data.trashed', false);
    $this->deleteJson("/api/v1/drive/items/file/{$logo}")->assertOk();
    $this->deleteJson("/api/v1/drive/items/file/{$logo}?forever=1")->assertOk();
    expect(File::find($logo))->toBeNull();
});
