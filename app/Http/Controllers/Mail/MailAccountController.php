<?php

namespace App\Http\Controllers\Mail;

use App\Http\Controllers\Controller;
use App\Models\Mail\MailAccount;
use App\Models\User;
use App\Services\Mail\Mailboxes;
use App\Services\Mail\MailService;
use App\Services\Mail\MailSync;
use App\Services\Mail\Stalwart;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Setting mailboxes up: people connecting their own, and admins handing out addresses and shared inboxes. */
class MailAccountController extends Controller
{
    public function __construct(protected MailService $mail) {}

    /** First visit with no mailbox yet. */
    public function setup(Request $request)
    {
        return view('modules.mail.setup', ['accounts' => $this->mail->accountsFor($request->user()), 'server' => MailService::server(),
            'canManage' => $request->user()->can('Manage mail'), 'stalwart' => Stalwart::configured()]);
    }

    protected function serverRules(): array
    {
        return [
            'imap_host' => ['required', 'string', 'max:191'], 'imap_port' => ['required', 'integer', 'between:1,65535'], 'imap_encryption' => ['required', Rule::in(['ssl', 'tls', 'none'])],
            'smtp_host' => ['required', 'string', 'max:191'], 'smtp_port' => ['required', 'integer', 'between:1,65535'], 'smtp_encryption' => ['required', Rule::in(['ssl', 'tls', 'none'])],
            'username' => ['nullable', 'string', 'max:191'],
        ];
    }

    /** Try the settings before saving, so nobody ends up with a mailbox that never loads. */
    protected function tested(MailAccount $account): void
    {
        if ($err = Mailboxes::test($account)) throw ValidationException::withMessages(['password' => 'Could not connect. ' . $err]);
    }

