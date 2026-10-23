<?php

use App\Http\Controllers\Reports\CommandCenterController;
use Illuminate\Support\Facades\Route;

// Qudroid Command Center: the executive view across every module. Loaded inside the signed-in group.
Route::get('/command-center', [CommandCenterController::class, 'index'])->middleware('permission:View command center')->name('command-center');
