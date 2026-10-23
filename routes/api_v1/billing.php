<?php

use App\Http\Controllers\Api\V1\Billing\BillingApiController;
use Illuminate\Support\Facades\Route;

// My bills: own invoices, receipts and Paystack payments. Loaded inside the signed-in API group.
Route::prefix('billing')->name('billing.')->controller(BillingApiController::class)->group(function () {
    Route::get('invoices', 'invoices')->name('invoices');
    Route::get('invoices/{invoice}', 'show')->whereNumber('invoice')->name('invoices.show');
    Route::get('invoices/{invoice}/pdf', 'invoicePdf')->whereNumber('invoice')->name('invoices.pdf');
    Route::post('invoices/{invoice}/pay', 'pay')->whereNumber('invoice')->middleware('throttle:10,1')->name('pay');
    Route::post('payments/verify', 'verify')->middleware('throttle:30,1')->name('verify');
    Route::get('payments', 'payments')->name('payments');
    Route::get('payments/{payment}/receipt', 'receipt')->whereNumber('payment')->name('payments.receipt');
});
