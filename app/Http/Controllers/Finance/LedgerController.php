<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Finance\Account;
use App\Models\Finance\JournalEntry;
use App\Models\Finance\JournalLine;
use App\Services\Finance\LedgerService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/** Chart of accounts, journal entries and the accounting reports. */
class LedgerController extends Controller
{
    public function __construct(protected LedgerService $ledger) {}

    // ── Chart of accounts ───────────────────────────────────────────────

    public function accounts()
    {
        $totals = $this->ledger->totals();
        $accounts = Account::orderBy('code')->get()->map(function ($a) use ($totals) {
            $t = $totals->get($a->id);
            $net = $t ? Money::minor($t->debit) - Money::minor($t->credit) : 0;
            $a->balance_minor = $a->isDebitNormal() ? $net : -$net;

            return $a;
        })->groupBy('type');

        return view('modules.finance.ledger.accounts', compact('accounts'));
    }

    public function storeAccount(Request $request)
    {
        $data = $request->validate([
            'code'        => ['required', 'string', 'max:20', 'alpha_dash', 'unique:finance_accounts,code'],
            'name'        => ['required', 'string', 'max:150'],
            'type'        => ['required', Rule::in(array_keys(Account::TYPES))],
            'description' => ['nullable', 'string', 'max:500'],
        ]);
        Account::create($data + ['is_system' => false, 'is_active' => true]);

        return back()->with('success', 'Account added.');
    }

    public function updateAccount(Request $request, Account $account)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:150'], 'description' => ['nullable', 'string', 'max:500']]);
        $data['is_active'] = $account->is_system || $request->boolean('is_active');
        $account->update($data);

        return back()->with('success', 'Account updated.');
    }

    /** Every line on one account, with a running balance. */
    public function account(Request $request, Account $account)
    {
        [$from, $to] = $this->period($request, startOfYear: true);
        $opening = $this->ledger->balanceMinor($account, null, $from->copy()->subDay());

        $lines = JournalLine::with('entry')->where('account_id', $account->id)
            ->whereHas('entry', fn ($q) => $q->whereDate('date', '>=', $from)->whereDate('date', '<=', $to))
            ->get()->sortBy(fn ($l) => [$l->entry->date->toDateString(), $l->journal_entry_id])->values();

        $running = $opening;
        $rows = $lines->map(function ($l) use (&$running, $account) {
            $net = Money::minor($l->debit) - Money::minor($l->credit);
            $running += $account->isDebitNormal() ? $net : -$net;

            return ['line' => $l, 'balance' => $running];
        });

        return view('modules.finance.ledger.account', compact('account', 'rows', 'opening', 'from', 'to'));
    }

    // ── Journal ─────────────────────────────────────────────────────────

    public function journals(Request $request)
    {
        $entries = JournalEntry::with('lines.account', 'creator')
            ->when($request->query('kind'), fn ($q, $k) => $q->where('kind', $k))
            ->when(trim((string) $request->query('q')), fn ($q, $s) => $q->where(fn ($w) => $w->where('number', 'like', "%{$s}%")->orWhere('description', 'like', "%{$s}%")))
            ->latest('date')->latest('id')->paginate(30)->withQueryString();

        return view('modules.finance.ledger.journals', compact('entries'));
    }

    public function journal(JournalEntry $entry)
    {
        $entry->load('lines.account', 'creator', 'reverses', 'reversedBy');

        return view('modules.finance.ledger.journal', compact('entry'));
    }

    public function createJournal()
    {
        return view('modules.finance.ledger.journal-form', ['accounts' => Account::where('is_active', true)->orderBy('code')->get()]);
    }

    public function storeJournal(Request $request)
    {
        $data = $request->validate([
            'date'              => ['required', 'date', 'before_or_equal:today'],
            'description'       => ['required', 'string', 'max:255'],
            'lines'             => ['required', 'array', 'min:2', 'max:30'],
            'lines.*.account'   => ['nullable', 'integer'],
            'lines.*.debit'     => ['nullable', 'numeric', 'min:0'],
            'lines.*.credit'    => ['nullable', 'numeric', 'min:0'],
            'lines.*.memo'      => ['nullable', 'string', 'max:255'],
        ]);
        $lines = collect($data['lines'])->filter(fn ($l) => !empty($l['account']) || (float) ($l['debit'] ?? 0) || (float) ($l['credit'] ?? 0))
            ->map(fn ($l) => ['account' => (int) ($l['account'] ?? 0), 'debit' => $l['debit'] ?? 0, 'credit' => $l['credit'] ?? 0, 'memo' => $l['memo'] ?? null])->values()->all();

        $entry = $this->ledger->post($data['description'], $lines, $request->user(), 'manual', null, Carbon::parse($data['date']));

        return redirect()->route('finance.ledger.journal', $entry)->with('success', "Journal {$entry->number} posted.");
    }

    public function reverseJournal(Request $request, JournalEntry $entry)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:200']]);
        $this->ledger->reverse($entry->load('lines'), $data['reason'], $request->user());

        return back()->with('success', 'Reversed.');
    }

    // ── Reports ─────────────────────────────────────────────────────────

    public function trialBalance(Request $request)
    {
        $asAt = $request->filled('as_at') ? Carbon::parse($request->query('as_at')) : today();

        return view('modules.finance.ledger.trial-balance', ['tb' => $this->ledger->trialBalance($asAt), 'asAt' => $asAt]);
    }

    public function profitAndLoss(Request $request)
    {
        [$from, $to] = $this->period($request, startOfYear: false);

        return view('modules.finance.ledger.profit-loss', ['pl' => $this->ledger->profitAndLoss($from, $to), 'from' => $from, 'to' => $to]);
    }

    /** @return array{0: Carbon, 1: Carbon} */
    protected function period(Request $request, bool $startOfYear): array
    {
        $from = $request->filled('from') ? Carbon::parse($request->query('from')) : ($startOfYear ? today()->startOfYear() : today()->startOfMonth());
        $to = $request->filled('to') ? Carbon::parse($request->query('to')) : today();

        return $to->lt($from) ? [$to, $from] : [$from, $to];
    }
}
