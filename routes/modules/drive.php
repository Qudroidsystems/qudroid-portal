<?php

use App\Http\Controllers\Drive\DriveController;
use Illuminate\Support\Facades\Route;

/*
| Workspace Drive. Loaded inside the signed-in group. Who may see or change
| what is checked in DriveService ({type} is "folder" or "file").
*/

Route::prefix('drive')->name('drive.')->middleware('permission:Use connect')->group(function () {
    Route::get('/', [DriveController::class, 'index'])->name('index');
    Route::get('/shared', [DriveController::class, 'shared'])->name('shared');
    Route::get('/recent', [DriveController::class, 'recent'])->name('recent');
    Route::get('/starred', [DriveController::class, 'starred'])->name('starred');
    Route::get('/trash', [DriveController::class, 'trash'])->name('trash');
    Route::post('/trash/empty', [DriveController::class, 'emptyTrash'])->name('trash.empty');
    Route::get('/chats', [DriveController::class, 'chats'])->name('chats');
    Route::post('/chats/{message}/save', [DriveController::class, 'saveFromChat'])->name('chats.save');
    Route::get('/search', [DriveController::class, 'search'])->name('search');
    Route::get('/projects/{project}', [DriveController::class, 'project'])->name('project');

    Route::post('/folders', [DriveController::class, 'storeFolder'])->name('folders.store');
    Route::post('/upload', [DriveController::class, 'upload'])->middleware('throttle:120,1')->name('upload');
    Route::get('/folders/{folder}', [DriveController::class, 'folder'])->name('folder');
    Route::get('/folders/{folder}/zip', [DriveController::class, 'zip'])->name('folder.zip');

    Route::get('/files/{file}', [DriveController::class, 'file'])->name('file');
    Route::get('/files/{file}/view', [DriveController::class, 'view'])->name('file.view');
    Route::get('/files/{file}/download', [DriveController::class, 'download'])->name('file.download');
    Route::post('/files/{file}/versions', [DriveController::class, 'newVersion'])->name('file.versions.store');
    Route::post('/files/{file}/versions/{version}/restore', [DriveController::class, 'restoreVersion'])->name('file.versions.restore');
    Route::post('/files/{file}/describe', [DriveController::class, 'describe'])->name('file.describe');

    Route::prefix('{type}/{id}')->whereIn('type', ['folder', 'file'])->whereNumber('id')->group(function () {
        Route::post('/rename', [DriveController::class, 'rename'])->name('rename');
        Route::get('/move', [DriveController::class, 'moveForm'])->name('move');
        Route::post('/move', [DriveController::class, 'move'])->name('move.store');
        Route::post('/trash', [DriveController::class, 'trashItem'])->name('trash.item');
        Route::post('/restore', [DriveController::class, 'restore'])->name('restore');
        Route::delete('/', [DriveController::class, 'destroy'])->name('destroy');
        Route::post('/star', [DriveController::class, 'star'])->name('star');
        Route::get('/share', [DriveController::class, 'shareForm'])->name('share');
        Route::post('/share', [DriveController::class, 'share'])->name('share.store');
        Route::delete('/share/{user}', [DriveController::class, 'unshare'])->name('share.destroy');
        Route::post('/link', [DriveController::class, 'createLink'])->name('link.store');
        Route::delete('/link', [DriveController::class, 'revokeLink'])->name('link.destroy');
        Route::post('/send', [DriveController::class, 'sendToChat'])->name('send');
    });
});
