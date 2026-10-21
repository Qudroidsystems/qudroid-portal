<?php

/*
|--------------------------------------------------------------------------
| Mobile app API — version 1
|--------------------------------------------------------------------------
| Loaded from routes/api.php under the /api/v1 prefix and the "api.v1."
| route-name prefix. Authentication: Laravel Sanctum bearer tokens
| (Authorization: Bearer <token>), issued by POST /api/v1/auth/login.
|
| Every JSON response has the same envelope — see
| App\Http\Controllers\Api\V1\Concerns\RespondsWithApi.
*/

use App\Http\Controllers\Api\V1\Admin\DashboardController as AdminDashboard;
use App\Http\Controllers\Api\V1\Admin\LeaveApprovalController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CalendarController;
use App\Http\Controllers\Api\V1\DeviceController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\PreferenceController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\ResultsController;
use App\Http\Controllers\Api\V1\SchoolController;
use App\Http\Controllers\Api\V1\Staff\AttendanceController as StaffAttendance;
use App\Http\Controllers\Api\V1\Staff\HomeController as StaffHome;
use App\Http\Controllers\Api\V1\Staff\LeaveController as StaffLeave;
use App\Http\Controllers\Api\V1\Staff\PayController as StaffPay;
use App\Http\Controllers\Api\V1\Staff\ScoresheetController;
use App\Http\Controllers\Api\V1\Staff\SubjectController as StaffSubjects;
use App\Http\Controllers\Api\V1\StudentController;
use App\Http\Controllers\Api\V1\StudentExtrasController;
use App\Http\Controllers\Api\V1\StudentLeaveController;
use App\Http\Controllers\Api\V1\WebsiteFeedController;
use Illuminate\Support\Facades\Route;

// ── Public ──────────────────────────────────────────────────────────────
Route::get('school', [SchoolController::class, 'show'])->name('school');

// The school's separate public website (shared key, see VerifyWebsiteKey).
Route::middleware(['api.website', 'throttle:120,1'])->prefix('website')->name('website.')->group(function () {
    Route::get('feed', [WebsiteFeedController::class, 'feed'])->name('feed');
    Route::get('staff', [WebsiteFeedController::class, 'staff'])->name('staff');
});

Route::prefix('auth')->name('auth.')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1,api-login')->name('login');
    Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:5,10,api-forgot')->name('forgot');
    Route::post('reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:10,10,api-reset')->name('reset');
});

// Payment gateway return page (opened inside the app's WebView, no token).
Route::get('payments/callback', [PaymentController::class, 'callback'])->name('payments.callback');

