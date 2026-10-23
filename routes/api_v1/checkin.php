<?php

use App\Http\Controllers\Api\V1\Checkin\CheckinApiController;
use Illuminate\Support\Facades\Route;

// QR check-in for the mobile app. Loaded inside the signed-in API group.
Route::prefix('checkin')->name('checkin.')->group(function () {
    Route::middleware('permission:Check in|Run check-in')->group(function () {
        Route::get('me', [CheckinApiController::class, 'me'])->name('me');
        Route::get('history', [CheckinApiController::class, 'history'])->name('history');
        Route::post('door', [CheckinApiController::class, 'door'])->middleware('throttle:30,1')->name('door');
        // Organisers; who may run each event is checked per event.
        Route::get('events/{event}', [CheckinApiController::class, 'event'])->name('events.show');
        Route::post('events/{event}/scan', [CheckinApiController::class, 'scan'])->name('events.scan');
    });
});
