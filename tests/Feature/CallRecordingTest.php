<?php

use App\Models\Connect\Call;
use App\Models\Connect\CallRecording;
use App\Models\Connect\Message;
use App\Models\Connect\Note;
use App\Models\User;
use App\Services\Connect\ConnectService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function recUser(string $name): User
{
    $u = User::factory()->create(['user_type' => 'staff', 'name' => $name]);
    $u->assignRole('Staff');
    $u->forceFill(['must_change_password' => false])->save();

    return $u;
}

beforeEach(function () {
    Storage::fake('local');
    $this->seed(DatabaseSeeder::class);
    $this->ada = recUser('Ada Obi');
    $this->ben = recUser('Ben Eze');
    $this->cy = recUser('Cy Uche');
    $this->chat = app(ConnectService::class)->group($this->ada, 'Design', [$this->ben->id]);
    $this->actingAs($this->ada)->post(route('connect.calls.start', $this->chat), ['kind' => 'video']);
    $this->call = Call::firstOrFail();
    $this->actingAs($this->ada)->postJson(route('connect.calls.join', $this->call))->assertOk();
    $this->actingAs($this->ben)->postJson(route('connect.calls.join', $this->call))->assertOk();
});

test('a recording is uploaded in order, seen by everyone in the call, and posted to the chat when it stops', function () {
    $start = $this->actingAs($this->ada)->postJson(route('connect.recordings.start', $this->call), ['mime' => 'video/webm;codecs=vp8,opus'])->assertOk();
    $rec = CallRecording::findOrFail($start->json('id'));
    expect(Message::where('is_system', true)->latest('id')->value('body'))->toBe('Ada Obi started recording');

    // one at a time, and only for people in the call
    $this->actingAs($this->ben)->postJson(route('connect.recordings.start', $this->call), ['mime' => 'video/webm'])->assertUnprocessable()->assertJsonValidationErrors('call');
    $this->actingAs($this->cy)->postJson(route('connect.recordings.start', $this->call), ['mime' => 'video/webm'])->assertForbidden();

    // Ben's call page shows it is being recorded
    $this->actingAs($this->ben)->getJson(route('connect.calls.poll', $this->call))->assertJsonPath('recording.by', 'Ada Obi')->assertJsonPath('recording.mine', false);

    $piece = fn ($s) => UploadedFile::fake()->createWithContent('piece', $s);
    $this->actingAs($this->ada)->post(route('connect.recordings.chunk', $rec), ['seq' => 0, 'piece' => $piece('AAAA')])->assertOk()->assertJsonPath('next', 1);
    $this->actingAs($this->ada)->post(route('connect.recordings.chunk', $rec), ['seq' => 0, 'piece' => $piece('AAAA')])->assertOk()->assertJsonPath('next', 1);   // a retry
    $this->actingAs($this->ada)->postJson(route('connect.recordings.chunk', $rec), ['seq' => 3, 'piece' => $piece('XX')])->assertJsonValidationErrors('seq');
    $this->actingAs($this->ben)->postJson(route('connect.recordings.chunk', $rec), ['seq' => 1, 'piece' => $piece('XX')])->assertJsonValidationErrors('recording');
    $this->actingAs($this->ada)->post(route('connect.recordings.chunk', $rec), ['seq' => 1, 'piece' => $piece('BB')])->assertOk();
    expect(Storage::disk('local')->get($rec->fresh()->path))->toBe('AAAABB');

    $this->actingAs($this->ada)->postJson(route('connect.recordings.stop', $rec), ['seconds' => 95])->assertOk()->assertJsonPath('status', 'ready');
    $msg = Message::where('kind', 'recording')->firstOrFail();
    expect($msg->meta['recording_id'])->toBe($rec->id)->and($msg->preview())->toBe('Call recording · 1 min 35 sec');
    $this->actingAs($this->ben)->get(route('connect.show', $this->chat))->assertSee('Call recording');

    // people in the chat can play it; others cannot
    $this->actingAs($this->ben)->get(route('connect.recordings.show', $rec))->assertOk()->assertHeader('Content-Type', 'video/webm');
    $this->actingAs($this->cy)->get(route('connect.recordings.show', $rec))->assertNotFound();
    $this->actingAs($this->ben)->get(route('connect.calls.recap', $this->call))->assertOk()->assertSee('1 min 35 sec');

    // Ben (not an admin, not the recorder) cannot delete; Ada can
    $this->actingAs($this->ben)->delete(route('connect.recordings.destroy', $rec))->assertSessionHasErrors('recording');
    $this->actingAs($this->ada)->delete(route('connect.recordings.destroy', $rec))->assertRedirect();
    expect(CallRecording::count())->toBe(0)->and(Storage::disk('local')->exists($rec->path))->toBeFalse()
        ->and($msg->fresh()->isDeleted())->toBeTrue();
});

