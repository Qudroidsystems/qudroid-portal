<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Instructors teach only their own classes. "Manage course content" now means
 * the courses you teach a class on (and your own students' work); the new
 * "Manage all course content" is for academy staff who build and mark every
 * course. Instructors also stop seeing every class's attendance.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $all = Permission::firstOrCreate(['name' => 'Manage all course content', 'guard_name' => 'web']);
        $all->forceFill(['title' => 'Academy & LMS', 'description' => 'Build and mark every course and see every class, not only the ones you teach.'])->save();
        Permission::where('name', 'Manage course content')->where('guard_name', 'web')->first()?->forceFill([
            'description' => "Teach: build the content and assessments of the courses you teach a class on, and mark your own students' work.",
        ])->save();

        Role::where('name', 'Academy Administrator')->where('guard_name', 'web')->first()?->givePermissionTo($all);

        $instructor = Role::where('name', 'Instructor')->where('guard_name', 'web')->first();
        if ($instructor && $instructor->hasPermissionTo('View attendance')) {
            $instructor->revokePermissionTo('View attendance');
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Role::where('name', 'Instructor')->where('guard_name', 'web')->first()?->givePermissionTo('View attendance');
        Permission::where('name', 'Manage all course content')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
