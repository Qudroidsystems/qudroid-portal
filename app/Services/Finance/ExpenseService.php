<?php

namespace App\Services\Finance;

use App\Models\Finance\Account;
use App\Models\Finance\Budget;
use App\Models\Finance\Expense;
use App\Models\User;
use App\Services\Messaging\NotificationActions;
use App\Services\Messaging\PortalNotifier;
use App\Support\Money;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Expenses: Finance records one, someone with Approve finance (not the
 * person who recorded it) approves or rejects it, then Finance pays it,
 * which posts it to the ledger. A paid expense is never edited; a mistake
 * is fixed with a reversing journal entry.
 */
class ExpenseService
{
    public function __construct(protected LedgerService $ledger) {}

    public function submit(array $data, User $by, ?UploadedFile $file = null): Expense
    {
        $account = Account::findOrFail($data['account_id']);
        $this->check($account->type !== 'expense' || !$account->is_active, 'account_id', 'Choose an open expense account.');
        $this->check(Money::minor($data['amount']) <= 0, 'amount', 'Enter the amount.');

        $expense = DB::transaction(fn () => Expense::create([
            'number'       => DocumentNumber::next('expense'),
            'date'         => $data['date'],
            'payee'        => trim($data['payee']),
            'description'  => trim($data['description']),
            'account_id'   => $account->id,
            'amount'       => Money::major(Money::minor($data['amount'])),
            'currency'     => strtoupper($data['currency'] ?? config('qudroid.academy.currency', 'NGN')),
            'status'       => 'submitted',
            'submitted_by' => $by->id,
        ]));

        if ($file) {
            $expense->update(['attachment_path' => $file->store('finance/expenses', 'local'), 'attachment_name' => mb_substr($file->getClientOriginalName(), 0, 255)]);
        }

        $approvers = array_diff(PortalNotifier::userIdsWithPermission('Approve finance'), [$by->id]);
        PortalNotifier::toUsers($approvers, 'Expense to approve: ' . $expense->number,
            $expense->payee . ', ' . Money::format($expense->amount, $expense->currency) . ' (' . $account->name . ')',
            route('finance.expenses.show', $expense), 'approval', 'expense:' . $expense->id . ':submitted',
            ['target' => ['kind' => 'expense', 'id' => $expense->id], 'actions' => NotificationActions::for('expense')]);

        return $expense;
    }

    public function approve(Expense $expense, User $by): Expense
    {
        $this->mayDecide($expense, $by);
        $expense->update(['status' => 'approved', 'approved_by' => $by->id, 'approved_at' => now(), 'rejection_reason' => null]);
        $this->tell($expense, 'Expense approved: ' . $expense->number, 'Approved by ' . $by->name . '. It can now be paid.');

        return $expense;
    }

    public function reject(Expense $expense, User $by, string $reason): Expense
    {
        $this->mayDecide($expense, $by);
        $this->check(trim($reason) === '', 'reason', 'Give a reason.');
        $expense->update(['status' => 'rejected', 'approved_by' => $by->id, 'approved_at' => now(), 'rejection_reason' => mb_substr(trim($reason), 0, 300)]);
        $this->tell($expense, 'Expense rejected: ' . $expense->number, trim($reason));

        return $expense;
    }

    public function pay(Expense $expense, User $by, int $fromAccountId, ?string $reference, Carbon $paidOn): Expense
    {
        $this->check($expense->status !== 'approved', 'status', 'Only an approved expense can be paid.');
        $from = Account::find($fromAccountId);
        $this->check(!$from || $from->type !== 'asset' || !$from->is_active, 'paid_from_account_id', 'Choose the cash or bank account it was paid from.');
        $this->check($paidOn->isFuture(), 'paid_on', 'The payment date can\'t be in the future.');

        DB::transaction(function () use ($expense, $by, $from, $reference, $paidOn) {
            $expense->update([
                'status' => 'paid', 'paid_from_account_id' => $from->id, 'paid_by' => $by->id,
                'paid_at' => $paidOn, 'payment_reference' => $reference ? mb_substr(trim($reference), 0, 100) : null,
            ]);
            $this->ledger->expensePaid($expense, $by);
        });

        return $expense;
    }

    /**
     * Each budget with what has been spent from its account in its period
     * (debits less credits on the ledger, so reversals count), in minor units.
     */
    public function budgetReport(Carbon $on): \Illuminate\Support\Collection
    {
        return Budget::with('account')->whereDate('starts_on', '<=', $on)->whereDate('ends_on', '>=', $on)->get()
            ->map(function (Budget $b) {
                $spent = $this->ledger->balanceMinor($b->account, $b->starts_on, $b->ends_on);
                $budget = Money::minor($b->amount);
                $committed = Money::minor(Expense::where('account_id', $b->account_id)->where('status', 'approved')
                    ->whereBetween('date', [$b->starts_on->toDateString(), $b->ends_on->toDateString()])->sum('amount'));

                return (object) [
                    'budget' => $b, 'amount' => $budget, 'spent' => $spent, 'committed' => $committed,
                    'left' => $budget - $spent - $committed,
                    'percent' => $budget > 0 ? (int) round(($spent + $committed) * 100 / $budget) : 0,
                ];
            })->sortBy(fn ($r) => $r->budget->account->code)->values();
    }

    protected function mayDecide(Expense $expense, User $by): void
    {
        $this->check($expense->status !== 'submitted', 'status', 'This expense has already been decided.');
        $this->check($expense->submitted_by === $by->id, 'status', 'Someone else must approve an expense you recorded.');
    }

    protected function tell(Expense $expense, string $title, string $body): void
    {
        if ($expense->submitted_by) {
            PortalNotifier::toUsers([$expense->submitted_by], $title, $body, route('finance.expenses.show', $expense), 'payment');
        }
    }

    protected function check(bool $failed, string $field, string $message): void
    {
        if ($failed) {
            throw ValidationException::withMessages([$field => $message]);
        }
    }
}
