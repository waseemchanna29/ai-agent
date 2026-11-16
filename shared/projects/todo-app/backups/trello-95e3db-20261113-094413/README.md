# School To-Do

A simple per-user to-do list web app. Register, log in, and manage your own private
list of tasks — add, complete/uncomplete, and delete. Each user only ever sees their
own tasks.

Built with **Laravel 12** (PHP 8.2+), **Blade** templates, and **MySQL** (`school_db`)
via XAMPP. Auth is hand-rolled on Laravel's standard `Auth`/`Hash`/session — plain
Blade + inline CSS, so there is **no Node/npm build step**.

## Features

- Register / log in / log out (passwords bcrypt-hashed; session-based auth).
- Add tasks (required title, max 255 chars).
- List your tasks, newest first, with an empty state.
- Toggle tasks complete/incomplete (strike-through when done).
- Delete tasks permanently (with a confirmation prompt).
- Per-user isolation: you can never see or touch another user's tasks (404 on foreign ids).
- Laravel CSRF protection on every form.
- User management: list/add/edit/delete any account via the **Users** nav link
  (open to every logged-in user — no admin role yet; you cannot delete your own
  account).

> Requires JavaScript enabled: the complete-toggle and delete confirmation depend on it.

## Quick start

From this folder (`C:\xampp\htdocs\todo-app`):

```bash
composer install
cp .env.example .env          # Windows: copy .env.example .env
php artisan key:generate
# create the MySQL database `school_db` (utf8mb4), then:
php artisan migrate
php artisan serve             # → http://127.0.0.1:8000
```

Or via XAMPP Apache: `http://localhost/todo-app/public`.

The database defaults (`.env.example`) are MySQL `school_db`, user `root`, empty
password — local XAMPP only.

## Test

```bash
php artisan test
```

Tests run on in-memory SQLite and do not touch `school_db`. 31 automated tests pass
(Auth, Task, and User Management feature tests combined).

## Routes

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/` | Redirect to `/tasks` |
| GET/POST | `/register` | Register (auto-login on success) |
| GET/POST | `/login` | Log in |
| POST | `/logout` | Log out |
| GET | `/tasks` | List your tasks |
| POST | `/tasks` | Add a task |
| PATCH | `/tasks/{task}` | Toggle complete/incomplete |
| DELETE | `/tasks/{task}` | Delete a task |
| GET | `/users` | List all users (paginated) |
| GET/POST | `/users/create`, `/users` | Add a user |
| GET/PUT | `/users/{user}/edit`, `/users/{user}` | Edit a user |
| DELETE | `/users/{user}` | Delete a user |

## Documentation

Full docs live in `agents/docs/projects/todo-app/`:

- **USER_GUIDE.md** — step-by-step for end users.
- **API.md** — every HTTP route, inputs, responses, and status codes.
- **ARCHITECTURE.md** — components, request/data flow, diagrams, security model.
- **DEPLOYMENT.md** — environment variables, install, run, reset, troubleshooting.
- **CHANGELOG.md** — what this iteration delivered and known issues.

## Known limitations

- Registration requires a **lowercase** email (uppercase is rejected, not normalised).
- Complete-toggle and delete confirmation require JavaScript.
- No password reset, email verification, admin role, due dates, priorities, or search.
- Deletion is permanent (no undo).
- User management has no admin role: every logged-in user can manage every
  account (only self-deletion is blocked).
