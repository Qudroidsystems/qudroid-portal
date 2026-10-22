<?php

use App\Http\Controllers\Crm\ActivityController;
use App\Http\Controllers\Crm\CompanyController;
use App\Http\Controllers\Crm\ContactController;
use App\Http\Controllers\Crm\CrmController;
use App\Http\Controllers\Crm\DealController;
use Illuminate\Support\Facades\Route;

/*
| Clients & CRM — companies, contacts, the deal pipeline and follow-ups.
| Loaded inside the signed-in group. View clients reads everything and
| logs notes and follow-ups; Manage clients changes records and deals.
*/

Route::prefix('crm')->name('crm.')->middleware('permission:View clients|Manage clients')->group(function () {

    Route::middleware('permission:Manage clients')->group(function () {
        Route::get('/companies/create', [CompanyController::class, 'create'])->name('companies.create');
        Route::post('/companies', [CompanyController::class, 'store'])->name('companies.store');
        Route::get('/companies/{company}/edit', [CompanyController::class, 'edit'])->name('companies.edit');
        Route::put('/companies/{company}', [CompanyController::class, 'update'])->name('companies.update');

        Route::post('/contacts', [ContactController::class, 'store'])->name('contacts.store');
        Route::put('/contacts/{contact}', [ContactController::class, 'update'])->name('contacts.update');
        Route::delete('/contacts/{contact}', [ContactController::class, 'destroy'])->name('contacts.destroy');
        Route::post('/contacts/{contact}/access', [ContactController::class, 'access'])->name('contacts.access');

        Route::post('/deals', [DealController::class, 'store'])->name('deals.store');
        Route::put('/deals/{deal}', [DealController::class, 'update'])->name('deals.update');
        Route::post('/deals/{deal}/move', [DealController::class, 'move'])->name('deals.move');
        Route::post('/deals/{deal}/win', [DealController::class, 'win'])->name('deals.win');
        Route::post('/deals/{deal}/lose', [DealController::class, 'lose'])->name('deals.lose');
        Route::post('/deals/{deal}/reopen', [DealController::class, 'reopen'])->name('deals.reopen');
        Route::delete('/deals/{deal}', [DealController::class, 'destroy'])->name('deals.destroy');
    });

    Route::get('/', CrmController::class)->name('index');
    Route::get('/companies', [CompanyController::class, 'index'])->name('companies.index');
    Route::get('/companies/{company}', [CompanyController::class, 'show'])->name('companies.show');
    Route::get('/contacts', [ContactController::class, 'index'])->name('contacts.index');
    Route::get('/contacts/{contact}', [ContactController::class, 'show'])->name('contacts.show');
    Route::get('/deals', [DealController::class, 'index'])->name('deals.index');
    Route::get('/deals/{deal}', [DealController::class, 'show'])->name('deals.show');

    Route::get('/follow-ups', [ActivityController::class, 'followUps'])->name('follow-ups');
    Route::post('/{type}/{id}/activities', [ActivityController::class, 'store'])->whereIn('type', ['company', 'contact', 'deal'])->whereNumber('id')->name('activities.store');
    Route::post('/activities/{activity}/complete', [ActivityController::class, 'complete'])->name('activities.complete');
    Route::delete('/activities/{activity}', [ActivityController::class, 'destroy'])->name('activities.destroy');
});
