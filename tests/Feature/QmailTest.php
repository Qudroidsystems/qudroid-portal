<?php

use App\Models\Mail\MailAccount;
use App\Models\Mail\MailDraft;
use App\Models\Mail\MailMessage;
use App\Models\User;
use App\Services\Mail\FakeGateway;
use App\Services\Mail\Mailboxes;
use App\Services\Mail\MailSync;
use App\Services\Settings\Settings;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function qmUser(string $role, string $name): User
{
    $u = User::factory()->create(['user_type' => 'staff', 'name' => $name]);
    $u->assignRole($role);
    $u->forceFill(['must_change_password' => false])->save();

    return $u;
}

function qmBox(User $u, string $email, string $kind = 'personal'): MailAccount
{
    $a = MailAccount::create(['email' => $email, 'name' => $u->name, 'kind' => $kind, 'imap_host' => 'imap.test', 'smtp_host' => 'smtp.test',
        'username' => $email, 'password' => 'secret-pass']);
    $a->users()->attach($u->id, ['role' => 'owner', 'is_default' => true]);
    FakeGateway::deliver($email, "Subject: x\r\n\r\n", 'Drafts');   // creates the fake mailbox
    unset(FakeGateway::$boxes[$email]['Drafts']['messages'][1]);

    return $a;
}

/** A plain RFC 822 message. */
function qmRaw(string $from, string $to, string $subject, string $body, array $headers = []): string
{
    $h = ['From' => $from, 'To' => $to, 'Subject' => $subject, 'Date' => now()->toRfc2822String(),
        'Message-ID' => '<' . uniqid('m', true) . '@ext.test>', 'MIME-Version' => '1.0', 'Content-Type' => 'text/plain; charset=utf-8'];
    $h = array_merge($h, $headers);

    return collect($h)->map(fn ($v, $k) => $k . ': ' . $v)->implode("\r\n") . "\r\n\r\n" . $body . "\r\n";
}

function qmSync(MailAccount $a): array
{
    return app(MailSync::class)->sync($a->fresh());
}

beforeEach(function () {
    Mailboxes::fake();
    Storage::fake('local');
    $this->seed(DatabaseSeeder::class);
    $this->ada = qmUser('Staff', 'Ada Lovelace');
    $this->ben = qmUser('Staff', 'Ben Okafor');
    $this->box = qmBox($this->ada, 'ada@qudroid.test');
});

it('syncs a mailbox and groups replies into one conversation', function () {
    FakeGateway::deliver('ada@qudroid.test', qmRaw('Chi <chi@ext.test>', 'ada@qudroid.test', 'Invoice 42', 'Please see the invoice.', ['Message-ID' => '<root@ext.test>']));
    FakeGateway::deliver('ada@qudroid.test', qmRaw('Chi <chi@ext.test>', 'ada@qudroid.test', 'Re: Invoice 42', 'Any update?', ['In-Reply-To' => '<root@ext.test>', 'References' => '<root@ext.test>']));
    FakeGateway::deliver('ada@qudroid.test', qmRaw('Dan <dan@ext.test>', 'ada@qudroid.test', 'Lunch', 'Friday?'));

    $r = qmSync($this->box);
    expect($r['ok'])->toBeTrue()
        ->and(MailMessage::count())->toBe(3)
        ->and(MailMessage::distinct()->count('thread_key'))->toBe(2)
        ->and($this->box->fresh()->folders()->pluck('role')->filter()->sort()->values()->all())->toBe(['archive', 'drafts', 'inbox', 'junk', 'sent', 'trash']);

    $this->actingAs($this->ada)->get(route('mail.index'))->assertRedirect(route('mail.box', [$this->box, 'inbox']));
    $this->actingAs($this->ada)->get(route('mail.box', [$this->box, 'inbox']))->assertOk()
        ->assertSee('Invoice 42')->assertSee('Lunch')->assertSee('1–2 of 2', false);

    // A second sync adds nothing.
    expect(qmSync($this->box)['new'])->toBe(0)->and(MailMessage::count())->toBe(3);
});

it('reads a conversation and marks it read on the server', function () {
    $uid = FakeGateway::deliver('ada@qudroid.test', qmRaw('Chi <chi@ext.test>', 'ada@qudroid.test', 'Hello there', 'Body text here'));
    qmSync($this->box);
    $m = MailMessage::first();
    expect($m->seen)->toBeFalse();

    $this->actingAs($this->ada)->get(route('mail.thread', [$this->box, $m->thread_key]))->assertOk()->assertSee('Hello there')->assertSee('Chi');
    expect($m->fresh()->seen)->toBeTrue()
        ->and(FakeGateway::$boxes['ada@qudroid.test']['INBOX']['messages'][$uid]['flags'])->toContain('\Seen');
});

