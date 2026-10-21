# Mobile API v1 — reference

Base URL: `<school portal>/api/v1`, e.g. `https://portal.csskabba.ng/api/v1` (locally: `http://<your-mac-ip>/csskabba-portal/public/api/v1`).

**One app, many schools.** The Vite Schools app asks for the school's portal address on first launch, checks it with
`GET /school` (which must answer with `name` and `api_version`), and then talks to that school only. Every school
runs this same API on its own portal.

Code: `routes/api_v1.php`, controllers in `app/Http/Controllers/Api/V1/`, helpers in `app/Services/Api/`.

## Basics

**Auth.** Laravel Sanctum bearer tokens. Sign in with `POST /auth/login`, then send
`Authorization: Bearer <token>` and `Accept: application/json` on every request.
Each phone gets its own token; signing out on one phone does not sign out the others.

**Envelope.** Every JSON response has the same shape:

```json
{ "success": true,  "message": "optional", "data": { }, "meta": { } }
{ "success": false, "message": "Shown to the user", "code": "machine_reason", "errors": { "field": ["…"] } }
```

**Error codes** (`code`): `validation_failed`, `invalid_credentials`, `unauthenticated` (401 — sign in again),
`account_disabled`, `password_change_required` (403 — only `/auth/me`, `/auth/password`, `/auth/logout` work until
the temporary password is changed), `forbidden`, `not_found`, `module_disabled` (the school switched that module
off in Feature Flags), `result_on_hold`, `too_many_requests`, `server_error`.

**Same rules as the portal.** The API calls the portal's own services, so figures match the web pages:
fee statements (`StudentFeeStatementService`), the result hold for unpaid fees (`ResultAccessService`),
score totals / grades / cum / cum_ave / positions (`MyScoreSheetController`), attendance backdating
(`AttendanceController`), leave rules (`LeaveService`, `StudentLeaveService`), online payment
(`OnlineFeeCheckoutService`) and the management dashboard (`ManagementDashboardService`).

**Rate limits.** Login 10/min per IP, forgot-password 5 per 10 min, reset 10 per 10 min,
signed-in requests 120/min per user, checkout 10/min.

## Public

| Method | Path | Notes |
|---|---|---|
| GET | `/school` | Name, motto, logo, phones, current session/term, all sessions and terms. |
| POST | `/auth/login` | `login` (e-mail **or** phone — parents use their phone), `password`, optional `device_name`, `platform` (android/ios), `app_version`, `push_token`. Returns `token` and `user`. |
| POST | `/auth/forgot-password` | Parents. `phone`. Texts a 6-digit code (same flow as the web). Always answers the same way. |
| POST | `/auth/reset-password` | `phone`, `code`, `password`, `password_confirmation`. Returns a new `token` and `user`. |
| GET | `/payments/callback` | Where Paystack/OPay return the payer. Shows a small page; the app closes its WebView when it sees this URL. |

## Signed in — everyone

| Method | Path | Notes |
|---|---|---|
| GET | `/auth/me` | The user: `faces` (`parent`, `student`, `staff`, `admin`), `default_face`, `abilities`, `modules`, `student_id`, `staff_id`, `must_change_password`, `unread_notifications`. The app builds its menus from this. |
| PUT | `/auth/password` | `current_password` (not needed for a temporary password), `password`, `password_confirmation`. Signs out the user's other phones. |
| POST | `/auth/logout` | Revokes this phone's token. |
| POST | `/auth/logout-all` | Revokes every token. |
| POST / DELETE | `/devices` | Save / remove the phone's push token (`push_token`, `platform`, `device_name`, `app_version`). Stored in `mobile_devices` for push notifications later. |
| GET | `/notifications` | Paged (`page`, `per_page`, `filter=unread`). `meta.unread`. |
| GET | `/notifications/unread-count` | |
| POST | `/notifications/{id}/read` · `/notifications/read-all` | |
| GET | `/calendar?from=YYYY-MM-DD&to=YYYY-MM-DD` | Events the user's audience may see, plus holidays (`holidays=0` to hide). |
| GET / PUT | `/me/preferences` | `push_enabled`, `lesson_reminders`, `reminder_minutes` (1–60). Also returns `push_available` (the school has set up Firebase). |

### `abilities` keys

`scores.enter`, `attendance.mark`, `management.dashboard`, `students.view`, `leave.apply`, `leave.approve`,
`student_leave.apply`, `student_leave.approve`, `payroll.self`, `notices.view`, `results.board`, `reports.submit`,
`reports.approve`, `timetable.reminders`.

## Parent / student

