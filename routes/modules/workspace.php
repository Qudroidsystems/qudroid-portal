<?php

use App\Http\Controllers\Workspace\WorkspaceController;
use Illuminate\Support\Facades\Route;

// Workspace home: chats, calls, meetings and files in one place. Loaded inside the signed-in group.
Route::get('/workspace', [WorkspaceController::class, 'index'])->middleware('permission:Use connect')->name('workspace.index');
