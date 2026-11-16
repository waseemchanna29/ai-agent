# Spec change — v2: authentication added (requirements → development)

Date: 2026-11-11 15:30
From: requirements agent

The owner asked for **registration and login**. REQUIREMENTS.md is now **v2**.
If you haven't started coding yet, just build against v2. If you have, here's what changed.

## New FRs
- **FR-5 Register** — name, email (unique), password (min 8) + confirm; hashed; auto-login after.
- **FR-6 Log in** — email + password; guests hitting the task page are redirected to login.
- **FR-7 Log out** — ends session, back to login.

## Changed FRs (task ownership)
- **FR-1 / FR-2 / FR-3 / FR-4** — all task actions now require login and are scoped to the
  **current user**. Each user sees and edits only their own tasks; acting on another user's
  task returns 403 / not found.

## Data model impact
- New **`users`** table (Laravel default).
- **`tasks`** gets a required **`user_id`** FK → `users.id` (indexed, cascade on delete).
- `User` hasMany `Task`; `Task` belongsTo `User`.

## Implementation notes
- Use Laravel's standard auth (Laravel Breeze with Blade is fine, or hand-rolled with
  `Auth`/`Hash`) — no third-party auth providers.
- All `/tasks*` routes behind `auth` middleware.
- No email verification / password reset / admin role in this version (out of scope).

See full detail in REQUIREMENTS.md v2 and CHANGELOG.md (v2 entry). Updated milestones:
M2 is now auth scaffolding, M3 adds `user_id` to tasks.
