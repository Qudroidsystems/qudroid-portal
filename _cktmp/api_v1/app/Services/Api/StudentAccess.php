<?php

namespace App\Services\Api;

use App\Models\Student;
use App\Models\User;
use App\Services\Parents\ParentAccountService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Which students a signed-in user may look at through the API:
 *  - a parent: their linked children;
 *  - a student: themselves;
 *  - staff with "View student": any student (admin look-ups).
 */
class StudentAccess
{
    /** Students listed on the user's home screen (children + self). */
    public static function mine(User $user): Collection
    {
        $children = ParentAccountService::children($user)->map(function ($c) {
            $c->relationship = $c->relationship ?? 'child';
            return $c;
        });

        if (!empty($user->student_id) && !$children->firstWhere('id', (int) $user->student_id)) {
            $self = self::row((int) $user->student_id);
            if ($self) {
                $self->relationship = 'self';
                $children->prepend($self);
            }
        }

        return $children->values();
    }

    public static function canView(User $user, int $studentId): bool
    {
        if ((int) ($user->student_id ?? 0) === $studentId) return true;
        if (ParentAccountService::isParentOf($user, $studentId)) return true;
        try {
            return $user->can('View student') || $user->can('Show Student');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Self or parent only (not staff) — used for actions like paying fees or asking for leave. */
    public static function isOwnOrChild(User $user, int $studentId): bool
    {
        return (int) ($user->student_id ?? 0) === $studentId || ParentAccountService::isParentOf($user, $studentId);
    }

    /** Same columns as ParentAccountService::children(), for one student. */
    public static function row(int $studentId): ?object
    {
        return DB::table('studentRegistration as s')
            ->leftJoin('studentclass as sc', 'sc.studentId', '=', 's.id')
            ->leftJoin('schoolclass as c', 'c.id', '=', 'sc.schoolclassid')
            ->leftJoin('schoolarm as a', 'a.id', '=', 'c.arm')
            ->leftJoin('studentpicture as pic', 'pic.studentid', '=', 's.id')
            ->where('s.id', $studentId)
            ->orderByDesc('sc.sessionid')->orderByDesc('sc.termid')
            ->first(['s.id', 's.firstname', 's.lastname', 's.othername', 's.admissionNo', 's.statusId',
                     'sc.schoolclassid as class_id', 'sc.termid as term_id', 'sc.sessionid as session_id', 'pic.picture',
                     DB::raw("TRIM(CONCAT(COALESCE(c.schoolclass,''), ' ', COALESCE(a.arm,''))) as class_name")]);
    }

    public static function model(int $studentId): ?Student
    {
        return Student::find($studentId);
    }
}
