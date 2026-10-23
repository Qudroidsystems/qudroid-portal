<?php

namespace App\Http\Controllers\Api\V1\Mail;

use App\Http\Controllers\Api\V1\Concerns\RespondsWithApi;
use App\Http\Controllers\Mail\MailController;
use App\Models\Mail\MailAccount;
use App\Models\Mail\MailAttachment;
use App\Models\Mail\MailDraft;
use App\Models\Mail\MailMessage;
use App\Services\Mail\HtmlSanitizer;
use App\Services\Mail\MailService;
use Illuminate\Http\Request;

/**
 * Qmail for the mobile app. Same rules as the web (it extends the web controller):
 * a mailbox is reachable only by the people it is given to.
 */
class MailApiController extends MailController
{
    use RespondsWithApi;

    protected function accountRow(MailAccount $a): array
    {
        return ['id' => $a->id, 'email' => $a->email, 'name' => $a->name, 'kind' => $a->kind, 'is_default' => (bool) ($a->pivot->is_default ?? false),
            'status' => $a->status, 'error' => $a->status === 'error' ? $a->last_error : null, 'synced_at' => $a->last_synced_at?->toIso8601String(),
            'signature' => $a->signature];
    }

    /** My mailboxes, with unread counts and the labels (other folders) of each. */
    public function accounts(Request $request)
    {
        return $this->ok($this->mail->accountsFor($request->user())->map(fn ($a) => $this->accountRow($a) + [
            'counts' => $this->mail->unreadCounts($a),
            'labels' => $a->folders()->whereNull('role')->orderBy('name')->get(['id', 'name'])->map(fn ($f) => ['id' => $f->id, 'name' => $f->name])->values(),
        ])->values(), null, 200, ['views' => MailService::VIEWS]);
    }

    /** Conversations in a view (inbox, starred, snoozed, sent, archive, junk, trash, or a label id), optionally searched with `q`. */
    public function threads(Request $request, MailAccount $account)
    {
        $this->account($request, $account);
        $view = (string) $request->query('view', 'inbox');
        if (!(in_array($view, MailService::VIEWS, true) && !in_array($view, ['drafts', 'scheduled'], true))
            && !(ctype_digit($view) && $account->folders()->whereKey((int) $view)->exists())) {
            return $this->fail('Unknown view.', 422, 'bad_view');
        }
        $this->syncSoon($account);
        $page = $this->mail->threads($account, $view, (string) $request->query('q', ''), max(1, $request->integer('page', 1)), min(100, max(10, $request->integer('per_page', 50))));

        return $this->paged($page, fn ($t) => [
            'key' => $t->key, 'subject' => $t->subject, 'snippet' => $t->snippet, 'people' => $t->people, 'to' => $t->to, 'count' => $t->count,
            'unread' => $t->unread, 'starred' => $t->starred, 'has_attachments' => $t->files, 'at' => $t->at?->toIso8601String(),
            'snoozed_until' => $t->snoozed_until ? \Illuminate\Support\Carbon::parse($t->snoozed_until)->toIso8601String() : null,
        ]);
    }

    /**
     * One conversation, oldest first, marked read. `body_html` is cleaned (no scripts, forms or
     * frames) and pictures sent inside the mail point at the API; show it in a WebView that blocks
     * remote images until the person taps "Show images" (`has_remote_images`).
     */
    public function thread(Request $request, MailAccount $account, string $thread)
    {
        $this->account($request, $account);
        $messages = $this->mail->thread($account, $thread);
        if ($messages->isEmpty()) return $this->fail('Conversation not found.', 404, 'not_found');
        $unread = $messages->where('seen', false);
        if ($unread->isNotEmpty()) {
            try { $this->mail->markRead($account, $unread, true); } catch (\Throwable $e) { report($e); }
        }

        return $this->ok([
            'key' => $thread, 'subject' => $messages->first()->subject ?: '(no subject)', 'starred' => $messages->contains('flagged', true),
            'folders' => $messages->map(fn ($m) => $m->folder)->filter()->unique('id')->map(fn ($f) => ['id' => $f->id, 'role' => $f->role, 'name' => $f->label()])->values(),
            'messages' => $messages->map(fn ($m) => $this->messageRow($account, $m))->values(),
        ]);
    }

