<?php

namespace App\Services\Connect;

use App\Models\Connect\Conversation;
use App\Models\Connect\Message;
use App\Models\Connect\Reminder;
use App\Models\Connect\ScheduledMessage;
use App\Models\User;
use App\Services\Messaging\PortalNotifier;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * "Later" in Connect: messages scheduled to send at a set time, and
 * reminders about a message (or just a note) for yourself. The
 * connect:later command, every minute, sends what is due.
 */
class LaterService
{
    /** The quick choices offered in the menus: key => label. */
    public const CHOICES = [
        '20'        => 'In 20 minutes',
        '60'        => 'In 1 hour',
        '180'       => 'In 3 hours',
        'tomorrow9' => 'Tomorrow at 9:00',
        'monday9'   => 'Next Monday at 9:00',
    ];

    public function __construct(protected ConnectService $connect) {}

    /** Turn a quick choice or a typed date and time into a moment. */
    public static function when(?string $choice, ?string $at = null): ?Carbon
    {
        $t = match (true) {
            ctype_digit((string) $choice) => now()->addMinutes(min((int) $choice, 60 * 24 * 365)),
            $choice === 'tomorrow9' => now()->addDay()->setTime(9, 0),
            $choice === 'monday9' => now()->next(Carbon::MONDAY)->setTime(9, 0),
            default => null,
        };
        if (!$t && $at) {
            try { $t = Carbon::parse($at); } catch (\Throwable $e) { $t = null; }
        }

        return $t?->second(0);
    }

    // ── Scheduled messages ──────────────────────────────────────────────

    public function schedule(Conversation $c, User $me, string $body, ?Carbon $at, ?int $replyTo = null, ?int $threadId = null): ScheduledMessage
    {
        $body = trim($body);
        $this->check(!$this->connect->participant($c, $me), 'body', 'You are not in this conversation.');
        $this->check($body === '', 'body', 'Write the message to schedule.');
        $this->check(mb_strlen($body) > 5000, 'body', 'That message is too long.');
        $this->checkTime($at, 'send_at');

        return ScheduledMessage::create([
            'conversation_id' => $c->id, 'user_id' => $me->id, 'body' => $body, 'send_at' => $at,
            'reply_to_id' => $replyTo ?: null, 'thread_id' => $threadId ?: null,
        ]);
    }

    public function reschedule(ScheduledMessage $s, User $me, ?Carbon $at, ?string $body = null): ScheduledMessage
    {
        $this->own($s, $me);
        $this->checkTime($at, 'send_at');
        $data = ['send_at' => $at, 'failed_reason' => null];
        if ($body !== null) {
            $body = trim($body);
            $this->check($body === '' || mb_strlen($body) > 5000, 'body', 'Write a message of up to 5000 characters.');
            $data['body'] = $body;
        }
        $s->update($data);

        return $s;
    }

    public function cancel(ScheduledMessage $s, User $me): void
    {
        $this->own($s, $me);
        $s->delete();
    }

    /** Send one now (from the list, or when due). Returns the message, or null with the reason saved. */
    public function deliver(ScheduledMessage $s): ?Message
    {
        $s->loadMissing('conversation', 'user');
        try {
            if (!$s->conversation || !$s->user || $s->user->is_disabled) {
                throw ValidationException::withMessages(['body' => 'The conversation or the sender no longer exists.']);
            }
            $m = $this->connect->send($s->conversation, $s->user, $s->body, null, $s->reply_to_id, $s->thread_id);
            $s->update(['sent_at' => now(), 'message_id' => $m->id]);

            return $m;
        } catch (ValidationException $e) {
            $reason = collect($e->errors())->flatten()->first() ?? 'It could not be sent.';
            ScheduledMessage::whereKey($s->id)->update(['failed_reason' => Str::limit($reason, 250), 'sent_at' => null, 'updated_at' => now()]);
            $s->forceFill(['failed_reason' => Str::limit($reason, 250), 'sent_at' => null])->syncOriginal();
            if ($s->user) {
                PortalNotifier::toUsers([$s->user_id], 'A scheduled message was not sent', $reason . ' "' . Str::limit($s->body, 80) . '"',
                    route('connect.later'), 'reminder', 'connect-scheduled-failed:' . $s->id);
            }

            return null;
        }
    }

