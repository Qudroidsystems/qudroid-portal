<?php

use App\Http\Controllers\Api\V1\Crm\CrmApiController;
use Illuminate\Support\Facades\Route;

// CRM. Loaded inside the signed-in API group; access is checked in the controller (View clients / Manage clients).
Route::prefix('crm')->name('crm.')->controller(CrmApiController::class)->group(function () {
    Route::get('companies', 'companies')->name('companies');
    Route::post('companies', 'storeCompany')->middleware('throttle:30,1')->name('companies.store');
    Route::get('companies/{company}', 'company')->whereNumber('company')->name('companies.show');
    Route::get('contacts', 'contacts')->name('contacts');
    Route::post('contacts', 'storeContact')->middleware('throttle:30,1')->name('contacts.store');
    Route::get('contacts/{contact}', 'contact')->whereNumber('contact')->name('contacts.show');
    Route::get('deals', 'deals')->name('deals');
    Route::get('deals/{deal}', 'deal')->whereNumber('deal')->name('deals.show');
    Route::post('deals/{deal}/move', 'moveDeal')->whereNumber('deal')->name('deals.move');
    Route::get('activities', 'activities')->name('activities');
    Route::post('activities', 'storeActivity')->middleware('throttle:60,1')->name('activities.store');
    Route::post('activities/{activity}/done', 'completeActivity')->whereNumber('activity')->name('activities.done');
});
