<?php

namespace App\Services\Messaging;

use App\Models\Connect\Conversation;
use App\Models\Finance\Expense;
use App\Models\Hr\LeaveRequest;
use App\Models\Meetings\Meeting;
use App\Models\User;
use App\Services\Connect\ConnectService;
use App\Services\Finance\ExpenseService;
use App\Services\Hr\LeaveService;
use App\Services\Meetings\MeetingService;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Validation\ValidationException;

/**
 * Buttons on a notification ("Approve", "Reply", "Going"), for the web
 * notification centre and the mobile app. A notification names its target
 * (`target: {kind, id}`) and its buttons; this runs one through the same
 * service the page uses, so the usual permission checks apply. After a
 * button is used the notification is marked read and shows what was done.
 */
class NotificationActions
{
    /** The buttons each kind of target can have. */
    public const BUTTONS = [
        'leave'   => [['id' => 'approve', 'label' => 'Approve', 'style' => 'primary'], ['id' => 'decline', 'label' => 'Decline', 'style' => 'destructive', 'input' => 'Reason']],
        'expense' => [['id' => 'approve', 'label' => 'Approve', 'style' => 'primary'], ['id' => 'reject', 'label' => 'Reject', 'style' => 'destructive', 'input' => 'Reason']],
        'meeting' => [['id' => 'accepted', 'label' => 'Going', 'style' => 'primary'], ['id' => 'tentative', 'label' => 'Maybe'], ['id' => 'declined', 'label' => 'Not going', 'style' => 'destructive']],
        'message' => [['id' => 'reply', 'label' => 'Reply', 'style' => 'primary', 'input' => 'Reply…']],
    ];

    public static function for(string $kind): array
    {
        return self::BUTTONS[$kind] ?? [];
    }

    /** @return string what happened, e.g. "Approved" */
    public function run(DatabaseNotification $n, User $user, string $action, ?string $input = null): string
    {
        $data = (array) $n->data;
        $target = (array) ($data['target'] ?? []);
        $buttons = collect($data['actions'] ?? [])->keyBy('id');
        if (!$buttons->has($action) || empty($target['kind']) || empty($target['id'])) $this->fail('This notification has no such button.');
        if (!empty($data['done'])) $this->fail('Already done: ' . $data['done'] . '.');
        $input = trim((string) $input) ?: null;

        $done = match ($target['kind']) {
            'leave' => $this->leave((int) $target['id'], $user, $action, $input),
            'expense' => $this->expense((int) $target['id'], $user, $action, $input),
            'meeting' => $this->meeting((int) $target['id'], $user, $action),
            'message' => $this->reply($target, $user, $input),
            default => $this->fail('This notification has no such button.'),
        };

        $n->forceFill(['data' => $data + ['done' => $done], 'read_at' => $n->read_at ?? now()])->save();

        return $done;
    }

    protected function leave(int $id, User $user, string $action, ?string $note): string
    {
        $leave = LeaveRequest::findOrFail($id);
        // Someone else got there first: show what happened instead of an error.
        if ($leave->status !== 'pending') return 'Already ' . $leave->status;
        app(LeaveService::class)->decide($leave, $user, $action === 'approve', $note);

        return $action === 'approve' ? 'Approved' : 'Declined';
    }

    protected function expense(int $id, User $user, string $action, ?string $reason): string
    {
        $expense = Expense::findOrFail($id);
        $svc = app(ExpenseService::class);
        if ($expense->status !== 'submitted') return 'Already ' . $expense->status;
        if ($action === 'approve') {
            $svc->approve($expense, $user);

            return 'Approved';
        }
        if (!$reason) $this->fail('Give a reason.');
        $svc->reject($expense, $user, $reason);

        return 'Rejected';
    }

    protected function meeting(int $id, User $user, string $action): string
    {
        $meeting = Meeting::findOrFail($id);
        app(MeetingService::class)->respond($meeting, $user, $action);

        return ['accepted' => 'Going', 'tentative' => 'Maybe', 'declined' => 'Not going'][$action] ?? 'Answered';
    }

    protected function reply(array $target, User $user, ?string $text): string
    {
        if (!$text) $this->fail('Write a reply.');
        $c = Conversation::findOrFail((int) $target['id']);
        $connect = app(ConnectService::class);
        if (!$connect->canView($c, $user)) $this->fail('You are no longer in this conversation.');
        $connect->send($c, $user, mb_substr($text, 0, 4000), null, null, isset($target['thread']) ? (int) $target['thread'] : null);
        $connect->markRead($c, $user);

        return 'Replied';
    }

    protected function fail(string $message): never
    {
        throw ValidationException::withMessages(['action' => $message]);
    }
}
