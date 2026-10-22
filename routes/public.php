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
