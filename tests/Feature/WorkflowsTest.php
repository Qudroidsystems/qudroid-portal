<?php

use App\Mail\WorkflowMail;
use App\Models\Connect\Message;
use App\Models\Finance\Invoice;
use App\Models\Inventory\Item;
use App\Models\Inventory\Location;
use App\Models\Projects\Project;
use App\Models\Projects\Task;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Models\Workflows\WorkflowRun;
use App\Services\Connect\ConnectService;
use App\Services\Inventory\StockService;
use App\Services\Projects\ProjectService;
use App\Services\Workflows\Engine;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

function wfUser(string $role, string $name = null, string $type = 'staff'): User
{
    $u = User::factory()->create(['user_type' => $type] + ($name ? ['name' => $name] : []));
    $u->assignRole($role);
    $u->forceFill(['must_change_password' => false])->save();

    return $u;
}

/** Notifications a user has, newest first, as [title, body]. */
function wfBell(User $u): array
{
    return DB::table('notifications')->where('notifiable_id', $u->id)->orderByDesc('created_at')->pluck('data')
        ->map(fn ($d) => json_decode($d, true))->map(fn ($d) => [$d['title'], $d['body']])->all();
}

function wfSave($test, array $definition, array $extra = [])
{
    return $test->actingAs($test->admin)->postJson(route('workflows.store'), ['name' => $extra['name'] ?? 'Test flow', 'definition' => json_encode($definition)] + $extra);
}

function wfDef(string $trigger, array $steps, array $rules = [], array $config = []): array
{
    return ['trigger' => ['key' => $trigger, 'config' => $config], 'conditions' => ['match' => 'all', 'rules' => $rules], 'steps' => $steps];
}

function wfNotify(array $people, string $title, string $message = '', array $roles = [], array $users = []): array
{
    return ['type' => 'notify', 'config' => ['to' => ['people' => $people, 'users' => $users, 'roles' => $roles], 'title' => $title, 'message' => $message, 'link' => '']];
}

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->admin = wfUser('Super Administrator', 'Ada Admin');
    $this->pm = wfUser('Project Manager', 'Pat Manager');
    $this->dev = wfUser('Staff', 'Dev Staff');
    $this->project = app(ProjectService::class)->create(['code' => 'web', 'name' => 'Website', 'priority' => 'normal', 'manager_id' => $this->pm->id], $this->pm);
    app(ProjectService::class)->addMember($this->project, $this->dev, 'member', $this->pm);
});

test('only people allowed to manage workflows can open them', function () {
    $this->actingAs($this->dev)->get(route('workflows.index'))->assertForbidden();
    $this->actingAs($this->admin)->get(route('workflows.index'))->assertOk()->assertSee('Chase an overdue invoice');
    $this->actingAs($this->admin)->get(route('workflows.create', ['recipe' => 'invoice-chase']))->assertOk()->assertSee('WF_CATALOG', false);
});

test('a saved workflow runs when a task moves to review, and its rule keeps other moves out', function () {
    wfSave($this, wfDef('task.status_changed', [wfNotify(['task.reporter'], 'Review {{task.key}}', '{{actor.name}} moved "{{task.title}}" to {{task.status}}.')],
        [['field' => 'task.status', 'op' => 'is', 'value' => 'review']]), ['is_active' => '1'])->assertOk()->assertJsonPath('ok', true);

    $svc = app(ProjectService::class);
    $task = $svc->createTask($this->project, ['title' => 'Home page', 'assignee_id' => $this->dev->id], $this->pm);
    $this->actingAs($this->dev);
    $svc->move($task, 'in_progress', [], $this->dev);
    Engine::flush();
    expect(WorkflowRun::count())->toBe(0);

    $svc->move($task->fresh(), 'review', [], $this->dev);
    Engine::flush();
    $run = WorkflowRun::sole();
    expect($run->status)->toBe('done')
        ->and(wfBell($this->pm))->toContain(['Review WEB-1', 'Dev Staff moved "Home page" to In review.'])
        ->and(Workflow::first()->runs_count)->toBe(1);
});

test('switched-off workflows do not run, and switching on checks the workflow again', function () {
    wfSave($this, wfDef('task.created', [wfNotify(['project.manager'], 'New task')]))->assertOk();
    $wf = Workflow::sole();
    expect($wf->is_active)->toBeFalse();

    app(ProjectService::class)->createTask($this->project, ['title' => 'One'], $this->dev);
    Engine::flush();
    expect(WorkflowRun::count())->toBe(0);

    $this->actingAs($this->admin)->post(route('workflows.toggle', $wf))->assertRedirect();
    app(ProjectService::class)->createTask($this->project, ['title' => 'Two'], $this->dev);
    Engine::flush();
    expect(WorkflowRun::sole()->status)->toBe('done')->and(wfBell($this->pm)[0][0])->toBe('New task');
});