test('a recording whose recorder leaves is kept with what was uploaded; an empty one is dropped', function () {
    $id = $this->actingAs($this->ada)->postJson(route('connect.recordings.start', $this->call), ['mime' => 'audio/webm'])->json('id');
    $this->actingAs($this->ada)->post(route('connect.recordings.chunk', $id), ['seq' => 0, 'piece' => UploadedFile::fake()->createWithContent('p', 'abc')]);
    $this->actingAs($this->ada)->postJson(route('connect.calls.leave', $this->call));
    expect(CallRecording::find($id)->status)->toBe('ready');

    // Ben records nothing, then the call ends
    $id2 = $this->actingAs($this->ben)->postJson(route('connect.recordings.start', $this->call), ['mime' => 'audio/webm'])->json('id');
    $this->actingAs($this->ben)->postJson(route('connect.calls.leave', $this->call));
    expect($this->call->fresh()->ended_at)->not->toBeNull()->and(CallRecording::find($id2)->status)->toBe('failed');
});

test('the transcript writes down each person, shows as captions, and becomes shared notes', function () {
    // nothing is kept until someone turns it on
    $this->actingAs($this->ben)->postJson(route('connect.calls.say', $this->call), ['text' => 'secret'])->assertJsonPath('ok', false);
    $this->actingAs($this->ada)->postJson(route('connect.calls.transcribe', $this->call), ['on' => true])->assertJsonPath('transcribing', true);
    $this->actingAs($this->ada)->postJson(route('connect.calls.say', $this->call), ['text' => '  Let us ship on   Friday '])->assertJsonPath('ok', true);
    $this->actingAs($this->ben)->postJson(route('connect.calls.say', $this->call), ['text' => 'I will write the release notes'])->assertJsonPath('ok', true);
    $this->actingAs($this->cy)->postJson(route('connect.calls.say', $this->call), ['text' => 'x'])->assertForbidden();

    $poll = $this->actingAs($this->ben)->getJson(route('connect.calls.poll', $this->call))->assertJsonPath('transcribing', true);
    expect(collect($poll->json('captions'))->pluck('text')->all())->toBe(['Let us ship on Friday', 'I will write the release notes']);
    $first = $poll->json('captions.0.id');
    $this->actingAs($this->ben)->getJson(route('connect.calls.poll', $this->call) . '?cap=' . $first)->assertJsonCount(1, 'captions');

    $txt = $this->actingAs($this->ben)->get(route('connect.calls.transcript', $this->call))->assertOk()->getContent();
    expect($txt)->toContain('Ada Obi: Let us ship on Friday')->toContain('Ben Eze: I will write the release notes');

    $this->actingAs($this->ben)->post(route('connect.calls.note', $this->call))->assertRedirect();
    $note = Note::firstOrFail();
    expect($note->conversation_id)->toBe($this->chat->id)
        ->and($note->body)->toContain('**Who came:** Ada Obi, Ben Eze')->toContain('## Action items')->toContain('**Ben Eze**')
        ->and($this->call->fresh()->note_id)->toBe($note->id);
    // a second click opens the same note
    $this->actingAs($this->ada)->post(route('connect.calls.note', $this->call))->assertRedirect(route('connect.notes.edit', $note));
    expect(Note::count())->toBe(1);
    $this->actingAs($this->cy)->get(route('connect.calls.recap', $this->call))->assertNotFound();
    $this->actingAs($this->ada)->get(route('connect.calls.recap', $this->call))->assertOk()->assertSee('I will write the release notes')->assertSee('Open notes');
});
