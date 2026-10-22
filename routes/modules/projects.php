<?php

use App\Http\Controllers\Projects\MyWorkController;
use App\Http\Controllers\Projects\ProjectController;
use App\Http\Controllers\Projects\TaskController;
use Illuminate\Support\Facades\Route;

/*
| Projects & Tasks. Loaded inside the signed-in group. The route
| permission only opens the module; what someone may do on a project
| depends on their role in it (ProjectService).
*/

Route::prefix('projects')->name('projects.')->middleware('permission:View projects|Manage projects')->group(function () {
    Route::get('/', [ProjectController::class, 'index'])->name('index');
    Route::get('/my-work', MyWorkController::class)->name('my-work');

    Route::middleware('permission:Manage projects')->group(function () {
        Route::get('/create', [ProjectController::class, 'create'])->name('create');
        Route::post('/', [ProjectController::class, 'store'])->name('store');
        Route::delete('/{project}', [ProjectController::class, 'destroy'])->name('destroy');
    });

    Route::get('/{project}', [ProjectController::class, 'show'])->name('show');
    Route::get('/{project}/edit', [ProjectController::class, 'edit'])->name('edit');
    Route::put('/{project}', [ProjectController::class, 'update'])->name('update');
    Route::post('/{project}/status', [ProjectController::class, 'status'])->name('status');

    Route::post('/{project}/members', [ProjectController::class, 'addMember'])->name('members.store');
    Route::put('/{project}/members/{member}', [ProjectController::class, 'updateMember'])->name('members.update');
    Route::delete('/{project}/members/{member}', [ProjectController::class, 'removeMember'])->name('members.destroy');

    Route::post('/{project}/milestones', [ProjectController::class, 'addMilestone'])->name('milestones.store');
    Route::post('/{project}/milestones/{milestone}/toggle', [ProjectController::class, 'toggleMilestone'])->name('milestones.toggle');
    Route::delete('/{project}/milestones/{milestone}', [ProjectController::class, 'removeMilestone'])->name('milestones.destroy');

    Route::get('/{project}/board', [TaskController::class, 'board'])->name('board');
    Route::post('/{project}/tasks', [TaskController::class, 'store'])->name('tasks.store');
    Route::get('/{project}/tasks/{number}', [TaskController::class, 'show'])->whereNumber('number')->name('tasks.show');
    Route::put('/{project}/tasks/{number}', [TaskController::class, 'update'])->whereNumber('number')->name('tasks.update');
    Route::post('/{project}/tasks/{number}/move', [TaskController::class, 'move'])->whereNumber('number')->name('tasks.move');
    Route::post('/{project}/tasks/{number}/checklist', [TaskController::class, 'checklist'])->whereNumber('number')->name('tasks.checklist');
    Route::delete('/{project}/tasks/{number}', [TaskController::class, 'destroy'])->whereNumber('number')->name('tasks.destroy');
    Route::post('/{project}/tasks/{number}/comments', [TaskController::class, 'comment'])->whereNumber('number')->name('tasks.comment');
    Route::get('/{project}/tasks/{number}/comments/{comment}/file', [TaskController::class, 'attachment'])->whereNumber('number')->name('tasks.attachment');

    Route::get('/{project}/time', [TaskController::class, 'timesheet'])->name('time');
    Route::post('/{project}/time', [TaskController::class, 'logTime'])->name('time.store');
    Route::delete('/{project}/time/{entry}', [TaskController::class, 'deleteTime'])->name('time.destroy');
});
