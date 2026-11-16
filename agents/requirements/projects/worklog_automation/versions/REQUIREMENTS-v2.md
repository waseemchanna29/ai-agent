# Requirements — worklog_automation
Version: 2 · Date: 2026-11-16 · Status: as-is baseline

## 1. Overview & Glossary

worklog_automation is a Laravel 12 web application for collecting daily work logs (timesheets)
from team members and forwarding each submission to an external n8n automation webhook. This
document describes the system **as it exists today**, reconstructed from the code. Every
functional requirement is tagged `[existing]`.

Glossary:

- **Project For** — the top-level bucket a project belongs to (a client or an internal area,
  e.g. "Avaib", "QC", "TradeSora"). Table `project_fors`.
- **Project** — a body of work under a "Project For" (e.g. "Renter Vibe"). Table `projects`.
- **Task** — a specific unit of work under a project (e.g. "Quick Task"). Table `tasks`.
- **Worklog / entry** — one row recording a user, date, project context, task, hours and
  optional details. Table `worklogs`.
- **UUID link** — the personal URL `/worklog/{uuid}` an employee uses to open their form.
- **n8n** — the external automation platform that receives each submission via webhook.

## 2. User Roles & Permissions

| Capability | Administrator | Employee (UUID link) | Guest |
|---|---|---|---|
| Log in to admin area | Yes | No (login rejected) | No |
| Open personal work-log form | Via UUID link | Via UUID link | No |
| Submit work logs | Yes (any) | Yes | No |
| Create a task inline from the form | Yes | Yes | No |
| Manage Users / Project Fors / Projects / Tasks | Yes | No | No |
| View dashboard & recent logs | Yes | No | No |

Roles are stored on `users.role` as an enum: `admin` or `employee` (default `employee`). The
`role` middleware alias enforces `admin`-only access to the dashboard and admin area. Login
(`AuthController::login`) forces `role = admin`, so only administrators can authenticate.

## 3. Functional Requirements

### FR-1 Open personal work-log form `[existing]`
As an employee, I open `/worklog/{uuid}` and see a form pre-filled with my name, email and
designation so I can log my work without a password.
- Acceptance: a valid `uuid` resolves to its user and renders the form; an unknown `uuid`
  returns HTTP 404. The full Project For → Project → Task tree is loaded for the cascading
  dropdowns. If the resolved user is an admin, a link to Login/Dashboard is shown.

### FR-2 Cascading selection of Project For → Project → Task `[existing]`
As a user, when I pick a "Project For" the Project dropdown shows only its projects, and when I
pick a Project the Task dropdown shows only its tasks.
- Acceptance: Project is disabled until a Project For is chosen; Task is disabled until a
  Project is chosen; changing a parent resets the child selections.

### FR-3 Submit one or more work-log entries `[existing]` `[changed in v2]`
As a user, I choose a date and project context, add one or more entries (task, hours, optional
details) and submit them together; the entries are always recorded against the user who owns the
UUID link I opened, not against any identity I supply.
- Acceptance (`POST /worklogs`, `StoreWorklogRequest`): `work_date`
  (date, not in the future), `project_for_id`, `project_id` (must belong to the chosen
  `project_for_id`) are required; `worklog_entries` is a non-empty array; each entry requires a
  `task_id` that belongs to the chosen project and `hours_spent` numeric between 0.25 and 24;
  `details` is optional, max 5000 characters. All entries are saved in a single DB transaction.
  Response is JSON `{message, count}` with HTTP 201. The endpoint is rate-limited to 30
  requests per minute.
- Submission identity (changed in v2 — see FR-12): the acting user is the owner of the UUID that
  loaded the form. The request must carry that UUID (or the UUID-scoped route); the server
  resolves `user_id` from it and **ignores any client-supplied `user_id`**. A request that
  attempts to log on behalf of a user other than the UUID owner is rejected (HTTP 403) or has the
  mismatched value ignored — it must never create rows for a different user.

### FR-4 Add a task inline `[existing]` `[changed in v2]`
As a user filling the form, I can add a new task to the current project with a "+ Add task"
button without leaving the page.
- Acceptance (`POST /tasks`, `quickStore`): requires `project_id` (exists) and `title` (string,
  max 255). The task is created with `firstOrCreate` on (`project_id`, trimmed `title`); an
  existing task is reused without changing its audit fields. Returns JSON
  `{id, project_id, title}` with HTTP 201. Rate-limited to 30 per minute.