A parent may open their linked children; a student only themselves; staff with *View student* may open anyone
(read only — paying and leave requests are for the parent or student).

| Method | Path | Notes |
|---|---|---|
| GET | `/students` | My children (parent) and/or myself (student), each with `summary` (fee balance, attendance rate, days absent, latest result). |
| GET | `/students/{id}` | One student, with a short `profile`. |
| GET | `/students/{id}/results?term_id=&session_id=` | `terms` (each with `allowed`, `hold_message`, `amount_owed`), `selected`, and `result` for the selected term: subjects with CA breakdown, total, cum average, grade, position, class average; comments; `report_card_url`. Module: `results`. |
| GET | `/students/{id}/report-card/{session}/{term}/{class}` | PDF (same renderer as the portal). 403 `result_on_hold` when held. |
| GET | `/students/{id}/fees?term_id=&session_id=` | Statement: totals, arrears, bills (discounts, paid, balance, status), payments. Module: `finance`. |
| GET | `/students/{id}/payment-quote?term_id=&session_id=` | Bills that can be paid online now (`current` + `arrears`, each with a `key`), gateways, minimum. Module: `online_payments`. |
| POST | `/payments/checkout` | `student_id`, `term_id`, `session_id`, `items: { "<key>": amount_in_naira }`, optional `gateway`. Returns `authorization_url` and `reference`. |
| GET | `/payments/{reference}` | Status; re-checks the gateway while pending. |
| GET | `/students/{id}/payments` | Online payment history (paged). |
| GET | `/students/{id}/attendance?term_id=&session_id=` | Summary + day-by-day marks. |
| GET | `/students/{id}/timetable` | Published class timetable: `days`, `periods`, `slots`. Module: `timetable`. |
| GET | `/students/{id}/teachers` | `class_teachers` and `subject_teachers` (name, photo, subjects, `phone`, `email`). Contacts are hidden when `MOBILE_SHARE_TEACHER_CONTACTS=false`. |
| GET | `/students/{id}/performance` | Term-by-term `average` and `class_average`, per-subject `scores` across terms with `trend`, `summary` (latest, change, strongest, needs attention). Held results are left out (`hidden_terms`). Module: `results`. |
| GET | `/students/{id}/learning` | E-learning: enrolled `courses` (progress, quiz average) and `assignments` with `state` = pending / overdue / submitted / graded, score and feedback. |
| GET | `/students/{id}/payment-statement?term_id=&session_id=` | Payment statement PDF (same layout as the portal receipt). Module: `finance`. |
| GET | `/student-leave` | My students' leave requests; `meta.reasons`. |
| POST | `/student-leave` | `student_id`, `reason_type`, `reason`, `start_date`, `end_date`, optional `contact_phone`, `attachment` (multipart). |
| POST | `/student-leave/{id}/cancel` | Withdraw while still pending. |

## Staff

| Method | Path | Notes |
|---|---|---|
| GET | `/staff/home` | Current period, today's lessons, `next_lesson` (with `starts_in_minutes` / `in_progress`), subject / class counts, pending leave. |
| GET | `/staff/timetable` | The teacher's week across all classes. |
| GET | `/staff/subjects?session_id=&term_id=` | Subject assignments with score-entry progress (same counts as *My Subjects*). `meta.periods` lists every session (newest first, `current` flag) and its terms the teacher has taught in — for opening past score sheets / results. |
| GET | `/staff/scoresheets/{subjectclass}?term_id=&session_id=[&recalculate=1]` | Assessments (with sub-assessments) and one row per student: scores by assessment id, total, cum, grade, position, lock state. Only the assigned teacher. |
| POST | `/staff/scoresheets/score` | One score: `broadsheet_id`, `assessment_id`, `score` (+ `is_sub`, `sub_assessment_id`). Returns new total, grade, positions, GPA. |
| POST | `/staff/scoresheets/{subjectclass}/bulk` | `term_id`, `session_id`, `scores: [{ id: broadsheet_id, assessments: { "<assessment_id>": score } }]`. Returns the refreshed sheet. 423-style `locked` failures when the sheet is locked. **Parts of an assessment** (e.g. Exam → Theory / Objective): add `is_sub: true`, `assessment_id: <parent>`, and key the scores by sub-assessment id; the parent score is worked out and scaled as on the portal. |
| GET | `/staff/scoresheets/{subjectclass}/excel?term_id=&session_id=` | The protected Excel template the portal's *Download* button builds (xlsx). |
| POST | `/staff/scoresheets/{subjectclass}/excel` | Multipart `file` (xlsx/xls ≤ 10 MB), `term_id`, `session_id`. Imports with the portal's importer, recalculates totals and positions, and returns the refreshed sheet; `meta.imported` and `meta.warnings` ([{row, errors}]). 423 `locked` when the sheet is locked; 422 `import_failed` with `errors.rows` when nothing could be imported. |
| GET | `/staff/scoresheets/{subjectclass}/marksheet?term_id=&session_id=` | Printable marks sheet (PDF). |
| GET | `/staff/classes` | Classes the user is class teacher of. |
| GET | `/staff/attendance/register?class_id&term_id&session_id&date&period` | Students with today's marks, whether the date can be edited (backdating rules), holiday flag. |
| POST | `/staff/attendance` | `schoolclass_id`, `term_id`, `session_id`, `attendance_date`, `period`, `records: [{student_id, status, notes?}]`. Status: `present`, `absent`, `late`, `excused`, `sick_leave`. |
| POST | `/staff/attendance/mark-all-present` | Same keys + `student_ids`. |
| GET | `/staff/leave` | Balances for the year, my requests, colleagues (relief staff). |
| POST | `/staff/leave` | `leave_type_id`, `start_date`, `end_date`, `reason`, optional `half_day`, `relief_staff_id`, `contact_phone`, `attachment`, `handover_note` (multipart when attaching). |
| POST | `/staff/leave/{id}/cancel` | |
| GET | `/staff/payslips` | Published payslips + year-to-date. Module: `payroll`. |
| GET | `/staff/payslips/{run}` · `/staff/payslips/{run}/pdf` | Detail with lines · PDF. Own, published only. |

