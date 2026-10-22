<?php

use App\Http\Controllers\Academy\AcademyController;
use App\Http\Controllers\Academy\ApplicationController;
use App\Http\Controllers\Academy\AssessmentController;
use App\Http\Controllers\Academy\CohortController;
use App\Http\Controllers\Academy\ContentController;
use App\Http\Controllers\Academy\CourseController;
use App\Http\Controllers\Academy\EnrollmentController;
use App\Http\Controllers\Academy\LearnAssessmentController;
use App\Http\Controllers\Academy\LearnController;
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

        Route::get('/applications', [ApplicationController::class, 'index'])->name('applications.index');
        Route::get('/applications/{application}', [ApplicationController::class, 'show'])->name('applications.show');
        Route::post('/applications/{application}/admit', [ApplicationController::class, 'admit'])->name('applications.admit');
        Route::post('/applications/{application}/decline', [ApplicationController::class, 'decline'])->name('applications.decline');

        Route::get('/enrollments', [EnrollmentController::class, 'index'])->name('enrollments.index');
        Route::post('/enrollments', [EnrollmentController::class, 'store'])->name('enrollments.store');
        Route::post('/enrollments/{enrollment}/complete', [EnrollmentController::class, 'complete'])->name('enrollments.complete');
        Route::post('/enrollments/{enrollment}/end', [EnrollmentController::class, 'end'])->name('enrollments.end');
    });

    // ── Course content (academy staff and instructors) ──────────────────
    Route::middleware('permission:Manage academy|Manage course content')->prefix('/courses/{course}/content')->name('content.')->group(function () {
        Route::get('/', [ContentController::class, 'index'])->name('index');
        Route::post('/sections', [ContentController::class, 'storeSection'])->name('sections.store');
        Route::put('/sections/{section}', [ContentController::class, 'updateSection'])->name('sections.update');
        Route::delete('/sections/{section}', [ContentController::class, 'destroySection'])->name('sections.destroy');
        Route::post('/sections/{section}/move', [ContentController::class, 'moveSection'])->name('sections.move');
        Route::get('/lessons/create', [ContentController::class, 'createLesson'])->name('lessons.create');
        Route::post('/lessons', [ContentController::class, 'storeLesson'])->name('lessons.store');
        Route::get('/lessons/{lesson}/edit', [ContentController::class, 'editLesson'])->name('lessons.edit');
        Route::put('/lessons/{lesson}', [ContentController::class, 'updateLesson'])->name('lessons.update');
        Route::delete('/lessons/{lesson}', [ContentController::class, 'destroyLesson'])->name('lessons.destroy');
        Route::post('/lessons/{lesson}/move', [ContentController::class, 'moveLesson'])->name('lessons.move');
    });

    // ── Quizzes and assignments: building and marking ───────────────────
    Route::middleware('permission:Manage academy|Manage course content')->prefix('/courses/{course}/assessments')->name('assessments.')->group(function () {
        Route::get('/create', [AssessmentController::class, 'create'])->name('create');
        Route::post('/', [AssessmentController::class, 'store'])->name('store');
        Route::get('/{assessment}/edit', [AssessmentController::class, 'edit'])->name('edit');
        Route::put('/{assessment}', [AssessmentController::class, 'update'])->name('update');
        Route::delete('/{assessment}', [AssessmentController::class, 'destroy'])->name('destroy');
        Route::post('/{assessment}/questions', [AssessmentController::class, 'storeQuestion'])->name('questions.store');
        Route::put('/{assessment}/questions/{question}', [AssessmentController::class, 'updateQuestion'])->name('questions.update');
        Route::delete('/{assessment}/questions/{question}', [AssessmentController::class, 'destroyQuestion'])->name('questions.destroy');
        Route::get('/{assessment}/results', [AssessmentController::class, 'results'])->name('results');
        Route::get('/{assessment}/attempts/{attempt}', [AssessmentController::class, 'attempt'])->name('attempt');
        Route::post('/{assessment}/attempts/{attempt}/grade', [AssessmentController::class, 'gradeAttempt'])->name('attempt.grade');
        Route::post('/{assessment}/submissions/{submission}/grade', [AssessmentController::class, 'gradeSubmission'])->name('submission.grade');
        Route::get('/{assessment}/submissions/{submission}/file', [AssessmentController::class, 'download'])->name('submission.file');
    });

    Route::get('/cohorts/{cohort}/gradebook', [CohortController::class, 'gradebook'])
        ->middleware('permission:Manage academy|Manage course content')->name('cohorts.gradebook');

    Route::post('/enrollments/{enrollment}/access', [EnrollmentController::class, 'grantAccess'])
        ->middleware('permission:Grant course access')->name('enrollments.access');

    // ── Everyone with academy access (students, instructors, management) ─
    Route::middleware('permission:View academy|Manage academy|Manage course content')->group(function () {
        Route::get('/', [AcademyController::class, 'index'])->name('index');
        Route::get('/my', [AcademyController::class, 'my'])->name('my');
        Route::post('/my/applications/{application}/withdraw', [ApplicationController::class, 'withdraw'])->name('applications.withdraw');
        Route::get('/courses', [CourseController::class, 'index'])->name('courses.index');
        Route::get('/courses/{course}', [CourseController::class, 'show'])->name('courses.show');

        // Learning (access is checked per lesson in LearnController / LearningService)
        Route::get('/learn/{course}', [LearnController::class, 'course'])->name('learn.course');
        Route::get('/learn/{course}/lessons/{lesson}', [LearnController::class, 'lesson'])->name('learn.lesson');
        Route::post('/learn/{course}/lessons/{lesson}/complete', [LearnController::class, 'complete'])->name('learn.complete');
        Route::get('/learn/{course}/lessons/{lesson}/file', [LearnController::class, 'download'])->name('learn.download');

        // Quizzes and assignments (access checked in LearnAssessmentController / AssessmentService)
        Route::get('/learn/{course}/assessments/{assessment}', [LearnAssessmentController::class, 'show'])->name('learn.assessment');
        Route::post('/learn/{course}/assessments/{assessment}/start', [LearnAssessmentController::class, 'start'])->middleware('throttle:20,1')->name('learn.start');
        Route::post('/learn/{course}/assessments/{assessment}/hand-in', [LearnAssessmentController::class, 'handIn'])->middleware('throttle:20,1')->name('learn.hand-in');
        Route::get('/learn/{course}/assessments/{assessment}/my-file', [LearnAssessmentController::class, 'file'])->name('learn.my-file');
        Route::get('/learn/{course}/attempts/{attempt}', [LearnAssessmentController::class, 'attempt'])->name('learn.attempt');
        Route::post('/learn/{course}/attempts/{attempt}', [LearnAssessmentController::class, 'submit'])->name('learn.submit');
    });
});
