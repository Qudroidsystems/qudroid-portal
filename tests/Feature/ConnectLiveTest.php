<?php

use App\Models\Connect\Message;
use App\Models\User;
use App\Services\Connect\ConnectService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function liveUser(string $name): User
{
    $u = User::factory()->create(['user_type' => 'staff', 'name' => $name]);
    $u->assignRole('Staff');
    $u->forceFill(['must_change_password' => false])->save();

    return $u;
}

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->ada = liveUser('Ada Obi');
    $this->ben = liveUser('Ben Eze');
    $this->cy = liveUser('Cy Uche');
    $this->svc = app(ConnectService::class);
    $this->group = $this->svc->group($this->ada, 'Design', [$this->ben->id, $this->cy->id]);
});

test('threads keep replies out of the main timeline and tell the people in them', function () {
    $root = $this->svc->send($this->group, $this->ada, 'Logo options?');
    $this->ben->notifications()->delete();
    $this->cy->notifications()->delete();

    $this->actingAs($this->ben)->postJson(route('connect.send', $this->group), ['body' => 'Option B', 'thread_id' => $root->id])->assertOk();
    $reply = Message::latest('id')->first();
    expect($reply->thread_id)->toBe($root->id)->and($root->refresh()->reply_count)->toBe(1)
        ->and($this->group->refresh()->last_message_id)->toBe($root->id)
        ->and($this->ada->notifications()->count())->toBe(1)   // the thread starter
        ->and($this->cy->notifications()->count())->toBe(0);   // not in the thread

    // replies don't count as unread in the conversation list (only the first message does)
    expect($this->svc->unreadTotal($this->cy))->toBe(1);

    // the timeline shows the count, not the reply; the thread shows both
    $this->actingAs($this->cy)->get(route('connect.show', $this->group))->assertOk()->assertSee('1 reply')->assertDontSee('Option B');
    $this->actingAs($this->cy)->getJson(route('connect.messages.thread', $root))->assertOk()
        ->assertJsonPath('last_id', $reply->id)->assertSee('Option B');

    // polling an open thread returns new replies
    $this->svc->send($this->group, $this->cy, 'B for me too', threadId: $root->id);
    $this->actingAs($this->ada)->getJson(route('connect.poll', $this->group) . '?after=' . $root->id . '&thread=' . $root->id . '&thread_after=' . $reply->id)
        ->assertOk()->assertJsonPath('thread.id', $root->id)->assertSee('B for me too');
    expect($this->ben->notifications()->count())->toBe(1);

    // a thread can't start from a reply or another conversation's message
    $this->actingAs($this->ada)->postJson(route('connect.send', $this->group), ['body' => 'x', 'thread_id' => $reply->id])->assertUnprocessable();
});

test('@mentions reach people even in muted chats and channels, and are highlighted', function () {
    $this->svc->mute($this->group, $this->cy, true);
    $m = $this->svc->send($this->group, $this->ada, 'Can @Cy Uche check this? And @Ben too');
    expect($m->mentions)->toEqualCanonicalizing([$this->cy->id, $this->ben->id])
        ->and($this->cy->notifications()->first()->data['title'])->toContain('mentioned you');

    $html = $m->html($this->cy);
    expect($html)->toContain('<span class="cx-mention me">@Cy Uche</span>')->toContain('<span class="cx-mention">@Ben</span>');

    $channel = $this->svc->channel($this->ada, 'general', null);
    $this->svc->join($channel, $this->ben);
    $this->ben->notifications()->delete();
    $this->svc->send($channel, $this->ada, 'Plain channel message');
    expect($this->ben->notifications()->count())->toBe(0);
    $all = $this->svc->send($channel, $this->ada, 'Heads up @everyone');
    expect($all->mentions)->toBe(['all'])->and($this->ben->notifications()->count())->toBe(1);

    // names are escaped before highlighting
    $evil = liveUser('<b>Eve</b>');
    $this->svc->add($this->group, $this->ada, [$evil->id]);
    $x = $this->svc->send($this->group, $this->ada, 'hi @<b>Eve</b>');
    expect($x->html())->not->toContain('<b>Eve</b>');
});

test('pins, saved messages and search', function () {
    $m = $this->svc->send($this->group, $this->ada, 'The client portal launch is on Friday');
    $this->actingAs($this->ben)->postJson(route('connect.messages.pin', $m))->assertJsonPath('pinned', true);
    $this->actingAs($this->ben)->get(route('connect.show', $this->group))->assertSee('Pinned');

    $this->actingAs($this->cy)->postJson(route('connect.messages.save', $m))->assertJsonPath('saved', true);
    $this->actingAs($this->cy)->get(route('connect.saved'))->assertOk()->assertSee('client portal launch');
    $this->actingAs($this->ben)->get(route('connect.saved'))->assertOk()->assertDontSee('client portal launch');

    $this->actingAs($this->cy)->get(route('connect.search', ['q' => 'portal launch']))->assertOk()->assertSee('client portal launch');
    $outsider = liveUser('Out Sider');
    $this->actingAs($outsider)->get(route('connect.search', ['q' => 'portal launch']))->assertOk()->assertDontSee('client portal launch');
    $this->actingAs($outsider)->postJson(route('connect.messages.save', $m))->assertUnprocessable();

    // jumping to a message from a result
    $this->actingAs($this->cy)->get(route('connect.show', [$this->group, 'around' => $m->id]))->assertOk()->assertSee('id="m' . $m->id . '"', false);
});

test('polling reports who is typing, presence and how far the other person has read', function () {
    $direct = $this->svc->direct($this->ada, $this->ben);
    $m = $this->svc->send($direct, $this->ada, 'Hi Ben');

    $this->actingAs($this->ben)->postJson(route('connect.typing', $direct))->assertOk();
    $this->actingAs($this->ada)->getJson(route('connect.poll', $direct) . '?after=' . $m->id)
        ->assertJsonPath('typing', ['Ben'])->assertJsonPath('seen', 0);

    $this->ben->forceFill(['last_seen_at' => now()])->save();
    $this->actingAs($this->ben)->get(route('connect.show', $direct))->assertOk();
    $res = $this->actingAs($this->ada)->getJson(route('connect.poll', $direct) . '?after=' . $m->id)->assertOk();
    expect($res->json('seen'))->toBe($m->id)->and($res->json('presence'))->toBe('Online');

    // sending stops the typing note
    $this->svc->send($direct, $this->ben, 'Hello');
    $this->actingAs($this->ada)->getJson(route('connect.poll', $direct) . '?after=' . $m->id)->assertJsonPath('typing', []);
});

test('the Connect home page opens with no conversation selected', function () {
    $this->svc->send($this->group, $this->ada, 'hello');
    $this->actingAs($this->ben)->get(route('connect.index'))->assertOk()->assertSee('Design');
});
