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
