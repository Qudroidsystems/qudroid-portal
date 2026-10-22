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
php artisan test                # foundation and academy checks
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

## Academy (phase 2, first part)

`routes/modules/academy.php`, `app/{Models,Http/Controllers,Services}/Academy`,
`resources/views/modules/academy`. Tables start with `academy_`.

- **Programmes** group **courses**. A course has a code, level, delivery mode,
  duration, fee (and currency), optional instalments and prerequisites, and is
  draft, published or archived. Students only see published courses.
- **Cohorts** are intakes of a course: dates, registration window, capacity,
  instructor, schedule and an optional fee for that intake.
- **Students** (`academy_students`) sit on a `student` user account. Creating
  one makes the sign-in (role Student, temporary password) and a student
  number (`QCA/2026/0001`, prefix `QUDROID_STUDENT_NO_PREFIX`). Status:
  applicant → admitted → active → completed (or suspended / withdrawn).
- **Enrollments** go through `App\Services\Academy\EnrollmentService`. It checks
  admission, cohort status, registration window, seats, duplicates and
  prerequisites, and copies the fee onto the enrollment. Access stays locked
  until the net fee is nil, Finance calls `paymentConfirmed()`, or someone with
  **Grant course access** opens it with a reason.

- **Course content**: sections and lessons (Markdown reading, YouTube/Vimeo
  video, private file download, or link), with ordering, drafts and free
  previews. Built by people with **Manage course content** (Academy
  Administrator, Instructor) at Academy → course → Content. Files are stored on
  the private `local` disk and served only to people who can open the lesson
  (`QUDROID_LESSON_MAX_KB`, default 50 MB; raise PHP's `upload_max_filesize`
  and `post_max_size` to match).
- **Learning**: students with course access study at `/academy/learn/{course}`,
  mark lessons complete, and see their progress (published lessons only).
  Progress is stored per enrollment and shown on My academy and the cohort.
- **Online applications**: the public page `/apply` (no sign-in, `routes/public.php`)
  lists published courses whose cohorts are open and inside their registration
  window. A new applicant gets a student account (status applicant) and a
  password-reset e-mail to choose a password, so set up `MAIL_*` in `.env`.
  An e-mail that already has an account must sign in first. Signed-in students
  apply from My academy. Staff review at Academy → Applications: **Admit**
  admits the student and enrolls them on the chosen cohort (issuing the
  invoice); if that fails (e.g. the cohort is full) they stay admitted and staff
  see why. **Decline** needs a reason the applicant sees. References are
  `APP-2026-0001`. `ApplicationService` holds the rules.

