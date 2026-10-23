<?php

use App\Http\Controllers\Mail\MailAccountController;
use App\Http\Controllers\Mail\MailController;
use Illuminate\Support\Facades\Route;

// Qmail. Loaded inside the signed-in group. Mailbox access is checked per account in the controllers.
Route::prefix('mail')->name('mail.')->middleware('permission:Use mail')->group(function () {
    Route::get('/', [MailController::class, 'index'])->name('index');
    Route::get('/setup', [MailAccountController::class, 'setup'])->name('setup');
    Route::post('/connect', [MailAccountController::class, 'connect'])->middleware('throttle:mail')->name('connect');

    Route::middleware('permission:Manage mail')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/', [MailAccountController::class, 'admin'])->name('index');
        Route::post('/', [MailAccountController::class, 'store'])->middleware('throttle:mail')->name('store');
        Route::put('/{account}/members', [MailAccountController::class, 'members'])->name('members');
        Route::post('/{account}/password', [MailAccountController::class, 'resetPassword'])->middleware('throttle:mail')->name('password');
        Route::post('/{account}/check', [MailAccountController::class, 'check'])->middleware('throttle:mail')->name('check');
        Route::delete('/{account}', [MailAccountController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('{account}')->group(function () {
        Route::get('/settings', [MailAccountController::class, 'settings'])->name('settings');
        Route::put('/settings', [MailAccountController::class, 'updateSettings'])->middleware('throttle:mail')->name('settings.update');
        Route::delete('/disconnect', [MailAccountController::class, 'disconnect'])->name('disconnect');

        Route::get('/t/{thread}', [MailController::class, 'thread'])->where('thread', '[A-Za-z0-9]{1,64}')->name('thread');
        Route::get('/m/{message}/body', [MailController::class, 'body'])->name('body');
        Route::get('/files/{attachment}', [MailController::class, 'attachment'])->name('attachment');
        Route::post('/act', [MailController::class, 'act'])->middleware('throttle:mail')->name('act');
        Route::get('/compose', [MailController::class, 'compose'])->name('compose');
        Route::post('/drafts', [MailController::class, 'saveDraft'])->middleware('throttle:mail')->name('drafts.save');
        Route::delete('/drafts/{draft}', [MailController::class, 'discardDraft'])->name('drafts.discard');
        Route::post('/send', [MailController::class, 'send'])->middleware('throttle:mail')->name('send');
        Route::get('/contacts', [MailController::class, 'contacts'])->middleware('throttle:mail')->name('contacts');
        Route::get('/poll', [MailController::class, 'poll'])->middleware('throttle:mail')->name('poll');
        Route::post('/sync', [MailController::class, 'syncNow'])->middleware('throttle:mail')->name('sync');
        Route::get('/{view?}', [MailController::class, 'box'])->where('view', '[a-z]+|[0-9]+')->name('box');
    });
});
