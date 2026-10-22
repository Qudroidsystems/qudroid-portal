<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Every permission in Qudroid Hub, grouped by module (`title` is the group
 * the role editor shows). Safe to run again: it adds what is missing and
 * refreshes the groups and descriptions, and never removes anything.
 *
 * When you build a module, add its permissions to its group here.
 */
class PermissionSeeder extends Seeder
{
    /** group => [permission => description] */
    public const PERMISSIONS = [
        'Dashboard' => [
            'View dashboard' => 'See the organisation overview on the dashboard (users, sign-ins, recent activity).',
        ],
        'Users' => [
            'View user'   => 'See the list of accounts and each person\'s profile.',
            'Create user' => 'Create accounts and import staff from Excel.',
            'Update user' => 'Edit accounts, reset passwords, change roles and disable sign-in.',
            'Delete user' => 'Delete accounts. This cannot be undone.',
        ],
        'Roles' => [
            'View role'        => 'See roles and who holds them.',
            'Create role'      => 'Create new roles.',
            'Update role'      => 'Change a role\'s name and permissions.',
            'Delete role'      => 'Delete roles.',
            'Update user-role' => 'Add people to a role.',
            'Remove user-role' => 'Remove people from a role.',
        ],
        'Permissions' => [
            'View permission'   => 'See the list of permissions.',
            'Create permission' => 'Create permissions.',
            'Update permission' => 'Rename permissions.',
            'Delete permission' => 'Delete permissions.',
        ],
        'Security & Audit' => [
            'View activity log' => 'See every change made in the portal and export it.',
            'View online staff' => 'See which staff are signed in right now.',
        ],

        // ── Modules (switched on in the launcher as each one is built) ─────
        'Academy & LMS' => [
            'View academy'   => 'Open the Academy: courses, lessons and learning progress.',
            'Manage academy' => 'Run admissions, courses, fees, assessments and student records.',
            'Manage course content' => 'Build course sections and lessons (text, video, files, links) and publish them.',
            'Grant course access' => 'Open course access without a confirmed payment (e.g. approved instalment plan) and waive prerequisites. A reason is recorded.',
        ],
        'Certificates' => [
            'View certificates'   => 'See issued certificates.',
            'Manage certificates' => 'Design templates and issue, renew or revoke certificates.',
        ],
        'Attendance' => [
            'View attendance'   => 'See attendance registers and reports.',
            'Manage attendance' => 'Take registers and correct attendance records.',
        ],
        'Projects & Tasks' => [
            'View projects'   => 'Open project workspaces, boards and documents you belong to.',
            'Manage projects' => 'Create projects, assign work, and manage budgets and milestones.',
        ],
        'Clients & CRM' => [
            'View clients'   => 'See client accounts, contacts and support tickets.',
            'Manage clients' => 'Manage leads, proposals, contracts and client records.',
        ],
        'Qudroid Connect' => [
            'Use connect'      => 'Chat in channels and direct messages, share files and join calls.',
            'Manage channels'  => 'Create, archive and moderate channels.',
        ],
        'Meetings & Calendar' => [
            'View meetings'   => 'See and join meetings and shared calendars.',
            'Manage meetings' => 'Schedule meetings and manage meeting rooms.',
        ],
        'Finance & Invoicing' => [
            'View finance'     => 'See invoices, payments, bills and financial reports.',
            'Manage finance'   => 'Issue invoices and record bank-transfer, cash and POS payments.',
            'Approve finance'  => 'Void invoices, reverse payments, and approve bills, expenses and refunds.',
        ],
        'Inventory & Assets' => [
            'View inventory'   => 'See stock levels, suppliers and the asset register.',
            'Manage inventory' => 'Receive, issue and adjust stock; assign and retire assets.',
        ],
        'HR & Payroll' => [
            'View employees'   => 'See employee records, leave and timesheets.',
            'Manage employees' => 'Manage employee records, leave and attendance.',
            'Manage payroll'   => 'Prepare and approve payroll and payslips.',
        ],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $group => $perms) {
            foreach ($perms as $name => $description) {
                $p = Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
                $p->forceFill(['title' => $group, 'description' => $description])->save();
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->command?->info('Permissions: ' . Permission::count());
    }
}
