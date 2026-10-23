<?php

namespace App\Http\Controllers\Api\V1\Hr;

use App\Http\Controllers\Api\V1\ApiController;
use App\Models\Finance\Expense;
use App\Models\Hr\LeaveRequest;
use App\Services\Hr\LeaveService;
use App\Services\Messaging\NotificationActions;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Everything waiting for my decision, in one list: leave for my team (or
 * everyone, for HR) and expenses (for finance approvers). Deciding goes
 * through NotificationActions, the same path as the notification buttons.
 */
class ApprovalApiController extends ApiController
{
    /** ?type=leave|expense */
    public function index(Request $request, LeaveService $leave)
    {
        $user = $request->user();
        $type = $request->get('type');
        $items = collect();

        if (!$type || $type === 'leave') {
            $items = $items->concat($leave->toDecide($user)->filter(fn ($l) => $leave->canDecide($l, $user))->map(fn ($l) => [
                'id' => $l->id, 'type' => 'leave', 'title' => $l->employee->user->name . ': ' . $l->type?->name,
                'subtitle' => $l->starts_on->format('j M') . ' – ' . $l->ends_on->format('j M Y') . ' · ' . (float) $l->days . ' working days',
                'requested_by' => $this->person($l->employee->user), 'amount_label' => null, 'at' => $l->created_at?->toIso8601String(),
                'details' => array_values(array_filter([
                    ['label' => 'Type', 'value' => $l->type?->name], ['label' => 'From', 'value' => $l->starts_on->format('D j M Y')],
                    ['label' => 'To', 'value' => $l->ends_on->format('D j M Y')], ['label' => 'Working days', 'value' => (string) (float) $l->days],
                    $l->reason ? ['label' => 'Reason', 'value' => $l->reason] : null,
                ])),
                'decline_needs_note' => true, 'web_url' => route('hr.leave.show', $l),
            ]));
        }
        if ((!$type || $type === 'expense') && $user->can('Approve finance')) {
            $items = $items->concat(Expense::with('account', 'submitter')->where('status', 'submitted')->where('submitted_by', '!=', $user->id)->oldest('date')->get()->map(fn ($x) => [
                'id' => $x->id, 'type' => 'expense', 'title' => 'Expense ' . $x->number . ': ' . $x->payee, 'subtitle' => $x->description,
                'requested_by' => $x->submitter ? $this->person($x->submitter) : null,
                'amount' => Money::minor($x->amount), 'amount_label' => Money::format($x->amount, $x->currency), 'at' => $x->created_at?->toIso8601String(),
                'details' => [['label' => 'Date', 'value' => $x->date?->format('j M Y')], ['label' => 'Category', 'value' => $x->account?->name],
                    ['label' => 'Receipt', 'value' => $x->attachment_path ? 'Attached' : 'None']],
                'decline_needs_note' => true, 'web_url' => route('finance.expenses.show', $x),
            ]));
        }

        $items = $items->sortBy('at')->values();

        return $this->ok($items, null, 200, ['counts' => $items->countBy('type')]);
    }

    /** POST approvals/{leave|expense}/{id}/{approve|decline} {note?} — decline needs a note. */
    public function decide(Request $request, string $type, int $id, string $decision, NotificationActions $actions)
    {
        $request->validate(['note' => ['nullable', 'string', 'max:500']]);
        $user = $request->user();
        $item = $type === 'leave' ? LeaveRequest::with('employee')->find($id) : Expense::find($id);
        if (!$item) return $this->fail('Not found.', 404, 'not_found');
        $mayDecide = $type === 'leave' ? app(LeaveService::class)->canDecide($item, $user) : $user->can('Approve finance') && $item->submitted_by !== $user->id;
        if (!$mayDecide) return $this->fail('You can\'t decide this.', 403, 'forbidden');
        if ($item->status !== ($type === 'leave' ? 'pending' : 'submitted')) return $this->fail('This has already been decided.', 409, 'already_decided');

        $action = $decision === 'approve' ? 'approve' : ($type === 'expense' ? 'reject' : 'decline');
        try {
            $done = $actions->perform($type, $id, $user, $action, $request->input('note'));
        } catch (ValidationException $e) {
            return $this->fail(collect($e->errors())->flatten()->first(), 422, 'decision_failed');
        }

        return $this->ok(['type' => $type, 'id' => $id, 'status' => $item->fresh()->status, 'done' => $done], $done . '.');
    }

    protected function person($u): array
    {
        return ['id' => $u->id, 'name' => $u->name, 'avatar' => $u->avatar_url];
    }
}
