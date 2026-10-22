<?php

use App\Http\Controllers\Api\V1\Connect\ConnectApiController;
use Illuminate\Support\Facades\Route;

// Qudroid Connect (chat). Loaded inside the signed-in API group.
Route::prefix('connect')->name('connect.')->middleware('permission:Use connect')->group(function () {
    Route::get('conversations', [ConnectApiController::class, 'conversations'])->name('conversations');
    Route::get('people', [ConnectApiController::class, 'people'])->name('people');
    Route::post('direct', [ConnectApiController::class, 'direct'])->name('direct');
    Route::get('conversations/{conversation}/messages', [ConnectApiController::class, 'messages'])->name('messages');
    Route::post('conversations/{conversation}/messages', [ConnectApiController::class, 'send'])->name('send');
    Route::post('conversations/{conversation}/read', [ConnectApiController::class, 'read'])->name('read');
    Route::post('messages/{message}/react', [ConnectApiController::class, 'react'])->name('react');
    Route::delete('messages/{message}', [ConnectApiController::class, 'destroy'])->name('destroy');
    Route::get('messages/{message}/file', [ConnectApiController::class, 'file'])->name('file');
});
