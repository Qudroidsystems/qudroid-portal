<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API routes
|--------------------------------------------------------------------------
| Laravel prefixes everything here with /api.
| The mobile app (Flutter) uses version 1 — see routes/api_v1.php.
*/

Route::prefix('v1')->name('api.v1.')->group(base_path('routes/api_v1.php'));