- Submission identity (changed in v2 — see FR-12): the creator (`created_by`) is the UUID owner,
  resolved server-side from the UUID; any client-supplied `user_id` is ignored and must not be
  trusted to set the acting identity.

### FR-5 Forward each submission to n8n `[existing]`
As the business, every submitted work log is delivered to the company n8n webhook for
automation, reliably and in the background.
- Acceptance (`SendWorklogToN8n` job, dispatched `afterCommit`): posts a JSON payload
  containing the user (id, name, email, designation), `work_date`, `project_for`, `project`,
  the list of entries (task id/title, hours, details, timestamp) and `total_hours`. Sends
  header `X-Webhook-Secret` with the configured secret, 15-second timeout. On a non-2xx
  response or network error the job throws and retries up to 5 times with backoff
  (10, 30, 60, 300 seconds). If all referenced work logs were deleted before the job runs, it
  exits without sending.

### FR-6 Administrator login `[existing]`
As an administrator, I log in with email and password to reach the admin area.
- Acceptance (`GET /login`, `POST /login`): credentials are validated; authentication only
  succeeds for users whose `role = admin`; failed attempts return an error message
  ("Email ya password galat hai."); login is rate-limited to 5 attempts per minute; on success
  the session is regenerated and the user is redirected to `/dashboard`. `POST /logout`
  invalidates the session.

### FR-7 Admin dashboard `[existing]`
As an administrator, I see headline counts and recent work logs after logging in.
- Acceptance (`GET /dashboard`, admin only): shows counts of Users, Project Fors, Projects and
  total hours for the current month, plus the 10 most recent work logs (newest work_date
  first).

### FR-8 Manage Users `[existing]`
As an administrator, I create, list, search, edit and delete users.
- Acceptance (`admin.users.*`): list is paginated (15) and searchable by name/email; create
  and edit validate `name`, `email` (unique, lowercased), `designation`, `role` (enum), and
  `password` (min 8, required for an admin without an existing password, otherwise optional —
  leaving it blank keeps the current password). A new user automatically receives a UUID. An
  administrator cannot remove their own admin role, cannot delete their own account, and cannot
  delete a user who has work logs.

### FR-9 Manage "Project For" `[existing]`
As an administrator, I create, list, edit and delete "Project For" buckets.
- Acceptance (`admin.project-fors.*`): `name` is required and unique; list is paginated (15)
  with a count of child projects; a "Project For" referenced by any work log cannot be deleted;
  deleting one cascades to its projects and their tasks (database cascade).

### FR-10 Manage Projects `[existing]`
As an administrator, I create, list, filter, edit and delete projects.
- Acceptance (`admin.projects.*`): requires `project_for_id` (exists) and `name` (unique within
  that "Project For"); list is paginated (15), filterable by "Project For", with a task count; a
  project with work logs cannot be deleted and cannot be moved to a different "Project For";
  deleting a project cascades to its tasks.

### FR-11 Manage Tasks `[existing]`
As an administrator, I create, list, filter, search, edit and delete tasks.
- Acceptance (`admin.tasks.*`): requires `project_id` (exists) and `title` (unique within the
  project, trimmed); list is paginated (15), filterable by project and searchable by title,
  shows creator/updater and a worklog count; a task with work logs cannot be deleted and cannot
  be moved to a different project; create/update stamp `created_by` / `updated_by`.

### FR-12 Submission identity is bound to the UUID link `[new]`
As the business, I want the UUID link to be the sole, intentional identity for a public
submission, so that an employee's work logs and inline tasks are always recorded against the
user that UUID belongs to — and never against another user — even though no one logs in.
- **Design decision (owner, 2026-11-16):** the work-log form is deliberately public and
  password-free. There is no employee dashboard or login. Whoever holds a user's UUID link may
  submit work logs **for that user**. This is accepted and intended — it is not treated as a
  defect. The public UUID URL is permanent: it does not expire and is not rotated (see §9,
  item 2).
