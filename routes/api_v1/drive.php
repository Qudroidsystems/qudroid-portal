<?php

use App\Http\Controllers\Api\V1\Drive\DriveApiController;
use Illuminate\Support\Facades\Route;

// Workspace Drive. Loaded inside the signed-in API group.
Route::prefix('drive')->name('drive.')->middleware('permission:Use connect')->group(function () {
    Route::get('/', [DriveApiController::class, 'index'])->name('index');
    Route::get('projects', [DriveApiController::class, 'projects'])->name('projects');
    Route::get('shared-with-me', [DriveApiController::class, 'shared'])->name('shared');
    Route::get('recent', [DriveApiController::class, 'recent'])->name('recent');
    Route::get('starred', [DriveApiController::class, 'starred'])->name('starred');
    Route::get('trash', [DriveApiController::class, 'trash'])->name('trash');
    Route::get('people', [DriveApiController::class, 'people'])->name('people');
    Route::post('folders', [DriveApiController::class, 'storeFolder'])->name('folders.store');
    Route::post('files', [DriveApiController::class, 'upload'])->middleware('throttle:120,1')->name('upload');
    Route::get('files/{file}', [DriveApiController::class, 'file'])->name('file');
    Route::get('files/{file}/download', [DriveApiController::class, 'download'])->name('download');

    Route::prefix('items/{type}/{id}')->whereIn('type', ['folder', 'file'])->whereNumber('id')->group(function () {
        Route::put('/', [DriveApiController::class, 'update'])->name('update');
        Route::delete('/', [DriveApiController::class, 'destroy'])->name('destroy');
        Route::post('restore', [DriveApiController::class, 'restore'])->name('restore');
        Route::post('star', [DriveApiController::class, 'star'])->name('star');
        Route::post('share', [DriveApiController::class, 'share'])->name('share');
        Route::delete('share', [DriveApiController::class, 'unshare'])->name('unshare');
        Route::post('link', [DriveApiController::class, 'link'])->name('link');
        Route::delete('link', [DriveApiController::class, 'unlink'])->name('unlink');
    });
});
