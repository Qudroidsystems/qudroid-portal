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
        $isStudent = !empty($user->student_id);
        $isStaff   = (bool) $staff;

        $can = fn (string ...$perms) => self::safe(function () use ($user, $perms) {
            foreach ($perms as $p) if ($user->can($p)) return true;
            return false;
        });

        $teaches = $isStaff && DB::table('subjectteacher')->where('staffid', $user->id)->exists();
        $classTeacher = $isStaff && DB::table('classteacher')->where('staffid', $user->id)->exists();

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
        ];

        $faces = array_values(array_filter([
            $isParent ? 'parent' : null,
            $isStudent ? 'student' : null,
            $isStaff ? 'staff' : null,
            $abilities['management.dashboard'] ? 'admin' : null,
        ]));

        $flags = self::safe(fn () => FeatureFlag::map(), []);
        $modules = [];
        foreach (self::MODULES as $m) $modules[$m] = array_key_exists($m, $flags) ? (bool) $flags[$m] : true;

        return [
            'id'                   => $user->id,
            'name'                 => $user->name,
            'email'                => self::realEmail($user->email),
            'phone'                => $user->phone_number,
            'avatar_url'           => self::safe(fn () => $user->avatar_url),
            'roles'                => self::safe(fn () => $user->getRoleNames()->values()->all(), []),
            'faces'                => $faces ?: ['guest'],
            'default_face'         => $faces[0] ?? 'guest',
            'student_id'           => $isStudent ? (int) $user->student_id : null,
            'staff_id'             => $staff ? (int) $staff->id : null,
            'must_change_password' => (bool) ($user->must_change_password ?? false),
            'abilities'            => $abilities,
            'modules'              => $modules,
            'unread_notifications' => Schema::hasTable('notifications') ? $user->unreadNotifications()->count() : 0,
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
