<?php

namespace App\Services\Mail;

use App\Models\Mail\MailAccount;
use App\Models\Mail\MailAttachment;
use App\Models\Mail\MailFolder;
use App\Models\Mail\MailMessage;
use App\Services\Messaging\PortalNotifier;
use App\Services\Realtime\Realtime;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Brings a mailbox's folders and messages into the portal, so lists, threads and
 * search are fast. The mail server stays the source of truth: changes made here
 * (read, star, move) are written to it straight away, and changes made in other
 * mail apps arrive on the next sync.
 */
class MailSync
{
    /** On the first sync of a folder, only the newest messages. */
    public const FIRST_SYNC = 300;
    /** Messages fetched per round trip. */
    public const CHUNK = 25;
    /** Most new messages taken per folder per sync; the rest come next time. */
    public const PER_RUN = 200;
    /** Recent messages whose read/star state is checked each sync. */
    public const FLAG_WINDOW = 300;

    protected const ROLE_NAMES = [
        'inbox' => ['inbox'],
        'sent' => ['sent', 'sent items', 'sent messages', 'sent mail', '[gmail]/sent mail'],
        'drafts' => ['drafts', 'draft', '[gmail]/drafts'],
        'trash' => ['trash', 'deleted items', 'deleted messages', 'bin', '[gmail]/trash', '[gmail]/bin'],
        'junk' => ['junk', 'spam', 'junk e-mail', 'junk email', '[gmail]/spam'],
        'archive' => ['archive', 'archives', 'all mail', '[gmail]/all mail'],
    ];

    /** @return array{ok: bool, new: int, error?: string} */
    public function sync(MailAccount $account, bool $notify = true): array
    {
        $lock = Cache::lock('mail-sync:' . $account->id, 300);
        if (!$lock->get()) return ['ok' => true, 'new' => 0, 'busy' => true];

        $gateway = null;
        $new = [];
        try {
            $gateway = Mailboxes::connect($account);
            $this->folders($account, $gateway);
            foreach ($account->folders()->orderByRaw("role = 'inbox' desc")->get() as $folder) {
                $added = $this->folder($account, $folder, $gateway);
                if ($folder->role === 'inbox') $new = [...$new, ...$added];
            }
            $first = $account->last_synced_at === null;
            $account->forceFill(['status' => 'ok', 'last_error' => null, 'last_synced_at' => now()])->save();
            if ($notify && !$first && $new) $this->announce($account, $new);

            return ['ok' => true, 'new' => count($new)];
        } catch (\Throwable $e) {
            Log::warning('Mail sync failed', ['account' => $account->email, 'error' => $e->getMessage()]);
            $account->forceFill(['status' => 'error', 'last_error' => mb_substr(Mailboxes::friendly($e), 0, 500), 'last_synced_at' => now()])->save();

            return ['ok' => false, 'new' => 0, 'error' => $account->last_error];
        } finally {
            $gateway?->close();
            $lock->release();
        }
    }

    public function folders(MailAccount $account, Gateway $gateway): void
    {
        $remote = $gateway->folders();
        $seen = [];
        $taken = [];
        foreach ($remote as $path => $info) {
            $flags = array_map('strtolower', (array) ($info['flags'] ?? []));
            if (in_array('\noselect', $flags, true) || in_array('\nonexistent', $flags, true)) continue;
            $role = null;
            foreach (['sent', 'drafts', 'trash', 'junk', 'archive'] as $r) if (in_array('\\' . $r, $flags, true)) $role = $r;
            if (in_array('\all', $flags, true)) $role = 'archive';
            if (!$role) {
                $name = mb_strtolower((string) $path);
                foreach (self::ROLE_NAMES as $r => $names) if (in_array($name, $names, true)) $role = $r;
            }
            if (strtoupper((string) $path) === 'INBOX') $role = 'inbox';
            if ($role && isset($taken[$role])) $role = null; // one folder per role
            if ($role) $taken[$role] = true;

            $delim = (string) ($info['delimiter'] ?? '/') ?: '/';
            $name = strtoupper((string) $path) === 'INBOX' ? 'Inbox' : (string) last(explode($delim, (string) $path));
            $folder = MailFolder::firstOrNew(['mail_account_id' => $account->id, 'path' => (string) $path]);
            $folder->fill(['name' => mb_substr(mb_convert_encoding($name, 'UTF-8', 'UTF7-IMAP'), 0, 191), 'role' => $role, 'delimiter' => mb_substr($delim, 0, 4)])->save();
            $seen[] = $folder->id;
        }
        // Folders removed on the server go here too (their messages with them).
        $account->folders()->whereNotIn('id', $seen ?: [0])->get()->each(fn ($f) => $this->forgetFolder($f));
    }