it('keeps other people out of a mailbox', function () {
    FakeGateway::deliver('ada@qudroid.test', qmRaw('chi@ext.test', 'ada@qudroid.test', 'Private', 'secret'));
    qmSync($this->box);
    $m = MailMessage::first();

    $this->actingAs($this->ben)->get(route('mail.box', [$this->box, 'inbox']))->assertForbidden();
    $this->actingAs($this->ben)->get(route('mail.thread', [$this->box, $m->thread_key]))->assertForbidden();
    $this->actingAs($this->ben)->get(route('mail.body', [$this->box, $m]))->assertForbidden();
    $this->actingAs($this->ben)->postJson(route('mail.act', $this->box), ['action' => 'trash', 'threads' => [$m->thread_key]])->assertForbidden();
    // Ben has no mailbox yet: setup page.
    $this->actingAs($this->ben)->get(route('mail.index'))->assertRedirect(route('mail.setup'));

    $student = qmUser('Student', 'Sam Student');
    $this->actingAs($student)->get(route('mail.index'))->assertForbidden();
});

it('shows message HTML safely, with remote images only when asked', function () {
    $html = '<html><head><style>.x{color:red}</style></head><body><p class="x" onclick="evil()">Hi</p><script>alert(1)</script><img src="https://track.test/p.gif"><a href="javascript:alert(1)">bad</a></body></html>';
    FakeGateway::deliver('ada@qudroid.test', qmRaw('chi@ext.test', 'ada@qudroid.test', 'Styled', $html, ['Content-Type' => 'text/html; charset=utf-8']));
    qmSync($this->box);
    $m = MailMessage::first();

    $r = $this->actingAs($this->ada)->get(route('mail.body', [$this->box, $m]))->assertOk();
    $body = $r->getContent();
    expect($body)->toContain('.x{color:red}')->toContain('Hi')
        ->not->toContain('<script')->not->toContain('onclick')->not->toContain('javascript:');
    expect($r->headers->get('Content-Security-Policy'))->toContain("default-src 'none'")->not->toContain('https:');

    expect($this->actingAs($this->ada)->get(route('mail.body', [$this->box, $m, 'images' => 1]))->headers->get('Content-Security-Policy'))->toContain('https:');
    $this->actingAs($this->ada)->get(route('mail.thread', [$this->box, $m->thread_key]))->assertSee('Show images');
});

it('sends a reply that threads, lands in Sent and reaches the recipient without Bcc', function () {
    qmBox($this->ben, 'ben@qudroid.test');
    FakeGateway::deliver('ada@qudroid.test', qmRaw('Ben Okafor <ben@qudroid.test>', 'ada@qudroid.test', 'Plan', 'Shall we meet?', ['Message-ID' => '<plan@qudroid.test>']));
    qmSync($this->box);
    $m = MailMessage::first();

    $pre = $this->actingAs($this->ada)->getJson(route('mail.compose', [$this->box, 'mode' => 'reply', 'message' => $m->id]))->assertOk()->json();
    expect($pre['to'])->toBe('Ben Okafor <ben@qudroid.test>')->and($pre['subject'])->toBe('Re: Plan')->and($pre['body_html'])->toContain('<blockquote>');

    $this->actingAs($this->ada)->postJson(route('mail.send', $this->box), [
        'mode' => 'reply', 'source_message_id' => $m->id, 'to' => $pre['to'], 'bcc' => 'boss@ext.test',
        'subject' => $pre['subject'], 'body_html' => '<p>Yes, 3pm.</p><script>x()</script>' . $pre['body_html'],
    ])->assertOk()->assertJson(['ok' => true]);

    expect(Mailboxes::$sent)->toHaveCount(1)
        ->and(Mailboxes::$sent[0]['to'])->toContain('boss@ext.test')->toContain('ben@qudroid.test')
        ->and(Mailboxes::$sent[0]['raw'])->toContain('In-Reply-To: <plan@qudroid.test>')->not->toContain('<script');

    // Ben got it, without the Bcc line.
    $benRaw = collect(FakeGateway::$boxes['ben@qudroid.test']['INBOX']['messages'])->last()['raw'];
    expect($benRaw)->toContain('Yes, 3pm.')->not->toMatch('/^Bcc:/mi');

    // Ada's copy is in Sent and in the same conversation; the original is marked answered.
    $sent = MailMessage::whereHas('folder', fn ($q) => $q->where('role', 'sent'))->first();
    expect($sent)->not->toBeNull()->and($sent->thread_key)->toBe($m->thread_key)->and($m->fresh()->answered)->toBeTrue();
    $this->actingAs($this->ada)->get(route('mail.thread', [$this->box, $m->thread_key]))->assertSee('Shall we meet?')->assertSee('Yes, 3pm.');
});