    protected function messageRow(MailAccount $account, MailMessage $m): array
    {
        $html = null;
        if ($m->body_html !== null && trim($m->body_html) !== '') {
            $html = HtmlSanitizer::display($m->body_html);
            foreach ($m->attachments->whereNotNull('content_id') as $att) {
                $html = str_ireplace('cid:' . $att->content_id, route('api.v1.mail.attachment', [$account, $att]), $html);
            }
        }

        return [
            'id' => $m->id, 'message_id' => $m->message_id, 'from' => ['email' => $m->from_email, 'name' => $m->from_name],
            'to' => $m->to ?? [], 'cc' => $m->cc ?? [], 'bcc' => $m->bcc ?? [], 'reply_to' => $m->reply_to ?? [],
            'subject' => $m->subject, 'sent_at' => $m->sent_at?->toIso8601String(), 'snippet' => $m->snippet,
            'body_text' => $m->body_text, 'body_html' => $html,
            'has_remote_images' => (bool) ($m->body_html && preg_match('/(<img[^>]+src\s*=\s*["\']?\s*(https?:)?\/\/|url\(\s*["\']?\s*(https?:)?\/\/)/i', $m->body_html)),
            'seen' => true, 'starred' => (bool) $m->flagged, 'answered' => (bool) $m->answered, 'folder' => $m->folder?->role ?? $m->folder?->name,
            'attachments' => $m->attachments->where('inline', false)->map(fn ($a) => ['id' => $a->id, 'name' => $a->filename, 'mime' => $a->mime, 'size' => $a->size,
                'url' => route('api.v1.mail.attachment', [$account, $a])])->values(),
        ];
    }

    /** Act on conversations (`threads`: keys) or single messages (`messages`: ids). Same actions as the web. */
    public function act(Request $request, MailAccount $account)
    {
        $request->headers->set('Accept', 'application/json');
        $r = parent::act($request, $account)->getData(true);

        return $this->ok(['counts' => $r['counts']], $r['message']);
    }

    public function attachment(Request $request, MailAccount $account, MailAttachment $attachment)
    {
        return parent::attachment($request, $account, $attachment);
    }

    /** Drafts (`status=draft`) or scheduled messages (`status=scheduled`). */
    public function drafts(Request $request, MailAccount $account)
    {
        $this->account($request, $account);
        $status = $request->query('status') === 'scheduled' ? 'scheduled' : 'draft';
        $page = MailDraft::where('mail_account_id', $account->id)->where('user_id', $request->user()->id)->where('status', $status)->latest('updated_at')->paginate(50);

        return $this->paged($page, fn ($d) => ['id' => $d->id, 'mode' => $d->mode, 'to' => $d->to, 'subject' => $d->subject,
            'snippet' => mb_strimwidth(trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags(str_replace(['<br>', '</p>'], ' ', (string) $d->body_html))))), 0, 140, '…'),
            'send_at' => $d->send_at?->toIso8601String(), 'error' => $d->error, 'updated_at' => $d->updated_at?->toIso8601String()]);
    }

    /** What a new message, reply (`mode`+`message`) or saved draft (`draft`) starts with. */
    public function compose(Request $request, MailAccount $account)
    {
        return $this->ok(parent::compose($request, $account)->getData(true));
    }

    /** Save a draft (multipart: to, cc, bcc, subject, body_html, files[], remove_files[], draft_id). */
    public function saveDraft(Request $request, MailAccount $account)
    {
        $d = parent::saveDraft($request, $account)->getData(true);
        unset($d['ok']);

        return $this->ok($d, 'Draft saved.');
    }

    /** Send now, or later with `send_at`. Same fields as a draft. */
    public function send(Request $request, MailAccount $account)
    {
        $d = parent::send($request, $account)->getData(true);

        return $this->ok(['scheduled' => (bool) ($d['scheduled'] ?? false), 'counts' => $d['counts'] ?? null], $d['message']);
    }

    public function discardDraft(Request $request, MailAccount $account, MailDraft $draft)
    {
        $request->headers->set('Accept', 'application/json');
        parent::discardDraft($request, $account, $draft);

        return $this->ok(null, 'Draft discarded.');
    }

    public function contacts(Request $request, MailAccount $account)
    {
        return $this->ok(parent::contacts($request, $account)->getData(true)['contacts']);
    }

    /** Unread counts and the newest message id: refetch the list when `latest` grows. */
    public function poll(Request $request, MailAccount $account)
    {
        return $this->ok(parent::poll($request, $account)->getData(true));
    }

    public function syncNow(Request $request, MailAccount $account)
    {
        $request->headers->set('Accept', 'application/json');
        $r = parent::syncNow($request, $account)->getData(true);

        return $r['ok'] ? $this->ok(['new' => $r['new']], 'Up to date.') : $this->fail('Could not check mail: ' . ($r['error'] ?? ''), 502, 'mail_server');
    }
}
