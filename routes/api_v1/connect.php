<?php

use App\Http\Controllers\Api\V1\Connect\CallApiController;
use App\Http\Controllers\Api\V1\Connect\ConnectApiController;
use Illuminate\Support\Facades\Route;

// Qudroid Connect (chat). Loaded inside the signed-in API group.
Route::prefix('connect')->name('connect.')->middleware('permission:Use connect|Chat as a guest')->group(function () {
    Route::get('conversations', [ConnectApiController::class, 'conversations'])->name('conversations');
    Route::get('people', [ConnectApiController::class, 'people'])->name('people');
    Route::post('direct', [ConnectApiController::class, 'direct'])->name('direct');
    Route::get('conversations/{conversation}/messages', [ConnectApiController::class, 'messages'])->name('messages');
    Route::post('conversations/{conversation}/messages', [ConnectApiController::class, 'send'])->name('send');
    Route::post('conversations/{conversation}/read', [ConnectApiController::class, 'read'])->name('read');
    Route::post('messages/{message}/react', [ConnectApiController::class, 'react'])->name('react');
    Route::delete('messages/{message}', [ConnectApiController::class, 'destroy'])->name('destroy');
    Route::get('messages/{message}/file', [ConnectApiController::class, 'file'])->name('file');

    Route::get('poll', [ConnectApiController::class, 'poll'])->middleware('throttle:120,1')->name('poll');
    Route::get('search', [ConnectApiController::class, 'search'])->name('search');
    Route::get('saved', [ConnectApiController::class, 'saved'])->name('saved');
    Route::get('gifs', [ConnectApiController::class, 'gifs'])->middleware('throttle:60,1')->name('gifs');
    Route::post('conversations', [ConnectApiController::class, 'create'])->name('create');
    Route::get('conversations/{conversation}/members', [ConnectApiController::class, 'members'])->name('members');
    Route::post('conversations/{conversation}/members', [ConnectApiController::class, 'addMembers'])->name('members.add');
    Route::delete('conversations/{conversation}/members/{user}', [ConnectApiController::class, 'removeMember'])->name('members.remove');
    Route::post('conversations/{conversation}/mute', [ConnectApiController::class, 'mute'])->name('mute');
    Route::post('conversations/{conversation}/typing', [ConnectApiController::class, 'typing'])->middleware('throttle:60,1')->name('typing');
    Route::get('conversations/{conversation}/messages/{message}/thread', [ConnectApiController::class, 'thread'])->name('thread');
    Route::put('messages/{message}', [ConnectApiController::class, 'edit'])->name('edit');
    Route::post('messages/{message}/vote', [ConnectApiController::class, 'vote'])->name('vote');
    Route::post('messages/{message}/close-poll', [ConnectApiController::class, 'closePoll'])->name('close-poll');
    Route::post('messages/{message}/pin', [ConnectApiController::class, 'pin'])->name('pin');
    Route::post('messages/{message}/save', [ConnectApiController::class, 'save'])->name('save');
});

// Calls (voice, video, huddles and meeting calls).
Route::prefix('calls')->name('calls.')->middleware('permission:Use connect|Chat as a guest')->group(function () {
    Route::get('ice-servers', [CallApiController::class, 'iceServers'])->name('ice');
    Route::get('ringing', [CallApiController::class, 'ringing'])->middleware('throttle:60,1')->name('ringing');
    Route::post('/', [CallApiController::class, 'start'])->middleware('throttle:20,1')->name('start');
    Route::get('{call}', [CallApiController::class, 'show'])->whereNumber('call')->name('show');
    Route::post('{call}/join', [CallApiController::class, 'join'])->whereNumber('call')->name('join');
    Route::post('{call}/leave', [CallApiController::class, 'leave'])->whereNumber('call')->name('leave');
    Route::post('{call}/decline', [CallApiController::class, 'decline'])->whereNumber('call')->name('decline');
    Route::post('{call}/signal', [CallApiController::class, 'signal'])->whereNumber('call')->middleware('throttle:600,1')->name('signal');
    Route::get('{call}/signals', [CallApiController::class, 'signals'])->whereNumber('call')->middleware('throttle:200,1')->name('signals');
});
