<?php

use App\Models\Academy\Course;
use App\Models\Academy\Student;
use App\Models\Finance\Account;
use App\Models\Finance\Budget;
use App\Models\Finance\Expense;
use App\Models\Finance\JournalEntry;
use App\Models\User;
use App\Services\Academy\EnrollmentService;
use App\Services\Finance\InvoiceService;
use App\Services\Finance\LedgerService;
use App\Services\Finance\PaymentService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->admin = User::where('email', config('qudroid.super_admin.email'))->first();
    $this->admin->forceFill(['must_change_password' => false])->save();

    $this->clerk = User::factory()->create(['name' => 'Fola Clerk']);
    $this->clerk->assignRole('Finance Officer');
    $this->clerk->forceFill(['must_change_password' => false])->save();

    $this->md = User::factory()->create(['name' => 'Mo Director']);
    $this->md->assignRole('Managing Director');
    $this->md->forceFill(['must_change_password' => false])->save();

    $this->ledger = app(LedgerService::class);
    $this->bal = fn (string $code) => $this->ledger->balanceMinor(Account::byCode($code));
});

function enrolledInvoice(User $admin)
{
    $course = Course::create(['code' => 'WEB101', 'title' => 'Web Foundations', 'fee' => 150000, 'currency' => 'NGN', 'status' => 'published']);
    $cohort = $course->cohorts()->create(['code' => 'C1', 'name' => 'Cohort 1', 'status' => 'open']);
    $user = User::factory()->create(['user_type' => 'student']);
    $student = Student::create(['user_id' => $user->id, 'student_no' => Student::nextNumber(), 'status' => 'admitted']);

    return app(EnrollmentService::class)->enroll($student, $cohort, $admin)->invoices()->first();
}

test('invoices and payments post balanced entries to the ledger', function () {
    $invoice = enrolledInvoice($this->admin);
    expect(($this->bal)('1100'))->toBe(15000000)->and(($this->bal)('4000'))->toBe(15000000);

    $pay = app(PaymentService::class)->recordManual($invoice, ['amount' => 50000, 'method' => 'bank_transfer', 'reference' => 'T1'], $this->admin);
    expect(($this->bal)('1010'))->toBe(5000000)->and(($this->bal)('1100'))->toBe(10000000);

    app(PaymentService::class)->reverse($pay, $this->admin, 'Bounced');
    expect(($this->bal)('1010'))->toBe(0)->and(($this->bal)('1100'))->toBe(15000000)
        ->and($pay->fresh()->status)->toBe('reversed');

    app(InvoiceService::class)->void($invoice->fresh(), $this->admin, 'Withdrew');
    expect(($this->bal)('1100'))->toBe(0)->and(($this->bal)('4000'))->toBe(0);

    $tb = $this->ledger->trialBalance();
    expect($tb['debit'])->toBe($tb['credit'])
        ->and(JournalEntry::where('kind', 'reversal')->count())->toBe(2);

    // Services invoice (no enrollment) goes to services income.
    $client = User::factory()->create();
    app(InvoiceService::class)->issue($client, 'Website build', [['description' => 'Design', 'unit_price' => 200000]], $this->admin);
    expect(($this->bal)('4100'))->toBe(20000000);
});

test('a manual journal must balance, and can be reversed', function () {
    $cash = Account::byCode('1010')->id;
    $equity = Account::where('code', '3000')->first()->id;

    $this->actingAs($this->md)->post(route('finance.ledger.journals.store'), [
        'date' => today()->toDateString(), 'description' => 'Capital',
        'lines' => [['account' => $cash, 'debit' => 1000], ['account' => $equity, 'credit' => 900]],
    ])->assertSessionHasErrors();
    expect(JournalEntry::count())->toBe(0);

    $this->actingAs($this->md)->post(route('finance.ledger.journals.store'), [
        'date' => today()->toDateString(), 'description' => 'Capital',
        'lines' => [['account' => $cash, 'debit' => 1000], ['account' => $equity, 'credit' => 1000], ['account' => '', 'debit' => '', 'credit' => '']],
    ])->assertRedirect();
    $entry = JournalEntry::first();
    expect(($this->bal)('1010'))->toBe(100000);

    // A Finance Officer cannot post manual journals.
    $this->actingAs($this->clerk)->get(route('finance.ledger.journals.create'))->assertForbidden();

    $this->actingAs($this->md)->post(route('finance.ledger.journals.reverse', $entry), ['reason' => 'Typo'])->assertSessionHasNoErrors();
    expect(($this->bal)('1010'))->toBe(0)->and($entry->fresh()->reversed_by_id)->not->toBeNull();
    $this->actingAs($this->md)->post(route('finance.ledger.journals.reverse', $entry), ['reason' => 'Again'])->assertSessionHasErrors();
});

