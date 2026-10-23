<?php

use App\Http\Controllers\SearchController;
use Illuminate\Support\Facades\Route;

// One search across the portal: JSON for the ⌘K box, a full page otherwise. Loaded inside the signed-in group.
Route::get('/search', SearchController::class)->middleware('throttle:120,1')->name('search');
