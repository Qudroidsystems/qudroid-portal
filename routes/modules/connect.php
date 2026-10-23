<?php

use App\Http\Controllers\Connect\CallController;
use App\Http\Controllers\Connect\CallRecordingController;
use App\Http\Controllers\Connect\ConnectController;
use App\Http\Controllers\Connect\LaterController;
use App\Http\Controllers\Connect\NoteController;
use Illuminate\Support\Facades\Route;

/*
| Qudroid Connect — direct messages, groups and channels. Loaded inside
| the signed-in group. Who may read or post where is checked in
| ConnectService.
*/

Route::prefix('connect')->name('connect.')->middleware('permission:Use connect|Chat as a guest')->group(function () {
    Route::get('/', [ConnectController::class, 'index'])->name('index');
    Route::get('/poll/{conversation?}', [ConnectController::class, 'poll'])->middleware('throttle:240,1')->name('poll');
    Route::get('/calls', [CallController::class, 'index'])->name('calls.index');
    Route::get('/calls/ringing', [CallController::class, 'ringing'])->middleware('throttle:60,1')->name('calls.ringing');
    Route::post('/c/{conversation}/calls', [CallController::class, 'start'])->middleware('throttle:20,1')->name('calls.start');
    Route::post('/c/{conversation}/huddle', [CallController::class, 'huddle'])->middleware('throttle:20,1')->name('huddle');
    Route::get('/calls/{call}', [CallController::class, 'show'])->name('calls.show');
    Route::post('/calls/{call}/join', [CallController::class, 'join'])->name('calls.join');
    Route::get('/calls/{call}/poll', [CallController::class, 'poll'])->middleware('throttle:200,1')->name('calls.poll');
    Route::post('/calls/{call}/signal', [CallController::class, 'signal'])->middleware('throttle:600,1')->name('calls.signal');
    Route::post('/calls/{call}/leave', [CallController::class, 'leave'])->name('calls.leave');
    Route::post('/calls/{call}/decline', [CallController::class, 'decline'])->name('calls.decline');
    Route::post('/calls/{call}/recordings', [CallRecordingController::class, 'start'])->middleware('throttle:20,1')->name('recordings.start');
    Route::post('/recordings/{recording}/chunk', [CallRecordingController::class, 'chunk'])->middleware('throttle:240,1')->name('recordings.chunk');
    Route::post('/recordings/{recording}/stop', [CallRecordingController::class, 'stop'])->name('recordings.stop');
    Route::get('/recordings/{recording}', [CallRecordingController::class, 'show'])->name('recordings.show');
    Route::delete('/recordings/{recording}', [CallRecordingController::class, 'destroy'])->name('recordings.destroy');
    Route::post('/calls/{call}/transcribe', [CallRecordingController::class, 'transcribe'])->name('calls.transcribe');
    Route::post('/calls/{call}/say', [CallRecordingController::class, 'say'])->middleware('throttle:240,1')->name('calls.say');
    Route::get('/calls/{call}/recap', [CallRecordingController::class, 'recap'])->name('calls.recap');
    Route::get('/calls/{call}/transcript', [CallRecordingController::class, 'transcript'])->name('calls.transcript');
    Route::post('/calls/{call}/notes', [CallRecordingController::class, 'note'])->name('calls.note');

    Route::get('/gifs', [ConnectController::class, 'gifs'])->middleware('throttle:60,1')->name('gifs');
    Route::get('/c/{conversation}/notes', [NoteController::class, 'index'])->name('notes.index');
    Route::post('/c/{conversation}/notes', [NoteController::class, 'store'])->middleware('throttle:30,1')->name('notes.store');
    Route::post('/notes/preview', [NoteController::class, 'preview'])->middleware('throttle:120,1')->name('notes.preview');
    Route::get('/notes/{note}', [NoteController::class, 'show'])->name('notes.show');
    Route::get('/notes/{note}/edit', [NoteController::class, 'edit'])->name('notes.edit');
    Route::put('/notes/{note}', [NoteController::class, 'update'])->middleware('throttle:120,1')->name('notes.update');
    Route::get('/notes/{note}/state', [NoteController::class, 'state'])->middleware('throttle:240,1')->name('notes.state');
    Route::get('/notes/{note}/history', [NoteController::class, 'history'])->name('notes.history');
    Route::post('/notes/{note}/restore/{revision}', [NoteController::class, 'restore'])->name('notes.restore');
    Route::post('/notes/{note}/pin', [NoteController::class, 'pin'])->name('notes.pin');
    Route::delete('/notes/{note}', [NoteController::class, 'destroy'])->name('notes.destroy');
    Route::get('/later', [LaterController::class, 'index'])->name('later');
    Route::post('/c/{conversation}/schedule', [LaterController::class, 'schedule'])->middleware('throttle:30,1')->name('schedule');
    Route::put('/scheduled/{scheduled}', [LaterController::class, 'update'])->name('scheduled.update');
    Route::post('/scheduled/{scheduled}/send', [LaterController::class, 'sendNow'])->name('scheduled.send');
    Route::delete('/scheduled/{scheduled}', [LaterController::class, 'cancel'])->name('scheduled.cancel');
    Route::post('/reminders', [LaterController::class, 'remind'])->middleware('throttle:60,1')->name('reminders.store');
    Route::post('/reminders/{reminder}/done', [LaterController::class, 'complete'])->name('reminders.done');
    Route::post('/reminders/{reminder}/snooze', [LaterController::class, 'snooze'])->name('reminders.snooze');
    Route::delete('/reminders/{reminder}', [LaterController::class, 'destroy'])->name('reminders.destroy');
    Route::get('/saved', [ConnectController::class, 'saved'])->name('saved');
    Route::get('/search', [ConnectController::class, 'search'])->name('search');
    Route::get('/with/{user}', [ConnectController::class, 'direct'])->name('direct');
    Route::post('/groups', [ConnectController::class, 'storeGroup'])->name('groups.store');
    Route::post('/channels', [ConnectController::class, 'storeChannel'])->name('channels.store');
    Route::get('/project/{project}', [ConnectController::class, 'project'])->name('project');
    Route::get('/project/{project}/clients', [ConnectController::class, 'projectClients'])->name('project-clients');

    Route::get('/c/{conversation}', [ConnectController::class, 'show'])->name('show');
    Route::get('/c/{conversation}/older', [ConnectController::class, 'older'])->name('older');
    Route::post('/c/{conversation}/messages', [ConnectController::class, 'send'])->middleware('throttle:60,1')->name('send');
    Route::post('/c/{conversation}/typing', [ConnectController::class, 'typing'])->middleware('throttle:60,1')->name('typing');
    Route::post('/c/{conversation}/join', [ConnectController::class, 'join'])->name('join');
    Route::post('/c/{conversation}/leave', [ConnectController::class, 'leave'])->name('leave');
    Route::post('/c/{conversation}/members', [ConnectController::class, 'add'])->name('members.add');
    Route::delete('/c/{conversation}/members/{user}', [ConnectController::class, 'remove'])->name('members.remove');
    Route::post('/c/{conversation}/mute', [ConnectController::class, 'mute'])->name('mute');
    Route::post('/c/{conversation}/guests', [ConnectController::class, 'guests'])->name('guests');

    Route::put('/m/{message}', [ConnectController::class, 'edit'])->name('messages.edit');
    Route::delete('/m/{message}', [ConnectController::class, 'destroy'])->name('messages.destroy');
    Route::post('/m/{message}/vote', [ConnectController::class, 'vote'])->middleware('throttle:60,1')->name('messages.vote');
    Route::post('/m/{message}/close-poll', [ConnectController::class, 'closePoll'])->name('messages.close-poll');
    Route::post('/m/{message}/react', [ConnectController::class, 'react'])->name('messages.react');
    Route::get('/m/{message}/thread', [ConnectController::class, 'thread'])->name('messages.thread');
    Route::post('/m/{message}/pin', [ConnectController::class, 'pin'])->name('messages.pin');
    Route::post('/m/{message}/save', [ConnectController::class, 'save'])->name('messages.save');
    Route::get('/m/{message}/file', [ConnectController::class, 'attachment'])->name('messages.file');
});
