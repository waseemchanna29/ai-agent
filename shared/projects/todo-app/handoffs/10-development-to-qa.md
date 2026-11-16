# Handoff — Development → QA

Date: 2026-11-13
Trello card: "User has status field active and inactive" (XhsrArpP)
Built against requirements v2 (no FR for this yet — see note at bottom).

## What the card asked
Add a status field (active/inactive) in the user module. An **inactive user must not be able
to log in**.

## What changed
- **Migration** `database/migrations/2026_11_13_091800_add_status_to_users_table.php`
  — adds `users.status` (string, default `active`). **Already migrated on `school_db`** via
  `php artisan migrate --force`.
- **User model** — `status` in `$fillable`, `STATUS_ACTIVE`/`STATUS_INACTIVE` constants,
  `isActive()` helper.
- **Login** (`AuthenticatedSessionController::store`) — after a successful credential check,
  an inactive user is immediately logged out + session invalidated and shown the error
  "Your account is inactive. Please contact an administrator." (attached to the `email` field).
  The status check runs **after** the password is verified, so a wrong password still shows the
  normal "credentials do not match" error (status is not leaked).
- **User module** (`UsersController` store/update) — Status is validated
  (`required, in:active,inactive`) and saved. Add-user form defaults to Active.
- **Views** — Status select on `users/create` and `users/edit`; status shown on `users/index`.
- **Factory** — default status active; `User::factory()->inactive()` state added.
- Self-registration (`/register`) still works and produces an **active** user (DB default).

## How to run
- `php artisan test` → **35 passed** (109 assertions). No failures.
- Live: `php artisan serve` (or XAMPP). DB `school_db` already has the `status` column.

## What to test first
1. **Inactive cannot log in:** set a user to Inactive (Users → Edit, or create as Inactive),
   log out, try to log in with correct credentials → rejected with the inactive message, stays guest.
2. **Active can log in:** same user flipped back to Active → logs in normally to `/tasks`.
3. **Wrong password on an inactive account** still shows the generic credentials error (not the
   inactive message) — status must not leak.
4. **User module:** create a user as Active and as Inactive; invalid status value is rejected;
   edit toggles status; `/users` list shows the status.
5. **Regression:** existing auth/task/user-management flows unchanged (register auto-login,
   task CRUD + ownership 404s, self-delete 403).

## Note to requirements (also sent to their inbox)
There is **no FR** for user status yet, and no admin role in the spec, so I made status settable
by any logged-in user through the existing Users module (consistent with how user-management already
works). Please add a formal FR and decide whether status management should be admin-gated and
whether an inactive user's existing session should be force-ended.