test('the builder refuses workflows that could not work', function () {
    wfSave($this, wfDef('nope', [wfNotify([], 'x', '', ['Staff'])]))->assertStatus(422)->assertJsonValidationErrors('definition');
    wfSave($this, wfDef('task.created', []))->assertStatus(422);
    wfSave($this, wfDef('task.created', [wfNotify([], 'Nobody')]))->assertStatus(422)->assertJsonPath('errors.definition.0', 'Step 1: choose who to notify.');
    wfSave($this, wfDef('task.created', [['type' => 'wait', 'config' => ['amount' => 1, 'unit' => 'days']]]))->assertStatus(422);
    foreach (['http://localhost/hook', 'http://127.0.0.1/x', 'http://10.0.0.5/x', 'http://169.254.169.254/latest', 'ftp://example.com/x'] as $url) {
        wfSave($this, wfDef('task.created', [['type' => 'webhook', 'config' => ['url' => $url]]]))->assertStatus(422);
    }
    wfSave($this, wfDef('deal.won', [['type' => 'chat', 'config' => ['conversation_id' => 999, 'message' => 'hi']]]))->assertStatus(422);
    wfSave($this, wfDef('deal.won', [['type' => 'task', 'config' => ['project' => 'record', 'title' => 'x']]]))->assertStatus(422);
    wfSave($this, wfDef('schedule.daily', [wfNotify([], 'x', '', ['Staff'])], [], ['time' => '25:00']))->assertStatus(422);
    // Unknown fields and people are dropped, not stored.
    wfSave($this, wfDef('task.created', [wfNotify(['task.assignee', 'evil'], 'x')], [['field' => 'password', 'op' => 'is', 'value' => 'x']]))->assertOk();
    $def = Workflow::sole()->definition;
    expect($def['conditions']['rules'])->toBe([])->and($def['steps'][0]['config']['to']['people'])->toBe(['task.assignee']);
});

test('an overdue invoice is chased once, waits, and stops when it gets paid', function () {
    Mail::fake();
    $client = wfUser('Client', 'Cleo Client', 'client');
    $fin = wfUser('Finance Officer', 'Fola Finance');
    $this->actingAs($this->admin)->post(route('finance.invoices.store'), [
        'user_id' => $client->id, 'title' => 'Website', 'currency' => 'NGN', 'due_date' => today()->addDay()->toDateString(),
        'lines' => [['description' => 'Design', 'quantity' => '1', 'unit_price' => '150000', 'discount' => '0']],
    ])->assertRedirect();
    $invoice = Invoice::sole();
    $this->get(route('workflows.create', ['recipe' => 'invoice-chase']))->assertOk();
    wfSave($this, \App\Services\Workflows\Recipes::all()['invoice-chase']['definition'], ['is_active' => '1'])->assertOk();

    Engine::tick();
    expect(WorkflowRun::count())->toBe(0); // not overdue yet

    $this->travel(2)->days();
    Engine::tick();
    Engine::tick(); // the same due date only counts once
    $run = WorkflowRun::sole();
    expect($run->status)->toBe('waiting')->and($run->step)->toBe(2);
    Mail::assertSent(WorkflowMail::class, fn ($m) => $m->hasTo($client->email) && $m->subjectLine === 'Invoice ' . $invoice->number . ' is overdue' && str_contains($m->text, '₦150,000.00'));

    // Paid in the meantime: the rule after the wait sees today's state and stops.
    $invoice->update(['status' => 'paid', 'amount_paid' => '150000']);
    $this->travel(3)->days();
    $this->travel(1)->minutes();
    Engine::tick();
    expect($run->fresh()->status)->toBe('stopped')->and(wfBell($fin))->toBe([]);
});

test('if the invoice is still unpaid after the wait, finance hears about it', function () {
    Mail::fake();
    $client = wfUser('Client', 'Cleo Client', 'client');
    $fin = wfUser('Finance Officer', 'Fola Finance');
    $this->actingAs($this->admin)->post(route('finance.invoices.store'), [
        'user_id' => $client->id, 'title' => 'Hosting', 'currency' => 'NGN', 'due_date' => today()->addDay()->toDateString(),
        'lines' => [['description' => 'Hosting', 'quantity' => '1', 'unit_price' => '60000', 'discount' => '0']],
    ]);
    wfSave($this, \App\Services\Workflows\Recipes::all()['invoice-chase']['definition'], ['is_active' => '1'])->assertOk();
    $this->travel(2)->days();
    Engine::tick();
    $this->travel(3)->days();
    $this->travel(1)->minutes();
    Engine::tick();
    $run = WorkflowRun::sole();
    expect($run->status)->toBe('done')
        ->and(collect($run->log)->pluck('type')->all())->toBe(['email', 'wait', 'filter', 'notify'])
        ->and(wfBell($fin)[0][0])->toBe('Still unpaid: ' . Invoice::sole()->number);
});

