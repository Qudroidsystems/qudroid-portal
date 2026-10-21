<?php

namespace App\Services\Api;

use App\Models\FeatureFlag;
use App\Models\User;
use App\Services\Parents\ParentAccountService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The signed-in user as the mobile app sees it: profile, which "faces" of the
 * app to show (parent / student / staff / admin), what they may do, and which
 * school modules are switched on. The app builds its menus from this.
 */
class ApiUserPresenter
{
    /** Modules the app has screens for (FeatureFlag keys). */
    public const MODULES = ['results', 'finance', 'online_payments', 'timetable', 'elearning', 'cbt', 'payroll', 'exams'];

    public static function present(User $user): array
    {
        $staff = DB::table('staffbioinfo')->where('userid', $user->id)->first();
        $isParent  = self::safe(fn () => $user->hasRole(ParentAccountService::ROLE))
            && DB::table('parent_student')->where('user_id', $user->id)->exists();
        // Roles decide what the person is. Any role other than Parent / Student (Teacher,
        // Staff, Registrar, Bursar, Admin ...) means they work at the school, whether or not
        // a staff record has been created for them yet.
        $roleNames   = self::safe(fn () => $user->getRoleNames()->map(fn ($r) => strtolower($r))->values()->all(), []);
        $workRoles   = array_values(array_diff($roleNames, [strtolower((string) ParentAccountService::ROLE), 'parent', 'student']));
        $hasWorkRole = !empty($workRoles);
        // A student link on someone with a work role and no Student role is a stray link: ignore it.
        $isStudent = !empty($user->student_id) && (in_array('student', $roleNames, true) || !$hasWorkRole);
        $isStaff   = (bool) $staff;

        $can = fn (string ...$perms) => self::safe(function () use ($user, $perms) {
            foreach ($perms as $p) if ($user->can($p)) return true;
            return false;
        });

        $teaches = ($isStaff || $hasWorkRole) && DB::table('subjectteacher')->where('staffid', $user->id)->exists();
        $classTeacher = ($isStaff || $hasWorkRole) && DB::table('classteacher')->where('staffid', $user->id)->exists();

        // Every permission the user has (from all their roles + any given directly).
        $permissions = self::safe(fn () => $user->getAllPermissions()->pluck('name')->values()->all(), []);

        $abilities = [
            'scores.enter'          => $teaches,
            'attendance.mark'       => $classTeacher || $can('Create attendance-register'),
            'management.dashboard'  => $can('View management dashboard'),
            'students.view'         => $can('View student', 'Show Student'),
            'leave.apply'           => $isStaff,
            'leave.approve'         => $can('Approve leave', 'Recommend leave'),
            'student_leave.apply'   => $isParent || $isStudent,
            'student_leave.approve' => $can('Approve student leave', 'Recommend student leave'),
            'payroll.self'          => $isStaff,
            'notices.view'          => $can('View notices'),
            'results.board'         => $classTeacher || $can('View report-approvals', 'Approve report cards', 'View student-report'),
            'reports.submit'        => $classTeacher && $can('Submit report cards', 'Approve report cards'),
            'reports.approve'       => $can('Approve report cards'),
            'timetable.reminders'   => $isStaff,
            'website.manage'        => $can('Manage website'),
            'calendar.view'         => $can('View school calendar', 'Manage school calendar'),
        ];

        // Order matters: the first face is the one the app opens. Someone who works at the
        // school (staff / management) lands there even if they are also a parent or have a
        // student record; they can still switch views in the app.
        $faces = array_values(array_filter([
            ($isStaff || $hasWorkRole) ? 'staff' : null,
            ($abilities['management.dashboard'] || $abilities['reports.approve']) ? 'admin' : null,
            $isParent ? 'parent' : null,
            $isStudent ? 'student' : null,
        ]));

        // Anyone whose role(s) carry permissions but who has no parent / student / staff
        // record (e.g. a Registrar) gets the admin face: the app then builds its tabs from
        // the permissions below (students, approvals, calendar, inbox ...).
        if (!$faces && $permissions) $faces = ['admin'];

        $flags = self::safe(fn () => FeatureFlag::map(), []);
        $modules = [];
        foreach (self::MODULES as $m) $modules[$m] = array_key_exists($m, $flags) ? (bool) $flags[$m] : true;
        // Results switched off for families by the admin (Admin → Result display).
        if (!$isStaff && class_exists(\App\Services\Results\ResultDisplay::class)) {
            $rd = self::safe(fn () => \App\Services\Results\ResultDisplay::settings(), null);
            if ($rd && !(($isParent && $rd['parents_can_view']) || ($isStudent && $rd['students_can_view']))) $modules['results'] = false;
        }

        return [
            'id'                   => $user->id,
            'name'                 => $user->name,
            'email'                => self::realEmail($user->email),
            'phone'                => $user->phone_number,
            'avatar_url'           => self::safe(fn () => $user->avatar_url),
            'roles'                => self::safe(fn () => $user->getRoleNames()->values()->all(), []),
            'permissions'          => $permissions,
            'faces'                => $faces ?: ['guest'],
            'default_face'         => $faces[0] ?? 'guest',
            'student_id'           => $isStudent ? (int) $user->student_id : null,
            'staff_id'             => $staff ? (int) $staff->id : null,
            'must_change_password' => (bool) ($user->must_change_password ?? false),
            'abilities'            => $abilities,
            'modules'              => $modules,
            'unread_notifications' => Schema::hasTable('notifications') ? $user->unreadNotifications()->count() : 0,
            'push_available'       => \App\Services\Push\PushService::enabled(),
        ];
    }

    /** Auto-generated parent e-mails (p0803…@parents.school) are not shown. */
    protected static function realEmail(?string $email): ?string
    {
        return $email && !str_contains($email, '@parents.') ? $email : null;
    }

    protected static function safe(callable $fn, $default = false)
    {
        try { return $fn(); } catch (\Throwable $e) { return $default; }
    }
}
