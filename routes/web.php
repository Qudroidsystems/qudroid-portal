<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\Auth\PasswordChangeController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Session;

/*
|--------------------------------------------------------------------------
| Qudroid Hub — web routes (foundation)
|--------------------------------------------------------------------------
| Authentication, users, roles and permissions, profile, notifications and
| the activity log. Each module (Academy, Projects, Connect, CRM, Finance,
| Inventory, HR …) adds its own file in routes/modules/ — every file there
| is loaded automatically inside the signed-in group below.
*/

Route::get('/', fn () => Auth::check() ? redirect()->route('dashboard') : redirect()->route('login'))->name('home');

Auth::routes([
    'register' => (bool) config('qudroid.allow_registration'),
    'verify'   => false,
]);

// Payment-provider webhooks (no sign-in; signature-checked)
require __DIR__ . '/webhooks.php';

// CSRF refresh (keeps long-open pages working)
Route::get('/refresh-csrf', function () {
    if (request()->ajax()) {
        Session::regenerateToken();
        return response()->json(['csrf_token' => csrf_token()]);
    }
    abort(404);
})->name('refresh.csrf');

Route::middleware('auth')->group(function () {

    // ── Temporary password → choose your own ────────────────────────────
    Route::get('/password/change', [PasswordChangeController::class, 'show'])->name('password.change');
    Route::post('/password/change', [PasswordChangeController::class, 'update'])->name('password.change.update');

    // ── Dashboard (app launcher) ────────────────────────────────────────
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // ── Users ───────────────────────────────────────────────────────────
    Route::get('/users/all', [UserController::class, 'allUsers'])->name('users.all');
    Route::get('/users/paginate', [UserController::class, 'paginate'])->name('users.paginate');
    Route::get('/users/roles', [UserController::class, 'roles'])->name('users.roles');
    Route::get('/users/staff/template', [UserController::class, 'generateStaffTemplate'])->name('users.staff.template');
    Route::post('/users/staff/import', [UserController::class, 'importStaffUsers'])->name('users.staff.import');
    Route::post('/users/{id}/toggle-status', [UserController::class, 'toggleStatus'])->whereNumber('id')->name('users.toggle-status');
    Route::get('/user/overview/{id}', [UserController::class, 'show'])->whereNumber('id')->name('users.overview');
    Route::resource('users', UserController::class);

    // ── Roles & permissions ─────────────────────────────────────────────
    Route::post('roles/bulk-remove-users', [RoleController::class, 'bulkRemoveUsers'])->name('roles.bulkremoveusers');
    Route::get('/roles/{role}/users', [RoleController::class, 'getRoleUsers'])->name('roles.users');
    Route::get('/adduser/{id}', [RoleController::class, 'adduser'])->name('roles.adduser');
    Route::post('/updateuserrole', [RoleController::class, 'updateuserrole'])->name('roles.updateuserrole');
    Route::delete('roles/removeuserrole/{userid}/{roleid}', [RoleController::class, 'removeuserrole'])->name('roles.removeuserrole');
    Route::resource('roles', RoleController::class);
    Route::resource('permissions', PermissionController::class);

    // ── My profile ──────────────────────────────────────────────────────
    Route::prefix('profile')->name('profile.')->group(function () {
        Route::get('/settings/{id}', [ProfileController::class, 'show'])->whereNumber('id')->name('settings');
        Route::post('/update-info', [ProfileController::class, 'updateInfo'])->name('update-info');
        Route::post('/update-avatar', [ProfileController::class, 'updateAvatar'])->name('update-avatar');
        Route::post('/update-email', [ProfileController::class, 'updateEmail'])->name('update-email');
        Route::post('/update-password', [ProfileController::class, 'updatePassword'])->name('update-password');
    });

    // ── Notifications (the bell) ────────────────────────────────────────
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/feed', [NotificationController::class, 'feed'])->name('notifications.feed');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::get('/notifications/{id}/open', [NotificationController::class, 'open'])->name('notifications.open');

    // ── Activity log & who's online ─────────────────────────────────────
    Route::get('/activity-log', [ActivityLogController::class, 'index'])->name('activity.index');
    Route::get('/activity-log/export', [ActivityLogController::class, 'export'])->name('activity.export');
    Route::get('/online-staff', [ActivityLogController::class, 'online'])->name('online-staff.index');
    Route::get('/online-staff/count', [ActivityLogController::class, 'onlineCount'])->name('online-staff.count');

    // ── Modules ─────────────────────────────────────────────────────────
    foreach (glob(__DIR__ . '/modules/*.php') ?: [] as $moduleRoutes) {
        require $moduleRoutes;
    }
});
