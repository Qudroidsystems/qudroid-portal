<?php

use App\Http\Controllers\Finance\FinanceController;
use App\Http\Controllers\Finance\InvoiceController;
use App\Http\Controllers\Finance\PaymentController;
use Illuminate\Support\Facades\Route;

/*
| Finance & Invoicing (part 1) — invoices, payments, receipts.
| Loaded inside the signed-in group (routes/web.php). People's own invoices
| and online payment live in routes/modules/billing.php.
*/

Route::prefix('finance')->name('finance.')->group(function () {

    Route::middleware('permission:Manage finance')->group(function () {
        Route::get('/invoices/create', [InvoiceController::class, 'create'])->name('invoices.create');
        Route::post('/invoices', [InvoiceController::class, 'store'])->name('invoices.store');
        Route::post('/invoices/{invoice}/payments', [PaymentController::class, 'store'])->name('payments.store');
    });

    Route::middleware('permission:Approve finance')->group(function () {
        Route::post('/invoices/{invoice}/void', [InvoiceController::class, 'void'])->name('invoices.void');
        Route::post('/payments/{payment}/reverse', [PaymentController::class, 'reverse'])->name('payments.reverse');
    });

    Route::middleware('permission:View finance|Manage finance|Approve finance')->group(function () {
        Route::get('/', [FinanceController::class, 'index'])->name('index');
        Route::get('/invoices', [InvoiceController::class, 'index'])->name('invoices.index');
        Route::get('/invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
        Route::get('/payments', [PaymentController::class, 'index'])->name('payments.index');
        Route::get('/payments/{payment}', [PaymentController::class, 'show'])->name('payments.show');
    });
});
