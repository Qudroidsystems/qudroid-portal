<?php

namespace App\Http\Controllers\Mail;

use App\Http\Controllers\Controller;
use App\Models\Mail\MailAccount;
use App\Models\Mail\MailAttachment;
use App\Models\Mail\MailDraft;
use App\Models\Mail\MailFolder;
use App\Models\Mail\MailMessage;
use App\Services\Mail\HtmlSanitizer;
use App\Services\Mail\MailService;
use App\Services\Mail\MailSync;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class MailController extends Controller
{
    public function __construct(protected MailService $mail, protected MailSync $sync) {}

    protected function account(Request $request, MailAccount $account): MailAccount
    {
        abort_unless($this->mail->canUse($request->user(), $account), 403, 'This mailbox is not yours.');

        return $account;
    }

    public function index(Request $request)
    {
        $accounts = $this->mail->accountsFor($request->user());
        if ($accounts->isEmpty()) return redirect()->route('mail.setup');
        $id = (int) $request->query('account');

        return redirect()->route('mail.box', [$accounts->firstWhere('id', $id) ?? $accounts->first(), 'inbox']);
    }

    /** Shared by the list and the conversation pages: sidebar, counts, labels. */
    protected function shell(Request $request, MailAccount $account, string $view): array
    {
        $accounts = $this->mail->accountsFor($request->user());
        $folders = $account->folders()->orderBy('name')->get();

        return [
            'account' => $account, 'accounts' => $accounts, 'view' => $view, 'counts' => $this->mail->unreadCounts($account),
            'labels' => $folders->whereNull('role')->values(), 'roles' => $folders->whereNotNull('role')->keyBy('role'),
            'syncing' => $account->last_synced_at === null,
        ];
    }

    public function box(Request $request, MailAccount $account, string $view = 'inbox')
    {
        $this->account($request, $account);
        abort_unless(in_array($view, MailService::VIEWS, true) || ($account->folders()->whereKey((int) $view)->exists() && ctype_digit($view)), 404);
        $this->syncSoon($account);
        $q = trim((string) $request->query('q', ''));

        if (in_array($view, ['drafts', 'scheduled'], true)) {
            $drafts = MailDraft::where('mail_account_id', $account->id)->where('user_id', $request->user()->id)
                ->where('status', $view === 'drafts' ? 'draft' : 'scheduled')->latest('updated_at')->paginate(50);

            return view('modules.mail.drafts', $this->shell($request, $account, $view) + ['drafts' => $drafts, 'q' => '']);
        }

        $threads = $this->mail->threads($account, $view, $q, max(1, (int) $request->query('page', 1)));
        if ($request->header('X-Live')) return view('modules.mail._list', $this->shell($request, $account, $view) + ['threads' => $threads, 'q' => $q]);

        return view('modules.mail.box', $this->shell($request, $account, $view) + ['threads' => $threads, 'q' => $q]);
    }

    public function thread(Request $request, MailAccount $account, string $thread)
    {
        $this->account($request, $account);
        $view = (string) $request->query('from', 'inbox');
        if (!in_array($view, MailService::VIEWS, true) && !ctype_digit($view)) $view = 'inbox';
        $messages = $this->mail->thread($account, $thread);
        abort_if($messages->isEmpty(), 404);
        // Opening a conversation reads it.
        $unread = MailMessage::where('mail_account_id', $account->id)->where('thread_key', $thread)->where('seen', false)->get();
        if ($unread->isNotEmpty()) {
            try { $this->mail->markRead($account, $unread, true); } catch (\Throwable $e) { report($e); }
        }

        return view('modules.mail.thread', $this->shell($request, $account, $view) + ['messages' => $messages, 'thread' => $thread, 'q' => '',
            'images' => $request->boolean('images')]);
    }

    public function act(Request $request, MailAccount $account)
    {
        $this->account($request, $account);
        $data = $request->validate([
            'action' => ['required', 'string', 'max:40'],
            'threads' => ['array', 'max:500'], 'threads.*' => ['string', 'max:64'],
            'messages' => ['array', 'max:500'], 'messages.*' => ['integer'],
            'view' => ['nullable', 'string', 'max:20'],
            'until' => ['nullable', 'date', 'after:now'],
        ]);
        $view = $data['view'] ?? null;
        $msgs = !empty($data['messages'])
            ? MailMessage::where('mail_account_id', $account->id)->whereIn('id', $data['messages'])->get()
            : $this->mail->messagesOf($account, $data['threads'] ?? [], $view);
        abort_if($msgs->isEmpty(), 422, 'Nothing selected.');
        $a = $data['action'];
        $n = $msgs->pluck('thread_key')->unique()->count();
        $what = $n === 1 ? 'Conversation' : $n . ' conversations';

        $msg = match (true) {
            $a === 'read' => tap('Marked as read.', fn () => $this->mail->markRead($account, $msgs, true)),
            $a === 'unread' => tap('Marked as unread.', fn () => $this->mail->markRead($account, $msgs, false)),
            $a === 'star' => tap('Starred.', fn () => $this->mail->star($account, $msgs, true)),
            $a === 'unstar' => tap('Star removed.', fn () => $this->mail->star($account, $msgs, false)),
            $a === 'archive' => tap($what . ' archived.', fn () => $this->mail->move($account, $msgs, 'archive')),
            $a === 'trash' => tap($what . ' moved to the bin.', fn () => $this->mail->move($account, $msgs, 'trash')),
            $a === 'spam' => tap($what . ' marked as spam.', fn () => $this->mail->move($account, $msgs, 'junk')),
            $a === 'inbox' => tap($what . ' moved to the inbox.', fn () => $this->mail->move($account, $msgs, 'inbox')),
            $a === 'delete' => tap($what . ' deleted for good.', function () use ($account, $msgs) {
                $bin = $account->folders()->whereIn('role', ['trash', 'junk'])->pluck('id')->all();
                abort_if($msgs->whereNotIn('mail_folder_id', $bin)->isNotEmpty(), 422, 'Only mail in the bin or spam can be deleted for good.');
                $this->mail->destroy($account, $msgs);
            }),
            $a === 'snooze' => tap($what . ' snoozed until ' . Carbon::parse($data['until'] ?? now()->addDay())->format('D j M, g:ia') . '.',
                fn () => $this->mail->snooze($account, $msgs, Carbon::parse($data['until'] ?? now()->addDay()->setTime(8, 0)))),
            $a === 'unsnooze' => tap('Back in the inbox.', fn () => $this->mail->snooze($account, $msgs, null)),
            str_starts_with($a, 'move:') => tap($what . ' moved.', fn () => $this->mail->move($account, $msgs, substr($a, 5))),
            default => abort(422, 'Unknown action.'),
        };

        return $request->expectsJson() ? response()->json(['ok' => true, 'message' => $msg, 'counts' => $this->mail->unreadCounts($account)]) : back()->with('success', $msg);
    }

    /** The message itself, for the sandboxed frame. Remote images only when asked. */
    public function body(Request $request, MailAccount $account, MailMessage $message)
    {
        $this->account($request, $account);
        abort_unless($message->mail_account_id === $account->id, 404);
        $html = $message->body_html !== null && trim($message->body_html) !== ''
            ? HtmlSanitizer::display($message->body_html)
            : '<pre style="white-space:pre-wrap;font:inherit;margin:0">' . e((string) $message->body_text) . '</pre>';
        // Pictures sent inside the e-mail (cid:) come from our own attachment store.
        foreach ($message->attachments()->whereNotNull('content_id')->get() as $att) {
            $html = str_ireplace('cid:' . $att->content_id, route('mail.attachment', [$account, $att, 'inline' => 1]), $html);
        }
        $images = $request->boolean('images');
        $self = $request->getSchemeAndHttpHost();
        $csp = "default-src 'none'; style-src 'unsafe-inline'; font-src data:; img-src data: {$self}" . ($images ? ' https: http:' : '') . "; base-uri 'none'; form-action 'none'";
        $doc = '<!doctype html><html><head><meta charset="utf-8"><base target="_blank">'
            . '<style>body{margin:0;padding:4px 2px;font:14px/1.55 -apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:#202124;word-wrap:break-word;overflow-wrap:anywhere}img{max-width:100%;height:auto}blockquote{margin:0 0 0 .6em;padding-left:.8em;border-left:2px solid #dadce0;color:#5f6368}table{max-width:100%}</style>'
            . '</head><body>' . $html . '</body></html>';

        return response($doc, 200, [
            'Content-Type' => 'text/html; charset=utf-8', 'Content-Security-Policy' => $csp, 'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'no-referrer', 'Cache-Control' => 'private, max-age=600',
        ]);
    }

    public function attachment(Request $request, MailAccount $account, MailAttachment $attachment)
    {
        $this->account($request, $account);
        abort_unless($attachment->message?->mail_account_id === $account->id && $attachment->path && Storage::disk('local')->exists($attachment->path), 404);
        $safeInline = $request->boolean('inline') && preg_match('#^image/(png|jpe?g|gif|webp)$#i', (string) $attachment->mime);

        return $safeInline
            ? response()->file(Storage::disk('local')->path($attachment->path), ['Content-Type' => $attachment->mime, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, max-age=86400'])
            : Storage::disk('local')->download($attachment->path, $attachment->filename, ['Content-Type' => $attachment->mime ?: 'application/octet-stream', 'X-Content-Type-Options' => 'nosniff']);
    }

    // ── Writing ─────────────────────────────────────────────────────

    /** What the compose window starts with: a reply, a forward, a saved draft or a blank page. */
    public function compose(Request $request, MailAccount $account)
    {
        $this->account($request, $account);
        $sig = $account->signature ? '<p><br></p><div class="qm-sig">-- <br>' . nl2br(e($account->signature)) . '</div>' : '';
        if ($id = $request->integer('draft')) {
            $d = MailDraft::where('mail_account_id', $account->id)->where('user_id', $request->user()->id)->findOrFail($id);

            return response()->json(['draft_id' => $d->id, 'mode' => $d->mode, 'source_message_id' => $d->source_message_id, 'to' => $d->to, 'cc' => $d->cc, 'bcc' => $d->bcc,
                'subject' => $d->subject, 'body_html' => $d->body_html, 'attachments' => $this->publicFiles($d->attachments ?? []), 'send_at' => $d->send_at?->toIso8601String()]);
        }
        $mode = (string) $request->query('mode', 'new');
        if (in_array($mode, ['reply', 'reply_all', 'forward'], true)) {
            $m = MailMessage::where('mail_account_id', $account->id)->findOrFail($request->integer('message'));
            $p = $this->mail->prefill($account, $m, $mode);
            // Forwarded attachments become the draft's own files.
            $draft = null;
            if ($p['attachments']) {
                $copies = [];
                foreach ($p['attachments'] as $a) {
                    $to = 'mail/' . $account->id . '/drafts/' . uniqid() . '-' . basename($a['path']);
                    Storage::disk('local')->copy($a['path'], $to);
                    $copies[] = ['path' => $to] + $a;
                }
                $draft = MailDraft::create(['mail_account_id' => $account->id, 'user_id' => $request->user()->id, 'mode' => $mode, 'source_message_id' => $m->id,
                    'subject' => $p['subject'], 'attachments' => $copies]);
            }

            return response()->json(['draft_id' => $draft?->id, 'mode' => $mode, 'source_message_id' => $m->id, 'to' => $p['to'], 'cc' => $p['cc'], 'bcc' => '',
                'subject' => $p['subject'], 'body_html' => '<p><br></p>' . $sig . '<br>' . $p['quote'], 'attachments' => $this->publicFiles($draft?->attachments ?? [])]);
        }

        return response()->json(['draft_id' => null, 'mode' => 'new', 'to' => (string) $request->query('to', ''), 'cc' => '', 'bcc' => '', 'subject' => '', 'body_html' => '<p><br></p>' . $sig, 'attachments' => []]);
    }

    protected function publicFiles(array $files): array
    {
        return array_values(array_map(fn ($f, $i) => ['i' => $i, 'name' => $f['name'], 'size' => $f['size']], $files, array_keys($files)));
    }

    protected function draftRules(): array
    {
        return [
            'draft_id' => ['nullable', 'integer'], 'mode' => ['nullable', Rule::in(['new', 'reply', 'reply_all', 'forward'])], 'source_message_id' => ['nullable', 'integer'],
            'to' => ['nullable', 'string', 'max:5000'], 'cc' => ['nullable', 'string', 'max:5000'], 'bcc' => ['nullable', 'string', 'max:5000'],
            'subject' => ['nullable', 'string', 'max:500'], 'body_html' => ['nullable', 'string', 'max:2000000'],
            'files' => ['array', 'max:20'], 'files.*' => ['file', 'max:' . (int) config('qudroid.mail.max_attachment_mb', 25) * 1024],
            'remove_files' => ['array'], 'remove_files.*' => ['integer'],
            'send_at' => ['nullable', 'date', 'after:now'],
        ];
    }

    protected function draftFor(Request $request, MailAccount $account, array $data): MailDraft
    {
        $draft = !empty($data['draft_id'])
            ? MailDraft::where('mail_account_id', $account->id)->where('user_id', $request->user()->id)->findOrFail($data['draft_id'])
            : new MailDraft(['mail_account_id' => $account->id, 'user_id' => $request->user()->id]);
        $files = $draft->attachments ?? [];
        foreach ($data['remove_files'] ?? [] as $i) {
            if (isset($files[$i])) {
                Storage::disk('local')->delete($files[$i]['path']);
                unset($files[$i]);
            }
        }
        $files = array_values([...$files, ...$this->mail->saveUploads($account, $request->file('files', []))]);
        $source = !empty($data['source_message_id']) && MailMessage::where('mail_account_id', $account->id)->whereKey($data['source_message_id'])->exists() ? (int) $data['source_message_id'] : null;
        $draft->fill([
            'mode' => $data['mode'] ?? 'new', 'source_message_id' => $source, 'to' => $data['to'] ?? null, 'cc' => $data['cc'] ?? null, 'bcc' => $data['bcc'] ?? null,
            'subject' => $data['subject'] ?? null, 'body_html' => HtmlSanitizer::clean((string) ($data['body_html'] ?? '')), 'attachments' => $files,
        ])->save();

        return $draft;
    }

    public function saveDraft(Request $request, MailAccount $account)
    {
        $this->account($request, $account);
        $draft = $this->draftFor($request, $account, $request->validate($this->draftRules()));
        // Editing a scheduled message cancels the schedule, as in Gmail.
        if ($draft->status === 'scheduled') $draft->update(['status' => 'draft', 'send_at' => null]);

        return response()->json(['ok' => true, 'draft_id' => $draft->id, 'attachments' => $this->publicFiles($draft->attachments ?? []), 'saved_at' => now()->format('g:i A')]);
    }

    public function send(Request $request, MailAccount $account)
    {
        $this->account($request, $account);
        $data = $request->validate($this->draftRules());
        $draft = $this->draftFor($request, $account, $data);
        // Make sure the addresses are right before saving it for later.
        foreach (['to', 'cc', 'bcc'] as $f) $this->mail->addresses($draft->{$f}, $f);

        if (!empty($data['send_at'])) {
            $at = Carbon::parse($data['send_at']);
            $draft->update(['send_at' => $at, 'status' => 'scheduled', 'error' => null]);

            return response()->json(['ok' => true, 'message' => 'Scheduled for ' . $at->format('D j M, g:i A') . '.', 'scheduled' => true]);
        }

        $this->mail->deliverDraft($draft);

        return response()->json(['ok' => true, 'message' => 'Message sent.', 'counts' => $this->mail->unreadCounts($account)]);
    }

    public function discardDraft(Request $request, MailAccount $account, MailDraft $draft)
    {
        $this->account($request, $account);
        abort_unless($draft->mail_account_id === $account->id && $draft->user_id === $request->user()->id, 404);
        foreach ($draft->attachments ?? [] as $f) Storage::disk('local')->delete($f['path']);
        $draft->delete();

        return $request->expectsJson() ? response()->json(['ok' => true, 'message' => 'Draft discarded.']) : back()->with('success', 'Draft discarded.');
    }

    public function contacts(Request $request, MailAccount $account)
    {
        $this->account($request, $account);
        $q = trim((string) $request->query('q', ''));

        return response()->json(['contacts' => mb_strlen($q) < 1 ? [] : $this->mail->contacts($account, $q)]);
    }

    /** The page asks every so often: counts now, and fresh mail fetched after the response. */
    public function poll(Request $request, MailAccount $account)
    {
        $this->account($request, $account);
        $this->syncSoon($account, 20);

        return response()->json(['counts' => $this->mail->unreadCounts($account), 'synced_at' => $account->last_synced_at?->toIso8601String(),
            'status' => $account->status, 'error' => $account->status === 'error' ? $account->last_error : null,
            'latest' => (int) MailMessage::where('mail_account_id', $account->id)->max('id')]);
    }

    public function syncNow(Request $request, MailAccount $account)
    {
        $this->account($request, $account);
        $r = $this->sync->sync($account);

        return $request->expectsJson() ? response()->json($r) : back()->with($r['ok'] ? 'success' : 'error', $r['ok'] ? 'Up to date.' : 'Could not check mail: ' . ($r['error'] ?? ''));
    }

    /** Fetch new mail after the response if it's been a while. */
    protected function syncSoon(MailAccount $account, int $seconds = 30): void
    {
        if ($account->last_synced_at && $account->last_synced_at->gt(now()->subSeconds($seconds))) return;
        app()->terminating(function () use ($account) {
            try { app(MailSync::class)->sync($account); } catch (\Throwable $e) { report($e); }
        });
    }
}
