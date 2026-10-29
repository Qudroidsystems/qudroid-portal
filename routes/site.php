<?php

use App\Http\Controllers\Website\AcademySiteController;
use App\Http\Controllers\Website\PayController;
use App\Http\Controllers\Website\SiteController;
use Illuminate\Support\Facades\Route;

/*
| The public website (managed in the portal under Website). "/" is its front
| page; with the website switched off, "/" goes to sign-in as before. Any
| address nothing else claims is tried as a website page (see the fallback
| at the end of web.php).
*/

Route::get('/', [SiteController::class, 'home'])->name('site.home');

Route::name('site.')->middleware('throttle:240,1')->group(function () {
    Route::get('/products', [SiteController::class, 'products'])->name('products');
    Route::get('/products/{product}', [SiteController::class, 'product'])->name('products.show');
    Route::get('/work', [SiteController::class, 'works'])->name('works');
    Route::get('/work/{work}', [SiteController::class, 'work'])->name('works.show');
    Route::get('/contact', [SiteController::class, 'contact'])->name('contact');
    Route::get('/forms/{form}', [SiteController::class, 'form'])->name('forms.show');
    Route::post('/forms/{form}', [SiteController::class, 'submit'])->middleware('throttle:6,1')->name('forms.submit');

    // Courses and registration
    Route::get('/courses', [AcademySiteController::class, 'index'])->name('academy');
    Route::get('/courses/registered/{reference}', [AcademySiteController::class, 'registered'])->name('academy.registered');
    Route::get('/courses/{slug}', [AcademySiteController::class, 'course'])->where('slug', '[A-Za-z0-9-]+')->name('academy.course');
    Route::get('/courses/{slug}/register/{cohort}', [AcademySiteController::class, 'register'])->whereNumber('cohort')->name('academy.register');
    Route::post('/courses/{slug}/register/{cohort}', [AcademySiteController::class, 'store'])->whereNumber('cohort')->middleware('throttle:10,10')->name('academy.store');

    // Paying an invoice from a signed link (no sign-in), and coming back from the gateway
    Route::get('/pay/{invoice}', [PayController::class, 'show'])->whereNumber('invoice')->middleware('signed')->name('pay');
    Route::post('/pay/{invoice}', [PayController::class, 'start'])->whereNumber('invoice')->middleware(['signed', 'throttle:10,1'])->name('pay.start');
    Route::get('/pay/return/{reference}', [PayController::class, 'returned'])->where('reference', 'QH-[0-9]+-[A-Z0-9]{10}')->name('pay.return');
});