## Broadsheets and report-card approval

Class teachers see their own classes; *View report-approvals*, *Approve report cards* or *View student-report* see all.
Module: `results`.

| Method | Path | Notes |
|---|---|---|
| GET | `/results/board?term_id=&session_id=` | Every class with results: students, subjects, % vetted, comment counts, approval `status` (`pending`, `submitted`, `returned`, `approved`). `meta.can_approve`, `meta.enforced`. |
| GET | `/results/classes/{class}/broadsheet?term_id=&session_id=&basis=cum_ave\|total` | Subjects, and one row per student: per-subject total / cum average / grade, term and cumulative averages, GPA and class position (ties share a position). Same maths as the web broadsheet (`ClassResultsLoader`). |
| GET | `/results/classes/{class}/approval?term_id=&session_id=` | The approval record (created if missing): status, notes, readiness, history, students with comment status, `can_submit`, `can_approve`. |
| POST | `/results/approvals/{id}/submit` | Class teacher (*Submit report cards*). `note`, `force` (submit with unvetted scores). Notifies the principal. |
| POST | `/results/approvals/{id}/approve` · `/return` · `/reopen` | *Approve report cards*. `note` (required to return). Approving notifies students/parents when that setting is on. |
| POST | `/results/approve-all` | `term_id`, `session_id`: approve every submitted class. |
| GET | `/results/approvals/{id}/preview/{student}` | Report card PDF before release. |

## Push notifications and lesson reminders

* Every in-portal notification (the bell — `PortalNotifier`) is also pushed to the user's phones through Firebase
  Cloud Messaging, after the page response. So are Laravel `database` notifications.
* `php artisan timetable:lesson-reminders` (scheduled every minute in `routes/console.php`) pushes "Mathematics — JSS 1 A
  in 10 min" to each teacher before their lessons, from the published timetable of the current session. A substitute
  assigned for the day gets it instead. Skipped on school holidays. Teachers can turn it off or change the lead time
  in the app; the school default is `TIMETABLE_REMINDER_MINUTES` (10). `--dry-run` lists what would be sent.
* Publishing or sending a timetable (`TimetableController` notifications) now also reaches teachers in the app.
* Dead device tokens are removed automatically.

**Server setup (once per school portal):** put the Vite Schools Firebase service-account JSON at
`storage/app/fcm-service-account.json` (or set `FCM_CREDENTIALS`), and make sure the scheduler cron is running.
Optional `.env`: `FCM_ENABLED=false` to switch pushes off, `FCM_PROJECT_ID`, `TIMETABLE_REMINDER_MINUTES`.

## Admin / management

| Method | Path | Needs |
|---|---|---|
| GET | `/admin/dashboard?term_id=&session_id=[&refresh=1]` | *View management dashboard*. Enrolment, today's attendance, fees (`null` + `fees_ready:false` while it is being worked out — call again), 14-day collections, results/approvals, messaging, action items. |
| GET | `/admin/students/search?q=` | *View student*. Name or admission number. Open the result with `/students/{id}/…`. |
| GET | `/admin/leave/pending` · POST `/admin/leave/{id}/decide` | *Recommend leave* / *Approve leave*. Body: `decision` (`yes`/`no`), `note` (required to decline). |
| GET | `/admin/student-leave/pending` · POST `/admin/student-leave/{id}/decide` | *Recommend student leave* (class teacher) / *Approve student leave*. |

