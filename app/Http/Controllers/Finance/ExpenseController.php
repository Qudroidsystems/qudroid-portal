<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Finance\Account;
use App\Models\Finance\Budget;
use App\Models\Finance\Expense;
use App\Models\Finance\JournalEntry;
use App\Services\Finance\ExpenseService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/** Expenses and budgets. */
class ExpenseController extends Controller
{
    public function __construct(protected ExpenseService $expenses) {}

    public function index(Request $request)
    {
        $status = $request->query('status');
        $list = Expense::with('account', 'submitter')
            ->when(isset(Expense::STATUSES[$status]), fn ($q) => $q->where('status', $status))
            ->when($request->integer('account_id'), fn ($q, $id) => $q->where('account_id', $id))
            ->when(trim((string) $request->query('q')), fn ($q, $s) => $q->where(fn ($w) => $w->where('number', 'like', "%{$s}%")->orWhere('payee', 'like', "%{$s}%")->orWhere('description', 'like', "%{$s}%")))
            ->latest('date')->latest('id')->paginate(25)->withQueryString();

        return view('modules.finance.expenses.index', [
            'expenses' => $list,
            'counts'   => Expense::selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status'),
            'accounts' => Account::ofType('expense')->get(),
        ]);
    }

    public function create()
    {
        return view('modules.finance.expenses.create', ['accounts' => Account::ofType('expense')->where('is_active', true)->get()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'date'        => ['required', 'date', 'before_or_equal:today'],
            'payee'       => ['required', 'string', 'max:150'],
            'description' => ['required', 'string', 'max:500'],
            'account_id'  => ['required', 'integer', 'exists:finance_accounts,id'],
            'amount'      => ['required', 'numeric', 'min:0.01', 'max:9999999999'],
            'attachment'  => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,heic,webp', 'max:10240'],
        ]);
        $expense = $this->expenses->submit($data, $request->user(), $request->file('attachment'));
        \App\Support\BusinessUnits::apply($request, $expense);

        return redirect()->route('finance.expenses.show', $expense)->with('success', "Expense {$expense->number} recorded and sent for approval.");
    }

    public function show(Expense $expense)
    {
        $expense->load('account', 'paidFrom', 'submitter', 'approver', 'payer');

        return view('modules.finance.expenses.show', [
            'expense' => $expense,
            'entries' => JournalEntry::where('source_type', $expense->getMorphClass())->where('source_id', $expense->id)->get(),
            'payFrom' => Account::where('type', 'asset')->where('is_active', true)->orderBy('code')->get(),
        ]);
    }

    public function approve(Request $request, Expense $expense)
    {
        $this->expenses->approve($expense, $request->user());

        return back()->with('success', 'Approved.');
    }

    public function reject(Request $request, Expense $expense)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:300']]);
        $this->expenses->reject($expense, $request->user(), $data['reason']);

        return back()->with('success', 'Rejected.');
    }

    public function pay(Request $request, Expense $expense)
    {
        $data = $request->validate([
            'paid_from_account_id' => ['required', 'integer'],
            'paid_on'              => ['required', 'date'],
            'payment_reference'    => ['nullable', 'string', 'max:100'],
        ]);
        $this->expenses->pay($expense, $request->user(), (int) $data['paid_from_account_id'], $data['payment_reference'] ?? null, Carbon::parse($data['paid_on']));

        return back()->with('success', 'Marked paid and posted to the ledger.');
    }

    public function attachment(Expense $expense)
    {
        abort_unless($expense->attachment_path && Storage::disk('local')->exists($expense->attachment_path), 404);

        return Storage::disk('local')->download($expense->attachment_path, $expense->attachment_name);
    }

    // ── Budgets ─────────────────────────────────────────────────────────

    public function budgets(Request $request)
    {
        $on = $request->filled('on') ? Carbon::parse($request->query('on')) : today();

        return view('modules.finance.budgets.index', [
            'report'   => $this->expenses->budgetReport($on),
            'on'       => $on,
            'all'      => Budget::with('account')->orderByDesc('starts_on')->get(),
            'accounts' => Account::ofType('expense')->where('is_active', true)->get(),
        ]);
    }

    public function storeBudget(Request $request)
    {
        $data = $request->validate([
            'account_id' => ['required', Rule::exists('finance_accounts', 'id')->where('type', 'expense')],
            'starts_on'  => ['required', 'date'],
            'ends_on'    => ['required', 'date', 'after:starts_on'],
            'amount'     => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'notes'      => ['nullable', 'string', 'max:300'],
        ]);
        $budget = Budget::where('account_id', $data['account_id'])->whereDate('starts_on', '<=', $data['ends_on'])->whereDate('ends_on', '>=', $data['starts_on'])->first();
        if ($budget) {
            return back()->withInput()->withErrors(['starts_on' => 'This account already has a budget for part of that period. Change that one instead.']);
        }
        Budget::create($data + ['created_by' => $request->user()->id]);

        return back()->with('success', 'Budget set.');
    }

    public function updateBudget(Request $request, Budget $budget)
    {
        $budget->update($request->validate(['amount' => ['required', 'numeric', 'min:0', 'max:9999999999'], 'notes' => ['nullable', 'string', 'max:300']]));

        return back()->with('success', 'Budget updated.');
    }

    public function destroyBudget(Budget $budget)
    {
        $budget->delete();

        return back()->with('success', 'Budget removed.');
    }
}
