<?php

namespace App\Services\Mail;

use App\Models\Mail\MailAccount;
use App\Models\Mail\MailDraft;
use App\Models\Mail\MailFolder;
use App\Models\Mail\MailMessage;
use App\Models\User;
use App\Services\Messaging\PortalNotifier;
use App\Services\Settings\Settings;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Qmail's reading, organising and sending. Every change is made on the mail
 * server first and then mirrored here, so other mail apps stay in step.
 */
class MailService
{
    /** Views in the sidebar that aren't plain folders. */
    public const VIEWS = ['inbox', 'starred', 'snoozed', 'sent', 'drafts', 'scheduled', 'archive', 'junk', 'trash'];

    public function __construct(protected MailSync $sync) {}

    // ── Access ──────────────────────────────────────────────────────

    /** @return Collection<MailAccount> */
    public function accountsFor(User $user): Collection
    {
        return $user->mailAccounts()->orderByDesc('mail_account_user.is_default')->orderBy('email')->get();
    }

    public function canUse(User $user, MailAccount $account): bool
    {
        return $account->users()->whereKey($user->id)->exists();
    }

    protected function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }

    // ── Server defaults ─────────────────────────────────────────────

    /** Server settings for new mailboxes: Settings → Mail first, then .env. */
    public static function server(): array
    {
        $s = app(Settings::class);
        $out = [];
        foreach (['domain', 'imap_host', 'imap_port', 'imap_encryption', 'smtp_host', 'smtp_port', 'smtp_encryption', 'stalwart_url', 'stalwart_token'] as $k) {
            $out[$k] = $s->get('mail.' . $k, config('qudroid.mail.' . $k));
        }

        return $out;
    }

    // ── Listing ─────────────────────────────────────────────────────

    /** The message query behind a sidebar view (or a label: a folder id). */
    public function scope(MailAccount $account, string $view)
    {
        $q = MailMessage::query()->where('mail_messages.mail_account_id', $account->id);
        $folderIds = fn (array $roles) => $account->folders()->whereIn('role', $roles)->pluck('id');
        $notBin = fn () => $q->whereNotIn('mail_folder_id', $folderIds(['trash', 'junk']));

        if (ctype_digit($view)) return $q->where('mail_folder_id', (int) $view);

        return match ($view) {
            'inbox' => $q->whereIn('mail_folder_id', $folderIds(['inbox']))->where(fn ($w) => $w->whereNull('snoozed_until')->orWhere('snoozed_until', '<=', now())),
            'starred' => tap($q->where('flagged', true), fn () => $notBin()),
            'snoozed' => $q->where('snoozed_until', '>', now()),
            'archive' => tap($q, fn () => $notBin()),   // "All mail": everything except spam and bin
            'search' => tap($q, fn () => $notBin()),
            'anywhere' => $q,
            default => $q->whereIn('mail_folder_id', $folderIds([$view])),
        };
    }

    /** Gmail-style search: words, plus from:, to:, subject:, has:attachment, is:unread, is:starred, in:anywhere. */
    public function applySearch($q, string $search, MailAccount $account)
    {
        preg_match_all('/(\w+):("[^"]*"|\S+)|"([^"]+)"|(\S+)/u', $search, $m, PREG_SET_ORDER);
        foreach ($m as $t) {
            $op = mb_strtolower($t[1] ?? '');
            $val = trim(($t[2] ?? '') !== '' ? $t[2] : (($t[3] ?? '') !== '' ? $t[3] : ($t[4] ?? '')), '"');
            if ($val === '') continue;
            $like = '%' . addcslashes($val, '%_\\') . '%';
            match ($op) {
                'from' => $q->where(fn ($w) => $w->where('from_email', 'like', $like)->orWhere('from_name', 'like', $like)),
                'to' => $q->where(fn ($w) => $w->where('to', 'like', $like)->orWhere('cc', 'like', $like)),
                'subject' => $q->where('subject', 'like', $like),
                'has' => $val === 'attachment' ? $q->where('has_attachments', true) : null,
                'is' => match ($val) { 'unread' => $q->where('seen', false), 'read' => $q->where('seen', true), 'starred' => $q->where('flagged', true), default => null },
                'in' => null, // in:anywhere is handled by threads()
                default => $q->where(fn ($w) => $w->where('subject', 'like', $like)->orWhere('from_email', 'like', $like)->orWhere('from_name', 'like', $like)
                    ->orWhere('to', 'like', $like)->orWhere('snippet', 'like', $like)->orWhere('body_text', 'like', $like)),
            };
        }

        return $q;
    }

    /**
     * One row per conversation, newest first: the latest message, how many there are,
     * whether any is unread or starred, and who took part.
     */
    public function threads(MailAccount $account, string $view, ?string $search = null, int $page = 1, int $per = 50): LengthAwarePaginator
    {
        $search = trim((string) $search);
        // Searching from the inbox looks through all mail, like Gmail; "in:anywhere" adds spam and bin.
        $scope = $search !== '' && $view === 'inbox' ? 'search' : $view;
        if (preg_match('/(^|\s)in:anywhere(\s|$)/i', $search)) $scope = 'anywhere';
        $q = $this->scope($account, $scope);
        if ($search) $this->applySearch($q, $search, $account);

        $groups = (clone $q)->select('thread_key', DB::raw('max(sent_at) as last_at'), DB::raw('count(*) as n'),
                DB::raw('sum(case when seen then 0 else 1 end) as unread'), DB::raw('max(case when flagged then 1 else 0 end) as starred'),
                DB::raw('max(case when has_attachments then 1 else 0 end) as files'))
            ->groupBy('thread_key')->orderByDesc('last_at');
        $total = DB::query()->fromSub((clone $groups), 'g')->count();
        $rows = $groups->forPage($page, $per)->get();

        $keys = $rows->pluck('thread_key')->all();
        $all = (clone $q)->whereIn('thread_key', $keys ?: ['-'])->orderBy('sent_at')
            ->get(['id', 'thread_key', 'mail_folder_id', 'subject', 'from_name', 'from_email', 'to', 'snippet', 'sent_at', 'seen', 'snoozed_until'])->groupBy('thread_key');
        $me = mb_strtolower($account->email);
        $items = $rows->map(function ($r) use ($all, $me) {
            $msgs = $all[$r->thread_key] ?? collect();
            $last = $msgs->last();
            $people = $msgs->map(fn ($m) => mb_strtolower((string) $m->from_email) === $me ? 'me' : (Str::before((string) ($m->from_name ?: $m->from_email), ' ') ?: $m->from_email))
                ->unique()->values();

            return (object) [
                'key' => $r->thread_key, 'count' => (int) $r->n, 'unread' => (int) $r->unread > 0, 'starred' => (bool) $r->starred, 'files' => (bool) $r->files,
                'subject' => $msgs->first()?->subject ?: '(no subject)', 'snippet' => $last?->snippet, 'at' => $last?->sent_at,
                'people' => $people->take(3)->implode(', ') . ($people->count() > 3 ? ' …' : ''),
                'to' => $last ? MailMessage::names($last->to) : '', 'snoozed_until' => $msgs->max('snoozed_until'), 'ids' => $msgs->pluck('id')->all(),
            ];
        });

        return new LengthAwarePaginator($items, $total, $per, $page, ['path' => request()->url(), 'query' => request()->query()]);
    }

    /** The messages of one conversation (one copy each, wherever it's filed). */
    public function thread(MailAccount $account, string $key, bool $includeBin = false): Collection
    {
        $bin = $account->folders()->whereIn('role', ['trash', 'junk'])->pluck('id');
        $msgs = MailMessage::with(['attachments', 'folder'])->where('mail_account_id', $account->id)->where('thread_key', $key)
            ->when(!$includeBin, fn ($q) => $q->whereNotIn('mail_folder_id', $bin))->orderBy('sent_at')->orderBy('id')->get();
        if ($msgs->isEmpty() && !$includeBin) return $this->thread($account, $key, true);

        // The same message can sit in two folders (e.g. sent to yourself): show it once.
        return $msgs->groupBy(fn ($m) => $m->message_id ?: 'id' . $m->id)->map(fn ($g) => $g->sortBy(fn ($m) => $m->folder?->role === 'sent' ? 1 : 0)->first())->values();
    }

    public function unreadCounts(MailAccount $account): array
    {
        $folders = $account->folders()->get();
        $out = ['inbox' => $this->scope($account, 'inbox')->where('seen', false)->distinct()->count('thread_key')];
        foreach ($folders as $f) if (!$f->role) $out[(string) $f->id] = $f->messages()->where('seen', false)->distinct()->count('thread_key');
        $out['drafts'] = MailDraft::where('mail_account_id', $account->id)->where('user_id', auth()->id())->where('status', 'draft')->count();
        $out['scheduled'] = MailDraft::where('mail_account_id', $account->id)->where('user_id', auth()->id())->where('status', 'scheduled')->count();
        $out['junk'] = $account->folders()->where('role', 'junk')->value('unread') ?? 0;

        return $out;
    }

    // ── Changes ─────────────────────────────────────────────────────

    /** Run $fn(gateway, folderPath, uids) once per folder for these messages. */
    protected function perFolder(MailAccount $account, Collection $messages, callable $fn): void
    {
        if ($messages->isEmpty()) return;
        $g = Mailboxes::connect($account);
        try {
            foreach ($messages->groupBy('mail_folder_id') as $folderId => $msgs) {
                $folder = MailFolder::find($folderId);
                if ($folder) $fn($g, $folder, $msgs->pluck('uid')->map(fn ($u) => (int) $u)->all(), $msgs);
            }
        } finally {
            $g->close();
        }
    }

    public function messagesOf(MailAccount $account, array $keys, ?string $view = null): Collection
    {
        $q = MailMessage::where('mail_account_id', $account->id)->whereIn('thread_key', $keys);
        // Archive, bin and spam act on the copies in the current folder (e.g. only the inbox ones).
        $folderView = $view !== null && (in_array($view, ['inbox', 'junk', 'trash'], true) || ctype_digit($view));
        if ($folderView) $q->whereIn('mail_messages.id', $this->scope($account, $view)->select('mail_messages.id'));

        return $q->get();
    }

    public function markRead(MailAccount $account, Collection $messages, bool $read): void
    {
        $messages = $messages->filter(fn ($m) => $m->seen !== $read);
        $this->perFolder($account, $messages, fn ($g, $f, $uids) => $g->setFlags($f->path, $uids, ['\Seen'], $read));
        MailMessage::whereIn('id', $messages->pluck('id'))->update(['seen' => $read]);
        $this->recount($account);
    }

    public function star(MailAccount $account, Collection $messages, bool $on): void
    {
        $this->perFolder($account, $messages, fn ($g, $f, $uids) => $g->setFlags($f->path, $uids, ['\Flagged'], $on));
        MailMessage::whereIn('id', $messages->pluck('id'))->update(['flagged' => $on]);
    }

    /** Move to a role folder (archive, trash, junk, inbox) or a label folder. Creates Archive if the server has none. */
    public function move(MailAccount $account, Collection $messages, string $to): ?MailFolder
    {
        $target = ctype_digit($to) ? $account->folders()->find((int) $to) : $account->folders()->where('role', $to)->first();
        if (!$target && $to === 'archive') {
            $g = Mailboxes::connect($account);
            try {
                $g->createFolder('Archive');
            } finally {
                $g->close();
            }
            $target = MailFolder::create(['mail_account_id' => $account->id, 'path' => 'Archive', 'name' => 'Archive', 'role' => 'archive']);
        }
        if (!$target) $this->fail('to', 'That folder is not available on this mailbox.');

        $messages = $messages->where('mail_folder_id', '!=', $target->id);
        if ($messages->isEmpty()) return $target;
        $from = $messages->pluck('mail_folder_id')->unique();
        $this->perFolder($account, $messages, fn ($g, $f, $uids) => $g->move($f->path, $uids, $target->path));
        $this->sync->forgetMessages($messages->pluck('id')->all());
        // Bring the moved copies in under their new UIDs.
        $this->refresh($account, [$target->id, ...$from->all()]);

        return $target;
    }

    /** Delete for good (only from the bin or spam). */
    public function destroy(MailAccount $account, Collection $messages): void
    {
        $this->perFolder($account, $messages, fn ($g, $f, $uids) => $g->delete($f->path, $uids));
        $this->sync->forgetMessages($messages->pluck('id')->all());
        $this->recount($account);
    }

    public function snooze(MailAccount $account, Collection $messages, ?\DateTimeInterface $until): void
    {
        MailMessage::whereIn('id', $messages->pluck('id'))->update(['snoozed_until' => $until]);
        if ($until) $this->markRead($account, $messages, true);
    }

    /** Snoozes that are over: back in the inbox, unread. Returns how many. */
    public function wakeSnoozed(): int
    {
        $n = 0;
        foreach (MailMessage::where('snoozed_until', '<=', now())->with('account')->get()->groupBy('mail_account_id') as $msgs) {
            $account = $msgs->first()->account;
            MailMessage::whereIn('id', $msgs->pluck('id'))->update(['snoozed_until' => null]);
            try { $this->markRead($account, $msgs, false); } catch (\Throwable $e) {}
            $n += $msgs->count();
        }

        return $n;
    }

    /** Re-read some folders now (after a move or a send). */
    public function refresh(MailAccount $account, array $folderIds): void
    {
        $g = Mailboxes::connect($account);
        try {
            foreach (MailFolder::whereIn('id', array_unique($folderIds))->get() as $f) $this->sync->folder($account, $f, $g);
        } finally {
            $g->close();
        }
    }

    protected function recount(MailAccount $account): void
    {
        foreach ($account->folders()->get() as $f) $f->update(['unread' => $f->messages()->where('seen', false)->count()]);
    }

    // ── Writing ─────────────────────────────────────────────────────

    /** "Ada <ada@x.com>, ben@y.com" → Address[]; complains about anything that isn't an address. */
    public function addresses(?string $list, string $field): array
    {
        $out = [];
        foreach (preg_split('/[,;\n]+/', (string) $list) as $part) {
            $part = trim($part);
            if ($part === '') continue;
            if (preg_match('/^(.*)<([^>]+)>$/', $part, $m)) {
                [$name, $email] = [trim($m[1], " \"'"), trim($m[2])];
            } else {
                [$name, $email] = ['', $part];
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $this->fail($field, '"' . mb_strimwidth($part, 0, 60, '…') . '" is not an e-mail address.');
            $out[mb_strtolower($email)] = new Address($email, $name);
        }

        return array_values($out);
    }

    /**
     * Send now. $data: to, cc, bcc, subject, body_html, mode, source_message_id; $files: uploads;
     * $stored: [{path, name, mime, size}] already saved (from a draft or a forward).
     */
    public function send(MailAccount $account, User $by, array $data, array $files = [], array $stored = []): MailMessage|bool
    {
        $to = $this->addresses($data['to'] ?? '', 'to');
        $cc = $this->addresses($data['cc'] ?? '', 'cc');
        $bcc = $this->addresses($data['bcc'] ?? '', 'bcc');
        if (!$to && !$cc && !$bcc) $this->fail('to', 'Add at least one recipient.');
        if (count($to) + count($cc) + count($bcc) > 100) $this->fail('to', 'Send to 100 people or fewer at a time.');
        $max = (int) config('qudroid.mail.max_attachment_mb', 25) * 1048576;
        $size = array_sum(array_map(fn (UploadedFile $f) => $f->getSize(), $files)) + array_sum(array_column($stored, 'size'));
        if ($size > $max) $this->fail('files', 'Attachments add up to more than ' . config('qudroid.mail.max_attachment_mb', 25) . ' MB.');

        $source = !empty($data['source_message_id']) ? MailMessage::where('mail_account_id', $account->id)->find($data['source_message_id']) : null;
        $html = HtmlSanitizer::clean((string) ($data['body_html'] ?? ''));
        $domain = Str::after($account->email, '@') ?: 'qudroid.local';

        $email = (new Email())
            ->from(new Address($account->email, (string) ($account->name ?: $by->name)))
            ->to(...$to)->cc(...$cc)->bcc(...$bcc)
            ->subject(mb_substr(trim((string) ($data['subject'] ?? '')), 0, 500))
            ->html('<!doctype html><html><body>' . $html . '</body></html>')
            ->text(MailParser::htmlToText($html));
        $email->getHeaders()->addIdHeader('Message-ID', Str::uuid() . '@' . $domain);
        if ($source && in_array($data['mode'] ?? '', ['reply', 'reply_all'], true) && $source->message_id) {
            $refs = trim(($source->references ? $source->references . ' ' : '') . $source->message_id);
            $email->getHeaders()->addIdHeader('In-Reply-To', $source->message_id);
            $email->getHeaders()->addIdHeader('References', preg_split('/\s+/', $refs));
        }
        foreach ($files as $f) $email->attachFromPath($f->getRealPath(), $f->getClientOriginalName(), $f->getMimeType() ?: 'application/octet-stream');
        foreach ($stored as $s) $email->attach(Storage::disk('local')->get($s['path']), $s['name'], $s['mime'] ?? 'application/octet-stream');

        try {
            $raw = Mailboxes::send($account, $email);
        } catch (\Throwable $e) {
            $this->fail('to', 'The mail server did not take the message: ' . Mailboxes::friendly($e));
        }

        $sent = $account->folders()->where('role', 'sent')->first();
        $g = Mailboxes::connect($account);
        try {
            if ($sent && config('qudroid.mail.save_sent', true)) $g->append($sent->path, $raw, ['\Seen']);
            if ($source && in_array($data['mode'] ?? '', ['reply', 'reply_all'], true)) {
                $g->setFlags($source->folder->path, [(int) $source->uid], ['\Answered'], true);
                $source->update(['answered' => true]);
            }
            if ($sent) $this->sync->folder($account, $sent, $g);
        } catch (\Throwable $e) {
            // Sent fine; only the copy in Sent failed. The next sync tidies up.
            report($e);
        } finally {
            $g->close();
        }
        $messageId = $email->getHeaders()->get('Message-ID')?->getBodyAsString();

        return MailMessage::where('mail_account_id', $account->id)->where('message_id', trim((string) $messageId, '<> '))->latest('id')->first() ?? true;
    }

    /** Send a saved draft (now, or when its scheduled time comes) and remove it. */
    public function deliverDraft(MailDraft $draft): void
    {
        $this->send($draft->account, $draft->user, [
            'to' => $draft->to, 'cc' => $draft->cc, 'bcc' => $draft->bcc, 'subject' => $draft->subject, 'body_html' => $draft->body_html,
            'mode' => $draft->mode, 'source_message_id' => $draft->source_message_id,
        ], [], $draft->attachments ?? []);
        foreach ($draft->attachments ?? [] as $f) Storage::disk('local')->delete($f['path']);
        $draft->delete();
    }

    /** Scheduled messages whose time has come. A failure goes back to Drafts with the reason, and the writer is told. */
    public function sendScheduled(): array
    {
        $sent = $failed = 0;
        $due = MailDraft::where('status', 'scheduled')->where('send_at', '<=', now())->with(['account', 'user'])->limit(50)->get();
        foreach ($due as $d) {
            // Claim it so two runs never send it twice.
            if (!MailDraft::whereKey($d->id)->where('status', 'scheduled')->update(['status' => 'sending'])) continue;
            try {
                if (!$d->account || !$d->user || !$this->canUse($d->user, $d->account)) throw new \RuntimeException('You no longer have this mailbox.');
                $this->deliverDraft($d);
                $sent++;
            } catch (\Throwable $e) {
                $why = $e instanceof ValidationException ? collect($e->errors())->flatten()->first() : Mailboxes::friendly($e);
                $d->update(['status' => 'draft', 'error' => mb_substr((string) $why, 0, 500)]);
                if ($d->user) PortalNotifier::toUsers([$d->user->id], 'A scheduled e-mail was not sent', ($d->subject ?: '(no subject)') . ': ' . $why . ' It is back in Drafts.',
                    $d->account ? route('mail.box', [$d->account, 'drafts']) : null, 'mail');
                $failed++;
            }
        }

        return ['sent' => $sent, 'failed' => $failed];
    }

    public function saveUploads(MailAccount $account, array $files): array
    {
        $out = [];
        foreach ($files as $f) {
            $path = $f->store('mail/' . $account->id . '/drafts', 'local');
            $out[] = ['path' => $path, 'name' => mb_substr($f->getClientOriginalName(), 0, 255), 'mime' => $f->getMimeType() ?: 'application/octet-stream', 'size' => $f->getSize()];
        }

        return $out;
    }

    /** What a reply / reply all / forward starts with. */
    public function prefill(MailAccount $account, MailMessage $m, string $mode): array
    {
        $me = mb_strtolower($account->email);
        $fmt = fn (array $a) => ($a['name'] ?? '') !== '' ? $a['name'] . ' <' . $a['email'] . '>' : $a['email'];
        $from = ['email' => $m->from_email, 'name' => $m->from_name];
        $replyTo = $m->reply_to[0] ?? null;
        $mineSent = mb_strtolower((string) $m->from_email) === $me;
        $to = $mode === 'forward' ? [] : ($mineSent ? ($m->to ?? []) : [$replyTo ?? $from]);
        $cc = [];
        if ($mode === 'reply_all') {
            $cc = collect([...($mineSent ? [] : ($m->to ?? [])), ...($m->cc ?? [])])->reject(fn ($a) => mb_strtolower($a['email']) === $me)
                ->reject(fn ($a) => collect($to)->contains(fn ($t) => mb_strtolower($t['email'] ?? '') === mb_strtolower($a['email'])))->values()->all();
        }
        $subject = (string) $m->subject;
        $prefix = $mode === 'forward' ? 'Fwd: ' : 'Re: ';
        if (!preg_match('/^' . trim($prefix) . '/i', $subject)) $subject = $prefix . $subject;

        $when = $m->sent_at?->format('D, j M Y \a\t g:i A');
        $body = $m->body_html ? HtmlSanitizer::clean($m->body_html) : nl2br(e((string) $m->body_text));
        $quote = $mode === 'forward'
            ? '<p>---------- Forwarded message ----------<br>From: ' . e($fmt($from)) . '<br>Date: ' . e((string) $when) . '<br>Subject: ' . e((string) $m->subject) . '<br>To: ' . e(collect($m->to)->map($fmt)->implode(', ')) . '</p>' . $body
            : '<p>On ' . e((string) $when) . ', ' . e($fmt($from)) . ' wrote:</p><blockquote>' . $body . '</blockquote>';

        return [
            'to' => collect($to)->map($fmt)->implode(', '), 'cc' => collect($cc)->map($fmt)->implode(', '),
            'subject' => $subject, 'quote' => $quote,
            'attachments' => $mode === 'forward' ? $m->attachments->where('inline', false)->map(fn ($a) => ['path' => $a->path, 'name' => $a->filename, 'mime' => $a->mime, 'size' => $a->size])->values()->all() : [],
        ];
    }

    /** People to suggest in To/Cc: staff, then addresses from this mailbox. */
    public function contacts(MailAccount $account, string $q): array
    {
        $like = '%' . addcslashes($q, '%_\\') . '%';
        $staff = User::where('is_disabled', false)->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('email', 'like', $like))
            ->limit(8)->get(['name', 'email'])->map(fn ($u) => ['name' => $u->name, 'email' => $u->email]);
        $seen = MailMessage::where('mail_account_id', $account->id)->where(fn ($w) => $w->where('from_email', 'like', $like)->orWhere('from_name', 'like', $like))
            ->select('from_email', 'from_name')->distinct()->limit(8)->get()->map(fn ($m) => ['name' => $m->from_name, 'email' => $m->from_email]);
        // Mailbox addresses (shared inboxes and colleagues' Qmail addresses).
        $boxes = MailAccount::where('email', 'like', $like)->orWhere('name', 'like', $like)->limit(5)->get(['name', 'email'])->map(fn ($a) => ['name' => $a->name, 'email' => $a->email]);

        return $boxes->concat($staff)->concat($seen)->filter(fn ($c) => $c['email'])->unique(fn ($c) => mb_strtolower($c['email']))->take(10)->values()->all();
    }
}
