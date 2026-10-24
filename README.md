# Qlouds Workspace

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

**Sample data.** To try the portal with a small company's worth of records (staff
with salaries and a paid payroll, clients and deals, projects and tasks, invoices
and expenses, courses with enrolled students and registers, stock and laptops,
meetings, chat, office clock-ins and a conference with sign-ups):

```bash
php artisan db:seed --class=DemoSeeder
```

Everyone it adds has an `@demo.qudroid.test` address and the password `Demo@2026!`
(it prints a sign-in for each role). It runs once, and it refuses on a production
site unless `DEMO_SEED_IN_PRODUCTION=true`. Use it on a test database, not live data.

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

## Meetings & Calendar

`routes/modules/meetings.php`, `routes/api_v1/meetings.php`, `app/{Models,Http/Controllers,Services}/Meetings`.

- Anyone with View meetings **schedules** their own meetings: time, room or
  place, video link, agenda, project or client, colleagues to invite, and
  outside guests by name. The organiser (or Manage meetings) edits or cancels.
  Rooms can't be double-booked, and the form warns when an invitee already has
  a meeting or approved leave at that time.
- Invitees answer **going / maybe / not going** (organiser hears about the last
  two). Moving a meeting asks everyone to answer again. Every change notifies
  the people affected. "Add to my calendar" downloads an `.ics` file.
- **Reminders**: `php artisan meetings:remind` runs every minute from the
  scheduler (one cron line, see `routes/console.php`) and reminds people still
  coming at each meeting's chosen time.
- **Minutes** can be written after the meeting; attendees are told.
- **Calendar**: a month view of your meetings, task due dates, public holidays
  and leave (your own and your team's; everyone's with View employees).
- Mobile app: `GET /api/v1/meetings` (`?past=1`), `GET /api/v1/meetings/calendar?from=&to=`,
  `GET /api/v1/meetings/{id}`, `POST /api/v1/meetings/{id}/respond`.

## Workspace Drive

`routes/modules/drive.php`, `app/{Models,Http/Controllers,Services}/Drive`. Uses the Use connect permission.

- **My Drive**: each person's own folders and files. Upload many files at once
  (or drag them onto the page), make folders, rename, move, star, and download
  a folder as a zip.
- **Project files**: every project has a shared space (also a "Files" tab on
  the project). Managers have full control, members edit, viewers view;
  clients only see what is shared with them directly.
- **Sharing**: give people view or edit access to a file, or to a folder and
  everything in it. "Anyone with the link" (`/s/{token}`, optional expiry) lets
  outsiders view and download without signing in. People hear about shares and
  about new files in folders shared with them.
- **Versions**: uploading a new version keeps the earlier ones; any of them can
  be downloaded or made current again.
- **Trash**: deleted things can be restored until the owner (or a project
  manager) deletes them for good, which is when the stored files are removed.
