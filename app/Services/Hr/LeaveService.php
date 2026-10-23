<?php

namespace App\Services\Hr;

use App\Models\Hr\Employee;
use App\Models\Hr\Holiday;
use App\Models\Hr\LeaveRequest;
use App\Models\Hr\LeaveType;
use App\Models\User;
use App\Services\Messaging\NotificationActions;
use App\Services\Messaging\PortalNotifier;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Leave: an employee asks for days off, their manager (or anyone with
 * Manage employees) approves or declines. Days are counted in working
 * days: weekends and public holidays don't use up leave.
 */
class LeaveService
{
    /** Working days from $from to $to inclusive. */
    public function workingDays(Carbon $from, Carbon $to): int
    {
        if ($to->lt($from)) return 0;
        $holidays = Holiday::whereDate('date', '>=', $from)->whereDate('date', '<=', $to)->get()
            ->map(fn ($h) => $h->date->toDateString())->all();

        $days = 0;
        for ($d = $from->copy()->startOfDay(); $d->lte($to); $d->addDay()) {
            if (!$d->isWeekend() && !in_array($d->toDateString(), $holidays, true)) $days++;
        }

        return $days;
    }

    /** Days used (approved) and waiting (pending) of each type in a year. */
    public function balances(Employee $employee, ?int $year = null): Collection
    {
        $year ??= (int) today()->year;
        $taken = $employee->leaveRequests()->whereIn('status', ['approved', 'pending'])
            ->whereYear('starts_on', $year)->get()->groupBy('leave_type_id');

        return LeaveType::where('is_active', true)->orderByDesc('is_annual')->orderBy('name')->get()->map(function (LeaveType $t) use ($employee, $taken) {
            $rows = $taken->get($t->id, collect());
            $used = (float) $rows->where('status', 'approved')->sum('days');
            $pending = (float) $rows->where('status', 'pending')->sum('days');
            $allowed = $t->allowanceFor($employee);

            return (object) ['type' => $t, 'allowed' => $allowed, 'used' => $used, 'pending' => $pending,
                'left' => $allowed === null ? null : max(0, $allowed - $used - $pending)];
        });
    }

    public function request(Employee $employee, array $data, User $by): LeaveRequest
    {
        $this->check($employee->status !== 'active', 'leave_type_id', 'Only active employees can ask for leave.');
        $type = LeaveType::where('is_active', true)->findOrFail($data['leave_type_id']);
        $from = Carbon::parse($data['starts_on'])->startOfDay();
        $to = Carbon::parse($data['ends_on'])->startOfDay();
        $this->check($to->lt($from), 'ends_on', 'The last day can\'t be before the first.');
        $this->check($from->year !== $to->year, 'ends_on', 'Leave can\'t run into the next year. Make two requests.');
        $days = $this->workingDays($from, $to);
        $this->check($days === 0, 'starts_on', 'Those dates are all weekends or public holidays.');

        $overlap = $employee->leaveRequests()->whereIn('status', ['pending', 'approved'])
            ->whereDate('starts_on', '<=', $to)->whereDate('ends_on', '>=', $from)->exists();
        $this->check($overlap, 'starts_on', 'You already have leave booked on some of those days.');

        $balance = $this->balances($employee, $from->year)->firstWhere('type.id', $type->id);
        $this->check($balance && $balance->left !== null && $days > $balance->left, 'ends_on',
            "That is {$days} working days, but only {$balance?->left} days of {$type->name} are left this year.");

        $leave = $employee->leaveRequests()->create([
            'leave_type_id' => $type->id, 'starts_on' => $from, 'ends_on' => $to, 'days' => $days,
            'reason' => isset($data['reason']) ? mb_substr(trim($data['reason']), 0, 500) : null, 'status' => 'pending',
        ]);

        $deciders = array_diff($this->deciderIds($employee), [$employee->user_id]);
        PortalNotifier::toUsers($deciders, 'Leave request: ' . $employee->user->name,
            "{$type->name}, {$days} working days from " . $from->format('j M') . ' to ' . $to->format('j M Y') . '.',
            route('hr.leave.show', $leave), 'leave', 'leave:' . $leave->id . ':request',
            ['target' => ['kind' => 'leave', 'id' => $leave->id], 'actions' => NotificationActions::for('leave')]);

        return $leave;
    }

    public function canDecide(LeaveRequest $leave, User $user): bool
    {
        if ($leave->employee->user_id === $user->id) return false;

        return $leave->employee->manager_id === $user->id || $user->can('Manage employees');
    }

    public function decide(LeaveRequest $leave, User $by, bool $approve, ?string $note = null): LeaveRequest
    {
        $this->check($leave->status !== 'pending', 'status', 'This request has already been decided.');
        $this->check(!$this->canDecide($leave, $by), 'status', 'Only their manager or HR can decide this request.');
        $this->check(!$approve && trim((string) $note) === '', 'decision_note', 'Say why you are declining.');

        $leave->update(['status' => $approve ? 'approved' : 'rejected', 'decided_by' => $by->id, 'decided_at' => now(),
            'decision_note' => $note ? mb_substr(trim($note), 0, 255) : null]);

        PortalNotifier::toUsers([$leave->employee->user_id], 'Leave ' . ($approve ? 'approved' : 'declined'),
            $leave->type->name . ', ' . $leave->starts_on->format('j M') . ' to ' . $leave->ends_on->format('j M Y') . ($note ? ': ' . $note : '.'),
            route('me.leave'), 'leave');

        return $leave;
    }

    /** The employee withdraws a request; approved leave can only be withdrawn before it starts. */
    public function cancel(LeaveRequest $leave, User $by): LeaveRequest
    {
        $this->check($leave->employee->user_id !== $by->id && !$by->can('Manage employees'), 'status', 'This isn\'t your request.');
        $this->check(!in_array($leave->status, ['pending', 'approved'], true), 'status', 'This request is already closed.');
        $this->check($leave->status === 'approved' && $leave->starts_on->lte(today()) && !$by->can('Manage employees'), 'status',
            'This leave has started. Ask HR to change it.');
        $leave->update(['status' => 'cancelled']);

        return $leave;
    }

    /** Requests this person may decide. */
    public function toDecide(User $user): Collection
    {
        return LeaveRequest::with('employee.user', 'type')->where('status', 'pending')
            ->whereHas('employee', function ($q) use ($user) {
                $q->where('user_id', '!=', $user->id);
                if (!$user->can('Manage employees')) $q->where('manager_id', $user->id);
            })->orderBy('starts_on')->get();
    }

    /** Approved unpaid leave days that fall inside a month, per employee id. */
    public function unpaidDays(Carbon $month): array
    {
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth()->startOfDay();
        $out = [];
        LeaveRequest::where('status', 'approved')->whereHas('type', fn ($q) => $q->where('is_paid', false))
            ->whereDate('starts_on', '<=', $end)->whereDate('ends_on', '>=', $start)->get()
            ->each(function ($l) use ($start, $end, &$out) {
                $days = $this->workingDays($l->starts_on->max($start), $l->ends_on->min($end));
                $out[$l->employee_id] = ($out[$l->employee_id] ?? 0) + $days;
            });

        return $out;
    }

    protected function deciderIds(Employee $employee): array
    {
        return array_values(array_unique(array_filter([$employee->manager_id, ...PortalNotifier::userIdsWithPermission('Manage employees')])));
    }

    protected function check(bool $failed, string $field, string $message): void
    {
        if ($failed) throw ValidationException::withMessages([$field => $message]);
    }
}
