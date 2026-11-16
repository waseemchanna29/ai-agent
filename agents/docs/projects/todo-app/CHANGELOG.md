# Changelog — School To-Do

All notable changes to this project are documented here.

## [Unreleased] — User management module (built 2026-11-12, pending QA)

Additive feature, not yet covered by a QA report (the current `QA_REPORT.md` predates
it) and not yet in `REQUIREMENTS.md` (still v2, no admin role / no user-management FRs).
Documented here from the code and `DEV_NOTES.md` so the build is traceable; verdict will
follow once QA signs off and requirements adds the FRs.

### Added — User management
- **Users listing** (`/users`) — paginated (15/page), newest-first.
- **Add a user** (`/users/create`) — name, email (valid + unique), password (min 8 +
  confirmed); stored bcrypt-hashed.
- **Edit a user** (`/users/{user}/edit`) — name/email editable (email uniqueness ignores
  the user's own current row); password optional — leave blank to keep the current one,
  or set a new one (re-hashed).
- **Delete a user** (`/users/{user}`, behind a JS confirmation) — permanent; deleting a
  user cascades to delete their tasks (existing `tasks.user_id` FK, `cascadeOnDelete`).
  A user **cannot delete their own (currently logged-in) account** — returns **403**.
- Reachable via a new **"Users"** link in the nav bar.
- All 6 routes sit behind the existing `auth` middleware — **open to every logged-in
  user**, since the app has no admin role (per REQUIREMENTS v2 §2). Any logged-in user
  can therefore add/edit/delete any other user and reset their password. Flagged by
  development to the requirements agent as an open decision (admin-gating via an
  `is_admin` column is the proposed alternative, not yet built).

### Tests
- `tests/Feature/UserManagementTest.php` — 10 new feature tests (listing requires auth,
  create validation + unique email + hashing, edit email-ignore-self + optional password,
  delete another user, cannot delete own account). Total suite: **31 passing** (93
  assertions), up from 30.

### Known gaps (as of this entry)
- Not yet reflected in `REQUIREMENTS.md` (no FR-8+ for user management).
- Pagination links use Laravel's default (Tailwind) markup; this app ships no Tailwind,
  so links beyond 15 users are functional but visually unstyled.
- **QA update (2026-11-12 15:10):** the regression run in `QA_REPORT.md` confirms all
  10 `UserManagementTest` cases pass (31 total, no failures), but that run's focus and
  verdict were about a **different, separate** request — FR-8 task due dates (see
  below) — not a dedicated sign-off of this module. User management therefore still
  has no formal QA verdict of its own.

## [Unreleased] — FR-8 "task due date" requested, not yet built

The owner asked QA to test adding/editing a task due date, empty/past-date handling,
and sorting by due date. **None of this exists in the app or the spec** — logged here
so the request is traceable even though nothing shipped.

- REQUIREMENTS.md (v2) stops at FR-7 and explicitly lists "no due dates" as an
  out-of-scope assumption (§10).
- No `due_date` column/migration, no model field, no validation, no input/display in
  the task view, no due-date sort, and **no task-edit screen at all** (today's only
  mutation is the completion toggle) — there is no way to edit a due date even if one
  existed.
- QA verdict: **NOT READY** (blocked — feature absent, not a defect). Existing
  features unaffected: `php artisan test` = 31 passed, no regression.
- Next steps (per `QA_REPORT.md` §6): requirements adds FR-8 with acceptance criteria
  (especially past-date handling and sort order vs. FR-2's newest-first rule), then
  development implements, then QA runs the pre-written FR-8.1–8.8 matrix in
  `TEST_PLAN.md`.

## [1.0.0] — 2026-11-12

First delivered iteration. Built against **REQUIREMENTS.md v2** (per-user tasks with
authentication) and verified by QA as **READY** — 30 automated tests passing, no
blocking defects.

### Added — Authentication (FR-5, FR-6, FR-7)
- **Register** an account (name, email, password + confirmation). Email must be
  valid and unique; password min 8 and confirmed. Passwords stored **bcrypt-hashed**.
  Successful registration logs the user in automatically.
- **Log in** with email + password, with optional "Remember me". Session regenerated
  on login.
- **Log out**, which invalidates the session and returns to the login page.
- Guests are redirected to `/login` when they try to open the task page; logged-in
  users are kept away from the register/login pages.

### Added — Tasks (FR-1 … FR-4)
- **Add a task** with a title (required, max 255 chars; empty/whitespace rejected).
  New tasks start incomplete.
- **List tasks** — shows only the current user's tasks, **newest first**, with an
  empty-state message when there are none.
- **Toggle complete/incomplete** via a checkbox; completed tasks are shown struck
  through and muted.
- **Delete a task** permanently, behind a JavaScript confirmation dialog.
- **Tenant isolation** — a user can only view or act on their own tasks; acting on
  another user's task returns **404**.

### Security / non-functional
- Laravel CSRF protection on every form (missing token → 419).
- Passwords hashed (bcrypt, 12 rounds); never stored or logged in plain text.
- Task routes behind `auth` middleware; all task queries scoped to the current user.
- Eloquent / bound queries only (no raw SQL); Blade `{{ }}` auto-escaping (XSS-safe).
- Session regenerated on login/registration; invalidated on logout.
- Mass-assignment limited; completion state set server-side only.

### Stack
- Laravel 12.72.0, PHP 8.2+, MySQL `school_db` (XAMPP). Plain Blade + inline CSS —
  **no Node/npm build step**. Hand-rolled auth on Laravel's standard `Auth`/`Hash`/session.

### Tests
- 21 developer feature tests + 9 QA supplementary tests = **30 passing**
  (in-memory SQLite; the live `school_db` is never touched).

### Known issues / limitations
- **OBS-1 (low, UX):** registration **rejects** uppercase emails rather than
  normalising them (the `lowercase` rule asserts instead of transforming). Register
  and log in with a lowercase email. *(File: `RegisteredUserController.php:32`.)*
- **OBS-2 (low, info):** the complete-toggle (checkbox auto-submit) and the delete
  confirmation dialog require **JavaScript enabled**. *(File: `tasks/index.blade.php`.)*

### Out of scope (this version)
- No password reset or email verification.
- No admin role.
- No due dates, priorities, categories, or search.
- Deletion is permanent (no soft-delete / undo).

## Document Version History

| Version | Date | Author | Description of changes |
|---|---|---|---|
| 1 | 2026-11-12 | Technical Writer | Initial release |
| 2 | 2026-11-12 | Technical Writer | Added "Unreleased" entry for the User Management module (listing/add/edit/delete, 6 routes, nav link, 10 tests) built in fixes 20261112-1112/1436; flagged as pending QA sign-off and missing REQUIREMENTS FRs. |
| 3 | 2026-11-12 | Technical Writer | Added User Management regression note from `QA_REPORT.md` (15:10 — 10/10 tests pass, 31 total, but no dedicated module sign-off). Added a new "Unreleased — FR-8 task due date" entry: feature requested by the owner but not implemented and not in REQUIREMENTS.md; logged as NOT READY / blocked pending spec, per QA's finding. |
