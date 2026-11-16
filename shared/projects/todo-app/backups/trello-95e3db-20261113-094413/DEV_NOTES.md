# DEV_NOTES — todo-app

**Built against requirements v2** (2026-11-11). Framework: Laravel 12.72.0, PHP 8.2+, MySQL `school_db` (XAMPP).

## Install / run
1. `composer install` (already done; `vendor/` present).
2. Copy `.env.example` → `.env`, then `php artisan key:generate`
   (the working `.env` already has a key and points at MySQL `school_db`, user `root`, no password).
3. Ensure the database exists: `school_db` (utf8mb4). Already created locally.
4. `php artisan migrate`
5. Serve: `php artisan serve` → http://127.0.0.1:8000, or browse via XAMPP Apache at
   http://localhost/todo-app/public.

## Auth approach
Hand-rolled on Laravel's standard `Auth`/`Hash`/session (no Breeze, so **no Node/npm build step** —
plain Blade + inline CSS). Uses Laravel defaults for CSRF, password hashing (bcrypt), and session
regeneration on login/logout.

## Structure (what I added/changed)
- `routes/web.php` — `/` → `/tasks`; `guest` group (register/login), `auth` group (logout, tasks CRUD).
- `app/Http/Controllers/Auth/RegisteredUserController.php` — show/handle registration + auto-login.
- `app/Http/Controllers/Auth/AuthenticatedSessionController.php` — login + logout.
- `app/Http/Controllers/TaskController.php` — index/store/update(toggle)/destroy, scoped to `auth()->user()`.
- `app/Models/Task.php` — `belongsTo(User)`, `is_completed` cast to bool.
- `app/Models/User.php` — added `hasMany(Task)`.
- `database/migrations/2026_11_11_153300_create_tasks_table.php` — `user_id` FK (cascade on delete), `title`, `is_completed`.
- `database/factories/TaskFactory.php` — for tests.
- `resources/views/layouts/app.blade.php` — layout, nav (user name + logout), flash + error blocks, CSS.
- `resources/views/auth/{register,login}.blade.php`, `resources/views/tasks/index.blade.php`.
- `.env` / `.env.example` — MySQL `school_db`.

## Authorization model
Task routes are behind `auth` middleware. Ownership is enforced in `TaskController::authorizeOwnership`
(`abort_if(... !== current user, 404)`), so acting on another user's task returns **404** (spec allows 403/not found).

## User management module (added 2026-11-12, fix 20261112-1436)
Reachable via the **Users** nav link (any logged-in user — no admin role yet, per spec).
- `routes/web.php` — 6 `users.*` routes in the `auth` group (index/create/store/edit/update/destroy).
- `app/Http/Controllers/UsersController.php` — already present; listing paginated newest-first,
  create/update share the auth validation pattern (email unique, password min 8 + confirm;
  password optional on edit), `destroy` blocks deleting your own logged-in account (403).
- `resources/views/users/{index,create,edit}.blade.php` — plain Blade, reuse layout CSS.
- `tests/Feature/UserManagementTest.php` — 10 feature tests.
- **Open decision:** keep open to all logged-in users (current) or gate to admins via an
  `is_admin` column. Requirements has no user-management FRs yet — flagged to requirements agent.

## User status (active / inactive) (added 2026-11-13, Trello card XhsrArpP)
Users now have a `status` field — `active` (default) or `inactive`. **Inactive users cannot log in.**
- `database/migrations/2026_11_13_091800_add_status_to_users_table.php` — adds `status`
  `string default 'active'` after `password`. Already migrated on `school_db`.
- `app/Models/User.php` — `status` added to `$fillable`; `STATUS_ACTIVE`/`STATUS_INACTIVE`
  constants; `isActive()` helper.
- `app/Http/Controllers/Auth/AuthenticatedSessionController.php::store` — after a **successful**
  `Auth::attempt`, if the user is not active the session is logged out/invalidated and an
  `email` validation error is thrown ("Your account is inactive. Please contact an administrator.").
  Checked **after** the password so account status is never revealed without the correct password.
- `app/Http/Controllers/UsersController.php` — `store`/`update` validate `status`
  (`required, in:active,inactive`) and persist it.
- Views: `users/create.blade.php` + `users/edit.blade.php` add a Status select (create defaults to
  Active); `users/index.blade.php` shows each user's status.
- `database/factories/UserFactory.php` — default `status` = active; new `inactive()` state.
- Registration (`RegisteredUserController`) does not set status, so self-registered users get the DB
  default `active`.
- **Assumption (no FR yet):** no admin role exists, so any logged-in user can set another user's
  status via the Users module (consistent with the existing user-management module). Flagged to the
  requirements agent to add a formal FR + decide whether this should be admin-gated.

## FR status
- FR-1 Add task — **done** (validation: required, max 255, trim-empty rejected).
- FR-2 List tasks — **done** (own tasks only, newest-first, empty state).
- FR-3 Toggle complete/incomplete — **done** (checkbox auto-submits; strike-through when done).
- FR-4 Delete task — **done** (JS confirm dialog; permanent delete).
- FR-5 Register — **done** (name/email-unique/password min 8 + confirm; hashed; auto-login).
- FR-6 Login — **done** (remember-me optional; guests redirected to `/login`).
- FR-7 Logout — **done** (session invalidated; back to `/login`).

## Tests
`php artisan test` — **35 passing** (109 assertions; 23 Auth+Task + 13 UserManagement).
Status feature adds: `AuthTest` inactive-user-blocked + active-user-can-login; `UserManagementTest`
create-as-inactive, reject-invalid-status, update-can-deactivate.
Feature tests run on in-memory SQLite
(phpunit.xml) with `RefreshDatabase`; they do **not** touch `school_db`.
- `tests/Feature/AuthTest.php` — register/login/logout, validation, guest redirect, password hashing.
- `tests/Feature/TaskTest.php` — add/validation/255-cap, list scoping, empty state, toggle, delete,
  newest-first ordering, cross-user 404 on toggle & delete.

Also verified end-to-end against the live MySQL-backed server (`artisan serve`): register → auto-login →
empty state → add task → task visible. Smoke artifacts and the smoke user were removed afterward.

## Known limitations (per spec, out of scope)
- No email verification / password reset, no admin role, no due dates/priority/categories/search.
- Permanent delete (no soft-delete/undo).
- Toggle is done via auto-submitting checkbox (needs JS); delete uses `confirm()` (needs JS).
