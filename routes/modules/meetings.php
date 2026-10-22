<?php

use App\Http\Controllers\Meetings\MeetingController;
use Illuminate\Support\Facades\Route;

/*
| Meetings & Calendar. Loaded inside the signed-in group. Anyone with View
| meetings schedules their own meetings; the organiser or Manage meetings
| changes them. Manage meetings also looks after the rooms.
*/

Route::prefix('meetings')->name('meetings.')->middleware('permission:View meetings|Manage meetings')->group(function () {
    Route::get('/', [MeetingController::class, 'index'])->name('index');
    Route::get('/calendar', [MeetingController::class, 'calendar'])->name('calendar');
    Route::get('/create', [MeetingController::class, 'create'])->name('create');
    Route::post('/', [MeetingController::class, 'store'])->name('store');
    Route::post('/check', [MeetingController::class, 'check'])->name('check');

    Route::middleware('permission:Manage meetings')->group(function () {
        Route::get('/rooms', [MeetingController::class, 'rooms'])->name('rooms');
        Route::post('/rooms', [MeetingController::class, 'storeRoom'])->name('rooms.store');
        Route::put('/rooms/{room}', [MeetingController::class, 'updateRoom'])->name('rooms.update');
    });

    Route::get('/{meeting}', [MeetingController::class, 'show'])->whereNumber('meeting')->name('show');
    Route::get('/{meeting}/edit', [MeetingController::class, 'edit'])->whereNumber('meeting')->name('edit');
    Route::put('/{meeting}', [MeetingController::class, 'update'])->whereNumber('meeting')->name('update');
    Route::post('/{meeting}/cancel', [MeetingController::class, 'cancel'])->whereNumber('meeting')->name('cancel');
    Route::post('/{meeting}/respond', [MeetingController::class, 'respond'])->whereNumber('meeting')->name('respond');
    Route::put('/{meeting}/minutes', [MeetingController::class, 'minutes'])->whereNumber('meeting')->name('minutes');
    Route::get('/{meeting}/ics', [MeetingController::class, 'ics'])->whereNumber('meeting')->name('ics');
});
