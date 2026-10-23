<?php

/*
|--------------------------------------------------------------------------
| Mobile app API — version 1
|--------------------------------------------------------------------------
| Loaded from routes/api.php under the /api/v1 prefix and the "api.v1."
| route-name prefix. Authentication: Laravel Sanctum bearer tokens
| (Authorization: Bearer <token>), issued by POST /api/v1/auth/login.
|
| Every JSON response has the same envelope — see
| App\Http\Controllers\Api\V1\Concerns\RespondsWithApi.
|
| Modules add their endpoints in routes/api_v1/*.php (loaded automatically
| inside the signed-in group).
*/

use App\Http\Controllers\Api\V1\AppConfigController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DeviceController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\PreferenceController;
use App\Http\Controllers\Api\V1\ProfileController;
use Illuminate\Support\Facades\Route;

// ── Public ──────────────────────────────────────────────────────────────
Route::get('app/config', [AppConfigController::class, 'show'])->name('app.config');

Route::prefix('auth')->name('auth.')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1,api-login')->name('login');
    Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:5,10,api-forgot')->name('forgot');
});

// ── Signed in ───────────────────────────────────────────────────────────
Route::middleware(['auth:sanctum', 'api.active', 'throttle:120,1,api'])->group(function () {

    Route::prefix('auth')->name('auth.')->group(function () {
        Route::get('me', [AuthController::class, 'me'])->name('me');
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        Route::post('logout-all', [AuthController::class, 'logoutAll'])->name('logout-all');
        Route::put('password', [AuthController::class, 'changePassword'])->name('password');
    });

    Route::post('devices', [DeviceController::class, 'store'])->name('devices.store');
    Route::delete('devices', [DeviceController::class, 'destroy'])->name('devices.destroy');

    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount'])->name('notifications.unread');
    Route::get('me/status', [\App\Http\Controllers\Api\V1\StatusController::class, 'show'])->name('status.show');
    Route::post('me/status', [\App\Http\Controllers\Api\V1\StatusController::class, 'update'])->middleware('throttle:60,1')->name('status.update');
    Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::post('notifications/{id}/read', [NotificationController::class, 'read'])->name('notifications.read');
    Route::delete('notifications/{id}', [NotificationController::class, 'destroy'])->name('notifications.destroy');
    Route::post('notifications/clear-read', [NotificationController::class, 'clearRead'])->name('notifications.clear-read');
    Route::post('notifications/{id}/actions/{action}', [NotificationController::class, 'act'])->where('action', '[a-z_]{2,20}')->middleware('throttle:60,1')->name('notifications.act');
    Route::get('notifications/settings', [NotificationController::class, 'settings'])->name('notifications.settings');
    Route::put('notifications/settings', [NotificationController::class, 'updateSettings'])->name('notifications.settings.update');

    Route::get('me/profile', [ProfileController::class, 'show'])->name('profile');
    Route::put('me/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::get('me/preferences', [PreferenceController::class, 'show'])->name('preferences.show');
    Route::put('me/preferences', [PreferenceController::class, 'update'])->name('preferences.update');

    foreach (glob(__DIR__ . '/api_v1/*.php') ?: [] as $moduleRoutes) {
        require $moduleRoutes;
    }
});

Route::fallback(fn () => response()->json(['success' => false, 'code' => 'not_found', 'message' => 'Endpoint not found.'], 404));
