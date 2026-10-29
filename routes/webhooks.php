<?php

use App\Http\Controllers\Finance\OpayWebhookController;
use App\Http\Controllers\Finance\PaystackWebhookController;
use Illuminate\Support\Facades\Route;

/*
| Calls from payment providers. No sign-in and no CSRF token (webhook/* is
| excluded in bootstrap/app.php); each handler checks the provider's signature.
*/

Route::post('/webhook/paystack', PaystackWebhookController::class)->middleware('throttle:120,1')->name('webhooks.paystack');
Route::post('/webhook/opay', OpayWebhookController::class)->middleware('throttle:120,1')->name('webhooks.opay');
