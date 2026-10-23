<?php

use App\Http\Controllers\Api\V1\Meetings\MeetingApiController;
use Illuminate\Support\Facades\Route;

// Meetings & Calendar. Loaded inside the signed-in API group.
Route::prefix('meetings')->name('meetings.')->middleware('permission:View meetings|Manage meetings')->group(function () {
    Route::get('/', [MeetingApiController::class, 'index'])->name('index');
    Route::get('calendar', [MeetingApiController::class, 'calendar'])->name('calendar');
    Route::get('people', [MeetingApiController::class, 'people'])->name('people');
    Route::post('/', [MeetingApiController::class, 'store'])->middleware('throttle:30,1')->name('store');
    Route::put('{meeting}', [MeetingApiController::class, 'update'])->whereNumber('meeting')->name('update');
    Route::delete('{meeting}', [MeetingApiController::class, 'destroy'])->whereNumber('meeting')->name('destroy');
    Route::get('{meeting}', [MeetingApiController::class, 'show'])->whereNumber('meeting')->name('show');
    Route::post('{meeting}/respond', [MeetingApiController::class, 'respond'])->whereNumber('meeting')->name('respond');
});
