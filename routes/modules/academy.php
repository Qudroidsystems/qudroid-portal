<?php

use App\Http\Controllers\Academy\AcademyController;
use App\Http\Controllers\Academy\CohortController;
use App\Http\Controllers\Academy\CourseController;
use App\Http\Controllers\Academy\EnrollmentController;
use App\Http\Controllers\Academy\ProgrammeController;
use App\Http\Controllers\Academy\StudentController;
use Illuminate\Support\Facades\Route;

/*
| Academy & LMS — programmes, courses and fees, cohorts, students and
| enrollments. Loaded inside the signed-in group (routes/web.php).
*/

Route::prefix('academy')->name('academy.')->group(function () {

    // ── Academy staff ───────────────────────────────────────────────────
    Route::middleware('permission:Manage academy')->group(function () {
        Route::get('/programmes', [ProgrammeController::class, 'index'])->name('programmes.index');
        Route::post('/programmes', [ProgrammeController::class, 'store'])->name('programmes.store');
        Route::put('/programmes/{programme}', [ProgrammeController::class, 'update'])->name('programmes.update');
        Route::delete('/programmes/{programme}', [ProgrammeController::class, 'destroy'])->name('programmes.destroy');

        Route::get('/courses/create', [CourseController::class, 'create'])->name('courses.create');
        Route::post('/courses', [CourseController::class, 'store'])->name('courses.store');
        Route::get('/courses/{course}/edit', [CourseController::class, 'edit'])->name('courses.edit');
        Route::put('/courses/{course}', [CourseController::class, 'update'])->name('courses.update');
        Route::delete('/courses/{course}', [CourseController::class, 'destroy'])->name('courses.destroy');

        Route::get('/courses/{course}/cohorts/create', [CohortController::class, 'create'])->name('cohorts.create');
        Route::post('/courses/{course}/cohorts', [CohortController::class, 'store'])->name('cohorts.store');
        Route::get('/cohorts/{cohort}', [CohortController::class, 'show'])->name('cohorts.show');
        Route::get('/cohorts/{cohort}/edit', [CohortController::class, 'edit'])->name('cohorts.edit');
        Route::put('/cohorts/{cohort}', [CohortController::class, 'update'])->name('cohorts.update');

        Route::post('/students/{student}/status', [StudentController::class, 'status'])->name('students.status');
        Route::resource('students', StudentController::class)->except('destroy');

        Route::get('/enrollments', [EnrollmentController::class, 'index'])->name('enrollments.index');
        Route::post('/enrollments', [EnrollmentController::class, 'store'])->name('enrollments.store');
        Route::post('/enrollments/{enrollment}/complete', [EnrollmentController::class, 'complete'])->name('enrollments.complete');
        Route::post('/enrollments/{enrollment}/end', [EnrollmentController::class, 'end'])->name('enrollments.end');
    });

    Route::post('/enrollments/{enrollment}/access', [EnrollmentController::class, 'grantAccess'])
        ->middleware('permission:Grant course access')->name('enrollments.access');

    // ── Everyone with academy access (students, instructors, management) ─
    Route::middleware('permission:View academy|Manage academy')->group(function () {
        Route::get('/', [AcademyController::class, 'index'])->name('index');
        Route::get('/my', [AcademyController::class, 'my'])->name('my');
        Route::get('/courses', [CourseController::class, 'index'])->name('courses.index');
        Route::get('/courses/{course}', [CourseController::class, 'show'])->name('courses.show');
    });
});
