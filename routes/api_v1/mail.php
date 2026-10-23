<?php

use App\Http\Controllers\Api\V1\Mail\MailApiController;
use Illuminate\Support\Facades\Route;

// Qmail. Loaded inside the signed-in API group. Mailbox access is checked per account.
Route::prefix('mail')->name('mail.')->middleware('permission:Use mail')->group(function () {
    Route::get('accounts', [MailApiController::class, 'accounts'])->name('accounts');
    Route::prefix('{account}')->group(function () {
        Route::get('threads', [MailApiController::class, 'threads'])->name('threads');
        Route::get('threads/{thread}', [MailApiController::class, 'thread'])->where('thread', '[A-Za-z0-9]{1,64}')->name('thread');
        Route::post('act', [MailApiController::class, 'act'])->middleware('throttle:mail')->name('act');
        Route::get('attachments/{attachment}', [MailApiController::class, 'attachment'])->name('attachment');
        Route::get('drafts', [MailApiController::class, 'drafts'])->name('drafts');
        Route::post('drafts', [MailApiController::class, 'saveDraft'])->middleware('throttle:mail')->name('drafts.save');
        Route::delete('drafts/{draft}', [MailApiController::class, 'discardDraft'])->name('drafts.discard');
        Route::get('compose', [MailApiController::class, 'compose'])->name('compose');
        Route::post('send', [MailApiController::class, 'send'])->middleware('throttle:mail')->name('send');
        Route::get('contacts', [MailApiController::class, 'contacts'])->middleware('throttle:mail')->name('contacts');
        Route::get('poll', [MailApiController::class, 'poll'])->middleware('throttle:mail')->name('poll');
        Route::post('sync', [MailApiController::class, 'syncNow'])->middleware('throttle:mail')->name('sync');
    });
});