    /** Someone connects a mailbox they already have (company server, Gmail/Zoho with an app password, …). */
    public function connect(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:191', Rule::unique('mail_accounts', 'email')], 'name' => ['nullable', 'string', 'max:150'],
            'password' => ['required', 'string', 'max:500'],
        ] + $this->serverRules(), ['email.unique' => 'That mailbox is already in the portal. Ask an admin to give you access.']);
        $account = new MailAccount(['kind' => 'personal', 'created_by' => $request->user()->id, 'name' => ($data['name'] ?? null) ?: $request->user()->name]
            + collect($data)->except('username')->all() + ['username' => ($data['username'] ?? null) ?: $data['email']]);
        $account->email = Str::lower($data['email']);
        $this->tested($account);
        DB::transaction(function () use ($account, $request) {
            $account->save();
            $default = !$request->user()->mailAccounts()->exists();
            $account->users()->attach($request->user()->id, ['role' => 'owner', 'is_default' => $default]);
        });
        $this->firstSync($account);

        return redirect()->route('mail.box', [$account, 'inbox'])->with('success', 'Mailbox connected. Your mail is loading.');
    }

    /** Load mail after the response, so the page opens straight away. */
    protected function firstSync(MailAccount $account): void
    {
        app()->terminating(function () use ($account) {
            try { app(MailSync::class)->sync($account, false); } catch (\Throwable $e) { report($e); }
        });
    }

    /** The person's own settings for a mailbox: display name, signature, default. */
    public function settings(Request $request, MailAccount $account)
    {
        abort_unless($this->mail->canUse($request->user(), $account), 403);
        $pivot = $account->users()->whereKey($request->user()->id)->first()->pivot;

        return view('modules.mail.settings', ['account' => $account, 'accounts' => $this->mail->accountsFor($request->user()), 'pivot' => $pivot,
            'owner' => $pivot->role === 'owner' && !$account->provisioned, 'view' => 'settings', 'counts' => $this->mail->unreadCounts($account),
            'labels' => $account->folders()->whereNull('role')->orderBy('name')->get(), 'roles' => collect(), 'q' => '', 'syncing' => false]);
    }

    public function updateSettings(Request $request, MailAccount $account)
    {
        abort_unless($this->mail->canUse($request->user(), $account), 403);
        $pivot = $account->users()->whereKey($request->user()->id)->first()->pivot;
        $owner = $pivot->role === 'owner' && !$account->provisioned;
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:150'], 'signature' => ['nullable', 'string', 'max:3000'], 'is_default' => ['nullable', 'boolean'],
            'password' => ['nullable', 'string', 'max:500'],
        ] + ($owner ? array_map(fn ($r) => array_map(fn ($x) => $x === 'required' ? 'sometimes' : $x, $r), $this->serverRules()) : []));

        // Name and signature belong to the mailbox; on a shared inbox, members share them.
        $account->fill(['name' => $data['name'] ?? $account->name, 'signature' => $data['signature'] ?? null]);
        if ($owner) {
            $account->fill(collect($data)->only(array_keys($this->serverRules()))->filter(fn ($v) => $v !== null)->all());
            if (!empty($data['password'])) $account->password = $data['password'];
            if ($account->isDirty(['imap_host', 'imap_port', 'imap_encryption', 'smtp_host', 'smtp_port', 'smtp_encryption', 'username', 'password'])) $this->tested($account);
        }
        $account->save();
        if (!empty($data['is_default'])) {
            DB::table('mail_account_user')->where('user_id', $request->user()->id)->update(['is_default' => false]);
            $account->users()->updateExistingPivot($request->user()->id, ['is_default' => true]);
        }

        return back()->with('success', 'Mail settings saved.');
    }

    /** Remove a mailbox you connected yourself (the mail stays on its server). */
    public function disconnect(Request $request, MailAccount $account)
    {
        abort_unless($this->mail->canUse($request->user(), $account), 403);
        $pivot = $account->users()->whereKey($request->user()->id)->first()->pivot;
        abort_if($account->provisioned || $pivot->role !== 'owner', 403, 'Only an admin can remove this mailbox.');
        $this->forget($account);

        return redirect()->route('mail.index')->with('success', 'Mailbox removed from the portal. Your mail is still on its server.');
    }

    protected function forget(MailAccount $account): void
    {
        Storage::disk('local')->deleteDirectory('mail/' . $account->id);
        $account->delete();
    }

    // ── Admin ───────────────────────────────────────────────────────

    public function admin(Request $request)
    {
        $accounts = MailAccount::with('users:id,name,email')->withCount('messages')->orderBy('kind')->orderBy('email')->get();

        return view('modules.mail.admin', ['accounts' => $accounts, 'server' => MailService::server(), 'stalwart' => Stalwart::configured(),
            'users' => User::where('is_disabled', false)->orderBy('name')->get(['id', 'name', 'email'])]);
    }

    /**
     * Create a new @company address on the mail server (Stalwart), or add an existing
     * mailbox, and give it to people. Shared inboxes (support@, sales@) have several members.
     */
    public function store(Request $request)
    {
        $server = MailService::server();
        $how = $request->input('how') === 'connect' ? 'connect' : 'create';
        $data = $request->validate([
            'how' => ['required', Rule::in(['create', 'connect'])], 'kind' => ['required', Rule::in(['personal', 'shared'])],
            'local' => [Rule::requiredIf($how === 'create'), 'nullable', 'string', 'max:64', 'regex:/^[a-z0-9]([a-z0-9._-]*[a-z0-9])?$/i'],
            'email' => [Rule::requiredIf($how === 'connect'), 'nullable', 'email', 'max:191'],
            'name' => ['nullable', 'string', 'max:150'], 'password' => [Rule::requiredIf($how === 'connect'), 'nullable', 'string', 'max:500'],
            'users' => ['required', 'array', 'min:1', 'max:200'], 'users.*' => ['integer', 'exists:users,id'],
            'quota_mb' => ['nullable', 'integer', 'min:0', 'max:1048576'],
        ] + ($how === 'connect' ? $this->serverRules() : []), ['local.regex' => 'Use letters, numbers, dots, dashes or underscores.', 'users.required' => 'Choose who gets this mailbox.']);

        if ($how === 'create') {
            if (!Stalwart::configured() || empty($server['domain'])) {
                throw ValidationException::withMessages(['local' => 'Set the mail domain and the Stalwart server under Settings → Mail first.']);
            }
            $email = Str::lower($data['local'] . '@' . $server['domain']);
            $password = Stalwart::password();
            $account = new MailAccount([
                'name' => $data['name'] ?? null, 'kind' => $data['kind'], 'username' => Stalwart::login($email), 'provisioned' => true, 'created_by' => $request->user()->id,
                'imap_host' => $server['imap_host'], 'imap_port' => $server['imap_port'] ?: 993, 'imap_encryption' => $server['imap_encryption'] ?: 'ssl',
                'smtp_host' => $server['smtp_host'], 'smtp_port' => $server['smtp_port'] ?: 465, 'smtp_encryption' => $server['smtp_encryption'] ?: 'ssl',
            ]);
        } else {
            $email = Str::lower($data['email']);
            $password = $data['password'];
            $account = new MailAccount(['name' => $data['name'] ?? null, 'kind' => $data['kind'], 'created_by' => $request->user()->id]
                + collect($data)->only(array_keys($this->serverRules()))->all());
            $account->username = ($data['username'] ?? null) ?: $email;
        }
        if (MailAccount::where('email', $email)->exists()) throw ValidationException::withMessages([$how === 'create' ? 'local' : 'email' => $email . ' is already in the portal.']);
        $account->email = $email;
        $account->password = $password;
        if (!$account->imap_host || !$account->smtp_host) throw ValidationException::withMessages(['local' => 'Set the IMAP and SMTP server under Settings → Mail first.']);

        if ($how === 'create') {
            app(Stalwart::class)->create($email, $data['name'] ?? null, $password, (int) ($data['quota_mb'] ?? 0));
        } else {
            $this->tested($account);
        }
        DB::transaction(function () use ($account, $data) {
            $account->save();
            foreach (array_unique($data['users']) as $i => $uid) {
                $hasOne = DB::table('mail_account_user')->where('user_id', $uid)->exists();
                $account->users()->attach($uid, ['role' => $data['kind'] === 'personal' && $i === 0 ? 'owner' : 'member', 'is_default' => !$hasOne]);
            }
        });
        if ($how === 'connect') $this->firstSync($account);

        return back()->with('success', $email . ($how === 'create' ? ' created.' : ' added.'));
    }

    /** Change who can use a mailbox. */
    public function members(Request $request, MailAccount $account)
    {
        $data = $request->validate(['users' => ['required', 'array', 'min:1', 'max:200'], 'users.*' => ['integer', 'exists:users,id'], 'name' => ['nullable', 'string', 'max:150']]);
        $current = $account->users()->pluck('users.id')->all();
        $keep = array_map('intval', array_unique($data['users']));
        $account->users()->detach(array_diff($current, $keep));
        foreach (array_diff($keep, $current) as $uid) {
            $account->users()->attach($uid, ['role' => 'member', 'is_default' => !DB::table('mail_account_user')->where('user_id', $uid)->exists()]);
        }
        if ($request->filled('name')) $account->update(['name' => $data['name']]);

        return back()->with('success', 'Access to ' . $account->email . ' updated.');
    }

    /** New password on the mail server, shown once, e.g. so someone can add the mailbox to a phone's mail app. */
    public function resetPassword(Request $request, MailAccount $account)
    {
        abort_unless($account->provisioned, 422, 'Only addresses created from the portal can be reset here.');
        $password = Stalwart::password();
        app(Stalwart::class)->setPassword($account->email, $password);
        $account->update(['password' => $password]);

        return back()->with('success', 'New password for ' . $account->email . ': ' . $password . ' (shown once; it is saved in the portal).');
    }

    public function destroy(Request $request, MailAccount $account)
    {
        $data = $request->validate(['confirm' => ['required', 'string']]);
        abort_unless(Str::lower(trim($data['confirm'])) === $account->email, 422, 'Type the address to confirm.');
        if ($account->provisioned && $request->boolean('delete_on_server')) app(Stalwart::class)->delete($account->email);
        $this->forget($account);

        return back()->with('success', $account->email . ' removed' . ($account->provisioned && $request->boolean('delete_on_server') ? ' and deleted from the mail server.' : ' from the portal.'));
    }

    public function check(Request $request, MailAccount $account)
    {
        $r = app(MailSync::class)->sync($account, false);

        return back()->with($r['ok'] ? 'success' : 'error', $r['ok'] ? $account->email . ' is working.' : $account->email . ': ' . ($r['error'] ?? 'could not connect.'));
    }
}
