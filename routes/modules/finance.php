<?php

use App\Http\Controllers\Finance\ExpenseController;
use App\Http\Controllers\Finance\FinanceController;
use App\Http\Controllers\Finance\InvoiceController;
use App\Http\Controllers\Finance\LedgerController;
use App\Http\Controllers\Finance\PaymentController;
use Illuminate\Support\Facades\Route;

/*
| Finance & Invoicing — invoices, payments, receipts, the ledger, expenses,
| budgets and reports.
| Loaded inside the signed-in group (routes/web.php). People's own invoices
| and online payment live in routes/modules/billing.php.
*/

Route::prefix('finance')->name('finance.')->group(function () {

    Route::middleware('permission:Manage finance')->group(function () {
        Route::get('/invoices/create', [InvoiceController::class, 'create'])->name('invoices.create');
        Route::post('/invoices', [InvoiceController::class, 'store'])->name('invoices.store');
        Route::post('/invoices/{invoice}/payments', [PaymentController::class, 'store'])->name('payments.store');

        Route::get('/expenses/create', [ExpenseController::class, 'create'])->name('expenses.create');
        Route::post('/expenses', [ExpenseController::class, 'store'])->name('expenses.store');
        Route::post('/expenses/{expense}/pay', [ExpenseController::class, 'pay'])->name('expenses.pay');
    });

    Route::middleware('permission:Approve finance')->group(function () {
        Route::post('/invoices/{invoice}/void', [InvoiceController::class, 'void'])->name('invoices.void');
        Route::post('/payments/{payment}/reverse', [PaymentController::class, 'reverse'])->name('payments.reverse');

        Route::post('/expenses/{expense}/approve', [ExpenseController::class, 'approve'])->name('expenses.approve');
        Route::post('/expenses/{expense}/reject', [ExpenseController::class, 'reject'])->name('expenses.reject');

        Route::post('/budgets', [ExpenseController::class, 'storeBudget'])->name('budgets.store');
        Route::put('/budgets/{budget}', [ExpenseController::class, 'updateBudget'])->name('budgets.update');
        Route::delete('/budgets/{budget}', [ExpenseController::class, 'destroyBudget'])->name('budgets.destroy');

        Route::post('/ledger/accounts', [LedgerController::class, 'storeAccount'])->name('ledger.accounts.store');
        Route::put('/ledger/accounts/{account}', [LedgerController::class, 'updateAccount'])->name('ledger.accounts.update');
        Route::get('/ledger/journals/create', [LedgerController::class, 'createJournal'])->name('ledger.journals.create');
        Route::post('/ledger/journals', [LedgerController::class, 'storeJournal'])->name('ledger.journals.store');
        Route::post('/ledger/journals/{entry}/reverse', [LedgerController::class, 'reverseJournal'])->name('ledger.journals.reverse');
    });

    Route::middleware('permission:View finance|Manage finance|Approve finance')->group(function () {
        Route::get('/', [FinanceController::class, 'index'])->name('index');
        Route::get('/invoices', [InvoiceController::class, 'index'])->name('invoices.index');
        Route::get('/invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
        Route::get('/invoices/{invoice}/pdf', [InvoiceController::class, 'pdf'])->name('invoices.pdf');
        Route::get('/payments', [PaymentController::class, 'index'])->name('payments.index');
        Route::get('/payments/{payment}', [PaymentController::class, 'show'])->name('payments.show');
        Route::get('/payments/{payment}/pdf', [PaymentController::class, 'pdf'])->name('payments.pdf');

        Route::get('/expenses', [ExpenseController::class, 'index'])->name('expenses.index');
        Route::get('/expenses/{expense}', [ExpenseController::class, 'show'])->name('expenses.show');
        Route::get('/expenses/{expense}/attachment', [ExpenseController::class, 'attachment'])->name('expenses.attachment');
        Route::get('/budgets', [ExpenseController::class, 'budgets'])->name('budgets.index');

        Route::get('/ledger/accounts', [LedgerController::class, 'accounts'])->name('ledger.accounts');
        Route::get('/ledger/accounts/{account}', [LedgerController::class, 'account'])->name('ledger.account');
        Route::get('/ledger/journals', [LedgerController::class, 'journals'])->name('ledger.journals');
        Route::get('/ledger/journals/{entry}', [LedgerController::class, 'journal'])->name('ledger.journal');
        Route::get('/reports/trial-balance', [LedgerController::class, 'trialBalance'])->name('reports.trial-balance');
        Route::get('/reports/profit-and-loss', [LedgerController::class, 'profitAndLoss'])->name('reports.profit-loss');
    });
});
