<?php

use App\Http\Controllers\Certificates\CertificateController;
use Illuminate\Support\Facades\Route;

/*
| Certificates — course certificates with serial numbers and QR
| verification. Loaded inside the signed-in group (routes/web.php); the
| public verify page is in routes/public.php.
*/

Route::prefix('certificates')->name('certificates.')->group(function () {
    Route::middleware('permission:View certificates|Manage certificates')->group(function () {
        Route::get('/', [CertificateController::class, 'index'])->name('index');
        Route::get('/{certificate}', [CertificateController::class, 'show'])->name('show');
    });

    Route::middleware('permission:Manage certificates')->group(function () {
        Route::post('/enrollments/{enrollment}/issue', [CertificateController::class, 'issue'])->name('issue');
        Route::post('/{certificate}/revoke', [CertificateController::class, 'revoke'])->name('revoke');
    });
});