- **Chats**: "From chats" lists files sent in your conversations ("Save to My
  Drive" keeps a copy), and any Drive item can be sent into a chat, which gives
  the people there view access.
- Files are stored privately on the `local` disk and only served after an
  access check. HTML and SVG are never shown inline. Each person's files count
  towards `QUDROID_DRIVE_QUOTA_MB` (default 5 GB); single uploads are capped by
  `QUDROID_DRIVE_MAX_UPLOAD_MB` (default 200, and PHP's upload limits).

## Workspace home, live chat and calls

`/workspace` brings everything together: chats waiting for a reply, calls going on now, your next meetings, recent Drive files and who is online.

Live chat (Connect) adds threads (replies stay out of the main timeline), `@name` / `@all` mentions (they notify even in muted chats and channels), pinned and saved messages, search across your conversations, typing indicators, online dots and "seen" in direct chats. Files can be attached straight from Drive, and chat attachments can be saved to Drive.

Calls are built in (WebRTC, no outside service). Press the phone or camera button in any chat; direct and group chats ring their members on any portal page, channels show a "Join" banner. Calls open in their own tab, with mute, camera, screen share and leave (M / V keys). Every browser connects to every other one, so calls suit up to about 8 people (`max_people`). Call history is at **Connect → Calls**.

- Updates use short polling (about every 2 seconds in an open chat, every second on a call page), so no websocket server is needed. Installing Laravel Reverb later would make it instant.
- Calls need HTTPS (or `localhost`) for the browser to allow the camera and microphone.
- On strict office or mobile networks some calls need a TURN relay. Set `QUDROID_TURN_URL`, `QUDROID_TURN_USERNAME` and `QUDROID_TURN_CREDENTIAL` (for example from coturn or a hosted TURN service). STUN servers come from `QUDROID_STUN_URLS` (comma separated; Google's public one by default).

Meetings link into the workspace: each meeting has a **Meeting chat** (members follow the invite list) and a **Start video call** button. On the Calendar page each person gets a private **subscribe link** (`/calendar/<token>.ics`) to add to Google Calendar, Outlook or Apple Calendar; it covers one month back to six months ahead, refreshes on the calendar app's schedule, and can be reset if it leaks.

## Settings (Paystack and payroll tax)

**Administration → Settings** (permission *Manage settings*; the Super Administrator always has it).

- **Payments**: switch online payments on or off, choose Test or Live, and save the Paystack public and secret keys. Secret keys are stored encrypted with `APP_KEY` and are never shown again (only the last four characters). Anything left empty falls back to the `PAYSTACK_*` values in `.env`, and "Use the .env values instead" removes everything saved here. "Test the key" asks Paystack's `/balance` endpoint whether the key works. The page shows the webhook URL to paste into the Paystack dashboard; Paystack signs webhooks with the secret key, so there is no separate webhook secret.
- **Payroll tax** (also open to *Manage payroll*): the PAYE bands, pension, NHF, rent relief and working days used by payroll, with example tax figures. Saved rates apply the next time a payroll is generated. An accountant's check can be recorded against the current rates; changing any rate clears it, and the Payroll page warns until the rates are checked. "Use the built-in rates" goes back to the Nigeria Tax Act 2025 defaults in `config/qudroid.php`.

After pulling this, run `php artisan migrate` and `php artisan db:seed --class=PermissionSeeder` (adds *Manage settings*).

## Command Center

`/command-center` (permission *View command center*) is the executive view across every module for this month, last month, this quarter or this year, each compared with the period before:

- Cash collected, expenses paid, profit from the ledger, money owed (and overdue), new enrolments, deals won, the open pipeline (also weighted by stage), and headcount.
- Money in and out for the last 12 months as a chart, which can also be shown as a table.
- Sales pipeline by stage, upcoming cohorts with seats taken, and active projects with progress and overdue tasks.
- **Needs attention**: overdue invoices, payroll not yet paid, expenses to approve or pay, overdue tasks, late projects, applications to review, leave to decide, late deals and stock running low. Each item links to where it's handled.

The figures come from `App\Services\Reports\CommandCenter`. Run `php artisan db:seed --class=PermissionSeeder` to add the permission.

## Business units

The organisation is split into business units: **Qudroid Systems** (QS), **Coding Academy** (QCA) and **ViteSchool** (VS) to start with. They're managed under **Settings → Business units**, where you can rename, recolour, reorder, add or switch off units.

- Invoices, expenses, deals, projects, employees and academy courses each belong to a unit, with a *Business unit* field on their forms. The field only appears while more than one unit is in use.
- New records default to `QUDROID_DEFAULT_UNIT` (QS). Courses, and invoices for course fees, default to `QUDROID_ACADEMY_UNIT` (QCA).
- Payments count towards their invoice's unit, and leave and enrolments towards their employee's or course's unit.
- When this is installed, existing courses and fee invoices go to the Coding Academy and everything else goes to Qudroid Systems.
- The **Command Center** can show one unit or all of them, and has a *By business unit* table with a combined total. The ledger isn't split by unit, so a single unit's view shows its cash result (collected less expenses paid) instead of ledger profit. Stock and payroll runs are organisation-wide.

## Search, notifications, apps menu and sounds

- **Search**: press `Ctrl+K` (or `⌘K`), or use the search icon on phones, to search pages and records together: people, students, courses, projects, tasks (type a key like `WEB-14`), clients, deals, invoices, payments, expenses, staff, stock, assets, meetings, Drive files and chat messages. Each source checks permissions, so people only find what they could open. Press Enter for the full results page at `/search?q=…`. Sources live in `App\Services\Search\GlobalSearch`.
- **Notifications**: filter the inbox by kind (messages, calls, meetings, tasks, files, money, leave and approvals, academy, clients, stock), switch kinds off under *What to tell me about* (announcements and system messages always arrive), and clear read ones. The bell checks every 30 seconds.
- **Apps menu**: the grid button in the top bar opens every module the person can use.
- **Sounds** (made in the browser, so there are no audio files): a ringtone for incoming calls on any page, ringback while you wait for someone to join, a short tone when someone joins, falling beeps when a call ends, a chime for new notifications and a tone for new chat messages. They can be switched off per device from the bell or the Notifications page. Browsers only allow sound after the person has clicked or typed on the page once.

After pulling this, run `php artisan migrate` (adds `users.muted_notifications`).

## Live pages and the project board

Pages can send a form in the background and swap in the new content without a reload. The sidebar, calls and sounds keep running and the result shows as a toast. This lives in `resources/views/layouts/partials/live.blade.php` (plain JavaScript, so there is no build step). To use it on a form, add `data-live`:

- `data-live-auto` submits when a select, checkbox or date changes, or 350 ms after typing in a search box (useful for filters and inline edits).
- `data-confirm="…"` asks first.
- `data-live-also="/url"` also refreshes the matching regions from that page.

Controllers don't change: they still redirect back with a flash message. A region is any element with an `id` and `data-live-region`, and the whole page region is `.main-content`. Page scripts can listen for `live:swapped` to re-bind.

**Projects** uses this everywhere:

- **Board:** drag and drop (SortableJS, bundled in `public/vendor/sortablejs`, works on touch too), adding tasks inline, filters for assignee, priority, due date and milestone, and a side panel that opens a task without leaving the board (`?task=12` links straight to it). The board refreshes every 20 seconds when someone else changes it.
- **List view and My work:** status, priority, assignee and due date can be edited in place.
- **Overview and Time:** milestones, members, status and time entries all update in place.

## Chat: emoji, polls and GIFs

- **Emoji:** the smiley button in the composer opens a picker. Reactions can use any emoji in the picker, and hovering a reaction shows who reacted.
- **Polls:** the chart button creates a poll with 2 to 10 options, single or multiple choice. People can change their vote or take it back. The person who asked, or a conversation admin, can close the poll. Votes are in `connect_poll_votes`.
- **GIFs:** the GIF button searches GIPHY through the portal (`/connect/gifs`), so the key never reaches the browser. Only `giphy.com` image links are accepted. To turn it on, add a free key under Settings → Integrations (stored encrypted) or set `GIPHY_API_KEY` in .env. `GIPHY_RATING` defaults to `pg`.

After pulling this, run `php artisan migrate`.

## Status and do not disturb

Open the avatar menu and choose **Set a status**. From there you can:

- **Set your availability:** Automatic, Away, Busy or Do not disturb, either until you change it or for a set time.
- **Set a status message:** an emoji and a short text, with presets such as "On leave", which can clear itself.

On Automatic, the portal works out your availability for you: On a call (in a Connect call), In a meeting (a meeting on your calendar is happening now), Online, Away (seen in the last 15 minutes) or Offline.

Do not disturb silences the portal's sounds and skips phone push notifications. Notifications still arrive in the bell.

Status appears on your avatar, in direct chats, in the Connect list and on the Workspace "Online now" card. The logic lives in `App\Services\Presence\Presence`. The app uses `GET` and `POST /api/v1/me/status`.

After pulling this, run `php artisan migrate`.

## Send later and reminders

- **Send later:** the clock button next to Send schedules the message you've written. You can pick a preset time (in 20 minutes, in 1 hour, in 3 hours, tomorrow at 9:00, next Monday at 9:00) or choose your own date and time. The message is sent from you at that time.
- **Remind me about this:** available on any message, and it arrives as a notification in the bell (reminders are never muted).
- **Later page:** `/connect/later` (the alarm button in Connect) lists your reminders and scheduled messages. You can mark reminders done, snooze or delete them, and send, reschedule or cancel scheduled messages.
- **What does the sending:** the `connect:later` command, every minute, needs the scheduler cron. If a scheduled message can't be sent (for example, you left the chat), it says why and notifies you.

After pulling this, run `php artisan migrate`.

## Huddles

The headphones button in a group or channel starts a huddle: a drop-in voice room that doesn't ring or notify anyone. The chat shows a bar with who is in it and a Join button. The conversation list shows a headphones icon while a huddle is on.

The huddle opens in a small window docked on the Connect page (the call page in `?embed=1` mode). You can make it bigger, pop it out into its own tab, or leave. People can still turn on a camera or share their screen.

A huddle ends when the last person leaves, and "Huddle ended · duration" appears in the chat. Under the hood it is an ordinary Connect call with `is_huddle` set, so the same WebRTC mesh and limits apply.

After pulling this, run `php artisan migrate`.

## Notes and files in a chat

Every chat has **Notes** and **Files** tabs, opened with the note button in the chat header.

**Notes** are shared documents that anyone in the chat can edit: agendas, decisions, how-tos and checklists. To start one, use the Meeting notes, Checklist or How-to template.
- The editor uses Markdown, with a toolbar, a preview and side-by-side view. It saves as you type and shows who else has the note open.
- If two people save at the same moment, the second person is asked whether to load the other version or keep theirs. Nothing is overwritten silently.
- Every save is kept in the version history, and any version can be restored.
- Notes are shown as safe HTML: raw HTML and `javascript:` links are stripped.
- Only the author or a chat admin can delete a note.

**Files** lists everything sent in the chat.

After pulling this, run `php artisan migrate`.

## Clients in chat (guest access)

Clients can talk to the team in Connect without seeing the rest of it. The
Client role has the **Chat as a guest** permission (added by the migration
and the seeders); staff keep **Use connect**.

- A guest sees only chats they were added to that are **shared with clients**.
  Channels, new groups, new channels, adding people and Drive are hidden and
  refused for them.
- A guest can start a direct chat only with someone they already share a
  chat with, and never with another client. Staff can message any client.
- On a project with a client, **Client chat** (project page) opens a group
  with the project team and the client. Once it exists the client sees
  **Chat with the team** on the same page.
- A group admin can turn on **Share with clients** in People, then add
  clients from the Clients list. Sharing cannot be turned off while clients
  are still in the group.
- Shared chats show an **External** tag, a banner for staff, and a
  **Client** tag on clients' messages. The API marks them with
  `shared_with_clients`.

## Call recording, transcript and notes

Any call, huddle or meeting call can be recorded and transcribed from the
call page.

- **Record** (the red button): the browser of the person recording mixes
  everyone's sound and, for video calls, draws the tiles onto a 1280x720
  picture. It uploads a piece every 4 seconds to
  `storage/app/private/calls/{call}/`, so a closed tab loses only a few
  seconds. Everyone sees a **Recording** badge; the chat says who started
  and stopped it and then shows the recording. One recording at a time
  per call. `QUDROID_RECORDING_MAX_MB` caps one recording (default 1024).
- **Transcript** (the CC button): when it is on, each person's browser
  writes down their own speech (Chrome, Edge and Safari can; Firefox
  cannot), so every line has the right name. Lines show as live captions.
  Set the language with `QUDROID_TRANSCRIPT_LANG` (default `en-NG`).
  Chrome sends the audio to Google's speech service to do this.
- **Recap** (`/connect/calls/{id}/recap`, also linked from the call
  history, the end-of-call screen and a meeting's page): play or download
  recordings, search and download the transcript, see who came, and
  **Write notes**, which opens a shared chat note with the date, people,
  sections for decisions and action items, and the transcript.
- Recordings can be deleted by whoever recorded them or a chat admin.
  Tell people before recording; the confirm prompt and the badge are
  there so nobody is recorded without knowing.

## Instant updates (Reverb)

Out of the box every page polls, so chat, the bell and calls all work with no extra server. With
Laravel Reverb running, they update the moment something happens (about a third of a second in
testing) and the polling slows to a 15 second safety net.

1. `composer install` (adds `laravel/reverb`).
2. In `.env` set `BROADCAST_CONNECTION=reverb` and fill `REVERB_APP_ID`, `REVERB_APP_KEY` and
   `REVERB_APP_SECRET` with any random values (for example `php -r "echo bin2hex(random_bytes(16));"`),
   plus `REVERB_HOST`, `REVERB_PORT` and `REVERB_SCHEME` as the browser should reach the server.
   On XAMPP: `REVERB_HOST=localhost`, `REVERB_PORT=8080`, `REVERB_SCHEME=http`.
3. `php artisan config:clear`, then keep `php artisan reverb:start` running (Supervisor or a
   service in production; behind HTTPS, proxy `wss://` to it and set `REVERB_SCHEME=https`).

If Reverb stops, nothing breaks: the pages notice the socket is gone and go back to normal polling.

Only small "something changed" signals go over the socket, never message text. The browser then
fetches through the usual endpoints, which check access as before. The private channels are
`user.{id}` (inbox, notification, call, signal), `connect.c.{id}` (message, changed, typing,
read) and `call.{id}` (people). Who may listen is in `routes/channels.php`. The mobile app gets
the settings from `GET /api/v1/realtime` and signs channels at `POST /api/v1/broadcasting/auth`.

## Workflows (automation)

Workflows → New workflow (permission "Manage workflows"; the Super Administrator has it). A workflow is:

- **When**: one of 25 triggers, such as a task added, moved or overdue, a deal won or lost, an invoice raised,
  paid or overdue, a payment confirmed, an expense submitted, a course application, a new employee, a leave
  request, low stock, a meeting scheduled, a message in a public channel (never direct messages), or a schedule
  (daily, weekly, monthly).
- **Only if** (optional): rules on the record's details, such as "Priority is Urgent".
- **Then**: steps in order: notification (bell and phone), e-mail, post in a channel or group (as whoever built the
  workflow), create a task, call a webhook (JSON POST signed with `X-Qudroid-Signature: sha256=HMAC` when a
  secret is set; public addresses only), wait, and "only continue if".

Text fields take `{{task.title}}`-style fields from the record ("Insert field" lists them). After a Wait, later steps
see the record as it is then, so "wait 3 days, then only if the invoice is not paid" works. **Try it** shows what
each step would do with the newest real record without sending anything. Ten ready-made workflows are on the
Workflows page. Each run is logged under Runs; a failed run can be tried again.

Steps run just after the page responds, so a slow webhook never slows anyone down. The scheduler's
`workflows:tick` (every minute) resumes waits, checks for overdue tasks and invoices (each counts once per due
date) and starts scheduled workflows (within two hours of their time). Workflows that set off other workflows stop
after three levels and never loop back into themselves. E-mail uses the normal `MAIL_*` settings.

## Qmail (e-mail)

Gmail-style mail inside the portal at `/mail`: conversations, stars, labels (the
server's other folders), archive, spam and bin, snooze, search (`from:`, `to:`,
`subject:`, `has:attachment`, `is:unread`, `is:starred`, `in:anywhere`), drafts
that save as you type, scheduled send, reply / reply all / forward, attachments,
address suggestions, signatures, Gmail's keyboard shortcuts, and new-mail
notifications. Mail stays on the mail server (IMAP); the portal keeps a synced
copy for speed and sends through the account's SMTP server.

- **Mail server.** Any IMAP/SMTP server works. For company addresses we recommend
  hosting [Stalwart](https://stalw.art) on your domain (set its MX, SPF, DKIM and
  DMARC records). Put the server and an API key under Settings → Mail; admins
  with "Manage mail" then create addresses and shared inboxes (support@, sales@)
  under Qmail → Mailboxes and choose who uses each one.
- **Own mailboxes.** Anyone with "Use mail" can connect a mailbox they already
  have (Gmail, Outlook, Zoho, Yahoo with an app password, or any IMAP server).
  Passwords are stored encrypted and the details are tested before saving.
- **Background work.** `mail:sync` runs every minute from the scheduler: it fetches
  new mail, brings snoozed conversations back and sends scheduled messages (a
  failure goes back to Drafts with the reason, and the writer is notified).
  Open pages also check every 30 seconds, or instantly with Reverb.
- **Safety.** Incoming HTML is cleaned (no scripts, forms or frames) and shown in a
  sandboxed frame whose Content-Security-Policy blocks remote images until you
  choose "Show images".

Needs the PHP `zip` and `fileinfo` extensions (for `webklex/php-imap`). Code:
`routes/modules/mail.php`, `routes/api_v1/mail.php`, `app/{Models,Services}/Mail`,
`app/Http/Controllers/Mail`, `resources/views/modules/mail`.

## QR check-in (attendance)

One check-in system for classes, staff days, meetings and conferences, at `/checkin`.

- **Two ways to check in.** A door QR on a screen or projector changes every 20
  seconds, so a photo of it can't be shared; people scan it with their phone and
  are checked in as themselves (signing in first if needed). Or a host scans a
  person's QR with the scanner page: staff and students have a personal badge
  QR (My QR), and guests get a ticket QR by e-mail.
- **Students.** "QR check-in" on a class register opens the door QR for that
  session; check-ins go straight onto the academy register (late after the
  late window) and closing marks the rest absent.
- **Staff.** HR adds office locations (hours, grace minutes, workdays, optional
  location check). Each location has a daily door screen; the first scan clocks
  in (late after the grace period), a later scan clocks out. Check-in → Staff
  gives the monthly sheet and a CSV.
- **Meetings.** "QR check-in" on a meeting lets invited people check in.
- **Events and conferences.** Create an event with an optional public sign-up
  page (`/e/{slug}`), capacity and closing date. Sign-ups get a ticket by e-mail;
  hosts scan tickets at the door and see the head count live. Guests can also be
  added by hand, cancelled or sent their ticket again.
- **Location.** Any event or office can require people to be within a radius of
  the venue when they use the door QR (the phone's location is checked).
- Hosts can also mark people by hand and undo; every event exports to CSV.

Permissions: "Check in" (everyone), "Run check-in" (organisers),
"Manage check-in places" (HR). Code: `routes/modules/checkin.php`,
`app/Services/Checkin`, `app/Http/Controllers/Checkin`, `resources/views/modules/checkin`.

## Apple-style look

Every page wears a calmer, Apple-like skin: `public/css/qd-apple.css`, loaded after `cb-ui.css` and switched on by `body.qd-apple` in `layouts/master.blade.php`. It only restyles; no page markup changed.

- Inter type, large titles, borderless cards, a frosted top bar, a translucent sidebar, pill buttons, iOS switches and segmented tabs, glass dropdowns and modals, full dark mode.
- The tint comes from `QUDROID_PRIMARY_COLOR` (config `qudroid.primary_color`, default `#405189`). Colours are tokens (`--qd-*`) at the top of the file; the mobile app uses the same values.
- To compare with the old look, remove `qd-apple` from the `<body>` class.

## Notification centre

The bell opens a slide-over notification centre (`layouts/partials/notification-bell.blade.php`); `/notifications` is the full page with settings.

- **Buttons on notifications.** Approve or reject expenses, approve or decline leave, answer meeting invites, reply to messages, without leaving the page. They run through the normal services (`NotificationActions`), so the same permission checks apply. A used notification shows what was done.
- **Stacks and filters.** Items from one conversation, task or approval stack together (`thread`). Filters: All, Unread, Mentions, Approvals, or a kind.
- **Banners and sounds** for new items, except "passive" ones (files, stock, system).
- **Quiet hours** per person: no banners, sounds or phone pushes, except calls, approvals, leave and meetings.
- **Daily summary** e-mail at 07:00 (`notifications:digest`, scheduled in `routes/console.php`) for people who switch it on and have unread items. The scheduler must be running (`php artisan schedule:work`, or cron on a server).
- **Phone pushes** carry the unread badge, a category, a thread for stacking, the buttons, and the notification id. Android channels: messages, calls, meetings, approvals, tasks, general.
- Senders pass buttons with `PortalNotifier::toUsers(..., $extra)`: `['target' => ['kind' => 'expense', 'id' => 8], 'actions' => NotificationActions::for('expense')]`.

The app API for all of this (list, stacks, buttons, settings) is under `/api/v1/notifications`.

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
Home and status: `GET home`, `GET search?q=`, `GET|PUT me/status`.

Chat (`connect/…`): `GET conversations`, `POST conversations` (group or
channel), `GET|POST conversations/{id}/members`,
`DELETE conversations/{id}/members/{user}` (yourself = leave),
`POST conversations/{id}/mute|typing|read`, `GET|POST conversations/{id}/messages`
(`kind=poll|gif`, `parent_id`, `files[]`),
`GET conversations/{id}/messages/{message}/thread`, `PUT|DELETE messages/{id}`,
`POST messages/{id}/react|vote|close-poll|pin|save`, `GET messages/{id}/file`,
`GET saved`, `GET search?q=`, `GET gifs?q=`, `GET people`, `POST direct`, and
`GET poll?since=` (new and changed messages, typing, presence and unread for
every chat in one call; pass back the `cursor` it returns).

Calls (`calls/…`): `GET ice-servers`, `GET ringing`, `POST /` (`conversation_id`
or `meeting_id`, `video`, `huddle`), `GET {id}`, `POST {id}/join|leave|decline`,
`POST {id}/signal` (`to_user_id`, `type`, `payload`) and `GET {id}/signals?since=`
(the once-a-second check-in). Incoming calls are a data-only, high-priority
push `{type: "call", call_id, conversation_id, from, from_id, avatar, video,
group}` that expires after a minute, then `{type: "call_end", call_id}`.
iPhones get a time-sensitive alert; ringing a closed iPhone app needs a VoIP
(PushKit) certificate, which is not set up yet.

Meetings: `GET meetings`, `GET meetings/calendar`, `GET meetings/people`,
`POST meetings`, `GET|PUT|DELETE meetings/{id}` (DELETE cancels),
`POST meetings/{id}/respond`. A meeting's `join` gives its chat and live call.

Mail (`mail/…`): `GET accounts` (with unread counts and labels), then per mailbox
`{account}/`: `GET threads?view=&q=&page=`, `GET threads/{key}` (cleaned
`body_html`, `has_remote_images`, attachments), `POST act` (`action`: read,
unread, star, unstar, archive, trash, spam, inbox, delete, snooze with `until`,
unsnooze, `move:{label id}`; `threads[]` or `messages[]`), `GET attachments/{id}`,
`GET compose?mode=reply|reply_all|forward&message=` or `?draft=`,
`GET|POST drafts`, `DELETE drafts/{id}`, `POST send` (`send_at` schedules),
`GET contacts?q=`, `GET poll`, `POST sync`. New mail also arrives as a
Reverb `mail` event on `user.{id}`.

Check-in (`checkin/…`): `GET me` (my badge `payload` to draw as a QR, plus an
`svg`; `today` lists what I can check in to and `running` what I can scan for),
`GET history`, `POST door` (`url` = the scanned door QR link, optional `lat`,
`lng`; `code` is `expired`, `location` or `refused` on failure),
`GET events/{id}` (stats and recent check-ins) and `POST events/{id}/scan`
(`code` = a scanned badge or ticket QR, or a typed ticket code).

Drive (`drive/…`): `GET /?folder_id=|project_id=|q=`, `GET projects`,
`GET shared-with-me|recent|starred|trash|people`, `POST folders`,
`POST files` (multipart `file` or `files[]`), `GET files/{id}`,
`GET files/{id}/download` (`?version=`, `?inline=1`),
`PUT|DELETE items/{type}/{id}` (rename/move; DELETE trashes, `?forever=1`
from the trash), `POST items/{type}/{id}/restore|star`,
`POST|DELETE items/{type}/{id}/share` and `POST|DELETE items/{type}/{id}/link`.

Notifications: `GET notifications?filter=&stacked=1`, `POST notifications/{id}/actions/{action}`
(Approve, Reply, Going…; `input` for words), `POST notifications/{id}/read` (`ids[]`, `unread`),
`DELETE notifications/{id}` (`ids[]`), `POST notifications/clear-read`, `GET|PUT notifications/settings`.

Bills (`billing/…`): `GET invoices?status=`, `GET invoices/{id}` (and `/pdf`), `POST invoices/{id}/pay`
(`amount` in kobo; returns the Paystack page), `POST payments/verify` (`reference`; asks Paystack, safe to
repeat), `GET payments`, `GET payments/{id}/receipt`.

HR and approvals: `GET hr/me`, `GET hr/leave`, `GET hr/leave/preview`, `POST hr/leave`,
`POST hr/leave/{id}/cancel`, `GET hr/payslips` (and `/{id}`, `/{id}/pdf`), `GET hr/assets`,
`GET expenses/mine`, `POST expenses` (finance staff), `GET approvals?type=`,
`POST approvals/{leave|expense}/{id}/{approve|decline}` (`note`).

Projects: `GET projects`, `GET projects/{id}`, `GET|POST projects/{id}/tasks`, `GET tasks/mine?scope=`,
`GET|PUT tasks/{id}`, `POST tasks/{id}/move|comments|time`, `GET task-comments/{id}/file`.

Academy (`academy/…`): `GET my`, `GET enrollments/{id}`, `GET lessons/{id}` (and `/file`),
`POST lessons/{id}/complete`, `GET assessments/{id}`, `POST assessments/{id}/attempts` (start or resume),
`GET|PUT attempts/{id}` (PUT autosaves), `POST attempts/{id}/submit`, `POST assessments/{id}/hand-in`,
`GET certificates`, `GET courses`, `GET courses/{id}`.

CRM (`crm/…`): `GET|POST companies`, `GET companies/{id}`, `GET|POST contacts`, `GET contacts/{id}`,
`GET deals` (board with stage totals), `GET deals/{id}`, `POST deals/{id}/move` (`stage`, `position`,
`lost_reason`), `GET|POST activities`, `POST activities/{id}/done`.

Full request and response shapes for the app are in the project's `mobile-app/phase3-api.md` and
`mobile-app/notifications-api.md`, and in the `tests/Feature/Api*Test.php` files.

Instant updates: `GET realtime` gives `enabled` and, when on, the Pusher-protocol `key`, `host`,
`port`, `tls`, `auth_url` and channel names; sign private channels at `POST broadcasting/auth`
(see "Instant updates (Reverb)"). Keep polling when `enabled` is false or the socket drops.

Every response: `{ "success": true, "message": "...", "data": ..., "meta": {...} }`.
