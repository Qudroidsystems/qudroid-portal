<?php

use App\Http\Controllers\Connect\ConnectController;
use Illuminate\Support\Facades\Route;

/*
| Qudroid Connect — direct messages, groups and channels. Loaded inside
| the signed-in group. Who may read or post where is checked in
| ConnectService.
*/

Route::prefix('connect')->name('connect.')->middleware('permission:Use connect')->group(function () {
    Route::get('/', [ConnectController::class, 'index'])->name('index');
    Route::get('/poll/{conversation?}', [ConnectController::class, 'poll'])->middleware('throttle:120,1')->name('poll');
    Route::get('/with/{user}', [ConnectController::class, 'direct'])->name('direct');
    Route::post('/groups', [ConnectController::class, 'storeGroup'])->name('groups.store');
    Route::post('/channels', [ConnectController::class, 'storeChannel'])->name('channels.store');
    Route::get('/project/{project}', [ConnectController::class, 'project'])->name('project');

    Route::get('/c/{conversation}', [ConnectController::class, 'show'])->name('show');
    Route::get('/c/{conversation}/older', [ConnectController::class, 'older'])->name('older');
    Route::post('/c/{conversation}/messages', [ConnectController::class, 'send'])->middleware('throttle:60,1')->name('send');
    Route::post('/c/{conversation}/join', [ConnectController::class, 'join'])->name('join');
    Route::post('/c/{conversation}/leave', [ConnectController::class, 'leave'])->name('leave');
    Route::post('/c/{conversation}/members', [ConnectController::class, 'add'])->name('members.add');
    Route::delete('/c/{conversation}/members/{user}', [ConnectController::class, 'remove'])->name('members.remove');
    Route::post('/c/{conversation}/mute', [ConnectController::class, 'mute'])->name('mute');

    Route::put('/m/{message}', [ConnectController::class, 'edit'])->name('messages.edit');
    Route::delete('/m/{message}', [ConnectController::class, 'destroy'])->name('messages.destroy');
    Route::post('/m/{message}/react', [ConnectController::class, 'react'])->name('messages.react');
    Route::get('/m/{message}/file', [ConnectController::class, 'attachment'])->name('messages.file');
});
