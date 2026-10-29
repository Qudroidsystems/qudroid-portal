<?php

/*
|--------------------------------------------------------------------------
| Qlouds Workspace — organisation and branding
|--------------------------------------------------------------------------
| One place for the names, logos and contact details the portal shows.
| The web layout, the login page, e-mails and the mobile app
| (GET /api/v1/app/config) all read from here.
*/

return [

    'name'       => env('QUDROID_NAME', 'Qlouds Workspace'),
    'company'    => env('QUDROID_COMPANY', 'Qudroid Systems'),
    'tagline'    => env('QUDROID_TAGLINE', 'One account. One workspace. Every department.'),
    'website'    => env('QUDROID_WEBSITE', 'https://qudroids.com'),
    'support_email' => env('QUDROID_SUPPORT_EMAIL', 'support@qudroids.com'),
    'support_phone' => env('QUDROID_SUPPORT_PHONE'),
    'address'    => env('QUDROID_ADDRESS'),          // shown on invoices and receipts

    // Brand colour used by the layout accents and the mobile app theme: Qlouds sky blue (a deeper shade of the
    // logo's sky so white text on it stays readable). Logo accents: orange #F97316 and green #22C55E.
    'primary_color' => env('QUDROID_PRIMARY_COLOR', '#0284C7'),

    // Paths under public/: the Qlouds logo, mark and favicon in public/images/brand/.
    'logo'       => env('QUDROID_LOGO', 'images/brand/qlouds-logo-dark.svg'),
    'logo_light' => env('QUDROID_LOGO_LIGHT', 'images/brand/qlouds-logo-light.svg'),
    'logo_small' => env('QUDROID_LOGO_SMALL', 'images/brand/qlouds-mark.svg'),
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
        // Largest lesson file, in KB (PHP's upload_max_filesize/post_max_size must allow it too).
        'max_upload_kb'     => (int) env('QUDROID_LESSON_MAX_KB', 51200),
        // Who signs course certificates.
        'certificate_signatory'       => env('QUDROID_CERT_SIGNATORY', 'Managing Director'),
        'certificate_signatory_title' => env('QUDROID_CERT_SIGNATORY_TITLE', 'Qudroid Systems'),
    ],

    // Finance: invoice and receipt numbers look like QUD-INV-2026-0001.
    'finance' => [
        'number_prefix'    => env('QUDROID_DOC_PREFIX', 'QUD'),
        'default_due_days' => (int) env('QUDROID_INVOICE_DUE_DAYS', 14),
        // Bank details shown on invoices for people paying by transfer.
        'bank_details'     => env('QUDROID_BANK_DETAILS'),
    ],

    // Workspace Drive. Each person's own files (and earlier versions) count towards their quota.
    'drive' => [
        'quota_mb'      => (int) env('QUDROID_DRIVE_QUOTA_MB', 5120),
        // Largest single upload, in MB (PHP's upload_max_filesize/post_max_size must allow it too).
        'max_upload_mb' => (int) env('QUDROID_DRIVE_MAX_UPLOAD_MB', 200),
    ],

    /*
    | Calls in Connect (browser to browser). STUN lets browsers find each
    | other; a TURN relay is only needed on strict office or mobile networks.
    */
    'calls' => [
        'stun' => array_values(array_filter(explode(',', (string) env('QUDROID_STUN_URLS', 'stun:stun.l.google.com:19302,stun:stun1.l.google.com:19302')))),
        'turn_url'        => env('QUDROID_TURN_URL'),            // e.g. turn:turn.example.com:3478
        'turn_username'   => env('QUDROID_TURN_USERNAME'),
        'turn_credential' => env('QUDROID_TURN_CREDENTIAL'),
        // People per call. Each browser sends to every other one, so keep this small.
        'max_people'      => (int) env('QUDROID_CALL_MAX_PEOPLE', 8),
        // Recordings are uploaded in pieces while the call runs; this caps one recording.
        'recording_max_mb' => (int) env('QUDROID_RECORDING_MAX_MB', 1024),
        // Language the browsers listen for when transcribing (a BCP 47 tag, e.g. en-NG, en-GB, fr-FR).
        'transcript_lang' => env('QUDROID_TRANSCRIPT_LANG', 'en-NG'),
    ],

    /*
    | Payroll. PAYE uses the Nigeria Tax Act 2025 personal income tax bands
    | (in force from 1 January 2026): each band is [width in naira a year,
    | rate %], applied in order; null width = everything above. Pension and
    | NHF contributions and rent relief (20% of annual rent, at most
    | N500,000) are deducted before tax. Check these with your accountant
    | and change them here if the law changes.
    */
    'payroll' => [
        'employee_prefix'     => env('QUDROID_EMPLOYEE_PREFIX', 'QS'),
        'tax_bands'           => [[800000, 0], [2200000, 15], [9000000, 18], [13000000, 21], [25000000, 23], [null, 25]],
        'pension_employee'    => 8,      // % of basic + housing + transport
        'pension_employer'    => 10,
        'nhf_rate'            => 2.5,    // % of basic
        'rent_relief_rate'    => 20,     // % of annual rent
        'rent_relief_cap'     => 500000,
        'working_days_month'  => 22,     // for unpaid-leave deductions
    ],

    /*
    | Business units: new records go to `default` unless another unit is
    | chosen; academy courses and student fees go to `academy`. Codes match
    | Settings → Business units.
    */
    'units' => [
        'default' => env('QUDROID_DEFAULT_UNIT', 'QS'),
        'academy' => env('QUDROID_ACADEMY_UNIT', 'QCA'),
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
        'command_center' => ['name' => 'Command Center',  'icon' => 'ri-dashboard-3-line',    'route' => 'command-center',     'permission' => 'View command center', 'phase' => 6, 'color' => '#0f172a', 'description' => 'The executive view: money, students, sales, projects, people and stock.'],
        'academy'      => ['name' => 'Academy & LMS',       'icon' => 'ri-book-open-line', 'route' => 'academy.index',      'permission' => 'View academy',      'phase' => 2, 'color' => '#6366f1', 'description' => 'Admissions, courses, lessons, assessments and progress.'],
        'certificates' => ['name' => 'Certificates',        'icon' => 'ri-award-line',          'route' => 'certificates.index', 'permission' => 'View certificates', 'phase' => 2, 'color' => '#f59e0b', 'description' => 'Templates, numbering, QR verification and revocation.'],
        'attendance'   => ['name' => 'Attendance',          'icon' => 'ri-calendar-check-line', 'route' => 'attendance.index',   'permission' => 'View attendance',   'phase' => 2, 'color' => '#14b8a6', 'description' => 'Class sessions, registers and staff attendance.'],
        'checkin'      => ['name' => 'Check-in',            'icon' => 'ri-qr-scan-2-line',      'route' => 'checkin.index',      'permission' => 'Check in|Run check-in|Manage check-in places', 'phase' => 2, 'color' => '#0d9488', 'description' => 'QR check-in for classes, staff clock-in, meetings and events.'],
        'projects'     => ['name' => 'Projects & Tasks',    'icon' => 'ri-layout-column-line',         'route' => 'projects.index',     'permission' => 'View projects',     'phase' => 3, 'color' => '#0ea5e9', 'description' => 'Workspaces, boards, docs, time tracking and approvals.'],
        'crm'          => ['name' => 'Clients & CRM',       'icon' => 'ri-briefcase-4-line',    'route' => 'crm.index',          'permission' => 'View clients',      'phase' => 3, 'color' => '#8b5cf6', 'description' => 'Clients, leads, proposals, contracts and support tickets.'],
        'workspace'    => ['name' => 'Workspace',           'icon' => 'ri-apps-2-line',         'route' => 'workspace.index',    'permission' => 'Use connect',       'phase' => 4, 'color' => '#14b8a6', 'description' => 'Your chats, calls, meetings and files in one place.'],
        'connect'      => ['name' => 'Qudroid Connect',     'icon' => 'ri-chat-smile-3-line',   'route' => 'connect.index',      'permission' => 'Use connect|Chat as a guest',       'phase' => 4, 'color' => '#22c55e', 'description' => 'Channels, direct messages, files and calls.'],
        'mail'         => ['name' => 'Qmail',               'icon' => 'ri-mail-line',           'route' => 'mail.index',         'permission' => 'Use mail',          'phase' => 4, 'color' => '#ea4335', 'description' => 'Your e-mail: inbox, conversations, labels, search and sending from your own address.'],
        'drive'        => ['name' => 'Drive',               'icon' => 'ri-folder-3-line',   'route' => 'drive.index',        'permission' => 'Use connect',       'phase' => 4, 'color' => '#3b82f6', 'description' => 'Your files and folders, project files, sharing and links.'],
        'meetings'     => ['name' => 'Meetings & Calendar', 'icon' => 'ri-video-chat-line',     'route' => 'meetings.index',     'permission' => 'View meetings',     'phase' => 4, 'color' => '#ec4899', 'description' => 'Scheduled meetings, rooms and shared calendars.'],
        'finance'      => ['name' => 'Finance & Invoicing', 'icon' => 'ri-money-dollar-circle-line', 'route' => 'finance.index', 'permission' => 'View finance',      'phase' => 2, 'color' => '#10b981', 'description' => 'Invoices, payments, bills, expenses and reports.'],
        'inventory'    => ['name' => 'Inventory & Assets',  'icon' => 'ri-archive-line',        'route' => 'inventory.index',    'permission' => 'View inventory',    'phase' => 5, 'color' => '#f97316', 'description' => 'Stock, procurement, suppliers and the IT asset register.'],
        'hr'           => ['name' => 'HR & Payroll',        'icon' => 'ri-team-line',           'route' => 'hr.index',           'permission' => 'View employees',    'phase' => 5, 'color' => '#ef4444', 'description' => 'Employees, leave, timesheets, payroll and payslips.'],
        'website'      => ['name' => 'Website',             'icon' => 'ri-global-line',         'route' => 'website.index',      'permission' => 'Manage website',    'phase' => 6, 'color' => '#0071e3', 'description' => 'The public website: pages, products, our work, forms, course registration and online payment.'],
        'workflows'    => ['name' => 'Workflows',           'icon' => 'ri-flow-chart',          'route' => 'workflows.index',    'permission' => 'Manage workflows',  'phase' => 6, 'color' => '#7c3aed', 'description' => 'Automations: when something happens, notify, e-mail, post, create tasks or call webhooks.'],
    ],


    /*
    | Qmail. Server defaults for new mailboxes (Settings → Mail can override them), and
    | the optional Stalwart admin API used to create addresses from the portal.
    */
    'mail' => [
        'domain' => env('QMAIL_DOMAIN'),
        'imap_host' => env('QMAIL_IMAP_HOST'),
        'imap_port' => (int) env('QMAIL_IMAP_PORT', 993),
        'imap_encryption' => env('QMAIL_IMAP_ENCRYPTION', 'ssl'),
        'smtp_host' => env('QMAIL_SMTP_HOST'),
        'smtp_port' => (int) env('QMAIL_SMTP_PORT', 465),
        'smtp_encryption' => env('QMAIL_SMTP_ENCRYPTION', 'ssl'),
        'validate_cert' => (bool) env('QMAIL_VALIDATE_CERT', true),
        'save_sent' => (bool) env('QMAIL_SAVE_SENT', true),
        'stalwart_url' => env('QMAIL_STALWART_URL'),
        'stalwart_token' => env('QMAIL_STALWART_TOKEN'),
        'max_attachment_mb' => (int) env('QMAIL_MAX_ATTACHMENT_MB', 25),
    ],

];
