<?php

use App\Http\Controllers\Api\V1\Meetings\MeetingApiController;
use Illuminate\Support\Facades\Route;

// Meetings & Calendar. Loaded inside the signed-in API group.
Route::prefix('meetings')->name('meetings.')->middleware('permission:View meetings|Manage meetings')->group(function () {
    Route::get('/', [MeetingApiController::class, 'index'])->name('index');
    Route::get('calendar', [MeetingApiController::class, 'calendar'])->name('calendar');
    Route::get('{meeting}', [MeetingApiController::class, 'show'])->whereNumber('meeting')->name('show');
    Route::post('{meeting}/respond', [MeetingApiController::class, 'respond'])->whereNumber('meeting')->name('respond');
});
