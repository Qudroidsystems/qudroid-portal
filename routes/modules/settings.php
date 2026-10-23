<?php

use App\Http\Controllers\Settings\SettingsController;
use Illuminate\Support\Facades\Route;

// Portal-wide settings: Paystack keys and payroll tax rates. Loaded inside the signed-in group.
Route::prefix('admin/settings')->name('portal-settings.')->group(function () {
    Route::middleware('permission:Manage settings')->group(function () {
        Route::get('/payments', [SettingsController::class, 'payments'])->name('payments');
        Route::post('/payments', [SettingsController::class, 'savePayments'])->name('payments.save');
        Route::post('/payments/test', [SettingsController::class, 'testPayments'])->name('payments.test');
        Route::post('/payments/env', [SettingsController::class, 'useEnv'])->name('payments.env');

        Route::get('/integrations', [SettingsController::class, 'integrations'])->name('integrations');
        Route::post('/integrations', [SettingsController::class, 'saveIntegrations'])->name('integrations.save');

        Route::get('/units', [SettingsController::class, 'units'])->name('units');
        Route::post('/units', [SettingsController::class, 'storeUnit'])->name('units.store');
        Route::put('/units/{unit}', [SettingsController::class, 'updateUnit'])->name('units.update');
    });
    Route::middleware('permission:Manage settings|Manage payroll')->group(function () {
        Route::get('/payroll', [SettingsController::class, 'payroll'])->name('payroll');
        Route::post('/payroll', [SettingsController::class, 'savePayroll'])->name('payroll.save');
        Route::post('/payroll/review', [SettingsController::class, 'reviewPayroll'])->name('payroll.review');
        Route::post('/payroll/reset', [SettingsController::class, 'resetPayroll'])->name('payroll.reset');
    });
});
