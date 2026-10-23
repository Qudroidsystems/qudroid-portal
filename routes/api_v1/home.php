<?php

use App\Http\Controllers\Api\V1\Home\HomeApiController;
use Illuminate\Support\Facades\Route;

// The app's home screen and search across modules. Loaded inside the signed-in API group.
Route::get('home', [HomeApiController::class, 'home'])->name('home');
Route::get('search', [HomeApiController::class, 'search'])->middleware('throttle:60,1')->name('search');