test('a daily schedule runs once in its window, on weekdays only when asked', function () {
    $wf = Workflow::create(['name' => 'Stand-up', 'trigger' => 'schedule.daily', 'is_active' => true, 'created_by' => $this->admin->id,
        'definition' => wfDef('schedule.daily', [wfNotify([], 'Stand-up {{today}}', '', ['Staff'])], [], ['time' => '09:00', 'weekdays_only' => true])]);

    $monday = Carbon::parse('next monday');
    Engine::tick($monday->copy()->setTime(8, 59));
    expect(WorkflowRun::count())->toBe(0);
    Engine::tick($monday->copy()->setTime(9, 0));
    Engine::tick($monday->copy()->setTime(9, 30));
    expect(WorkflowRun::count())->toBe(1)->and(WorkflowRun::sole()->status)->toBe('done');
    Engine::tick($monday->copy()->setTime(12, 0)->addDay()); // Tuesday, too late in the day
    expect(WorkflowRun::count())->toBe(1);
    Engine::tick($monday->copy()->addDays(5)->setTime(9, 5)); // Saturday
    expect(WorkflowRun::count())->toBe(1);

    $wf->update(['trigger' => 'schedule.weekly', 'definition' => wfDef('schedule.weekly', $wf->steps(), [], ['weekday' => '3', 'time' => '10:00'])]);
    expect(Engine::dueSlot($wf->fresh(), $monday->copy()->addDays(2)->setTime(10, 15)))->not->toBeNull()
        ->and(Engine::dueSlot($wf->fresh(), $monday->copy()->addDays(1)->setTime(10, 15)))->toBeNull();
});

test('a workflow that adds tasks on task.created does not loop', function () {
    wfSave($this, wfDef('task.created', [['type' => 'task', 'config' => ['project' => 'record', 'title' => 'Check {{task.title}}', 'assignee' => 'person:task.assignee', 'priority' => 'high', 'due_days' => '2']]]),
        ['is_active' => '1'])->assertOk();
    app(ProjectService::class)->createTask($this->project, ['title' => 'Logo', 'assignee_id' => $this->dev->id], $this->pm);
    Engine::flush();

    expect(Task::where('project_id', $this->project->id)->pluck('title')->all())->toBe(['Logo', 'Check Logo'])
        ->and(Task::where('title', 'Check Logo')->first()->assignee_id)->toBe($this->dev->id)
        ->and(Task::where('title', 'Check Logo')->first()->due_on->toDateString())->toBe(today()->addDays(2)->toDateString())
        ->and(WorkflowRun::count())->toBe(1);
});

test('a webhook gets the details with a signature it can check', function () {
    Http::fake(['hooks.example.com/*' => Http::response(['ok' => true], 200)]);
    wfSave($this, wfDef('task.created', [['type' => 'webhook', 'config' => ['url' => 'https://hooks.example.com/abc', 'secret' => 's3cret']]]), ['is_active' => '1'])->assertOk();
    app(ProjectService::class)->createTask($this->project, ['title' => 'API'], $this->pm);
    Engine::flush();

    Http::assertSent(function (HttpRequest $r) {
        $body = json_decode($r->body(), true);

        return $r->url() === 'https://hooks.example.com/abc' && $body['data']['task.title'] === 'API' && !isset($body['data']['_people'])
            && $r->header('X-Qudroid-Signature')[0] === 'sha256=' . hash_hmac('sha256', $r->body(), 's3cret');
    });
    expect(WorkflowRun::sole()->status)->toBe('done');
});

test('a failing step marks the run failed without breaking the save, and can be retried', function () {
    Http::fake(['hooks.example.com/*' => Http::sequence()->push('nope', 500)->push('ok', 200)]);
    wfSave($this, wfDef('task.created', [['type' => 'webhook', 'config' => ['url' => 'https://hooks.example.com/x']], wfNotify(['project.manager'], 'After hook')]), ['is_active' => '1']);
    $task = app(ProjectService::class)->createTask($this->project, ['title' => 'Still saved'], $this->pm);
    Engine::flush();
    $run = WorkflowRun::sole();
    expect($task->exists)->toBeTrue()->and($run->status)->toBe('failed')->and($run->error)->toBe('The webhook answered 500.');
    $this->actingAs($this->admin)->get(route('workflows.runs', $run->workflow_id))->assertOk()->assertSee('The webhook answered 500.');

    $this->actingAs($this->admin)->post(route('workflows.runs.retry', $run))->assertRedirect();
    expect($run->fresh()->status)->toBe('done')->and(wfBell($this->pm)[0][0])->toBe('After hook');
});

