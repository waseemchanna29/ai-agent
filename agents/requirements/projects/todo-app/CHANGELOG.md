# Changelog — todo-app

## v2 — 2026-11-11
Requested by: owner   Reason: Add user accounts — registration and login screens.
- Added:   FR-5 Register an account
- Added:   FR-6 Log in
- Added:   FR-7 Log out
- Changed: FR-1 Add a task — now requires login; task owned by current user
- Changed: FR-2 List tasks — now shows only the current user's tasks (private per user)
- Changed: FR-3 Toggle complete — login required; only own tasks (authorization)
- Changed: FR-4 Delete a task — login required; only own tasks (authorization)
Impact:
- DB: new `users` table (Laravel default); `tasks` gets a required `user_id` FK
  (indexed, cascade on delete).
- Code: all task routes move behind `auth` middleware; task queries scoped to the
  current user; new register/login/logout flows using Laravel's standard auth + hashing.
- Tests: QA must add auth tests (register/login/logout) and ownership-isolation tests
  (user A cannot see/modify user B's tasks), plus guest-redirect-to-login.
- Docs: will need to document registration/login.

## v1 — 2026-11-11
Requested by: owner   Reason: Initial specification from BRIEF.md
- Added: FR-1 Add a task
- Added: FR-2 List tasks
- Added: FR-3 Mark a task complete / incomplete
- Added: FR-4 Delete a task
Impact: Initial specification. Development can begin (Laravel 12 + Blade + MySQL
`school_db`). No prior code, tests, or docs affected.
