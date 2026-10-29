<?php

use App\Http\Controllers\Website\Admin\FormController;
use App\Http\Controllers\Website\Admin\MediaController;
use App\Http\Controllers\Website\Admin\PageController;
use App\Http\Controllers\Website\Admin\ProductController;
use App\Http\Controllers\Website\Admin\WebsiteController;
use App\Http\Controllers\Website\Admin\WorkController;
use Illuminate\Support\Facades\Route;

/*
| Website — managing the public website (routes/site.php) from the portal:
| pages built from blocks, products, our work, forms and their entries,
| pictures and the website's settings. Loaded inside the signed-in group.
*/

Route::prefix('website')->name('website.')->middleware('permission:Manage website')->group(function () {
    Route::get('/', [WebsiteController::class, 'index'])->name('index');
    Route::get('/settings', [WebsiteController::class, 'settings'])->name('settings');
    Route::post('/settings', [WebsiteController::class, 'saveSettings'])->name('settings.save');
    Route::post('/starter', [WebsiteController::class, 'starter'])->name('starter');

    Route::resource('pages', PageController::class)->except('show')->parameters(['pages' => 'page:id']);
    Route::resource('products', ProductController::class)->except('show')->parameters(['products' => 'product:id']);
    Route::resource('works', WorkController::class)->except('show')->parameters(['works' => 'work:id']);

    Route::resource('forms', FormController::class)->except('show')->parameters(['forms' => 'form:id']);
    Route::get('/forms/{form:id}/entries', [FormController::class, 'entries'])->name('forms.entries');
    Route::get('/forms/{form:id}/entries/export', [FormController::class, 'export'])->name('forms.export');
    Route::post('/entries/{entry}/status', [FormController::class, 'entryStatus'])->name('entries.status');
    Route::delete('/entries/{entry}', [FormController::class, 'destroyEntry'])->name('entries.destroy');

    Route::get('/media', [MediaController::class, 'index'])->name('media.index');
    Route::get('/media/list', [MediaController::class, 'list'])->name('media.list');
    Route::post('/media', [MediaController::class, 'store'])->middleware('throttle:60,1')->name('media.store');
    Route::patch('/media/{media}', [MediaController::class, 'update'])->name('media.update');
    Route::delete('/media/{media}', [MediaController::class, 'destroy'])->name('media.destroy');
});