    /** @return MailMessage[] messages added to this folder */
    public function folder(MailAccount $account, MailFolder $folder, Gateway $gateway): array
    {
        $status = $gateway->status($folder->path);
        if ($folder->uidvalidity && $status['uidvalidity'] && (int) $folder->uidvalidity !== (int) $status['uidvalidity']) {
            // The server renumbered the folder: start it again.
            $this->forgetMessages($folder->messages()->pluck('id')->all());
            $folder->last_uid = 0;
        }

        $remote = $gateway->uids($folder->path);
        sort($remote);
        $local = $folder->messages()->pluck('uid')->map(fn ($u) => (int) $u)->all();
        $gone = array_diff($local, $remote);
        if ($gone) $this->forgetMessages($folder->messages()->whereIn('uid', $gone)->pluck('id')->all());

        $todo = array_values(array_filter($remote, fn ($u) => $u > (int) $folder->last_uid));
        if ((int) $folder->last_uid === 0 && !$local) {
            // First look at this folder: only the newest messages, newest first.
            $todo = array_reverse(array_slice($todo, -self::FIRST_SYNC));
            $upTo = $remote ? max($remote) : 0;
        } else {
            // Oldest new ones first; anything past PER_RUN comes on the next sync.
            $todo = array_slice($todo, 0, self::PER_RUN);
            $upTo = $todo ? max($todo) : (int) $folder->last_uid;
        }
        $added = [];
        foreach (array_chunk($todo, self::CHUNK) as $chunk) {
            foreach ($gateway->fetch($folder->path, $chunk) as $uid => $m) {
                if ($msg = $this->store($account, $folder, (int) $uid, $m)) $added[] = $msg;
            }
        }

        // Read / starred / answered changes made elsewhere.
        $recent = array_slice(array_values(array_diff($local, $gone)), -self::FLAG_WINDOW);
        if ($recent) {
            $byUid = $folder->messages()->whereIn('uid', $recent)->get(['id', 'uid', 'seen', 'flagged', 'answered'])->keyBy('uid');
            foreach ($gateway->flags($folder->path, $recent) as $uid => $flags) {
                $row = $byUid[$uid] ?? null;
                if (!$row) continue;
                $f = self::flagState($flags);
                if ($row->seen !== $f['seen'] || $row->flagged !== $f['flagged'] || $row->answered !== $f['answered']) {
                    MailMessage::whereKey($row->id)->update($f);
                }
            }
        }

        $folder->fill([
            'uidvalidity' => $status['uidvalidity'] ?: $folder->uidvalidity,
            'last_uid' => max((int) $folder->last_uid, $upTo),
            'total' => count($remote),
            'unread' => $folder->messages()->where('seen', false)->count(),
        ])->save();

        return $added;
    }

    public static function flagState(array $flags): array
    {
        $flags = array_map('strtolower', $flags);

        return ['seen' => in_array('\seen', $flags, true), 'flagged' => in_array('\flagged', $flags, true), 'answered' => in_array('\answered', $flags, true)];
    }