it('rejects bad addresses with a clear message', function () {
    $this->actingAs($this->ada)->postJson(route('mail.send', $this->box), ['to' => 'not an address', 'subject' => 'x', 'body_html' => 'x'])
        ->assertStatus(422)->assertJsonPath('errors.to.0', '"not an address" is not an e-mail address.');
    $this->actingAs($this->ada)->postJson(route('mail.send', $this->box), ['to' => '', 'subject' => 'x', 'body_html' => 'x'])
        ->assertStatus(422)->assertJsonPath('errors.to.0', 'Add at least one recipient.');
    expect(Mailboxes::$sent)->toBe([]);
});

it('archives, bins, reports spam and deletes for good on the server too', function () {
    FakeGateway::deliver('ada@qudroid.test', qmRaw('chi@ext.test', 'ada@qudroid.test', 'One', 'a'));
    FakeGateway::deliver('ada@qudroid.test', qmRaw('dan@ext.test', 'ada@qudroid.test', 'Two', 'b'));
    qmSync($this->box);
    [$one, $two] = [MailMessage::where('subject', 'One')->first(), MailMessage::where('subject', 'Two')->first()];
    $act = fn ($action, $key, $view = 'inbox') => $this->actingAs($this->ada)->postJson(route('mail.act', $this->box), ['action' => $action, 'threads' => [$key], 'view' => $view]);

    $act('archive', $one->thread_key)->assertOk()->assertJsonPath('message', 'Conversation archived.');
    expect(FakeGateway::$boxes['ada@qudroid.test']['Archive']['messages'])->toHaveCount(1)
        ->and(MailMessage::where('subject', 'One')->first()->folder->role)->toBe('archive');

    // Deleting for good only works from the bin.
    $act('delete', $two->thread_key)->assertStatus(422);
    $act('trash', $two->thread_key)->assertOk();
    expect(MailMessage::where('subject', 'Two')->first()->folder->role)->toBe('trash');
    $act('delete', $two->thread_key, 'trash')->assertOk();
    expect(MailMessage::where('subject', 'Two')->exists())->toBeFalse()
        ->and(FakeGateway::$boxes['ada@qudroid.test']['Trash']['messages'])->toBe([]);

    $act('spam', MailMessage::where('subject', 'One')->first()->thread_key, 'archive')->assertOk();
    expect(MailMessage::where('subject', 'One')->first()->folder->role)->toBe('junk');

    $act('star', MailMessage::where('subject', 'One')->first()->thread_key, 'junk')->assertOk();
    expect(MailMessage::where('subject', 'One')->first()->flagged)->toBeTrue();
});

it('snoozes a conversation out of the inbox and brings it back', function () {
    FakeGateway::deliver('ada@qudroid.test', qmRaw('chi@ext.test', 'ada@qudroid.test', 'Later please', 'x'));
    qmSync($this->box);
    $m = MailMessage::first();

    $this->actingAs($this->ada)->postJson(route('mail.act', $this->box), ['action' => 'snooze', 'threads' => [$m->thread_key], 'until' => now()->addHours(3)->toIso8601String()])->assertOk();
    $this->actingAs($this->ada)->get(route('mail.box', [$this->box, 'inbox']))->assertDontSee('Later please');
    $this->actingAs($this->ada)->get(route('mail.box', [$this->box, 'snoozed']))->assertSee('Later please');

    $this->travel(4)->hours();
    $this->artisan('mail:sync')->assertSuccessful();
    expect($m->fresh()->snoozed_until)->toBeNull()->and($m->fresh()->seen)->toBeFalse();
    $this->actingAs($this->ada)->get(route('mail.box', [$this->box, 'inbox']))->assertSee('Later please');
});

it('searches with Gmail-style operators', function () {
    FakeGateway::deliver('ada@qudroid.test', qmRaw('Chi <chi@ext.test>', 'ada@qudroid.test', 'Quarterly report', 'numbers'));
    FakeGateway::deliver('ada@qudroid.test', qmRaw('Dan <dan@ext.test>', 'ada@qudroid.test', 'Party', 'cake and report'));
    qmSync($this->box);
    $see = fn ($q) => $this->actingAs($this->ada)->get(route('mail.box', [$this->box, 'inbox', 'q' => $q]));

    $see('report')->assertSee('Quarterly report')->assertSee('Party');
    $see('from:chi report')->assertSee('Quarterly report')->assertDontSee('Party');
    $see('subject:party')->assertSee('Party')->assertDontSee('Quarterly');
    $see('is:unread from:dan')->assertSee('Party');
    $see('nothing-matches-this')->assertSee('No messages matched your search.');
});