- **Quizzes and assignments** (`academy_assessments`, `AssessmentService`):
  built at Academy → course → Content. Quizzes have single, multiple,
  true/false and short-answer questions (marked automatically; several-answer
  questions give part marks) and essays (marked by a teacher). Attempts,
  time limit (checked on the server, 2 minutes' grace), closing date and
  shuffling are set per quiz; the best attempt counts. Assignments take text
  and/or a private file; teachers grade or return them for changes.
- **Grade**: each assessment has a weight; the enrollment's grade is the
  weighted average (not-done counts as 0; with no weights, all count equally).
  Cohort → Gradebook shows everyone's results.
- **Certificates** (`academy_certificates`, `CertificateService`, Certificates
  module): marking an enrollment completed issues one when the course awards
  certificates and the grade reaches the course's pass grade. Someone with
  **Manage certificates** can issue below the pass grade with a reason, or
  revoke one. Serials are `QUD-CERT-2026-0001`; the QR code points to the public
  `/verify/{token}` page. Print or save as PDF from the certificate page.
  Signatory: `QUDROID_CERT_SIGNATORY`, `QUDROID_CERT_SIGNATORY_TITLE`.

- **Attendance** (Attendance module, `routes/modules/attendance.php`,
  `AttendanceService`): add a cohort's class sessions one at a time or for a
  period on chosen weekdays, then take the register (present, late, absent,
  excused) on or after the day. Academy staff take any register; an
  instructor with **Manage attendance** takes the registers of cohorts they
  teach. Rate = (present + late) / marked sessions, leaving out excused
  absences and cancelled sessions. Shown on the cohort, gradebook and My academy.

`Grant course access` and `Manage course content` are new permissions. Roles only get their starting
permissions when first created, so on an existing database give them to the
Academy Administrator (and `Manage course content` to Instructor) in Roles &
Permissions after `php artisan db:seed`.

## Finance

`routes/modules/finance.php` (staff), `routes/modules/billing.php` ("My bills"
for everyone), `routes/webhooks.php`, `app/{Models,Http/Controllers,Services}/Finance`.

- **Invoices** (`QUD-INV-2026-0001`) are issued, never edited: a mistake is
  voided (Approve finance, with a reason) and re-issued. Enrolling a student
  issues the course-fee invoice automatically; withdrawing voids it if unpaid.
- **Payments** get a receipt number (`QUD-RCT-2026-0001`) when confirmed and are
  **allocated** to invoices; an invoice's paid amount is always recalculated
  from allocations. A confirmed payment is never edited or deleted, only
  **reversed** (Approve finance, with a reason).
- **Manual payments** (bank transfer, cash, POS, cheque) are recorded by
  Finance (Manage finance) after checking the bank or till.
- **Paystack**: the payer starts from their invoice in My bills. The payment
  is confirmed only after the server calls Paystack's verify API and the
  amount, currency and reference match — from the callback or from the
  signed webhook, whichever comes first (both are safe to repeat).
  Set the webhook URL in the Paystack dashboard to `https://<your-domain>/webhook/paystack`.
  Keys: `PAYSTACK_MODE` (`sandbox`|`live`) and `PAYSTACK_TEST_SECRET_KEY` /
  `PAYSTACK_LIVE_SECRET_KEY` (or `PAYSTACK_SECRET_KEY`). With no key, students
  are asked to pay by transfer. `QUDROID_BANK_DETAILS` is printed on invoices.
- **Course access** opens when the course-fee invoice is paid in full, or when
  the first payment reaches the course's minimum where instalments are
  allowed. Reversing that payment locks access again.
- **PDFs**: invoices and receipts download as PDFs (staff from Finance, the
  customer from My bills; add `?view=1` to open in the browser).
  `QUDROID_ADDRESS` prints under the company name.
- **Ledger** (double entry): issuing an invoice posts Dr Accounts receivable /
  Cr Course fees (4000) or Services income (4100); a confirmed payment posts
  Dr Cash, Bank or Paystack clearing / Cr Accounts receivable (any overpayment
  to Customer credit); voids and payment reversals post the mirror entry. Every
  entry must balance, and entries are never edited. People with Approve
  finance can add accounts and post or reverse manual journals (opening
  balances, capital, bank charges). Reports: chart of accounts with balances,
  account ledger with running balance, trial balance and profit and loss.
- **Expenses** (`QUD-EXP-2026-0001`) are recorded with a category and optional
  receipt (Manage finance), approved or rejected by someone else with Approve
  finance, then marked paid from a bank or cash account, which posts them.
- **Budgets** set an amount per expense category and period; the budgets page
  shows spent, committed (approved, unpaid) and what is left.

## Projects & Tasks

`routes/modules/projects.php`, `app/{Models,Http/Controllers,Services}/Projects`.

- **Projects** have a short code (`WEB`) used in task numbers (`WEB-14`), a
  manager, an optional client (name, plus a client portal account), dates,
  budget and status (planning, active, on hold, completed, cancelled).
  Creating projects needs Manage projects; everything else depends on the
  person's **role in the project**: manager (runs it), member (works on
  tasks, logs time), viewer (reads and comments) or client.
- **Clients** see the overview, milestones and only the tasks marked
  "visible to the client". Comments marked internal are hidden from them.
- **Board**: To do, In progress, In review, Done; drag cards between columns
  (SortableJS from the theme). Status changes and reassignments are written
  to the task's history. The assignee, reporter and earlier commenters are
  notified of comments; the reporter is told when a task reaches review.
- **Milestones** group tasks and show progress; **checklists** live on tasks.
- **Time**: members log time per task or for the project (billable or not);
  the timesheet totals per person. **My work** lists a person's tasks across
  projects by due date, and their time this week.

## Clients & CRM

`routes/modules/crm.php`, `app/{Models,Http/Controllers,Services}/Crm`.

- **Companies** (prospect, client, inactive) with an account owner, and their
  **contacts**. A contact can be given a **client portal account** (user type
  client, role Client, temporary password shown once); they then see the
  projects they are added to.
- **Pipeline**: deals move across New, Contacted, Qualified, Proposal sent and
  Negotiation on a drag-and-drop board; the chance of winning follows the
  stage unless set by hand. A deal is then marked **won** (its company becomes
  a client) or **lost** with a reason, and can be reopened. A won deal can
  start a project (Manage projects), which links back to the deal and company.
