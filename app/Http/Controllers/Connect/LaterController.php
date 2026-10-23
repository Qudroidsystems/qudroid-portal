<?php

namespace App\Http\Controllers\Connect;

use App\Http\Controllers\Controller;
use App\Models\Connect\Conversation;
use App\Models\Connect\Message;
use App\Models\Connect\Reminder;
use App\Models\Connect\ScheduledMessage;
use App\Services\Connect\LaterService;
use Illuminate\Http\Request;

/** Scheduled messages and reminders ("Later"). */
class LaterController extends Controller
{
    public function __construct(protected LaterService $later) {}

    public function index(Request $request)
    {
        $me = $request->user();
        $reminders = Reminder::with('message.user', 'message.conversation')->where('user_id', $me->id)
            ->where(fn ($q) => $q->whereNull('done_at')->orWhere('done_at', '>=', now()->subDays(7)))
            ->orderByRaw('case when done_at is null then 0 else 1 end')->orderBy('remind_at')->get();
        $scheduled = ScheduledMessage::with('conversation')->where('user_id', $me->id)
            ->where(fn ($q) => $q->whereNull('sent_at')->orWhere('sent_at', '>=', now()->subDays(7)))
            ->orderByRaw('case when sent_at is null then 0 else 1 end')->orderBy('send_at')->get();

        return view('modules.connect.later', [
            'me' => $me, 'reminders' => $reminders, 'scheduled' => $scheduled, 'choices' => LaterService::CHOICES,
        ]);
    }

    public function schedule(Request $request, Conversation $conversation)
    {
        $data = $this->validated($request, ['body' => ['required', 'string', 'max:5000'], 'reply_to_id' => ['nullable', 'integer'], 'thread_id' => ['nullable', 'integer']]);
        $s = $this->later->schedule($conversation, $request->user(), $data['body'], LaterService::when($data['when'] ?? null, $data['at'] ?? null), $data['reply_to_id'] ?? null, $data['thread_id'] ?? null);

        return $this->done($request, 'Scheduled for ' . $s->send_at->format('D j M, g:i a') . '.', ['id' => $s->id, 'send_at' => $s->send_at->toIso8601String()]);
    }

    public function update(Request $request, ScheduledMessage $scheduled)
    {
        $data = $this->validated($request, ['body' => ['nullable', 'string', 'max:5000']]);
        $this->later->reschedule($scheduled, $request->user(), LaterService::when($data['when'] ?? null, $data['at'] ?? null), $data['body'] ?? null);

        return $this->done($request, 'Scheduled message updated.');
    }

    public function sendNow(Request $request, ScheduledMessage $scheduled)
    {
        $m = $this->later->sendNow($scheduled, $request->user());

        return $m ? $this->done($request, 'Sent.') : back()->with('error', 'It could not be sent: ' . $scheduled->failed_reason);
    }

    public function cancel(Request $request, ScheduledMessage $scheduled)
    {
        $this->later->cancel($scheduled, $request->user());

        return $this->done($request, 'Scheduled message cancelled.');
    }

    public function remind(Request $request)
    {
        $data = $this->validated($request, ['message_id' => ['nullable', 'integer'], 'note' => ['nullable', 'string', 'max:255']]);
        $m = !empty($data['message_id']) ? Message::with('conversation')->findOrFail($data['message_id']) : null;
        $r = $this->later->remind($request->user(), $m, LaterService::when($data['when'] ?? null, $data['at'] ?? null), $data['note'] ?? null);

        return $this->done($request, 'I\'ll remind you ' . ($r->remind_at->isToday() ? 'at ' . $r->remind_at->format('g:i a') : $r->remind_at->format('D j M, g:i a')) . '.', ['id' => $r->id]);
    }

    public function complete(Request $request, Reminder $reminder)
    {
        $this->later->complete($reminder, $request->user(), !$request->boolean('undo'));

        return $this->done($request, $request->boolean('undo') ? 'Reminder reopened.' : 'Marked done.');
    }

    public function snooze(Request $request, Reminder $reminder)
    {
        $data = $this->validated($request, []);
        $this->later->snooze($reminder, $request->user(), LaterService::when($data['when'] ?? null, $data['at'] ?? null));

        return $this->done($request, 'Snoozed.');
    }

    public function destroy(Request $request, Reminder $reminder)
    {
        $this->later->delete($reminder, $request->user());

        return $this->done($request, 'Reminder deleted.');
    }

    protected function validated(Request $request, array $rules): array
    {
        return $request->validate($rules + [
            'when' => ['nullable', 'string', 'max:20'],
            'at'   => ['nullable', 'date'],
        ]);
    }

    protected function done(Request $request, string $message, array $extra = [])
    {
        return $request->expectsJson()
            ? response()->json(['ok' => true, 'message' => $message] + $extra)
            : back()->with('success', $message);
    }
}