test('expenses need a second person to approve, then post when paid', function () {
    Storage::fake('local');
    $rent = Account::where('code', 'like', '5%')->where('name', 'like', '%Rent%')->first() ?? Account::ofType('expense')->first();

    $this->actingAs($this->clerk)->post(route('finance.expenses.store'), [
        'date' => today()->toDateString(), 'payee' => 'Landlord', 'description' => 'October rent',
        'account_id' => $rent->id, 'amount' => 250000, 'attachment' => UploadedFile::fake()->create('bill.pdf', 20, 'application/pdf'),
    ])->assertRedirect();
    $expense = Expense::first();
    expect($expense->status)->toBe('submitted')->and($expense->number)->toContain('EXP');
    Storage::disk('local')->assertExists($expense->attachment_path);

    // The clerk cannot approve, and cannot pay before approval.
    $this->actingAs($this->clerk)->post(route('finance.expenses.approve', $expense))->assertForbidden();
    $this->actingAs($this->clerk)->post(route('finance.expenses.pay', $expense), ['paid_from_account_id' => Account::byCode('1010')->id, 'paid_on' => today()->toDateString()])->assertSessionHasErrors();

    // Nobody approves an expense they recorded themselves.
    $own = app(\App\Services\Finance\ExpenseService::class)->submit(['date' => today(), 'payee' => 'X', 'description' => 'Y', 'account_id' => $rent->id, 'amount' => 10], $this->md);
    $this->actingAs($this->md)->post(route('finance.expenses.approve', $own))->assertSessionHasErrors();

    $this->actingAs($this->md)->post(route('finance.expenses.approve', $expense))->assertSessionHasNoErrors();
    expect($expense->fresh()->status)->toBe('approved');

    // Paying from an expense account is refused.
    $this->actingAs($this->clerk)->post(route('finance.expenses.pay', $expense), ['paid_from_account_id' => $rent->id, 'paid_on' => today()->toDateString()])->assertSessionHasErrors();
    $this->actingAs($this->clerk)->post(route('finance.expenses.pay', $expense), ['paid_from_account_id' => Account::byCode('1010')->id, 'paid_on' => today()->toDateString(), 'payment_reference' => 'TRF9'])->assertSessionHasNoErrors();

    expect($expense->fresh()->status)->toBe('paid')
        ->and($this->ledger->balanceMinor($rent))->toBe(25000000)
        ->and(($this->bal)('1010'))->toBe(-25000000);

    $this->actingAs($this->md)->get(route('finance.expenses.show', $expense))->assertOk()->assertSee('Landlord');
    $this->actingAs($this->clerk)->get(route('finance.expenses.attachment', $expense))->assertOk();
});

test('budgets show spent and committed amounts', function () {
    $acct = Account::ofType('expense')->first();
    $this->actingAs($this->md)->post(route('finance.budgets.store'), [
        'account_id' => $acct->id, 'starts_on' => today()->startOfMonth()->toDateString(), 'ends_on' => today()->endOfMonth()->toDateString(), 'amount' => 1000,
    ])->assertSessionHasNoErrors();
    // Overlapping budget for the same account is refused.
    $this->actingAs($this->md)->post(route('finance.budgets.store'), [
        'account_id' => $acct->id, 'starts_on' => today()->toDateString(), 'ends_on' => today()->addMonth()->toDateString(), 'amount' => 5,
    ])->assertSessionHasErrors('starts_on');

    $svc = app(\App\Services\Finance\ExpenseService::class);
    $paid = $svc->submit(['date' => today(), 'payee' => 'A', 'description' => 'a', 'account_id' => $acct->id, 'amount' => 600], $this->clerk);
    $svc->approve($paid, $this->md);
    $svc->pay($paid->fresh(), $this->clerk, Account::byCode('1000')->id, null, today());
    $approved = $svc->submit(['date' => today(), 'payee' => 'B', 'description' => 'b', 'account_id' => $acct->id, 'amount' => 300], $this->clerk);
    $svc->approve($approved, $this->md);

    $row = $svc->budgetReport(today())->first();
    expect($row->spent)->toBe(60000)->and($row->committed)->toBe(30000)->and($row->left)->toBe(10000)->and($row->percent)->toBe(90);

    $this->actingAs($this->clerk)->get(route('finance.budgets.index'))->assertOk()->assertSee('90%');
    expect(Budget::count())->toBe(1);
});

test('ledger screens and reports load', function () {
    $invoice = enrolledInvoice($this->admin);
    app(PaymentService::class)->recordManual($invoice, ['amount' => 150000, 'method' => 'cash', 'reference' => 'C1'], $this->admin);

    $this->actingAs($this->clerk)->get(route('finance.ledger.accounts'))->assertOk()->assertSee('Accounts receivable');
    $this->actingAs($this->clerk)->get(route('finance.ledger.account', Account::byCode('1000')))->assertOk()->assertSee('150,000');
    $this->actingAs($this->clerk)->get(route('finance.ledger.journals'))->assertOk()->assertSee('JRN');
    $this->actingAs($this->clerk)->get(route('finance.ledger.journal', JournalEntry::first()))->assertOk()->assertSee($invoice->number);
    $this->actingAs($this->clerk)->get(route('finance.reports.trial-balance'))->assertOk()->assertDontSee('do not agree');
    $this->actingAs($this->clerk)->get(route('finance.reports.profit-loss'))->assertOk()->assertSee('Course fees')->assertSee('Net profit');
    $this->actingAs($this->clerk)->get(route('finance.expenses.index'))->assertOk();
    $this->actingAs($this->clerk)->get(route('finance.expenses.create'))->assertOk();
    $this->actingAs($this->md)->get(route('finance.ledger.journals.create'))->assertOk();
    $this->actingAs($this->clerk)->get(route('finance.index'))->assertOk();

    $pl = $this->ledger->profitAndLoss(today()->startOfMonth(), today());
    expect($pl['net'])->toBe(15000000);
});
