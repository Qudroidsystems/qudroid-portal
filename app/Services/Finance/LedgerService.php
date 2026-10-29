<?php

namespace App\Services\Finance;

use App\Models\Finance\Account;
use App\Models\Finance\Expense;
use App\Models\Finance\Invoice;
use App\Models\Finance\JournalEntry;
use App\Models\Finance\JournalLine;
use App\Models\Finance\Payment;
use App\Models\User;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The general ledger.
 *
 *  - Every entry balances: total debits = total credits (checked in minor units).
 *  - Entries are never changed. A mistake, a voided invoice or a reversed
 *    payment gets a reversing entry that swaps every debit and credit.
 *  - Automatic postings:
 *      invoice issued     Dr Accounts receivable   Cr Course fees / Services income
 *      payment confirmed  Dr Cash, Bank, Paystack or OPay Cr Accounts receivable (and Customer credit for any overpayment)
 *      expense paid       Dr the expense account   Cr the account it was paid from
 */
class LedgerService
{
    /**
     * @param  array<int, array{account: Account|string|int, debit?: string|float|int, credit?: string|float|int, memo?: ?string}>  $lines
     */
    public function post(string $description, array $lines, ?User $by = null, string $kind = 'manual', ?Model $source = null, ?Carbon $date = null): JournalEntry
    {
        $rows = [];
        $debits = $credits = 0;
        foreach ($lines as $i => $line) {
            $account = $line['account'] instanceof Account ? $line['account']
                : (is_int($line['account']) ? Account::find($line['account']) : Account::where('code', $line['account'])->first());
            $this->check(!$account, "lines.{$i}.account", 'Choose an account for every line.');
            $this->check(!$account->is_active && $kind === 'manual', "lines.{$i}.account", "{$account->label} is closed.");
            $debit = Money::minor($line['debit'] ?? 0);
            $credit = Money::minor($line['credit'] ?? 0);
            $this->check($debit < 0 || $credit < 0 || ($debit > 0 && $credit > 0), "lines.{$i}.debit", 'A line has either a debit or a credit, not both, and no negative amounts.');
            if ($debit === 0 && $credit === 0) continue;
            $rows[] = ['account_id' => $account->id, 'debit' => Money::major($debit), 'credit' => Money::major($credit), 'memo' => isset($line['memo']) ? mb_substr((string) $line['memo'], 0, 255) : null];
            $debits += $debit;
            $credits += $credit;
        }
        $this->check(count($rows) < 2, 'lines', 'An entry needs at least two lines.');
        $this->check($debits !== $credits, 'lines', 'Debits (' . Money::format(Money::major($debits)) . ') and credits (' . Money::format(Money::major($credits)) . ') must be equal.');

        return DB::transaction(function () use ($description, $rows, $by, $kind, $source, $date) {
            $entry = JournalEntry::create([
                'number'      => DocumentNumber::next('journal'),
                'date'        => ($date ?? today())->toDateString(),
                'description' => mb_substr(trim($description), 0, 255),
                'kind'        => $kind,
                'source_type' => $source?->getMorphClass(),
                'source_id'   => $source?->getKey(),
                'created_by'  => $by?->id,
            ]);
            $entry->lines()->createMany($rows);

            return $entry;
        });
    }

    /** Post the mirror image of an entry, and link the two. */
    public function reverse(JournalEntry $entry, string $reason, ?User $by = null, ?Carbon $date = null): JournalEntry
    {
        $this->check($entry->reversed_by_id !== null, 'reason', 'This entry has already been reversed.');
        $this->check($entry->kind === 'reversal', 'reason', 'A reversing entry can\'t itself be reversed. Post a new entry instead.');
        $this->check(trim($reason) === '', 'reason', 'Give a reason.');

        return DB::transaction(function () use ($entry, $reason, $by, $date) {
            $mirror = $this->post('Reversal of ' . $entry->number . ': ' . trim($reason),
                $entry->lines->map(fn ($l) => ['account' => $l->account_id, 'debit' => $l->credit, 'credit' => $l->debit, 'memo' => $l->memo])->all(),
                $by, 'reversal', $entry->source, $date);
            $mirror->update(['reverses_id' => $entry->id]);
            $entry->update(['reversed_by_id' => $mirror->id]);

            return $mirror;
        });
    }

