<?php

use App\Http\Controllers\Hr\EmployeeController;
use App\Http\Controllers\Hr\HrController;
use App\Http\Controllers\Hr\LeaveController;
use App\Http\Controllers\Hr\PayrollController;
use Illuminate\Support\Facades\Route;

/*
| HR & Payroll. Loaded inside the signed-in group. The HR screens need
| an HR permission; "my leave" and "my payslips" are open to any staff
| member with an employee record, and a line manager decides their
| team's leave without needing HR permissions.
*/

Route::prefix('hr')->name('hr.')->middleware('permission:View employees|Manage employees|Manage payroll')->group(function () {
    Route::get('/', [HrController::class, 'index'])->name('index');

    Route::get('/employees', [EmployeeController::class, 'index'])->name('employees.index');
    Route::middleware('permission:Manage employees')->group(function () {
        Route::get('/employees/create', [EmployeeController::class, 'create'])->name('employees.create');
        Route::post('/employees', [EmployeeController::class, 'store'])->name('employees.store');
        Route::get('/employees/{employee}/edit', [EmployeeController::class, 'edit'])->name('employees.edit');
        Route::put('/employees/{employee}', [EmployeeController::class, 'update'])->name('employees.update');

        Route::post('/departments', [HrController::class, 'storeDepartment'])->name('departments.store');
        Route::put('/departments/{department}', [HrController::class, 'updateDepartment'])->name('departments.update');
        Route::delete('/departments/{department}', [HrController::class, 'destroyDepartment'])->name('departments.destroy');

        Route::get('/leave/settings', [LeaveController::class, 'settings'])->name('leave.settings');
        Route::post('/leave/types', [LeaveController::class, 'storeType'])->name('leave.types.store');
        Route::put('/leave/types/{type}', [LeaveController::class, 'updateType'])->name('leave.types.update');
        Route::post('/leave/holidays', [LeaveController::class, 'storeHoliday'])->name('leave.holidays.store');
        Route::delete('/leave/holidays/{holiday}', [LeaveController::class, 'destroyHoliday'])->name('leave.holidays.destroy');
    });
    Route::get('/employees/{employee}', [EmployeeController::class, 'show'])->name('employees.show');
    Route::get('/departments', [HrController::class, 'departments'])->name('departments.index');
    Route::get('/leave', [LeaveController::class, 'index'])->name('leave.index');

    Route::prefix('payroll')->name('payroll.')->middleware('permission:Manage payroll|Approve finance')->group(function () {
        Route::get('/', [PayrollController::class, 'index'])->name('index');
        Route::get('/{run}', [PayrollController::class, 'show'])->whereNumber('run')->name('show');
        Route::get('/{run}/payslips/{payslip}', [PayrollController::class, 'payslip'])->whereNumber('run')->name('payslip');
        Route::post('/{run}/approve', [PayrollController::class, 'approve'])->whereNumber('run')->name('approve');

        Route::middleware('permission:Manage payroll')->group(function () {
            Route::post('/', [PayrollController::class, 'store'])->name('store');
            Route::post('/{run}/generate', [PayrollController::class, 'generate'])->whereNumber('run')->name('generate');
            Route::put('/{run}/payslips/{payslip}', [PayrollController::class, 'adjust'])->whereNumber('run')->name('adjust');
            Route::post('/{run}/reopen', [PayrollController::class, 'reopen'])->whereNumber('run')->name('reopen');
            Route::post('/{run}/pay', [PayrollController::class, 'pay'])->whereNumber('run')->name('pay');
            Route::delete('/{run}', [PayrollController::class, 'destroy'])->whereNumber('run')->name('destroy');
        });
    });
});

// Leave decisions: HR or the employee's manager (checked in LeaveService).
Route::get('/hr/leave/{leave}', [LeaveController::class, 'show'])->whereNumber('leave')->name('hr.leave.show');
Route::post('/hr/leave/{leave}/decide', [LeaveController::class, 'decide'])->whereNumber('leave')->name('hr.leave.decide');
Route::post('/hr/leave/{leave}/cancel', [LeaveController::class, 'cancel'])->whereNumber('leave')->name('hr.leave.cancel');

// Self-service.
Route::prefix('me')->name('me.')->group(function () {
    Route::get('/leave', [LeaveController::class, 'mine'])->name('leave');
    Route::post('/leave', [LeaveController::class, 'storeMine'])->name('leave.store');
    Route::get('/payslips', [PayrollController::class, 'mine'])->name('payslips');
    Route::get('/payslips/{payslip}', [PayrollController::class, 'myPayslip'])->name('payslips.show');
});