## Score sheets: totals and safe saving

* `GET /staff/scoresheets/{subjectclass}` returns `total_formula`: `sum` (CSS Kabba — assessments are added) or
  `avg_ca_exam` (Topclass — `((CA1 + CA2 + CA3) / 3 + Exam) / 2`, each out of 100). The app's live total preview uses it.
* Rows whose new assessment scores are all empty fall back to the legacy `ca1`–`ca3` / `exam` columns, so old scores
  show in the entry boxes instead of 0.
* **Conflict protection.** Each bulk score row and each attendance record may carry `base` — the value the phone last
  saw. If someone else has changed that cell on the server since, the server keeps their value, skips yours and
  lists it in `meta.conflicts` (`[{broadsheet_id, key, is_sub, assessment_id, yours, server}]` for scores,
  `[{student_id, yours, server}]` for attendance). Everything else in the request is saved.

## AI learning & career guidance

| Method | Path | Notes |
|---|---|---|
| GET | `/students/{id}/guidance?term_id=&session_id=` | The saved report for that term (made on first request). Module: `results`. Held results are never used. |
| POST | `/students/{id}/guidance/refresh` | Rebuild from the latest scores. Limited per term (`refreshes_per_term`, default 3) and 6/min. |

Report keys: `summary`, `strengths`, `areas_to_improve`, `learning_model {style, description, tips}`,
`study_plan [{period, focus, actions}]`, `reading_plan [{focus, what, how_often, why}]`,
`cognitive_profile [{trait, level, evidence}]`, `passions`, `vocational_interests [{field, why}]`,
`higher_education [{discipline, fit, why, key_subjects}]`, `parent_tips`, `disclaimer`. `meta`: `provider`,
`generated_at`, `refreshes_left`.

**Admin → AI Learning Guidance** (permission *Manage AI guidance*) chooses the engine: *Built-in rules* (free,
offline, no data leaves the server), Anthropic Claude, OpenAI or Google Gemini (API key stored encrypted;
"Save & test connection"), whether parents and/or students see it, refresh limit, and a short school context.
**Privacy:** the AI receives only class level, subject scores per term, attendance rate, teacher behaviour ratings
and e-learning stats — never the student's name, admission number, photo or parents' details. If the AI call fails,
the built-in rules answer instead.

## Offline use (app)

The app caches every screen it loads (per school and per user) and shows a "You're offline — showing data from …"
bar. Changes made offline go into an outbox that sends itself when the connection returns (every 30 s, on app
resume, and from **Account → Sync**).

**Safe offline** — read anything already opened (results, fees, attendance, timetable, report-card list, notices,
calendar, AI guide, class lists, score sheets — the staff home pre-loads score sheets, timetable and classes);
**enter scores** and **mark attendance** for a register already opened online (queued; sent with `base`, so nothing overwrites a colleague's newer entry —
conflicts are shown after sync).

**Online only** (refused cleanly offline, nothing half-done): sign in / password changes, fee payments, Excel
upload / download and PDFs not yet downloaded, leave requests and approvals, report-card approval, AI guide refresh,
admin dashboard and student search.

## Two apps: Vite Schools and TopClass Schools

CSS Kabba and TopClass are different portals, so each has its own app. Both apps still ask for the school's
portal address, so any number of schools can use each one.

| | Vite Schools (`viteSchoolsApp v2`) | TopClass Schools (`StudioProjects/topclassApp`) |
|---|---|---|
| Portal | CSS Kabba-type | TopClass-type |
| `GET /school` → `score_model` | `dynamic` | `fixed` |
| App id | `com.qudroid.viteschools` | `com.qudroid.topclassschools` |

`score_model` is set in `config/services.php` (`mobile.score_model`). Each app refuses the other kind of portal and
tells the user which app to install. `GET /school` also sends `gpa` (true on `dynamic` portals).

### TopClass portal differences

* Scores: fixed CA1, CA2, CA3, Exam (assessment ids 1–4, each out of 100), `total_formula: avg_ca_exam`. Excel
  template and import are Topclass's own layout; marks sheet uses `resources/views/api/marksheet.blade.php`.
* No GPA anywhere (results, broadsheet, display settings).
* Results: no report-card approval workflow — `/results/board` sends `meta.approvals: false` (the app hides the
  approval tab) and the approval endpoints answer 404 `not_available`. Broadsheet uses Topclass's `ClassResultsLoader`.
