<?php

use App\Http\Controllers\Attendance\AttendanceController;
use Illuminate\Support\Facades\Route;

/*
| Attendance — class sessions and registers for academy cohorts. Loaded
| inside the signed-in group. Who may take which register is checked in
| AttendanceService (academy staff, or the cohort's instructor).
*/

Route::prefix('attendance')->name('attendance.')
    ->middleware('permission:View attendance|Manage attendance|Manage academy')->group(function () {
        Route::get('/', [AttendanceController::class, 'index'])->name('index');
        Route::get('/report', [AttendanceController::class, 'report'])->name('report');
        Route::post('/report/nudge', [AttendanceController::class, 'nudge'])->middleware('throttle:10,1,modules-attendance.nudge')->name('nudge');
        Route::get('/cohorts/{cohort}/export', [AttendanceController::class, 'export'])->name('cohort.export');
        Route::post('/sessions/{session}/remind', [AttendanceController::class, 'remind'])->middleware('throttle:10,1,modules-attendance.remind')->name('sessions.remind');
        Route::get('/cohorts/{cohort}', [AttendanceController::class, 'cohort'])->name('cohort');
        Route::post('/cohorts/{cohort}/sessions', [AttendanceController::class, 'store'])->name('sessions.store');
        Route::post('/cohorts/{cohort}/schedule', [AttendanceController::class, 'schedule'])->name('sessions.schedule');
        Route::get('/sessions/{session}', [AttendanceController::class, 'register'])->name('register');
        Route::post('/sessions/{session}', [AttendanceController::class, 'save'])->name('register.save');
        Route::post('/sessions/{session}/cancel', [AttendanceController::class, 'cancel'])->name('sessions.cancel');
        Route::delete('/sessions/{session}', [AttendanceController::class, 'destroy'])->name('sessions.destroy');
    });
