<?php

use App\Http\Controllers\Workflows\WorkflowController;
use Illuminate\Support\Facades\Route;

// Workflow automations. Loaded inside the signed-in group.
Route::prefix('workflows')->name('workflows.')->middleware('permission:Manage workflows')->group(function () {
    Route::get('/', [WorkflowController::class, 'index'])->name('index');
    Route::get('/new', [WorkflowController::class, 'create'])->name('create');
    Route::post('/', [WorkflowController::class, 'store'])->name('store');
    Route::post('/preview', [WorkflowController::class, 'preview'])->middleware('throttle:30,1')->name('preview');
    Route::post('/runs/{run}/retry', [WorkflowController::class, 'retry'])->middleware('throttle:20,1')->name('runs.retry');
    Route::get('/{workflow}', [WorkflowController::class, 'edit'])->name('edit');
    Route::put('/{workflow}', [WorkflowController::class, 'update'])->name('update');
    Route::post('/{workflow}/toggle', [WorkflowController::class, 'toggle'])->name('toggle');
    Route::post('/{workflow}/duplicate', [WorkflowController::class, 'duplicate'])->name('duplicate');
    Route::delete('/{workflow}', [WorkflowController::class, 'destroy'])->name('destroy');
    Route::get('/{workflow}/runs', [WorkflowController::class, 'runs'])->name('runs');
});