    public function sendNow(ScheduledMessage $s, User $me): ?Message
    {
        $this->own($s, $me);

        return $this->deliver($s);
    }

    // ── Reminders ───────────────────────────────────────────────────────

    public function remind(User $me, ?Message $m, ?Carbon $at, ?string $note = null): Reminder
    {
        $note = trim((string) $note);
        $this->check(!$m && $note === '', 'note', 'Say what to remind you about.');
        $this->check($m && !$this->connect->canView($m->conversation, $me), 'note', 'You cannot see that message.');
        $this->checkTime($at, 'remind_at');

        return Reminder::create(['user_id' => $me->id, 'message_id' => $m?->id, 'note' => $note !== '' ? Str::limit($note, 250) : null, 'remind_at' => $at]);
    }

    public function complete(Reminder $r, User $me, bool $done = true): void
    {
        $this->check($r->user_id !== $me->id, 'note', 'That reminder is not yours.');
        $r->update(['done_at' => $done ? now() : null]);
    }

    public function snooze(Reminder $r, User $me, ?Carbon $at): void
    {
        $this->check($r->user_id !== $me->id, 'note', 'That reminder is not yours.');
        $this->checkTime($at, 'remind_at');
        $r->update(['remind_at' => $at, 'reminded_at' => null, 'done_at' => null]);
    }

    public function delete(Reminder $r, User $me): void
    {
        $this->check($r->user_id !== $me->id, 'note', 'That reminder is not yours.');
        $r->delete();
    }

    public static function reminderUrl(Reminder $r): string
    {
        $m = $r->message;
        if (!$m) return route('connect.later');

        return $m->thread_id
            ? route('connect.show', [$m->conversation_id, 'around' => $m->thread_id, 'thread' => $m->thread_id])
            : route('connect.show', [$m->conversation_id, 'around' => $m->id]) . '#m' . $m->id;
    }

    // ── The every-minute run ────────────────────────────────────────────

    /** @return array{sent: int, failed: int, reminded: int} */
    public function runDue(): array
    {
        $sent = $failed = $reminded = 0;
        ScheduledMessage::pending()->where('send_at', '<=', now())->orderBy('send_at')->limit(200)->get()->each(function ($s) use (&$sent, &$failed) {
            // claim it so two runs never send the same message
            if (!ScheduledMessage::whereKey($s->id)->whereNull('sent_at')->whereNull('failed_reason')->update(['sent_at' => now()])) return;
            $s->sent_at = null;
            $this->deliver($s) ? $sent++ : $failed++;
        });

        Reminder::with('message.user', 'message.conversation')->open()->whereNull('reminded_at')->where('remind_at', '<=', now())->limit(500)->get()
            ->each(function (Reminder $r) use (&$reminded) {
                if (!Reminder::whereKey($r->id)->whereNull('reminded_at')->update(['reminded_at' => now()])) return;
                $m = $r->message;
                $title = $m ? 'Reminder: message from ' . ($m->user?->name ?? 'someone') : 'Reminder';
                $body = trim(($r->note ? $r->note . ($m ? ' · ' : '') : '') . ($m ? '"' . $m->preview(100) . '"' : ''));
                PortalNotifier::toUsers([$r->user_id], $title, $body !== '' ? $body : 'You asked to be reminded.', self::reminderUrl($r), 'reminder', 'connect-reminder:' . $r->id . ':' . $r->remind_at->timestamp);
                $reminded++;
            });

        return compact('sent', 'failed', 'reminded');
    }

    protected function own(ScheduledMessage $s, User $me): void
    {
        $this->check($s->user_id !== $me->id, 'body', 'That scheduled message is not yours.');
        $this->check($s->sent_at !== null, 'body', 'That message has already been sent.');
    }

    protected function checkTime(?Carbon $at, string $field): void
    {
        $this->check(!$at, $field, 'Pick a time.');
        $this->check($at->lt(now()->addMinute()->second(0)), $field, 'Pick a time in the future.');
        $this->check($at->gt(now()->addYear()), $field, 'Pick a time within the next year.');
    }

    protected function check(bool $failed, string $field, string $message): void
    {
        if ($failed) throw ValidationException::withMessages([$field => $message]);
    }
}
