# Handoff 02 — development → qa

Date: 2026-11-11 15:33
From: development agent
Built against: **REQUIREMENTS.md v2** (auth + per-user tasks)

## What's ready
Full Laravel 12 app implementing all 7 FRs. Code in `C:\xampp\htdocs\todo-app`.
All 21 automated tests pass; also verified end-to-end against the live MySQL server.

## Stack / setup
- Laravel 12.72.0, PHP 8.2+, MySQL `school_db` (XAMPP). `.env` points at `school_db` / root / no password.
- Hand-rolled auth on Laravel's standard `Auth`/`Hash`/session — **no Node/npm build needed** (plain Blade + inline CSS).
- DB + migrations already run locally. To re-run: `php artisan migrate:fresh` (dev DB only).

## How to run
- `php artisan serve` → http://127.0.0.1:8000  (root redirects to `/tasks`)
- or XAMPP Apache: http://localhost/todo-app/public
- Tests: `php artisan test` (uses in-memory SQLite, does NOT touch `school_db`).

## Routes
- `GET /` → redirect `/tasks`
- Guest: `GET/POST /register`, `GET/POST /login`
- Auth: `POST /logout`, `GET /tasks`, `POST /tasks`, `PATCH /tasks/{task}`, `DELETE /tasks/{task}`

## Test first (highest-risk areas)
1. **Authorization / tenant isolation** — log in as user A, note a task id, then as user B try
   `PATCH /tasks/{A_id}` and `DELETE /tasks/{A_id}`. Must return **404** and not change A's data.
   User B's `/tasks` list must never show A's tasks.
2. **Auth gates** — hit `/tasks` as a guest → redirect to `/login`. After logout, `/tasks` is locked again.
3. **Registration validation** — duplicate email, password < 8, mismatched confirmation all rejected
   with visible messages; no user created. Password is stored **hashed** (check `users.password`).
4. **Add-task validation** — empty / whitespace-only title and title > 255 chars rejected; no row created.
5. **Toggle & delete** — checkbox toggles complete state (strike-through); delete shows a confirm
   dialog and permanently removes the row. New tasks appear newest-first.
6. **CSRF** — all mutating forms carry `@csrf`; posting without a token should fail (419).

## Notes for QA
- Toggle checkbox and delete confirm rely on JavaScript (auto-submit / `confirm()`); test in a real browser.
- No email verification / password reset / admin — intentionally out of scope (v2).
- See `C:\xampp\htdocs\todo-app\DEV_NOTES.md` for full file map and FR status.
