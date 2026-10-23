<?php

use App\Models\Connect\Message;
use App\Models\Connect\PollVote;
use App\Models\User;
use App\Services\Connect\ConnectService;
use App\Services\Settings\Settings;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function pgUser(string $name, string $role = 'Staff'): User
{
    $u = User::factory()->create(['user_type' => 'staff', 'name' => $name]);
    $u->assignRole($role);
    $u->forceFill(['must_change_password' => false])->save();

    return $u;
}

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->ada = pgUser('Ada Obi');
    $this->ben = pgUser('Ben Eze');
    $this->cy = pgUser('Cy Uche');
    $this->out = pgUser('Dee Out');
    $this->svc = app(ConnectService::class);
    $this->group = $this->svc->group($this->ada, 'Design', [$this->ben->id, $this->cy->id]);
});

test('a poll is posted, voted on, changed and closed', function () {
    $this->actingAs($this->ada)->postJson(route('connect.send', $this->group), [
        'poll_question' => 'Team lunch?', 'poll_options' => ['Rice', 'Pizza', '', 'Rice'],
    ])->assertOk();
    $poll = Message::latest('id')->first();
    expect($poll->kind)->toBe('poll')->and($poll->meta['options'])->toBe(['Rice', 'Pizza'])->and($poll->preview())->toBe('Poll: Team lunch?');

    $this->actingAs($this->ben)->postJson(route('connect.messages.vote', $poll), ['options' => [1]])->assertOk();
    $this->actingAs($this->cy)->postJson(route('connect.messages.vote', $poll), ['options' => [1]])->assertOk();
    // single choice: two options are refused; changing the vote replaces it
    $this->actingAs($this->ben)->postJson(route('connect.messages.vote', $poll), ['options' => [0, 1]])->assertStatus(422);
    $this->actingAs($this->ben)->postJson(route('connect.messages.vote', $poll), ['options' => [0]])->assertOk();
    $this->actingAs($this->out)->postJson(route('connect.messages.vote', $poll), ['options' => [0]])->assertStatus(422);

    $r = $poll->fresh()->pollResults($this->ben);
    expect($r['voters'])->toBe(2)->and($r['options'][0]['count'])->toBe(1)->and($r['options'][0]['mine'])->toBeTrue()
        ->and($r['options'][1]['names'])->toBe(['Cy Uche'])->and($r['options'][1]['pct'])->toBe(50);

    // the chat shows it, with who voted for what
    $this->actingAs($this->cy)->get(route('connect.show', $this->group))->assertOk()->assertSee('Team lunch?')->assertSee('2 people voted')->assertSee('title="Ben Eze"', false);

    // only the asker (or an admin) closes it; then no more votes, and it can't be edited
    $this->actingAs($this->ben)->postJson(route('connect.messages.close-poll', $poll))->assertStatus(422);
    $this->actingAs($this->ada)->postJson(route('connect.messages.close-poll', $poll))->assertOk();
    $this->actingAs($this->cy)->postJson(route('connect.messages.vote', $poll), ['options' => [0]])->assertStatus(422);
    $this->actingAs($this->ada)->putJson(route('connect.messages.edit', $poll), ['body' => 'x'])->assertStatus(422);
    expect(PollVote::where('message_id', $poll->id)->count())->toBe(2);

    // the empty list takes a vote back on an open multi-choice poll
    $multi = $this->svc->sendPoll($this->group, $this->ada, 'Which days?', ['Mon', 'Tue', 'Wed'], true);
    $this->actingAs($this->ben)->postJson(route('connect.messages.vote', $multi), ['options' => [0, 2]])->assertOk();
    $this->actingAs($this->ben)->postJson(route('connect.messages.vote', $multi), ['options' => []])->assertOk();
    expect(PollVote::where('message_id', $multi->id)->count())->toBe(0);

    // bad polls are refused
    $this->actingAs($this->ada)->postJson(route('connect.send', $this->group), ['poll_question' => 'Hmm', 'poll_options' => ['Only one']])->assertStatus(422);
});

test('GIFs come from GIPHY through the portal and only GIPHY links are accepted', function () {
    // with no key, the picker says so
    $this->actingAs($this->ada)->getJson(route('connect.gifs'))->assertOk()->assertJson(['enabled' => false, 'results' => []]);

    $admin = pgUser('Admin', 'Super Administrator');
    $this->actingAs($admin)->post(route('portal-settings.integrations.save'), ['giphy_api_key' => 'abc123KEY'])->assertSessionHasNoErrors();
    expect(app(Settings::class)->giphyKey())->toBe('abc123KEY')
        ->and(\App\Models\Setting::where('key', 'giphy.api_key')->value('value'))->not->toBe('abc123KEY');   // stored encrypted
    $this->actingAs($admin)->get(route('portal-settings.integrations'))->assertOk()->assertSee('••••3KEY')->assertDontSee('abc123KEY');

    Http::fake(['api.giphy.com/*' => Http::response(['data' => [
        ['id' => 'x1', 'title' => 'Happy dance', 'images' => ['fixed_height' => ['url' => 'https://media2.giphy.com/media/x1/200.gif?cid=abc', 'width' => '267', 'height' => '200'], 'fixed_height_small' => ['url' => 'https://media2.giphy.com/media/x1/100.gif?cid=abc']]],
        ['id' => 'bad', 'title' => 'Elsewhere', 'images' => ['fixed_height' => ['url' => 'https://evil.example.com/a.gif']]],
    ]])]);
    $res = $this->actingAs($this->ada)->getJson(route('connect.gifs', ['q' => 'dance']))->assertOk()->json();
    expect($res['enabled'])->toBeTrue()->and($res['results'])->toHaveCount(1)->and($res['results'][0]['url'])->toBe('https://media2.giphy.com/media/x1/200.gif');
    Http::assertSent(fn ($r) => str_contains($r->url(), 'api_key=abc123KEY') && str_contains($r->url(), 'q=dance'));

    $this->actingAs($this->ada)->postJson(route('connect.send', $this->group), ['gif_url' => 'https://evil.example.com/a.gif'])->assertStatus(422);
    $this->actingAs($this->ada)->postJson(route('connect.send', $this->group), ['gif_url' => 'https://media2.giphy.com/media/x1/200.gif', 'gif_width' => 267, 'gif_height' => 200, 'gif_title' => 'Happy dance'])->assertOk();
    $gif = Message::latest('id')->first();
    expect($gif->isGif())->toBeTrue()->and($gif->preview())->toBe('GIF: Happy dance');
    $this->actingAs($this->ben)->get(route('connect.show', $this->group))->assertSee('https://media2.giphy.com/media/x1/200.gif', false);
});

test('reactions take any emoji from the picker and show who reacted', function () {
    $m = $this->svc->send($this->group, $this->ada, 'Shipped!');
    $this->actingAs($this->ben)->postJson(route('connect.messages.react', $m), ['emoji' => '🚀'])->assertOk();
    $this->actingAs($this->cy)->postJson(route('connect.messages.react', $m), ['emoji' => '🚀'])->assertOk();
    $this->actingAs($this->cy)->postJson(route('connect.messages.react', $m), ['emoji' => '<b>'])->assertStatus(422);
    $this->actingAs($this->cy)->get(route('connect.show', $this->group))->assertSee('Ben Eze, You reacted with 🚀');
});
