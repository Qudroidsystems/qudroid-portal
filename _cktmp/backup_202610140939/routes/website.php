<?php

use App\Http\Controllers\Website\SiteController;
use App\Http\Controllers\Website\WebsiteAdminController as WA;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| School website (public) and its admin (portal › Website)
|--------------------------------------------------------------------------
| Included from routes/web.php. The home page "/" is registered there.
*/

Route::name('site.')->group(function () {
    Route::get('/pages/{slug}', [SiteController::class, 'page'])->name('page');
    Route::get('/news', [SiteController::class, 'news'])->name('news');
    Route::get('/news/{slug}', [SiteController::class, 'post'])->name('post');
    Route::get('/gallery', [SiteController::class, 'gallery'])->name('gallery');
    Route::get('/gallery/{slug}', [SiteController::class, 'album'])->name('album');
    Route::get('/our-people', [SiteController::class, 'people'])->name('people');
    Route::get('/academics', [SiteController::class, 'academics'])->name('academics');
    Route::get('/admissions', [SiteController::class, 'admissions'])->name('admissions');
    Route::post('/admissions/apply', [SiteController::class, 'apply'])->middleware('throttle:6,10')->name('apply');
    Route::get('/contact', [SiteController::class, 'contact'])->name('contact');
    Route::post('/contact', [SiteController::class, 'sendContact'])->middleware('throttle:6,10')->name('contact.send');
    Route::post('/newsletter', [SiteController::class, 'newsletter'])->middleware('throttle:6,10')->name('newsletter');
    Route::get('/school-calendar', [SiteController::class, 'calendar'])->name('calendar');
    Route::get('/app', [SiteController::class, 'app'])->name('app');
    Route::get('/app/download', [SiteController::class, 'apk'])->name('app.apk');
    Route::get('/media/{path}', [SiteController::class, 'media'])->where('path', 'website/.+')->name('media');
});

Route::middleware('auth')->prefix('admin/website')->name('website.admin.')->group(function () {
    Route::get('/', [WA::class, 'index'])->name('index');
    Route::get('/settings', [WA::class, 'settings'])->name('settings');
    Route::post('/settings', [WA::class, 'saveSettings'])->name('settings.save');

    Route::get('/gallery', [WA::class, 'albums'])->name('albums');
    Route::post('/gallery', [WA::class, 'saveAlbum'])->name('albums.store');
    Route::get('/gallery/{id}', [WA::class, 'album'])->whereNumber('id')->name('album');
    Route::post('/gallery/{id}', [WA::class, 'saveAlbum'])->whereNumber('id')->name('album.save');
    Route::post('/gallery/{id}/photos', [WA::class, 'uploadPhotos'])->whereNumber('id')->name('album.upload');
    Route::post('/gallery/{id}/photos/update', [WA::class, 'updatePhotos'])->whereNumber('id')->name('album.photos');
    Route::delete('/gallery/{id}', [WA::class, 'deleteAlbum'])->whereNumber('id')->name('album.delete');

    Route::get('/messages', [WA::class, 'messages'])->name('messages');
    Route::get('/messages/export', [WA::class, 'exportMessages'])->name('messages.export');
    Route::post('/messages/{id}/read', [WA::class, 'readMessage'])->whereNumber('id')->name('messages.read');
    Route::delete('/messages/{id}', [WA::class, 'deleteMessage'])->whereNumber('id')->name('messages.delete');

    Route::get('/app', [WA::class, 'app'])->name('app');
    Route::post('/app', [WA::class, 'saveApp'])->name('app.save');

    Route::get('/people/staff-options', [WA::class, 'staffOptions'])->name('people.staff');
    Route::post('/people/import', [WA::class, 'importStaff'])->name('people.import');

    Route::get('/{type}', [WA::class, 'list'])->where('type', '[a-z]+')->name('list');
    Route::get('/{type}/new', [WA::class, 'create'])->where('type', '[a-z]+')->name('create');
    Route::post('/{type}', [WA::class, 'store'])->where('type', '[a-z]+')->name('store');
    Route::get('/{type}/{id}', [WA::class, 'edit'])->where('type', '[a-z]+')->whereNumber('id')->name('edit');
    Route::post('/{type}/{id}', [WA::class, 'update'])->where('type', '[a-z]+')->whereNumber('id')->name('update');
    Route::delete('/{type}/{id}', [WA::class, 'destroy'])->where('type', '[a-z]+')->whereNumber('id')->name('destroy');
});
