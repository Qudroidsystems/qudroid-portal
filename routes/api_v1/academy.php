<?php

use App\Http\Controllers\Api\V1\Academy\AcademyApiController;
use Illuminate\Support\Facades\Route;

// The Academy for students. Loaded inside the signed-in API group.
Route::prefix('academy')->name('academy.')->controller(AcademyApiController::class)->group(function () {
    Route::get('my', 'my')->name('my');
    Route::get('enrollments/{enrollment}', 'showEnrollment')->whereNumber('enrollment')->name('enrollments.show');
    Route::get('lessons/{lesson}', 'lesson')->whereNumber('lesson')->name('lessons.show');
    Route::post('lessons/{lesson}/complete', 'complete')->whereNumber('lesson')->name('lessons.complete');
    Route::get('lessons/{lesson}/file', 'lessonFile')->whereNumber('lesson')->name('lessons.file');
    Route::get('assessments/{assessment}', 'assessment')->whereNumber('assessment')->name('assessments.show');
    Route::post('assessments/{assessment}/attempts', 'start')->whereNumber('assessment')->middleware('throttle:20,1')->name('assessments.start');
    Route::post('assessments/{assessment}/hand-in', 'handIn')->whereNumber('assessment')->middleware('throttle:20,1')->name('assessments.hand-in');
    Route::get('attempts/{attempt}', 'attempt')->whereNumber('attempt')->name('attempts.show');
    Route::put('attempts/{attempt}', 'save')->whereNumber('attempt')->middleware('throttle:120,1')->name('attempts.save');
    Route::post('attempts/{attempt}/submit', 'submit')->whereNumber('attempt')->middleware('throttle:20,1')->name('attempts.submit');
    Route::get('certificates', 'certificates')->name('certificates');
    Route::get('courses', 'courses')->name('courses');
    Route::get('courses/{course:id}', 'course')->whereNumber('course')->name('courses.show');
});