// ── Signed in ───────────────────────────────────────────────────────────
Route::middleware(['auth:sanctum', 'api.active', 'throttle:120,1,api'])->group(function () {

    Route::prefix('auth')->name('auth.')->group(function () {
        Route::get('me', [AuthController::class, 'me'])->name('me');
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        Route::post('logout-all', [AuthController::class, 'logoutAll'])->name('logout-all');
        Route::put('password', [AuthController::class, 'changePassword'])->name('password');
    });

    Route::post('devices', [DeviceController::class, 'store'])->name('devices.store');
    Route::delete('devices', [DeviceController::class, 'destroy'])->name('devices.destroy');

    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount'])->name('notifications.unread');
    Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::post('notifications/{id}/read', [NotificationController::class, 'read'])->name('notifications.read');

    Route::get('calendar', [CalendarController::class, 'index'])->name('calendar');

    Route::get('me/profile', [ProfileController::class, 'show'])->name('profile');
    Route::get('me/preferences', [PreferenceController::class, 'show'])->name('preferences.show');
    Route::put('me/preferences', [PreferenceController::class, 'update'])->name('preferences.update');

    // ── Broadsheets & report-card approval (class teachers, principal) ────
    Route::prefix('results')->name('results.')->middleware('api.feature:results')->group(function () {
        Route::get('board', [ResultsController::class, 'board'])->name('board');
        Route::post('approve-all', [ResultsController::class, 'approveAll'])->name('approve-all');
        Route::get('classes/{class}/broadsheet', [ResultsController::class, 'broadsheet'])->whereNumber('class')->name('broadsheet');
        Route::get('classes/{class}/approval', [ResultsController::class, 'approval'])->whereNumber('class')->name('approval');
        Route::post('approvals/{approval}/submit', [ResultsController::class, 'submit'])->whereNumber('approval')->name('submit');
        Route::post('approvals/{approval}/approve', [ResultsController::class, 'approve'])->whereNumber('approval')->name('approve');
        Route::post('approvals/{approval}/return', [ResultsController::class, 'returnBack'])->whereNumber('approval')->name('return');
        Route::post('approvals/{approval}/reopen', [ResultsController::class, 'reopen'])->whereNumber('approval')->name('reopen');
        Route::get('approvals/{approval}/preview/{student}', [ResultsController::class, 'preview'])->whereNumber(['approval', 'student'])->name('preview');
    });

    // ── Parent / student: my students ───────────────────────────────────
    Route::get('students', [StudentController::class, 'index'])->name('students.index');
    Route::prefix('students/{student}')->whereNumber('student')->name('students.')->group(function () {
        Route::get('/', [StudentController::class, 'show'])->name('show');
        Route::get('results', [StudentController::class, 'results'])->middleware('api.feature:results')->name('results');
        Route::get('report-card/{session}/{term}/{class}', [StudentController::class, 'reportCard'])
            ->whereNumber(['session', 'term', 'class'])->middleware('api.feature:results')->name('report-card');
        Route::get('fees', [StudentController::class, 'fees'])->middleware('api.feature:finance')->name('fees');
        Route::get('payment-quote', [PaymentController::class, 'quote'])->middleware('api.feature:online_payments')->name('payment-quote');
        Route::get('payments', [PaymentController::class, 'history'])->middleware('api.feature:online_payments')->name('payments');
        Route::get('attendance', [StudentController::class, 'attendance'])->name('attendance');
        Route::get('timetable', [StudentController::class, 'timetable'])->middleware('api.feature:timetable')->name('timetable');
        Route::get('teachers', [StudentExtrasController::class, 'teachers'])->name('teachers');
        Route::get('performance', [StudentExtrasController::class, 'performance'])->middleware('api.feature:results')->name('performance');
        Route::get('learning', [StudentExtrasController::class, 'learning'])->name('learning');
        Route::get('guidance', [StudentExtrasController::class, 'guidance'])->middleware('api.feature:results')->name('guidance');
        Route::post('guidance/refresh', [StudentExtrasController::class, 'refreshGuidance'])->middleware(['api.feature:results', 'throttle:6,1,api-guidance'])->name('guidance.refresh');
        Route::get('payment-statement', [StudentExtrasController::class, 'receipt'])->middleware('api.feature:finance')->name('payment-statement');
    });

    Route::middleware('api.feature:online_payments')->group(function () {
        Route::post('payments/checkout', [PaymentController::class, 'checkout'])->middleware('throttle:10,1,api-checkout')->name('payments.checkout');
        Route::get('payments/{reference}', [PaymentController::class, 'show'])->name('payments.show');
    });

    Route::get('student-leave', [StudentLeaveController::class, 'index'])->name('student-leave.index');
    Route::post('student-leave', [StudentLeaveController::class, 'store'])->name('student-leave.store');
    Route::post('student-leave/{leave}/cancel', [StudentLeaveController::class, 'cancel'])->whereNumber('leave')->name('student-leave.cancel');

    // ── Staff ───────────────────────────────────────────────────────────
    Route::prefix('staff')->name('staff.')->group(function () {
        Route::get('home', [StaffHome::class, 'home'])->name('home');
        Route::get('timetable', [StaffHome::class, 'timetable'])->middleware('api.feature:timetable')->name('timetable');

        Route::middleware('api.feature:results')->group(function () {
            Route::get('subjects', [StaffSubjects::class, 'index'])->name('subjects');
            Route::get('scoresheets/{subjectclass}', [ScoresheetController::class, 'sheet'])->whereNumber('subjectclass')->name('scoresheets.show');
            Route::post('scoresheets/score', [ScoresheetController::class, 'saveScore'])->name('scoresheets.score');
            Route::post('scoresheets/{subjectclass}/bulk', [ScoresheetController::class, 'saveBulk'])->whereNumber('subjectclass')->name('scoresheets.bulk');
            Route::get('scoresheets/{subjectclass}/excel', [ScoresheetController::class, 'excelDownload'])->whereNumber('subjectclass')->name('scoresheets.excel');
            Route::post('scoresheets/{subjectclass}/excel', [ScoresheetController::class, 'excelUpload'])->whereNumber('subjectclass')->middleware('throttle:20,1,api-import')->name('scoresheets.import');
            Route::get('scoresheets/{subjectclass}/marksheet', [ScoresheetController::class, 'marksheet'])->whereNumber('subjectclass')->name('scoresheets.marksheet');
        });

        Route::get('classes', [StaffAttendance::class, 'classes'])->name('classes');
        Route::get('attendance/register', [StaffAttendance::class, 'registerSheet'])->name('attendance.register');
        Route::post('attendance', [StaffAttendance::class, 'saveRegister'])->name('attendance.save');
        Route::post('attendance/mark-all-present', [StaffAttendance::class, 'allPresent'])->name('attendance.all-present');

        Route::get('leave', [StaffLeave::class, 'index'])->name('leave.index');
        Route::post('leave', [StaffLeave::class, 'store'])->name('leave.store');
        Route::post('leave/{id}/cancel', [StaffLeave::class, 'cancel'])->whereNumber('id')->name('leave.cancel');

        Route::middleware('api.feature:payroll')->group(function () {
            Route::get('payslips', [StaffPay::class, 'index'])->name('payslips.index');
            Route::get('payslips/{run}', [StaffPay::class, 'show'])->whereNumber('run')->name('payslips.show');
            Route::get('payslips/{run}/pdf', [StaffPay::class, 'pdf'])->whereNumber('run')->name('payslips.pdf');
        });
    });

    // ── Admin / management ──────────────────────────────────────────────
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('dashboard', [AdminDashboard::class, 'index'])->name('dashboard');
        Route::get('students/search', [AdminDashboard::class, 'searchStudents'])->name('students.search');

        Route::get('leave/pending', [LeaveApprovalController::class, 'staffQueue'])->name('leave.pending');
        Route::post('leave/{id}/decide', [LeaveApprovalController::class, 'staffDecide'])->whereNumber('id')->name('leave.decide');
        Route::get('student-leave/pending', [LeaveApprovalController::class, 'studentQueue'])->name('student-leave.pending');
        Route::post('student-leave/{id}/decide', [LeaveApprovalController::class, 'studentDecide'])->whereNumber('id')->name('student-leave.decide');
    });
});

Route::fallback(fn () => response()->json(['success' => false, 'code' => 'not_found', 'message' => 'Endpoint not found.'], 404));
