# Qudroid Hub

One account. One workspace. Every department.

The Qudroid Systems portal: Academy & LMS, projects, Qudroid Connect, CRM, finance,
inventory, HR and payroll in one Laravel application, sharing sign-in, roles and
permissions, notifications and the activity log. The mobile app (Flutter,
`StudioProjects/qudroidApp`) uses the same accounts through `/api/v1`.

This repository is the **foundation (roadmap phase 1)**: the layout and UI kit,
authentication, users, roles and permissions, profiles, notifications, the
activity log and the mobile API. Each module is added on top of it.

## Stack

| Layer | Choice |
|---|---|
| Backend | Laravel 12 (PHP 8.2+), modular monolith |
| UI | Blade + Velzon theme (`public/theme`), CB UI kit (`public/css/cb-ui.css`, `x-cb.*` components) |
| Database | MySQL 8.4 LTS, InnoDB |
| Permissions | spatie/laravel-permission |
| API | Laravel Sanctum tokens, `/api/v1` |
| Later | Redis (cache, queues), Laravel Reverb (chat and live notifications) |

## First run (XAMPP / local)

```bash
cp .env.example .env            # skip if you already have one — then check DB_DATABASE
composer update                 # the lock file needs refreshing once (Sanctum was added)
php artisan key:generate
# create an empty MySQL database named qudroid_hub, then:
php artisan migrate --seed
php artisan storage:link
php artisan test                # 18 checks of the foundation
```

Sign in with `QUDROID_ADMIN_EMAIL` / `QUDROID_ADMIN_PASSWORD` from `.env`
(default `admin@qudroids.com` / `ChangeMe@123`). You will be asked to choose your
own password straight away.

## What is here

- **Accounts** (`users`): one row per person. `user_type` is `staff`, `student`,
  `client` or `partner`; roles decide what they can do. Accounts can be disabled,
  given a temporary password (forced change at next sign-in) and imported from
  Excel (staff template on the Users page).
- **Roles** (seeded, editable in the portal): Super Administrator, Managing
  Director, Finance Officer, HR Manager, Project Manager, Staff, Academy
  Administrator, Instructor, Student, Client, Inventory Officer. A person can hold
  several. The Super Administrator passes every check and cannot be deleted or
  left without a holder.
- **Permissions** are grouped by module (`permissions.title`) in
  `database/seeders/PermissionSeeder.php`. Run `php artisan db:seed` again after
  adding some; it only adds what is missing.
- **Dashboard** = app launcher. Modules are listed in `config/qudroid.php`
  (`modules`); each shows as "coming soon" until its route exists.
- **Notifications**: `PortalNotifier::toUsers($ids, $title, $body, $url, $type)`
  writes the bell notification and pushes it to the user's phones.
- **Activity log**: every change made while signed in, plus sign-ins and
  failed sign-ins; who's online.
- **Branding**: `config/qudroid.php` / `QUDROID_*` in `.env`. Put the real logo
  files in `public/images/brand/` (the current ones are placeholders).

## Adding a module

1. Routes: `routes/modules/<module>.php` (loaded automatically inside the
   signed-in group). Name them `<module>.*` and give the module an index route
   matching `config/qudroid.php` (for example `projects.index`).
2. Permissions: add a group to `PermissionSeeder::PERMISSIONS`, run `php artisan db:seed`.
3. Controllers: `app/Http/Controllers/<Module>/…`; models in `app/Models/<Module>/…`.
4. Views: `resources/views/modules/<module>/…`, extending `layouts.master`.
   An optional `modules/<module>/nav.blade.php` replaces the module's single
   sidebar link with a sub-menu.
5. Mobile API: `routes/api_v1/<module>.php` (loaded inside the Sanctum group).
6. Money: invoices, payments, payroll and stock movements are separate, linked
   records inside database transactions; corrections are reversals, never edits.

## Mobile API (v1)

Public: `GET app/config`, `POST auth/login`, `POST auth/forgot-password`.
Signed in (Bearer token): `GET auth/me`, `POST auth/logout`, `POST auth/logout-all`,
`PUT auth/password`, `POST|DELETE devices`, `GET notifications`,
`GET notifications/unread-count`, `POST notifications/read-all`,
`POST notifications/{id}/read`, `GET|PUT me/profile`, `GET|PUT me/preferences`.

Every response: `{ "success": true, "message": "...", "data": ..., "meta": {...} }`.