- **Timeline**: notes, calls, emails and meetings on a company, contact or
  deal, plus **follow-ups** with a due time and a person (who is notified).
  Stage changes are written automatically.
- View clients reads everything and logs activity; Manage clients edits
  records and deals. The **Sales Officer** role has both (run
  `php artisan db:seed --class=RoleSeeder` to add it to an existing database).

## Qudroid Connect

`routes/modules/connect.php`, `routes/api_v1/connect.php`, `app/{Models,Services}/Connect`,
`app/Http/Controllers/Connect`.

- **Direct messages** (one per pair of people), private **groups** (admins
  add and remove people) and open **channels** (staff create them; anyone
  with Use connect can browse and join; members post).
- Text, files and photos (up to 25 MB), replies, reactions, editing your own
  messages, and deleting your own (group admins can delete any). Deleted
  messages show as "Message deleted"; their files are removed.
- **Unread counts** come from each person's last read message (sidebar badge
  and the conversation list). Direct and group messages notify through the
  bell and phone push, once until the person reads the chat again; channels
  and muted chats do not notify.
- The page refreshes itself every 4 seconds while open (no websocket server
  needed). A **project team chat** (Team chat button on a project) is a group
  that follows the project's staff members.
- Mobile app: `GET /api/v1/connect/conversations`, `GET|POST
  /api/v1/connect/conversations/{id}/messages` (`?after=` / `?before=`),
  `POST .../read`, `POST /api/v1/connect/direct`, `GET /api/v1/connect/people`,
  `POST /api/v1/connect/messages/{id}/react`, `DELETE /api/v1/connect/messages/{id}`.

## HR & Payroll

`routes/modules/hr.php`, `app/{Models,Http/Controllers,Services}/Hr`, `config/qudroid.php` → `payroll`.

- **Employees** (numbered QS-0001 …) are staff accounts with a job, department,
  manager, start date, next of kin, monthly pay (basic, housing, transport,
  other), bank, pension and tax details. Adding one can create the staff
  account (temporary password shown once). Only Manage payroll sees or sets pay.
- **Leave**: types with a yearly allowance (annual leave uses each person's own
  allowance), public holidays, and working-day counting (weekends and holidays
  are free). Staff book leave under **My leave & pay**; their manager (no HR
  permission needed) or HR approves or declines, and both sides are notified.
- **Payroll**: one run a month. Prepare (a payslip per active employee with
  pay), add bonuses and other deductions, have someone else approve it
  (Manage payroll or Approve finance), then mark it paid from a bank account.
  Paying posts to the ledger (Dr 5000 salaries + employer pension; Cr 2200
  PAYE, 2300 pension, 2400 NHF, 2500 other deductions, and the bank for net
  pay) and releases the payslips to staff, with a PDF for each.
- **PAYE** follows the Nigeria Tax Act 2025 bands (₦800k at 0%, then 15%,
  18%, 21%, 23%, 25%) after pension (8% of basic + housing + transport), NHF
  (2.5% of basic) and rent relief (20% of rent, max ₦500k). A bonus is taxed at
  the margin. Approved unpaid leave reduces pay by days ÷ 22. Every payslip
  keeps its tax workings. Check the rates with your accountant before the
  first live run; they live in `config/qudroid.php`.

## Inventory & Assets

`routes/modules/inventory.php`, `app/{Models,Http/Controllers,Services}/Inventory`.

- **Stock items** (SKU, unit, reorder level, usual supplier) held at
  **locations**. Stock only changes through a movement: received, issued (to a
  person or project), counted (adjusted with a reason) or moved between
  locations, so every balance has a history. Receiving keeps a weighted
  **average cost**, which values the stock. Falling to the reorder level tells
  everyone with Manage inventory, once a day.
- **Suppliers** and **purchase orders** (QUD-PO-2026-0001): draft, mark
  ordered, receive deliveries (partly or in full) into a location, then
  **send the bill to Finance**, which creates an expense for approval and
  payment. Lines without a stock item (delivery, services) are allowed.
- **Asset register** (QUD-AST-0001 …): equipment with serial, cost, supplier,
  warranty and condition; hand over to staff (they are notified and see it
  under `/me/assets`), return, repair, retire or report lost. Every step is
  kept on the asset's history. HR employee pages list the equipment a person
  holds.
- View inventory reads everything; Manage inventory makes changes. The
  **Inventory Officer** role has both.

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