- Acceptance: on `POST /worklogs` and `POST /tasks`, the server determines the acting user
  **only** from the UUID associated with the request (the UUID that loaded the form / the
  UUID-scoped route). Any `user_id` in the request body is ignored for identity purposes.
- It must be impossible to create work logs or tasks on behalf of a user other than the UUID
  owner by supplying a different `user_id`. A deliberate attempt resolves to the UUID owner,
  or is rejected with HTTP 403; it must never write rows for the supplied victim user. This
  closes the IDOR raised in QA (BUG-1) while preserving the public, no-login design above.
- The UUID resolves to exactly one existing user; an unknown UUID yields HTTP 404 (as FR-1).

## 4. Non-Functional Requirements

- **Security / access:** admin area protected by session auth + `role:admin` middleware. Login
  throttled 5/min; public submission endpoints throttled 30/min. CSRF protection on all
  browser POSTs. Passwords hashed (bcrypt, 12 rounds). The n8n webhook is authenticated with a
  shared secret header. **Note:** the employee submission flow is not otherwise authenticated —
  see Known Issues (Section 9).
- **Reliability:** work logs are written in a DB transaction; webhook delivery is queued and
  retried, so a transient n8n outage does not lose data (requires a running queue worker).
- **Performance:** the form loads the whole Project For/Project/Task tree in one query set;
  list screens are paginated (15 per page); `worklogs(user_id, work_date)` and `users.role`
  are indexed.
- **Availability / hosting:** local/XAMPP-style deployment, MySQL database `harva_avaib`.
  Database-backed sessions, cache and queue.
- **Usability:** employee form is a single responsive page (Tailwind + Alpine.js via CDN) with
  inline validation messages, a running hours total, and multi-entry support.

## 5. Tech Stack

- PHP 8.2, Laravel 12, MySQL (XAMPP, database `harva_avaib`).
- Blade templates; Tailwind CSS and Alpine.js loaded from CDN on the public form; Vite present
  for the admin layout assets.
- Database drivers for session, cache and queue; queue worker required for webhook delivery.
- External integration: n8n cloud webhook (configured via `N8N_WEBHOOK_URL` and
  `N8N_WEBHOOK_SECRET`).
- Note: the owner's global rule prefers plain PHP + MySQL with no framework, but this existing
  codebase is built on Laravel; this baseline documents it as-is.

## 6. Data Model

**users** — id, name, email (unique), email_verified_at (nullable), password (nullable, hashed),
designation (nullable), uuid (unique, auto-generated), role (`admin`|`employee`, default
`employee`, indexed), remember_token, timestamps.

**project_fors** — id, name (unique), timestamps.

**projects** — id, project_for_id (FK → project_fors, cascade on delete), name, timestamps.
Unique business rule: name unique within project_for_id (enforced in validation).

**tasks** — id, project_id (FK → projects, cascade on delete), title, created_by (FK → users,
null on delete), updated_by (FK → users, null on delete), timestamps. Unique (project_id,
title).

**worklogs** — id, user_id (FK → users, cascade on delete), project_for_id (FK), project_id
(FK), task_id (FK), work_date (date), hours_spent (decimal 5,2), details (nullable text),
timestamps. Index (user_id, work_date).

Supporting tables (Laravel defaults): password_reset_tokens, sessions, cache, jobs / job_batches
/ failed_jobs.

Relationships: ProjectFor 1—* Project 1—* Task; User 1—* Worklog; each Worklog references a
ProjectFor, Project and Task. (The worklog stores project_for_id and project_id directly, in
addition to task_id, which is why used tasks/projects cannot be re-parented.)

## 7. Interfaces

### Screens / pages
- `GET /worklog/{uuid}` — public employee work-log form.
- `GET /login`, `POST /login`, `POST /logout` — admin authentication.
- `GET /dashboard` — admin dashboard (admin only).
- `admin/users`, `admin/project-fors`, `admin/projects`, `admin/tasks` — resource CRUD screens
  (index/create/edit/store/update/destroy; no `show`), admin only.
- `resources/views/welcome.blade.php` exists but is not wired to any route (no `/` route).