it('saves drafts with attachments, and sends scheduled mail on time', function () {
    $d = $this->actingAs($this->ada)->post(route('mail.drafts.save', $this->box), [
        'to' => 'chi@ext.test', 'subject' => 'Contract', 'body_html' => '<p>Attached.</p>', 'files' => [UploadedFile::fake()->create('contract.pdf', 40, 'application/pdf')],
    ], ['Accept' => 'application/json'])->assertOk()->json();
    expect($d['attachments'][0]['name'])->toBe('contract.pdf');
    $draft = MailDraft::find($d['draft_id']);
    Storage::disk('local')->assertExists($draft->attachments[0]['path']);
    $this->actingAs($this->ada)->get(route('mail.box', [$this->box, 'drafts']))->assertSee('Contract');

    $this->actingAs($this->ada)->postJson(route('mail.send', $this->box), ['draft_id' => $draft->id, 'to' => 'chi@ext.test', 'subject' => 'Contract',
        'body_html' => '<p>Attached.</p>', 'send_at' => now()->addHour()->toIso8601String()])->assertOk()->assertJson(['scheduled' => true]);
    expect($draft->fresh()->status)->toBe('scheduled')->and(Mailboxes::$sent)->toBe([]);

    $this->artisan('mail:sync');
    expect(Mailboxes::$sent)->toBe([]);
    $this->travel(61)->minutes();
    $this->artisan('mail:sync');
    expect(Mailboxes::$sent)->toHaveCount(1)->and(Mailboxes::$sent[0]['raw'])->toContain('contract.pdf')
        ->and(MailDraft::find($draft->id))->toBeNull();
});

it('puts a scheduled message that fails back in drafts and tells the writer', function () {
    $draft = MailDraft::create(['mail_account_id' => $this->box->id, 'user_id' => $this->ada->id, 'mode' => 'new', 'to' => 'chi@ext.test',
        'subject' => 'Ghost', 'body_html' => 'x', 'status' => 'scheduled', 'send_at' => now()->subMinute()]);
    $this->box->users()->detach($this->ada->id);   // lost access in the meantime

    $this->artisan('mail:sync');
    expect($draft->fresh()->status)->toBe('draft')->and($draft->fresh()->error)->toContain('no longer have')
        ->and(DB::table('notifications')->where('notifiable_id', $this->ada->id)->count())->toBeGreaterThan(0);
});

it('tells people about new mail after the first sync', function () {
    qmSync($this->box);   // the first load stays quiet
    foreach (range(1, 4) as $i) FakeGateway::deliver('ada@qudroid.test', qmRaw('chi@ext.test', 'ada@qudroid.test', 'News ' . $i, 'x'));
    expect(qmSync($this->box)['new'])->toBe(4);
    $titles = DB::table('notifications')->where('notifiable_id', $this->ada->id)->pluck('data')->map(fn ($d) => json_decode($d, true)['title'])->all();
    expect($titles)->toContain('4 new e-mails');
    $this->actingAs($this->ada)->getJson(route('mail.poll', $this->box))->assertOk()->assertJsonPath('counts.inbox', 4);
});

