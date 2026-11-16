# HTTP Reference — School To-Do

This app is a **server-rendered Laravel application**, not a JSON/REST API. Every
endpoint is reached through an HTML form (or a redirect) and responds with an HTML
page or an HTTP redirect — there is no JSON interface and no API token auth.

This document lists every route, its inputs, and its responses so the behaviour is
fully traceable. It reflects the code in `routes/web.php` and the controllers.

## Conventions

- **Base URL:** `http://127.0.0.1:8000` (artisan serve) or
  `http://localhost/todo-app/public` (XAMPP Apache).
- **Auth:** Laravel **session cookie**. There is no bearer/API token.
- **CSRF:** every state-changing request (`POST`, `PATCH`, `DELETE`) must include a
  valid CSRF token. Blade forms add it via `@csrf`; a missing/invalid token returns
  **419 (Page Expired)**.
- **Method spoofing:** HTML forms cannot send `PATCH`/`DELETE` directly, so those
  forms POST with a hidden `_method` field (`@method('PATCH')` / `@method('DELETE')`).
- **Validation failures** redirect back (302) with the old input and error messages
  flashed to the session; the re-rendered page shows them in a red error box.
- `{task}` is a numeric task id bound via Laravel route-model binding.

---

## Route summary

| Method | Path | Name | Access | Purpose |
|--------|------|------|--------|---------|
| GET | `/` | — | any | Redirect to `/tasks` |
| GET | `/register` | `register` | guest only | Show registration form |
| POST | `/register` | — | guest only | Create account + auto-login |
| GET | `/login` | `login` | guest only | Show login form |
| POST | `/login` | — | guest only | Authenticate, start session |
| POST | `/logout` | `logout` | authenticated | End session |
| GET | `/tasks` | `tasks.index` | authenticated | List current user's tasks |
| POST | `/tasks` | `tasks.store` | authenticated | Create a task |
| PATCH | `/tasks/{task}` | `tasks.update` | authenticated | Toggle complete/incomplete |
| DELETE | `/tasks/{task}` | `tasks.destroy` | authenticated | Delete a task |
| GET | `/users` | `users.index` | authenticated | List all users (paginated) |
| GET | `/users/create` | `users.create` | authenticated | Show add-user form |
| POST | `/users` | `users.store` | authenticated | Create a user |
| GET | `/users/{user}/edit` | `users.edit` | authenticated | Show edit-user form |
| PUT | `/users/{user}` | `users.update` | authenticated | Update a user |
| DELETE | `/users/{user}` | `users.destroy` | authenticated | Delete a user |

> **Note:** the `/users` routes are open to **every** logged-in user — there is no
> admin role in this version, so any authenticated user can list, create, edit, or
> delete **any** account (not just their own).

- **guest only** routes are wrapped in Laravel's `guest` middleware: an
  already-authenticated user who opens them is redirected to `/tasks`.
- **authenticated** routes are wrapped in `auth` middleware: a guest who opens them
  is redirected to `/login`.

---

## Authentication

### `GET /` — Root redirect
Always `302` → `/tasks`. (Being unauthenticated there then redirects on to `/login`.)

### `GET /register`
Returns `200` with the registration page. If already logged in → `302` to `/tasks`.

### `POST /register` — Create account
Form fields:

| Field | Rules |
|-------|-------|
| `name` | required, string, max 255 |
| `email` | required, valid email, **lowercase**, max 255, unique in `users` |
| `password` | required, min 8, must match `password_confirmation` |
| `password_confirmation` | required, must equal `password` |

**Success:** creates the user (password hashed with bcrypt), logs them in,
regenerates the session, `302` → `/tasks`.
**Failure:** `302` back to `/register` with validation errors; no user created.

> **Note:** the `lowercase` rule *asserts* the email is already lowercase rather than
> converting it. `Me@Example.com` is **rejected** with "The email field must be
> lowercase." Register with a lowercase email. *(QA OBS-1.)*

### `GET /login`
Returns `200` with the login page. If already logged in → `302` to `/tasks`.

### `POST /login` — Authenticate
Form fields:

| Field | Rules |
|-------|-------|
| `email` | required, valid email |
| `password` | required |
| `remember` | optional (`1` = keep session on this browser) |

**Success:** `302` → the originally intended page, else `/tasks`; session regenerated.
**Failure:** `302` back with error "These credentials do not match our records."
(bound to the `email` field); not logged in.

### `POST /logout` — End session
Requires auth + CSRF token. Logs out, invalidates the session, regenerates the CSRF
token, `302` → `/login`.

---

## Tasks