### API / JSON endpoints
- `POST /worklogs` — create work-log entries (JSON), throttle 30/min.
- `POST /tasks` — inline task create (JSON), throttle 30/min.
- `GET /up` — Laravel health check.

### External integrations
- Outbound webhook to n8n (`N8N_WEBHOOK_URL`) with header `X-Webhook-Secret`
  (`N8N_WEBHOOK_SECRET`), sent by the `SendWorklogToN8n` queued job.

## 8. Milestones (baseline — already delivered)

This is an as-is baseline; the following are the existing, working capabilities, listed as
testable units for QA:

1. Employee form loads by UUID and rejects unknown UUIDs.
2. Cascading dropdowns and multi-entry submission with validation.
3. Inline task creation (firstOrCreate, dedup).
4. Transactional save + queued, retrying n8n delivery.
5. Admin login restricted to admins; throttling.
6. Dashboard counts and recent logs.
7. CRUD + referential-integrity guards for Users, Project Fors, Projects, Tasks.

## 9. Known Issues / Technical Debt

1. **Cross-user submission (IDOR) — being fixed in v2.** As built in v1, `POST /worklogs` and
   `POST /tasks` took `user_id` from the request body and only validated that it exists, so a
   caller could log hours or create tasks on behalf of **any** user (user IDs are sequential).
   Per the owner's decision (2026-11-16) this is a defect to fix: identity must come from the
   UUID, not the body (see FR-12). Note this is distinct from the public, no-login design, which
   the owner keeps — the fix only stops submitting *as a different user than the UUID owner*.
2. **Public, non-expiring UUID link — accepted by design.** The `GET /worklog/{uuid}` page is
   intentionally public and the UUID is permanent: it does not expire or rotate (owner decision,
   2026-11-16 — "this public url will never be changed"). Anyone holding a user's link can submit
   for that user indefinitely; this is the intended access model. Residual hardening that does
   *not* change the URL (e.g. throttling `GET /worklog/{uuid}` to limit UUID enumeration) remains
   optional and is not required by the owner.
3. **Webhook secret present in `.env` on disk.** The working directory contains a real-looking
   `N8N_WEBHOOK_SECRET`. It must never be committed; rotate it if it has been shared. Use
   `.env.example` for keys only.
4. **Unreachable employee dashboard branch.** `DashboardController` has a non-admin branch
   ("My recent worklogs"), but `/dashboard` is `role:admin` only, so employees can never reach
   it — dead code, or an unfinished employee portal.
5. **No work-log edit/delete or duplicate prevention.** Once submitted, logs cannot be
   corrected in the app, and the same user/task/date/hours can be submitted repeatedly.
6. **No per-day hours cap.** Each entry is capped at 24 hours, but a single submission (or
   several) can total far more than a day.
7. **Hardcoded seeder credentials.** `AdminSeeder` sets a fixed password (`Password@123!`) and a
   fixed admin email; must be changed before any real deployment.
8. **CDN front-end dependencies.** The public form loads Tailwind and Alpine.js from public
   CDNs at runtime — an availability/offline and supply-chain consideration for production.
9. **Mixed-language code comments.** Some comments and the login error message are in
   Roman Urdu; the user-facing login error is not in English.
10. **Queue worker dependency is implicit.** If no queue worker runs, submissions are saved but
    never delivered to n8n, with no in-app visibility of the backlog or failures.

## 10. Assumptions & Open Questions

- The company/owner of the system is Avaib; "Project For" values double as clients and internal
  areas (assumed from seed data).
- Employees receive their UUID link out-of-band (email/chat); the distribution method is not in
  the code (assumed, open question).
- n8n performs the actual "automation" (reporting/notifications); its behaviour is outside this
  codebase (assumed).
- See the questions sent to the owner's inbox for business rules the code cannot answer.

## 11. Document Version History

| Version | Date | Author | Description of changes |
|---|---|---|---|
| 1 | 2026-11-16 | AI Engineer | As-is baseline documented from code. |
| 2 | 2026-11-16 | AI Engineer | Owner decision on QA BUG-1/BUG-2: public non-expiring UUID link accepted by design; added FR-12 (submission identity bound to UUID); changed FR-3 and FR-4 to resolve the acting user from the UUID and ignore client-supplied `user_id`; updated §9 items 1–2. |
