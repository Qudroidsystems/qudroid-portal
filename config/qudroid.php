<?php

/*
|--------------------------------------------------------------------------
| Qudroid Hub — organisation and branding
|--------------------------------------------------------------------------
| One place for the names, logos and contact details the portal shows.
| The web layout, the login page, e-mails and the mobile app
| (GET /api/v1/app/config) all read from here.
*/

return [

    'name'       => env('QUDROID_NAME', 'Qudroid Hub'),
    'company'    => env('QUDROID_COMPANY', 'Qudroid Systems'),
    'tagline'    => env('QUDROID_TAGLINE', 'One account. One workspace. Every department.'),
    'website'    => env('QUDROID_WEBSITE', 'https://qudroids.com'),
    'support_email' => env('QUDROID_SUPPORT_EMAIL', 'support@qudroids.com'),
    'support_phone' => env('QUDROID_SUPPORT_PHONE'),

    // Brand colour used by the layout accents and the mobile app theme.
    'primary_color' => env('QUDROID_PRIMARY_COLOR', '#405189'),

    // Paths under public/. These are placeholder marks — drop the real
    // Qudroid logo files in public/images/brand/ (or point these at them).
    'logo'       => env('QUDROID_LOGO', 'images/brand/qudroid-hub-logo-dark.svg'),
    'logo_light' => env('QUDROID_LOGO_LIGHT', 'images/brand/qudroid-hub-logo-light.svg'),
    'logo_small' => env('QUDROID_LOGO_SMALL', 'images/brand/qudroid-hub-mark.svg'),
    'favicon'    => env('QUDROID_FAVICON', 'images/brand/favicon.svg'),

    // Allow people to create their own account from the login page.
    // Keep this off: staff, clients and students are invited by an administrator.
    'allow_registration' => (bool) env('QUDROID_ALLOW_REGISTRATION', false),

    /*
    | The first administrator, created by `php artisan db:seed`.
    | Change the password after the first sign-in.
    */
    'super_admin' => [
        'name'     => env('QUDROID_ADMIN_NAME', 'Super Administrator'),
        'email'    => env('QUDROID_ADMIN_EMAIL', 'admin@qudroids.com'),
        'password' => env('QUDROID_ADMIN_PASSWORD', 'ChangeMe@123'),
    ],

    // The role that bypasses every permission check.
    'super_admin_role' => 'Super Administrator',

    /*
    | What kind of account a user is. Roles decide what they can do;
    | the type decides which part of the portal is their home.
    */
    'user_types' => [
        'staff'   => 'Staff',
        'student' => 'Student',
        'client'  => 'Client',
        'partner' => 'Partner / Vendor',
    ],

    // Academy: student numbers look like QCA/2026/0001; course fees default to this currency.
    'academy' => [
        'student_no_prefix' => env('QUDROID_STUDENT_NO_PREFIX', 'QCA'),
        'currency'          => env('QUDROID_CURRENCY', 'NGN'),
    ],

    // Finance: invoice and receipt numbers look like QUD-INV-2026-0001.
    'finance' => [
        'number_prefix'    => env('QUDROID_DOC_PREFIX', 'QUD'),
        'default_due_days' => (int) env('QUDROID_INVOICE_DUE_DAYS', 14),
        // Bank details shown on invoices for people paying by transfer.
        'bank_details'     => env('QUDROID_BANK_DETAILS'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Modules (the app launcher on the dashboard and the sidebar)
    |--------------------------------------------------------------------------
    | Each module appears once its `route` exists and the user holds its
    | `permission` (or any permission in its `title` group). Until then the
    | launcher shows it as "Coming soon", so the roadmap stays visible.
    | `phase` follows the development roadmap.
    */
    'modules' => [
        'academy'      => ['name' => 'Academy & LMS',       'icon' => 'ri-book-open-line', 'route' => 'academy.index',      'permission' => 'View academy',      'phase' => 2, 'color' => '#6366f1', 'description' => 'Admissions, courses, lessons, assessments and progress.'],
        'certificates' => ['name' => 'Certificates',        'icon' => 'ri-award-line',          'route' => 'certificates.index', 'permission' => 'View certificates', 'phase' => 2, 'color' => '#f59e0b', 'description' => 'Templates, numbering, QR verification and revocation.'],
        'attendance'   => ['name' => 'Attendance',          'icon' => 'ri-calendar-check-line', 'route' => 'attendance.index',   'permission' => 'View attendance',   'phase' => 2, 'color' => '#14b8a6', 'description' => 'Class sessions, registers and staff attendance.'],
        'projects'     => ['name' => 'Projects & Tasks',    'icon' => 'ri-layout-column-line',         'route' => 'projects.index',     'permission' => 'View projects',     'phase' => 3, 'color' => '#0ea5e9', 'description' => 'Workspaces, boards, docs, time tracking and approvals.'],
        'crm'          => ['name' => 'Clients & CRM',       'icon' => 'ri-briefcase-4-line',    'route' => 'crm.index',          'permission' => 'View clients',      'phase' => 3, 'color' => '#8b5cf6', 'description' => 'Clients, leads, proposals, contracts and support tickets.'],
        'connect'      => ['name' => 'Qudroid Connect',     'icon' => 'ri-chat-smile-3-line',   'route' => 'connect.index',      'permission' => 'Use connect',       'phase' => 4, 'color' => '#22c55e', 'description' => 'Channels, direct messages, files and calls.'],
        'meetings'     => ['name' => 'Meetings & Calendar', 'icon' => 'ri-video-chat-line',     'route' => 'meetings.index',     'permission' => 'View meetings',     'phase' => 4, 'color' => '#ec4899', 'description' => 'Scheduled meetings, rooms and shared calendars.'],
        'finance'      => ['name' => 'Finance & Invoicing', 'icon' => 'ri-money-dollar-circle-line', 'route' => 'finance.index', 'permission' => 'View finance',      'phase' => 2, 'color' => '#10b981', 'description' => 'Invoices, payments, bills, expenses and reports.'],
        'inventory'    => ['name' => 'Inventory & Assets',  'icon' => 'ri-archive-line',        'route' => 'inventory.index',    'permission' => 'View inventory',    'phase' => 5, 'color' => '#f97316', 'description' => 'Stock, procurement, suppliers and the IT asset register.'],
        'hr'           => ['name' => 'HR & Payroll',        'icon' => 'ri-team-line',           'route' => 'hr.index',           'permission' => 'View employees',    'phase' => 5, 'color' => '#ef4444', 'description' => 'Employees, leave, timesheets, payroll and payslips.'],
    ],

];