All task routes require an authenticated session. Every query is scoped to the
current user (`$request->user()->tasks()`), so a user can only ever read or act on
their own tasks.

### `GET /tasks` — List
Returns `200` with the task page: the add-task form plus the current user's tasks
ordered **newest-first** (by `created_at`). Shows an empty-state message when the
user has no tasks.

### `POST /tasks` — Create
Form field:

| Field | Rules |
|-------|-------|
| `title` | required, string, max 255 (whitespace-only fails `required`) |

**Success:** creates the task for the current user with `is_completed = false`,
`302` → `/tasks`, flash "Task added."
**Failure:** `302` back with the validation error; no task created.

### `PATCH /tasks/{task}` — Toggle completion
No body fields — the controller flips `is_completed` server-side (the new value is
never taken from the request).
**Success (own task):** toggles the state, `302` → `/tasks`, flash "Task updated."
**Not your task / unknown id:** **404** (owner's data untouched).

### `DELETE /tasks/{task}` — Delete
**Success (own task):** permanently deletes the row, `302` → `/tasks`, flash
"Task deleted."
**Not your task / unknown id:** **404**.

> The front-end shows a `confirm()` dialog before sending the DELETE; see USER_GUIDE.

---

## Users

All `/users` routes require an authenticated session **only** — there is no
ownership/role check, so any logged-in user can manage any account.

### `GET /users` — List
Returns `200` with all users, newest-first, **paginated 15 per page**.

### `GET /users/create`
Returns `200` with the add-user form.

### `POST /users` — Create
Form fields:

| Field | Rules |
|-------|-------|
| `name` | required, string, max 255 |
| `email` | required, string, **lowercase**, valid email, max 255, unique in `users` |
| `password` | required, confirmed (must equal `password_confirmation`), min 8 |

**Success:** creates the user (bcrypt-hashed password), `302` → `/users`, flash
"User created." **Failure:** `302` back with validation errors; no user created.

### `GET /users/{user}/edit`
Returns `200` with the edit form pre-filled from `{user}` (route-model bound).

### `PUT /users/{user}` — Update
Form fields:

| Field | Rules |
|-------|-------|
| `name` | required, string, max 255 |
| `email` | required, string, lowercase, valid email, max 255, unique in `users` (ignores this user's own current row) |
| `password` | **optional** — nullable, confirmed, min 8 if provided |

**Success:** updates name/email; re-hashes and updates the password only if one was
submitted (blank = unchanged), `302` → `/users`, flash "User updated."
**Failure:** `302` back with validation errors; no change saved.

### `DELETE /users/{user}` — Delete
**Success:** permanently deletes the user **and cascades to delete all of their
tasks** (`tasks.user_id` FK `cascadeOnDelete`), `302` → `/users`, flash "User
deleted." **Deleting your own (currently logged-in) account:** rejected with
**403** — the row is left untouched.

> The front-end shows a `confirm()` dialog before sending the DELETE; see USER_GUIDE.

---

## Status codes you may see

| Code | Meaning here |
|------|--------------|
| `200` | Page rendered (GET register/login/tasks/users) |
| `302` | Redirect (after any successful POST/PATCH/PUT/DELETE, or auth/guest gating) |
| `403` | Trying to delete your own (currently logged-in) user account |
| `404` | Acting on a task that isn't yours, or an unknown task/user id |
| `419` | Missing/expired CSRF token on a POST/PATCH/PUT/DELETE |
| `422` | Not surfaced directly — validation errors redirect (302) with flashed messages |
| `500` | Unexpected server error (e.g. DB unreachable) |

---

## Example (cURL)

Because auth is session + CSRF based, scripting against it requires fetching a page
to obtain the `XSRF-TOKEN`/session cookies first. Illustrative only — the app is
designed for browser use:

```bash
# 1. GET the login page to receive session + CSRF cookies
curl -c cookies.txt http://127.0.0.1:8000/login

# 2. POST credentials with the token (value taken from the page/cookie)
curl -b cookies.txt -c cookies.txt \
     -d "email=me@example.com" \
     -d "password=secret123" \
     -d "_token=<csrf-token-from-page>" \
     http://127.0.0.1:8000/login
```

## Document Version History

| Version | Date | Author | Description of changes |
|---|---|---|---|
| 1 | 2026-11-12 | Technical Writer | Initial release |
| 2 | 2026-11-12 | Technical Writer | Added the 6 `/users` routes (list/create/edit/update/delete) to the route summary and a new "Users" section with request fields, success/failure responses, and the 403 self-delete case; updated the status-code table. |
