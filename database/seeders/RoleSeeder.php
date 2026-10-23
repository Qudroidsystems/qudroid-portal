<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * The starting roles. A role's permissions are only set when the role is
 * first created, so changes made later in the role editor are kept when
 * the seeder runs again. People can hold several roles at once.
 */
class RoleSeeder extends Seeder
{
    /** role => [badge colour, permissions] ('*' = everything; Super Administrator also bypasses checks) */
    public const ROLES = [
        'Super Administrator' => ['#405189', ['*']],

        'Managing Director' => ['#6a0572', [
            'View dashboard', 'View user', 'View role', 'View activity log', 'View online staff',
            'View academy', 'View certificates', 'View attendance', 'View projects', 'View clients',
            'Use connect', 'Use mail', 'View meetings', 'View finance', 'Approve finance', 'View inventory', 'View employees',
        ]],

        'Finance Officer' => ['#10b981', [
            'View dashboard', 'View finance', 'Manage finance', 'View clients', 'Use connect', 'Use mail', 'View meetings',
        ]],

        'HR Manager' => ['#ef4444', [
            'View dashboard', 'View user', 'Create user', 'Update user', 'View online staff',
            'View employees', 'Manage employees', 'Manage payroll', 'View attendance', 'Manage attendance',
            'Use connect', 'Use mail', 'View meetings',
        ]],

        'Project Manager' => ['#0ea5e9', [
            'View dashboard', 'View projects', 'Manage projects', 'View clients', 'Use connect', 'Use mail',
            'View meetings', 'Manage meetings',
        ]],

        // Developers and other staff
        'Sales Officer' => ['#8b5cf6', [
            'View dashboard', 'View clients', 'Manage clients', 'View projects', 'Use connect', 'Use mail', 'View meetings',
        ]],

        'Staff' => ['#457b9d', [
            'View projects', 'Use connect', 'Use mail', 'View meetings',
        ]],

        'Academy Administrator' => ['#6366f1', [
            'View dashboard', 'View user', 'Create user', 'Update user',
            'View academy', 'Manage academy', 'Manage course content', 'Grant course access', 'View certificates', 'Manage certificates',
            'View attendance', 'Manage attendance', 'Use connect', 'Use mail', 'View meetings',
        ]],

        'Instructor' => ['#0a9396', [
            'View academy', 'Manage course content', 'View attendance', 'Manage attendance', 'Use connect', 'Use mail', 'View meetings',
        ]],

        'Student' => ['#e76f51', [
            'View academy', 'View certificates', 'Use connect',
        ]],

        'Client' => ['#8b5cf6', [
            'View projects', 'Chat as a guest',
        ]],

        'Inventory Officer' => ['#f97316', [
            'View dashboard', 'View inventory', 'Manage inventory', 'Use connect', 'Use mail', 'View meetings',
        ]],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::ROLES as $name => [$badge, $perms]) {
            $role = Role::where('name', $name)->where('guard_name', 'web')->first();
            if ($role) continue; // keep whatever was configured since

            $role = Role::create(['name' => $name, 'guard_name' => 'web', 'badge' => $badge]);
            $role->syncPermissions($perms === ['*'] ? \Spatie\Permission\Models\Permission::all() : $perms);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->command?->info('Roles: ' . Role::count());
    }
}