* No unpaid-fees result hold (`App\Services\Api\ResultGate` allows every term); who may see results is set under
  Admin → Result display. The parent portal's Results page and report card now work on TopClass too.
* Attendance backdating: past dates allowed, future dates refused.
* Setup: `composer update laravel/sanctum`, then `php artisan migrate`.

## Result display (both portals)

**Admin → Result display** (permission *Manage result display*) decides what parents and students see on the
portal's Results page and in the apps. The printed report card keeps its own layout and column picker.

* Who: parents can view / students can view (staff always can). Off → terms answer `not_released`, and the
  report card, progress chart and AI guide are refused (403 `not_released`); `/auth/me` turns `modules.results` off.
* Per subject: CA & exam breakdown, total, BF, cum, cum average, grade, remark, subject position (class, by
  cumulative / by term total), arm position (by total / by cumulative), class average, highest & lowest.
* Overall: total obtained, term average, cumulative average, position in class (all arms), position in arm,
  number in class / arm, GPA (dynamic portals), comments, times absent, report card download.
* Overall positions rank every student with results in the class on the cumulative or the term average (setting);
  equal averages share a position.

`GET /students/{id}/results` → `result` now carries:

```json
"summary": { "term_total": 157, "term_average": 78.5, "cum_average": 78.5, "gpa": 4.5,
             "class_position": 1, "class_position_label": "1st", "class_size": 84,
             "arm_position": 1, "arm_position_label": "1st", "arm_size": 28, "basis": "cum" },
"display": { "subject": { "total": true, "position": true, … }, "summary": { "class_position": true, … },
             "position_basis": "cum", "score_model": "dynamic" }
```

Subjects also carry `position_label`, `position_total`, `arm_position`, `arm_position_cum` (ordinals). Anything the
school hides is left out of the response, not just hidden by the app. Code: `App\Services\Results\ResultDisplay`.

`GET /results/classes/{class}/broadsheet` (staff) now also sends per subject `cum` and `position` (subject position
on the chosen basis), and `class_size` and `gpa` (show GPA or not).

## School website (both portals)

Each portal has a public school website. Content is managed under **Admin → Website** (permission *Manage website*): settings (hero, intro,
colours, socials, map, admission open/closed), home slides, pages (About, Principal's welcome, Admission criteria /
policy, Curriculum, …), news / events / stories, gallery albums (multi-photo upload, auto-resized with thumbnails),
our people (can import from staff records), testimonials, FAQs, core values, highlights, partners, messages
(applications, contacts, visit bookings, newsletter — CSV export) and the mobile app page (Play / App Store links,
APK upload up to 200 MB, install steps). Class levels, subjects, staff and calendar events come straight from the
portal's own tables. Turning the website off in settings sends `/` to the login page.

**Domains.** The website runs on the school's main domain and the portal on its own, from the same Laravel app
(Admin → Website → Settings → Domains: *Website domain* e.g. `www.csskabba.ng`, *Portal address* e.g.
`https://portal.csskabba.ng`). `App\Http\Middleware\WebsiteDomain` then sends:

* `www.csskabba.ng` / `csskabba.ng` → the website; portal pages asked for there (`/login`, `/dashboard`, …) → the portal;
* `portal.csskabba.ng/` → the portal login, as before; website pages asked for there → the website domain;
* `/media/…` and `/app/download` work on both.

Until a website domain is saved, `/` opens the login page and the website is previewed at `/website-preview`.
Server: point the domain's DNS (`@` and `www`) at the server, add it to the hosting as an alias / parked domain
of the portal (same `public` folder), and install SSL for both names.

Public routes: `/` (on the website domain), `/website-preview`, `/pages/{slug}`, `/news`, `/news/{slug}`, `/gallery`, `/gallery/{slug}`, `/our-people`, `/academics`,
`/admissions` (+ online application), `/contact`, `/school-calendar`, `/app`, `/app/download`.

Setup: `php artisan migrate`, `php artisan storage:link` (uploads also work without it through `/media/...`), and for
APK uploads raise PHP `upload_max_filesize` / `post_max_size` to ~210M.

## Setup on a server

```bash
composer update laravel/sanctum        # adds laravel/sanctum (already in composer.json)
php artisan migrate                    # API, AI guidance, website and result-display tables
php artisan route:clear && php artisan config:clear
```

Optional `.env`: `SANCTUM_EXPIRATION=` minutes before a token expires (empty = never).

## Not in v1 yet

Submitting LMS assignments from the app, CBT, notices composer, finance back-office and payroll administration. These stay on the portal
for now.
