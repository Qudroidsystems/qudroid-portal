<?php

use App\Http\Controllers\Api\V1\Reports\CommandCenterApiController;
use Illuminate\Support\Facades\Route;

// The Command Center (View command center). Loaded inside the signed-in API group.
Route::get('command-center', [CommandCenterApiController::class, 'show'])->middleware('throttle:30,1')->name('command-center');
