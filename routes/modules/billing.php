<?php

use App\Http\Controllers\Finance\BillingController;
use Illuminate\Support\Facades\Route;

/*
| "My bills": anyone signed in (students, clients, staff) sees their own
| invoices and receipts and can pay online. Loaded inside the signed-in group.
*/

Route::prefix('billing')->name('billing.')->group(function () {
    Route::get('/', [BillingController::class, 'index'])->name('index');
    Route::get('/invoices/{invoice}', [BillingController::class, 'invoice'])->name('invoice');
    Route::get('/invoices/{invoice}/pdf', [BillingController::class, 'invoicePdf'])->name('invoice.pdf');
    Route::post('/invoices/{invoice}/pay', [BillingController::class, 'pay'])->middleware('throttle:10,1')->name('pay');
    Route::get('/paystack/callback', [BillingController::class, 'callback'])->name('paystack.callback');
    Route::get('/receipts/{payment}', [BillingController::class, 'receipt'])->name('receipt');
    Route::get('/receipts/{payment}/pdf', [BillingController::class, 'receiptPdf'])->name('receipt.pdf');
});
