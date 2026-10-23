<?php

use App\Http\Controllers\Api\V1\Projects\ProjectsApiController;
use Illuminate\Support\Facades\Route;

// Projects and tasks (ids, not codes). Loaded inside the signed-in API group.
Route::controller(ProjectsApiController::class)->group(function () {
    Route::get('projects', 'index')->name('projects.index');
    Route::get('projects/{project:id}', 'show')->whereNumber('project')->name('projects.show');
    Route::get('projects/{project:id}/tasks', 'tasks')->whereNumber('project')->name('projects.tasks');
    Route::post('projects/{project:id}/tasks', 'storeTask')->whereNumber('project')->middleware('throttle:60,1')->name('projects.tasks.store');
    Route::get('tasks/mine', 'mine')->name('tasks.mine');
    Route::get('tasks/{task}', 'showTask')->whereNumber('task')->name('tasks.show');
    Route::put('tasks/{task}', 'updateTask')->whereNumber('task')->name('tasks.update');
    Route::post('tasks/{task}/move', 'move')->whereNumber('task')->name('tasks.move');
    Route::post('tasks/{task}/comments', 'comment')->whereNumber('task')->middleware('throttle:60,1')->name('tasks.comments');
    Route::get('task-comments/{comment}/file', 'attachment')->whereNumber('comment')->name('tasks.comments.file');
    Route::post('tasks/{task}/time', 'logTime')->whereNumber('task')->name('tasks.time');
});
