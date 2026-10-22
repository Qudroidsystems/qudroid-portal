<?php

use App\Http\Controllers\Connect\CallController;
use App\Http\Controllers\Connect\ConnectController;
use Illuminate\Support\Facades\Route;

/*
| Qudroid Connect — direct messages, groups and channels. Loaded inside
| the signed-in group. Who may read or post where is checked in
| ConnectService.
*/

Route::prefix('connect')->name('connect.')->middleware('permission:Use connect')->group(function () {
    Route::get('/', [ConnectController::class, 'index'])->name('index');
    Route::get('/poll/{conversation?}', [ConnectController::class, 'poll'])->middleware('throttle:240,1')->name('poll');
    Route::get('/calls', [CallController::class, 'index'])->name('calls.index');
    Route::get('/calls/ringing', [CallController::class, 'ringing'])->middleware('throttle:60,1')->name('calls.ringing');
    Route::post('/c/{conversation}/calls', [CallController::class, 'start'])->middleware('throttle:20,1')->name('calls.start');
    Route::get('/calls/{call}', [CallController::class, 'show'])->name('calls.show');
    Route::post('/calls/{call}/join', [CallController::class, 'join'])->name('calls.join');
    Route::get('/calls/{call}/poll', [CallController::class, 'poll'])->middleware('throttle:200,1')->name('calls.poll');
    Route::post('/calls/{call}/signal', [CallController::class, 'signal'])->middleware('throttle:600,1')->name('calls.signal');
    Route::post('/calls/{call}/leave', [CallController::class, 'leave'])->name('calls.leave');
    Route::post('/calls/{call}/decline', [CallController::class, 'decline'])->name('calls.decline');

    Route::get('/saved', [ConnectController::class, 'saved'])->name('saved');
    Route::get('/search', [ConnectController::class, 'search'])->name('search');
    Route::get('/with/{user}', [ConnectController::class, 'direct'])->name('direct');
    Route::post('/groups', [ConnectController::class, 'storeGroup'])->name('groups.store');
    Route::post('/channels', [ConnectController::class, 'storeChannel'])->name('channels.store');
    Route::get('/project/{project}', [ConnectController::class, 'project'])->name('project');

    Route::get('/c/{conversation}', [ConnectController::class, 'show'])->name('show');
    Route::get('/c/{conversation}/older', [ConnectController::class, 'older'])->name('older');
    Route::post('/c/{conversation}/messages', [ConnectController::class, 'send'])->middleware('throttle:60,1')->name('send');
    Route::post('/c/{conversation}/typing', [ConnectController::class, 'typing'])->middleware('throttle:60,1')->name('typing');
    Route::post('/c/{conversation}/join', [ConnectController::class, 'join'])->name('join');
    Route::post('/c/{conversation}/leave', [ConnectController::class, 'leave'])->name('leave');
    Route::post('/c/{conversation}/members', [ConnectController::class, 'add'])->name('members.add');
    Route::delete('/c/{conversation}/members/{user}', [ConnectController::class, 'remove'])->name('members.remove');
    Route::post('/c/{conversation}/mute', [ConnectController::class, 'mute'])->name('mute');

    Route::put('/m/{message}', [ConnectController::class, 'edit'])->name('messages.edit');
    Route::delete('/m/{message}', [ConnectController::class, 'destroy'])->name('messages.destroy');
    Route::post('/m/{message}/react', [ConnectController::class, 'react'])->name('messages.react');
    Route::get('/m/{message}/thread', [ConnectController::class, 'thread'])->name('messages.thread');
    Route::post('/m/{message}/pin', [ConnectController::class, 'pin'])->name('messages.pin');
    Route::post('/m/{message}/save', [ConnectController::class, 'save'])->name('messages.save');
    Route::get('/m/{message}/file', [ConnectController::class, 'attachment'])->name('messages.file');
});