    // ── Automatic postings ──────────────────────────────────────────────

    public function invoiceIssued(Invoice $invoice, ?User $by): ?JournalEntry
    {
        if (Money::minor($invoice->total) <= 0) return null;
        $income = $invoice->enrollment_id ? Account::COURSE_FEES : Account::SERVICES;

        return $this->post("Invoice {$invoice->number}: {$invoice->title}", [
            ['account' => Account::RECEIVABLE, 'debit' => $invoice->total, 'memo' => $invoice->user?->name],
            ['account' => $income, 'credit' => $invoice->total],
        ], $by, 'invoice', $invoice, $invoice->issue_date);
    }

    public function invoiceVoided(Invoice $invoice, ?User $by): void
    {
        $this->reverseFor($invoice, 'invoice', 'Invoice voided: ' . $invoice->void_reason, $by);
    }

    public function paymentConfirmed(Payment $payment, ?User $by): ?JournalEntry
    {
        $amount = Money::minor($payment->amount);
        if ($amount <= 0) return null;
        $allocated = Money::minor($payment->allocations()->sum('amount'));
        $into = match ($payment->method) { 'cash' => Account::CASH, 'paystack' => Account::PAYSTACK, 'opay' => Account::OPAY, default => Account::BANK };

        $lines = [['account' => $into, 'debit' => $payment->amount, 'memo' => $payment->method_label . ($payment->reference ? ' ' . $payment->reference : '')]];
        if ($allocated > 0) $lines[] = ['account' => Account::RECEIVABLE, 'credit' => Money::major($allocated), 'memo' => $payment->user?->name];
        if ($amount > $allocated) $lines[] = ['account' => Account::CUSTOMER_CREDIT, 'credit' => Money::major($amount - $allocated), 'memo' => 'Not applied to an invoice'];

        return $this->post("Receipt {$payment->number} from " . ($payment->user?->name ?? 'customer'), $lines, $by, 'payment', $payment);
    }

    public function paymentReversed(Payment $payment, ?User $by): void
    {
        $this->reverseFor($payment, 'payment', 'Payment reversed: ' . $payment->reversal_reason, $by);
    }

    public function expensePaid(Expense $expense, ?User $by): JournalEntry
    {
        return $this->post("Expense {$expense->number}: {$expense->payee}, {$expense->description}", [
            ['account' => $expense->account_id, 'debit' => $expense->amount, 'memo' => $expense->payee],
            ['account' => $expense->paid_from_account_id, 'credit' => $expense->amount, 'memo' => $expense->payment_reference],
        ], $by, 'expense', $expense, $expense->paid_at ? Carbon::parse($expense->paid_at) : null);
    }

    /**
     * A paid payroll run: salaries and the employer's pension are the cost;
     * PAYE, pension and NHF are owed on to the authorities; net pay leaves the bank.
     */
    public function payrollPaid(\App\Models\Hr\PayrollRun $run, ?User $by): JournalEntry
    {
        $sum = fn (string $col) => $run->payslips->sum(fn ($p) => Money::minor($p->{$col}));
        $gross = $sum('gross');
        $employer = $sum('employer_pension');
        $lines = [['account' => Account::SALARIES, 'debit' => Money::major($gross + $employer), 'memo' => $run->payslips->count() . ' payslips']];
        foreach ([[Account::TAXES_PAYABLE, $sum('paye'), 'PAYE'], [Account::PENSION_PAYABLE, $sum('pension') + $employer, 'Pension, employee and employer'],
                  [Account::NHF_PAYABLE, $sum('nhf'), 'NHF'], [Account::PAYROLL_DEDUCTIONS, $sum('other_deductions'), 'Other deductions']] as [$code, $amount, $memo]) {
            if ($amount > 0) $lines[] = ['account' => $code, 'credit' => Money::major($amount), 'memo' => $memo];
        }
        $lines[] = ['account' => $run->paid_from_account_id, 'credit' => Money::major($sum('net')), 'memo' => 'Net pay'];

        return $this->post('Payroll ' . $run->label, $lines, $by, 'payroll', $run, $run->paid_at ? Carbon::parse($run->paid_at) : null);
    }

