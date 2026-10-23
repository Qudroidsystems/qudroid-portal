<?php

use App\Http\Controllers\Checkin\CheckinController;
use App\Http\Controllers\Checkin\PlaceController;
use Illuminate\Support\Facades\Route;

/*
| QR check-in. Loaded inside the signed-in group. Who may run which door is
| checked in CheckinService::canRun; the public pages are in routes/public.php.
*/
Route::prefix('checkin')->name('checkin.')->group(function () {
    Route::middleware('permission:Check in|Run check-in|Manage check-in places')->group(function () {
        Route::get('/', [CheckinController::class, 'index'])->name('index');
        Route::get('/badge', [CheckinController::class, 'badge'])->name('badge');
        Route::match(['get', 'post'], '/go/{event}', [CheckinController::class, 'go'])->middleware('throttle:30,1')->name('go');

        // Running a door (each event checks who may run it)
        Route::get('/events/{event}', [CheckinController::class, 'show'])->name('events.show');
        Route::put('/events/{event}', [CheckinController::class, 'update'])->name('events.update');
        Route::get('/events/{event}/screen', [CheckinController::class, 'screen'])->name('events.screen');
        Route::get('/events/{event}/screen.json', [CheckinController::class, 'screenData'])->name('events.screen.data');
        Route::get('/events/{event}/scan', [CheckinController::class, 'scanner'])->name('events.scanner');
        Route::post('/events/{event}/scan', [CheckinController::class, 'scan'])->middleware('throttle:240,1')->name('events.scan');
        Route::post('/events/{event}/mark', [CheckinController::class, 'mark'])->name('events.mark');
        Route::post('/events/{event}/close', [CheckinController::class, 'close'])->name('events.close');
        Route::post('/events/{event}/reopen', [CheckinController::class, 'reopen'])->name('events.reopen');
        Route::get('/events/{event}/export', [CheckinController::class, 'export'])->name('events.export');
        Route::delete('/records/{record}', [CheckinController::class, 'undo'])->name('records.undo');
        Route::post('/sessions/{session}', [CheckinController::class, 'forSession'])->name('session');
        Route::post('/meetings/{meeting}', [CheckinController::class, 'forMeeting'])->name('meeting');
    });

    Route::middleware('permission:Run check-in')->group(function () {
        Route::get('/events-new', [CheckinController::class, 'create'])->name('events.create');
        Route::post('/events', [CheckinController::class, 'store'])->name('events.store');
        Route::get('/events/{event}/edit', [CheckinController::class, 'edit'])->name('events.edit');
        Route::delete('/events/{event}', [CheckinController::class, 'destroy'])->name('events.destroy');
        Route::post('/events/{event}/people', [CheckinController::class, 'addPeople'])->name('events.people');
        Route::delete('/events/{event}/people/{user}', [CheckinController::class, 'removePerson'])->name('events.people.remove');
        Route::post('/events/{event}/guests', [CheckinController::class, 'addGuest'])->name('events.guests');
        Route::delete('/events/{event}/guests/{guest}', [CheckinController::class, 'cancelGuest'])->name('events.guests.cancel');
        Route::post('/events/{event}/guests/{guest}/resend', [CheckinController::class, 'resendTicket'])->middleware('throttle:20,1')->name('events.guests.resend');
    });

    Route::middleware('permission:Manage check-in places|Manage employees')->group(function () {
        Route::get('/places', [PlaceController::class, 'index'])->name('places.index');
        Route::post('/places', [PlaceController::class, 'store'])->name('places.store');
        Route::put('/places/{place}', [PlaceController::class, 'update'])->name('places.update');
        Route::get('/places/{place}/screen', [PlaceController::class, 'screen'])->name('places.screen');
        Route::get('/places/{place}/today', [PlaceController::class, 'today'])->name('places.today');
        Route::get('/staff', [PlaceController::class, 'report'])->name('staff');
    });
});
