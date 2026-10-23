<?php

use App\Http\Controllers\Academy\ApplyController;
use Illuminate\Support\Facades\Route;

/*
| Pages anyone can open without signing in: the Academy's online
| application. Signed-in students use the same pages.
*/

Route::prefix('apply')->name('apply.')->group(function () {
    Route::get('/', [ApplyController::class, 'index'])->name('index');
    Route::get('/done/{reference}', [ApplyController::class, 'done'])->name('done');
    Route::get('/{cohort}', [ApplyController::class, 'create'])->whereNumber('cohort')->name('create');
    Route::post('/{cohort}', [ApplyController::class, 'store'])->whereNumber('cohort')->middleware('throttle:10,10')->name('store');
});

// Certificate check (the QR code on every certificate points here)
Route::get('/verify/{token}', [\App\Http\Controllers\Certificates\CertificateController::class, 'verify'])
    ->middleware('throttle:60,1')->name('certificates.verify');

// Drive "anyone with the link" pages (view and download only)
Route::prefix('s/{token}')->middleware('throttle:120,1')->controller(\App\Http\Controllers\Drive\PublicLinkController::class)->group(function () {
    Route::get('/', 'show')->name('drive.public');
    Route::get('/folder/{folder}', 'folder')->whereNumber('folder')->name('drive.public.folder');
    Route::get('/file/{file}', 'file')->whereNumber('file')->name('drive.public.file');
    Route::get('/zip', 'zip')->name('drive.public.zip');
});

// Private calendar feed (subscribed from Google, Outlook or Apple Calendar; the token is the key)
Route::get('/calendar/{token}.ics', [\App\Http\Controllers\Meetings\MeetingController::class, 'feed'])
    ->where('token', '[A-Za-z0-9]{40,64}')->middleware('throttle:60,1')->name('meetings.feed');

// QR check-in: the door QR's link, and event sign-up and tickets (conferences)
Route::get('/c/{event}/{token}', [\App\Http\Controllers\Checkin\CheckinPublicController::class, 'door'])
    ->whereNumber('event')->where('token', '[a-f0-9]{12}')->middleware('throttle:checkin-public')->name('checkin.door');
Route::prefix('e/{slug}')->middleware('throttle:checkin-public')->controller(\App\Http\Controllers\Checkin\CheckinPublicController::class)->group(function () {
    Route::get('/', 'register')->name('checkin.public.register');
    Route::post('/', 'store')->name('checkin.public.store');
    Route::get('/ticket/{code}', 'ticket')->where('code', '[A-Za-z0-9]{10}')->name('checkin.public.ticket');
});

// Where Paystack sends people who paid in the mobile app. The app watches for this address and checks the
// payment itself (POST /api/v1/billing/payments/verify); this page is only seen if it doesn't.
Route::get('/billing/paystack/app-return', fn () => response()->view('modules.finance.billing.app-return'))
    ->middleware('throttle:60,1')->name('billing.paystack.app-return');