it('lets admins create company addresses on Stalwart and share inboxes', function () {
    $admin = qmUser('Super Administrator', 'Root Admin');
    app(Settings::class)->put(['mail.domain' => 'qudroid.test', 'mail.imap_host' => 'mail.qudroid.test', 'mail.smtp_host' => 'mail.qudroid.test',
        'mail.stalwart_url' => 'https://mail.qudroid.test', 'mail.stalwart_token' => 'tok-123']);
    Http::fake(['mail.qudroid.test/api/principal*' => Http::response(['data' => 1])]);

    $this->actingAs($admin)->post(route('mail.admin.store'), ['how' => 'create', 'kind' => 'shared', 'local' => 'support', 'name' => 'Qudroid Support',
        'users' => [$this->ada->id, $this->ben->id]])->assertSessionHasNoErrors()->assertSessionHas('success', 'support@qudroid.test created.');

    Http::assertSent(fn (HttpRequest $r) => $r->method() === 'POST' && $r['name'] === 'support@qudroid.test' && $r['emails'] === ['support@qudroid.test']
        && $r->hasHeader('Authorization', 'Bearer tok-123'));
    $shared = MailAccount::where('email', 'support@qudroid.test')->first();
    expect($shared->provisioned)->toBeTrue()->and($shared->users()->count())->toBe(2)
        ->and($shared->getRawOriginal('password'))->not->toContain($shared->password);   // stored encrypted

    $this->actingAs($admin)->get(route('mail.admin.index'))->assertOk()->assertSee('support@qudroid.test')->assertSee('Shared inbox');
    $this->actingAs($this->ada)->get(route('mail.settings', $shared))->assertOk()->assertSee('A shared inbox');

    // Ben had no mailbox, so the shared inbox becomes his default.
    $this->actingAs($this->ben)->get(route('mail.index'))->assertRedirect(route('mail.box', [$shared, 'inbox']));
    // Ada sees both and can switch.
    $this->actingAs($this->ada)->get(route('mail.box', [$this->box, 'inbox']))->assertSee('support@qudroid.test');

    $this->actingAs($admin)->put(route('mail.admin.members', $shared), ['users' => [$this->ada->id]])->assertSessionHasNoErrors();
    $this->actingAs($this->ben)->get(route('mail.box', [$shared, 'inbox']))->assertForbidden();

    $this->actingAs($admin)->delete(route('mail.admin.destroy', $shared), ['confirm' => 'support@qudroid.test', 'delete_on_server' => 1])->assertSessionHasNoErrors();
    Http::assertSent(fn (HttpRequest $r) => $r->method() === 'DELETE' && str_ends_with($r->url(), '/api/principal/support%40qudroid.test'));
    expect(MailAccount::where('email', 'support@qudroid.test')->exists())->toBeFalse();

    // Staff can't reach the admin page.
    $this->actingAs($this->ada)->get(route('mail.admin.index'))->assertForbidden();
});

it('lets people connect a mailbox they already have and set a signature', function () {
    $this->actingAs($this->ben)->get(route('mail.setup'))->assertOk()->assertSee('Gmail / Google Workspace');
    $this->actingAs($this->ben)->post(route('mail.connect'), ['email' => 'Ben@Gmail.test', 'password' => 'app-pass', 'imap_host' => 'imap.gmail.com', 'imap_port' => 993,
        'imap_encryption' => 'ssl', 'smtp_host' => 'smtp.gmail.com', 'smtp_port' => 465, 'smtp_encryption' => 'ssl'])->assertSessionHasNoErrors();
    $a = MailAccount::where('email', 'ben@gmail.test')->first();
    expect($a)->not->toBeNull()->and($a->username)->toBe('Ben@Gmail.test')->and($a->users()->first()->pivot->role)->toBe('owner');

    // The same mailbox can't be added twice.
    $this->actingAs($this->ada)->post(route('mail.connect'), ['email' => 'ben@gmail.test', 'password' => 'x', 'imap_host' => 'h', 'imap_port' => 993,
        'imap_encryption' => 'ssl', 'smtp_host' => 'h', 'smtp_port' => 465, 'smtp_encryption' => 'ssl'])->assertSessionHasErrors('email');

    $this->actingAs($this->ben)->put(route('mail.settings.update', $a), ['name' => 'Ben O.', 'signature' => "Ben Okafor\nQudroid"])->assertSessionHasNoErrors();
    $pre = $this->actingAs($this->ben)->getJson(route('mail.compose', $a))->json();
    expect($pre['body_html'])->toContain('Ben Okafor<br />');

    $this->actingAs($this->ben)->delete(route('mail.disconnect', $a))->assertRedirect(route('mail.index'));
    expect(MailAccount::find($a->id))->toBeNull();
});

it('saves mail server settings with the Stalwart key encrypted', function () {
    $admin = qmUser('Super Administrator', 'Root Admin');
    $this->actingAs($admin)->get(route('portal-settings.mail'))->assertOk();
    $this->actingAs($admin)->post(route('portal-settings.mail.save'), ['domain' => 'Qudroid.com', 'imap_host' => 'mail.qudroid.com', 'imap_port' => 993, 'imap_encryption' => 'ssl',
        'smtp_host' => 'mail.qudroid.com', 'smtp_port' => 465, 'smtp_encryption' => 'ssl', 'stalwart_url' => 'https://mail.qudroid.com', 'stalwart_token' => 'sekret-token'])
        ->assertSessionHasNoErrors();
    expect(app(Settings::class)->get('mail.domain'))->toBe('qudroid.com')
        ->and(app(Settings::class)->get('mail.stalwart_token'))->toBe('sekret-token')
        ->and(DB::table('settings')->where('key', 'mail.stalwart_token')->value('value'))->not->toContain('sekret');
    $this->actingAs($admin)->get(route('portal-settings.mail'))->assertDontSee('sekret-token')->assertSee('••••oken');
});
