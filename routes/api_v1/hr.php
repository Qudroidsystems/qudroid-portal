<?php

use App\Http\Controllers\Api\V1\Hr\ApprovalApiController;
use App\Http\Controllers\Api\V1\Hr\HrApiController;
use Illuminate\Support\Facades\Route;

// Staff self-service and approvals. Loaded inside the signed-in API group.
Route::prefix('hr')->name('hr.')->controller(HrApiController::class)->group(function () {
    Route::get('me', 'me')->name('me');
    Route::get('leave', 'leave')->name('leave');
    Route::get('leave/preview', 'preview')->name('leave.preview');
    Route::post('leave', 'storeLeave')->middleware('throttle:20,1')->name('leave.store');
    Route::post('leave/{leave}/cancel', 'cancelLeave')->whereNumber('leave')->name('leave.cancel');
    Route::get('payslips', 'payslips')->name('payslips');
    Route::get('payslips/{payslip}', 'payslip')->whereNumber('payslip')->name('payslips.show');
    Route::get('payslips/{payslip}/pdf', 'payslipPdf')->whereNumber('payslip')->name('payslips.pdf');
    Route::get('assets', 'assets')->name('assets');
});
Route::get('expenses/mine', [HrApiController::class, 'myExpenses'])->name('expenses.mine');
Route::post('expenses', [HrApiController::class, 'storeExpense'])->middleware('throttle:20,1')->name('expenses.store');

// One inbox of things waiting for my decision; the same calls the notification buttons make.
Route::get('approvals', [ApprovalApiController::class, 'index'])->name('approvals');
Route::post('approvals/{type}/{id}/{decision}', [ApprovalApiController::class, 'decide'])
    ->where(['type' => 'leave|expense', 'decision' => 'approve|decline'])->whereNumber('id')->middleware('throttle:60,1')->name('approvals.decide');