    protected function reverseFor(Model $source, string $kind, string $reason, ?User $by): void
    {
        JournalEntry::where('source_type', $source->getMorphClass())->where('source_id', $source->getKey())
            ->where('kind', $kind)->whereNull('reversed_by_id')->get()
            ->each(fn ($e) => $this->reverse($e, $reason, $by));
    }

    // ── Balances and reports ────────────────────────────────────────────

    /**
     * Debit and credit totals per account, for entries dated in a range.
     *
     * @return Collection<int, object{account_id: int, debit: string, credit: string}>
     */
    public function totals(?Carbon $from = null, ?Carbon $to = null): Collection
    {
        return JournalLine::query()
            ->join('finance_journal_entries as e', 'e.id', '=', 'finance_journal_lines.journal_entry_id')
            ->when($from, fn ($q) => $q->whereDate('e.date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('e.date', '<=', $to))
            ->groupBy('account_id')
            ->selectRaw('account_id, sum(debit) as debit, sum(credit) as credit')
            ->get()->keyBy('account_id');
    }

    /** An account's balance in its normal direction (positive = normal), in minor units. */
    public function balanceMinor(Account $account, ?Carbon $from = null, ?Carbon $to = null): int
    {
        $t = $this->totals($from, $to)->get($account->id);
        if (!$t) return 0;
        $net = Money::minor($t->debit) - Money::minor($t->credit);

        return $account->isDebitNormal() ? $net : -$net;
    }

    /**
     * Every account with its debit or credit balance at a date. The two
     * columns always add up to the same total.
     */
    public function trialBalance(?Carbon $asAt = null): array
    {
        $totals = $this->totals(null, $asAt);
        $rows = [];
        $dr = $cr = 0;
        foreach (Account::orderBy('code')->get() as $a) {
            $t = $totals->get($a->id);
            if (!$t) continue;
            $net = Money::minor($t->debit) - Money::minor($t->credit);
            if ($net === 0) continue;
            $rows[] = ['account' => $a, 'debit' => max(0, $net), 'credit' => max(0, -$net)];
            $dr += max(0, $net);
            $cr += max(0, -$net);
        }

        return ['rows' => $rows, 'debit' => $dr, 'credit' => $cr];
    }

    /** Income less expenses for a period. Amounts in minor units. */
    public function profitAndLoss(Carbon $from, Carbon $to): array
    {
        $totals = $this->totals($from, $to);
        $section = function (string $type) use ($totals) {
            return Account::ofType($type)->get()->map(function ($a) use ($totals) {
                $t = $totals->get($a->id);
                $net = $t ? Money::minor($t->debit) - Money::minor($t->credit) : 0;

                return ['account' => $a, 'amount' => $a->isDebitNormal() ? $net : -$net];
            })->filter(fn ($r) => $r['amount'] !== 0)->values();
        };
        $income = $section('income');
        $expenses = $section('expense');

        return [
            'income'   => $income,
            'expenses' => $expenses,
            'totalIncome'   => $income->sum('amount'),
            'totalExpenses' => $expenses->sum('amount'),
            'net'      => $income->sum('amount') - $expenses->sum('amount'),
        ];
    }

    protected function check(bool $failed, string $field, string $message): void
    {
        if ($failed) {
            throw ValidationException::withMessages([$field => $message]);
        }
    }
}