    public function store(MailAccount $account, MailFolder $folder, int $uid, array $m): ?MailMessage
    {
        try {
            $p = MailParser::parse($m['raw']);
        } catch (\Throwable $e) {
            Log::info('Unreadable mail skipped', ['account' => $account->email, 'folder' => $folder->path, 'uid' => $uid, 'error' => $e->getMessage()]);
            $p = ['message_id' => null, 'in_reply_to' => null, 'references' => [], 'subject' => '(this message could not be read)', 'from_email' => null, 'from_name' => null,
                'to' => [], 'cc' => [], 'bcc' => [], 'reply_to' => [], 'sent_at' => now(), 'body_text' => null, 'body_html' => null, 'snippet' => '', 'attachments' => []];
        }

        // Join the conversation of the message it answers, if we have it.
        $key = null;
        foreach (array_filter([$p['in_reply_to'], ...array_reverse($p['references'])]) as $ref) {
            if ($key = MailMessage::where('mail_account_id', $account->id)->where('message_id', $ref)->value('thread_key')) break;
        }
        if (!$key && $p['message_id']) {
            // A message we already hold in another folder (Sent and Inbox both have it).
            $key = MailMessage::where('mail_account_id', $account->id)->where('message_id', $p['message_id'])->value('thread_key');
        }
        $key ??= MailParser::threadKey($p['message_id'], $p['in_reply_to'], $p['references'], $p['subject']);

        $msg = MailMessage::updateOrCreate(['mail_folder_id' => $folder->id, 'uid' => $uid], [
            'mail_account_id' => $account->id, 'message_id' => $p['message_id'], 'in_reply_to' => $p['in_reply_to'],
            'references' => $p['references'] ? implode(' ', $p['references']) : null, 'thread_key' => $key,
            'subject' => $p['subject'], 'from_name' => $p['from_name'] ? mb_substr($p['from_name'], 0, 191) : null, 'from_email' => $p['from_email'] ? mb_substr($p['from_email'], 0, 191) : null,
            'to' => $p['to'], 'cc' => $p['cc'], 'bcc' => $p['bcc'], 'reply_to' => $p['reply_to'],
            'sent_at' => $p['sent_at'] ?? now(), 'snippet' => mb_substr($p['snippet'], 0, 300),
            'body_text' => $p['body_text'], 'body_html' => $p['body_html'],
            'has_attachments' => (bool) collect($p['attachments'])->firstWhere('inline', false), 'size' => (int) ($m['size'] ?? 0),
        ] + self::flagState($m['flags'] ?? []));

        foreach ($p['attachments'] as $i => $a) {
            $path = 'mail/' . $account->id . '/' . $msg->id . '/' . $i . '-' . (preg_replace('/[^A-Za-z0-9._-]+/', '_', $a['filename']) ?: 'file');
            Storage::disk('local')->put($path, $a['content']);
            MailAttachment::create(['mail_message_id' => $msg->id, 'filename' => $a['filename'], 'mime' => $a['mime'] ?: 'application/octet-stream',
                'size' => $a['size'], 'content_id' => $a['content_id'], 'inline' => (bool) $a['inline'], 'path' => $path]);
        }

        return $msg;
    }

    public function forgetMessages(array $ids): void
    {
        if (!$ids) return;
        foreach (array_chunk($ids, 500) as $chunk) {
            foreach (MailAttachment::whereIn('mail_message_id', $chunk)->pluck('path') as $p) if ($p) Storage::disk('local')->delete($p);
            MailMessage::whereIn('id', $chunk)->delete();
        }
    }

    protected function forgetFolder(MailFolder $folder): void
    {
        $this->forgetMessages($folder->messages()->pluck('id')->all());
        $folder->delete();
    }

    /** New mail in the inbox: a bell entry (and phone push) for the people on the account. */
    protected function announce(MailAccount $account, array $messages): void
    {
        $users = $account->users()->pluck('users.id')->all();
        $mine = array_map('strtolower', [$account->email]);
        $incoming = array_values(array_filter($messages, fn (MailMessage $m) => !in_array(strtolower((string) $m->from_email), $mine, true) && !$m->seen));
        if (!$incoming || !$users) return;
        Realtime::toUsers($users, 'mail', ['account' => $account->id]);
        if (count($incoming) > 3) {
            PortalNotifier::toUsers($users, count($incoming) . ' new e-mails', 'In ' . $account->email, route('mail.index', ['account' => $account->id]), 'mail');

            return;
        }
        foreach ($incoming as $m) {
            PortalNotifier::toUsers($users, $m->fromLabel() . ': ' . ($m->subject ?: '(no subject)'), (string) $m->snippet,
                route('mail.thread', [$account->id, $m->thread_key]), 'mail', 'mail:' . $m->id);
        }
    }
}