test('chat: an "urgent" channel message alerts managers, and a step posts in a channel', function () {
    $md = wfUser('Managing Director', 'Mo Director');
    $svc = app(ConnectService::class);
    $general = $svc->channel($this->admin, 'general', null);
    $alerts = $svc->channel($this->admin, 'alerts', null);
    $svc->join($general, $this->dev);

    wfSave($this, wfDef('message.channel', [
        wfNotify([], 'Urgent in #{{message.channel}}', '{{message.from}}: {{message.text}}', ['Managing Director']),
        ['type' => 'chat', 'config' => ['conversation_id' => $alerts->id, 'message' => 'Flagged: {{message.text}}', 'include_link' => false]],
    ], [['field' => 'message.text', 'op' => 'contains', 'value' => 'URGENT']]), ['is_active' => '1'])->assertOk();

    $svc->send($general, $this->dev, 'lunch?');
    $svc->send($general, $this->dev, 'Server is down, urgent!');
    Engine::flush();

    expect(wfBell($md))->toBe([['Urgent in #general', 'Dev Staff: Server is down, urgent!']])
        ->and(Message::where('conversation_id', $alerts->id)->where('is_system', false)->latest('id')->first()->body)->toBe('Flagged: Server is down, urgent!')
        ->and(WorkflowRun::count())->toBe(1); // the bot's own post in #alerts doesn't start it again

    // Direct messages are never read by workflows.
    $dm = $svc->direct($this->dev, $md);
    $svc->send($dm, $this->dev, 'urgent secret');
    Engine::flush();
    expect(WorkflowRun::count())->toBe(1);
});

test('low stock alerts once when it crosses the reorder level', function () {
    $inv = wfUser('Inventory Officer', 'Ike Inventory');
    $store = Location::create(['name' => 'Store', 'is_active' => true]);
    $item = Item::create(['sku' => 'TONER', 'name' => 'Toner', 'unit' => 'pcs', 'reorder_level' => 5]);
    $stock = app(StockService::class);
    $stock->receive($item, $store, 10, '1000', $this->admin);
    wfSave($this, \App\Services\Workflows\Recipes::all()['low-stock']['definition'], ['is_active' => '1'])->assertOk();

    $stock->issue($item->fresh(), $store, 3, $this->admin);  // 7 left
    $stock->issue($item->fresh(), $store, 3, $this->admin);  // 4 left: crosses
    $stock->issue($item->fresh(), $store, 1, $this->admin);  // 3 left: already low
    Engine::flush();
    $ours = array_values(array_filter(wfBell($inv), fn ($n) => str_starts_with($n[1], 'Only')));
    expect($ours)->toBe([['Low stock: Toner', 'Only 4 pcs left (reorder level 5). Supplier: .']]);
});

test('try it describes the steps with a real record and changes nothing', function () {
    $task = app(ProjectService::class)->createTask($this->project, ['title' => 'Preview me', 'assignee_id' => $this->dev->id], $this->pm);
    $def = wfDef('task.created', [wfNotify(['task.assignee'], 'Hi {{task.assignee}}', 'About {{task.title}}'), ['type' => 'wait', 'config' => ['amount' => 2, 'unit' => 'days']],
        ['type' => 'email', 'config' => ['to' => ['people' => [], 'users' => [], 'roles' => []], 'emails' => 'boss@example.com', 'subject' => 'Re {{task.key}}', 'body' => 'x']]]);
    Mail::fake();
    $bell = count(wfBell($this->dev));
    $res = $this->actingAs($this->admin)->postJson(route('workflows.preview'), ['definition' => json_encode($def)])->assertOk();

    expect($res->json('record'))->toBe('WEB-1 Preview me')
        ->and($res->json('steps.0.note'))->toBe('Would notify Dev Staff: "Hi Dev Staff" About Preview me')
        ->and($res->json('steps.1.note'))->toStartWith('Would wait 2 days')
        ->and($res->json('steps.2.note'))->toBe('Would e-mail boss@example.com: "Re WEB-1"')
        ->and(WorkflowRun::count())->toBe(0)->and(count(wfBell($this->dev)))->toBe($bell);
    Mail::assertNothingSent();
});

test('the run history and builder pages open for every recipe', function () {
    foreach (array_keys(\App\Services\Workflows\Recipes::all()) as $key) {
        $this->actingAs($this->admin)->get(route('workflows.create', ['recipe' => $key]))->assertOk();
    }
    wfSave($this, wfDef('task.created', [wfNotify(['project.manager'], 'x')]))->assertOk();
    $wf = Workflow::sole();
    $this->get(route('workflows.edit', $wf))->assertOk()->assertSee('Runs (0)');
    $this->get(route('workflows.runs', $wf))->assertOk();
    $this->post(route('workflows.duplicate', $wf))->assertRedirect();
    expect(Workflow::count())->toBe(2);
    $this->delete(route('workflows.destroy', $wf))->assertRedirect(route('workflows.index'));
});
