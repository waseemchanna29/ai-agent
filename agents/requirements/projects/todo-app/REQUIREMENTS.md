# Requirements — todo-app
Version: 2 · Date: 2026-11-11 · Status: draft

## 1. Overview & glossary
A web app where users register and log in, then manage their own private list of
tasks. Each logged-in user can add tasks, view them, mark them complete or
incomplete, and delete them. A user only ever sees their own tasks.

Glossary:
- **Task** — a single to-do item with a title and a completion state, owned by one user.
- **Complete / Done** — a task the user has finished.
- **Account / User** — a registered person with an email and password who logs in.

## 2. User roles & permissions
- **Guest (not logged in)** — can only access the registration and login pages.
  Cannot see or manage any tasks.
- **Registered user** — can log in/out and fully manage **their own** tasks
  (add, view, complete/uncomplete, delete). Cannot see or affect other users' tasks.
- No admin role in this version.

## 3. Functional requirements

### FR-1 Add a task `[changed]`
**User story:** As a logged-in user, I want to add a task with a title so that I can
record something I need to do.
**Acceptance criteria:**
- Available only to logged-in users; the created task is owned by the current user.
- There is an input field and an "Add" button on the task page.
- Submitting a non-empty title creates a task and shows it in the list.
- Submitting an empty/whitespace-only title is rejected with a visible validation
  message; no task is created.
- New tasks start as "not complete".
- Title length is limited to 255 characters.

### FR-2 List tasks `[changed]`
**User story:** As a logged-in user, I want to see my own tasks in a list so that I
know what I need to do.
**Acceptance criteria:**
- Available only to logged-in users.
- The task page shows **only the current user's** tasks (never other users' tasks).
- Each row shows the title and its completion state (e.g. checkbox + strike-through
  for completed).
- Tasks are ordered newest-first (by creation time).
- When the user has no tasks, an empty-state message is shown (e.g. "No tasks yet").

### FR-3 Mark a task complete / incomplete `[changed]`
**User story:** As a logged-in user, I want to mark my task as done (and undo it) so
that I can track progress.
**Acceptance criteria:**
- Available only to logged-in users, and only for tasks they own.
- Each task has a control (checkbox/button) to toggle its completion state.
- Toggling updates the task and the list reflects the new state.
- Completed tasks are visually distinct (e.g. strike-through / muted).
- Attempting to toggle a task owned by another user is rejected (403 / not found).

### FR-4 Delete a task `[changed]`
**User story:** As a logged-in user, I want to delete my task so that I can remove
things I no longer need.
**Acceptance criteria:**
- Available only to logged-in users, and only for tasks they own.
- Each task has a "Delete" control.
- Deleting removes the task permanently from the database and the list.
- A simple confirmation (e.g. confirm dialog) is shown before deletion.
- Attempting to delete a task owned by another user is rejected (403 / not found).

### FR-5 Register an account `[new]`
**User story:** As a guest, I want to create an account so that I can have my own
private task list.
**Acceptance criteria:**
- A registration page provides: name, email, password, and password confirmation.
- Email must be valid and unique (no two accounts with the same email).
- Password minimum length 8; password and confirmation must match.
- Passwords are stored hashed (Laravel `bcrypt`/`Hash`), never in plain text.
- On successful registration the user is logged in and redirected to the task page.
- Invalid input shows clear validation messages and does not create an account.

### FR-6 Log in `[new]`
**User story:** As a registered user, I want to log in so that I can access my tasks.
**Acceptance criteria:**
- A login page accepts email and password.
- Correct credentials start a session and redirect to the task page.
- Incorrect credentials show an error and do not log the user in.
- A "Remember me" option may be provided (optional).
- Guests who try to open the task page are redirected to the login page.

### FR-7 Log out `[new]`
**User story:** As a logged-in user, I want to log out so that my session ends.
**Acceptance criteria:**
- A "Log out" control is visible when logged in.
- Logging out ends the session and redirects to the login page.
- After logout, the task page is no longer accessible until logging in again.

## 4. Non-functional requirements
- **Security:**
  - Laravel's built-in CSRF protection on all forms.
  - Passwords hashed with Laravel `Hash` (bcrypt); never stored or logged in plain text.
  - All task routes protected by the `auth` middleware; task queries scoped to the
    current user so one user cannot read or modify another's tasks (authorization).
  - Eloquent / query bindings only (no raw unparameterized SQL) to avoid SQL injection.
  - Blade output escaping (default) to avoid XSS.
  - Session fixation handled by Laravel defaults (regenerate session on login).
- **Performance:** pages respond in under ~1 second on local XAMPP for up to a few
  hundred tasks per user.
- **Availability:** local development only; no uptime guarantees.
- **Usability:** simple desktop-browser UI; clear nav showing logged-in user and logout.
- **Maintainability:** follow Laravel 12 conventions and PSR-12 style.

## 5. Tech stack
- **Framework:** Laravel 12 (owner-specified).
- **Language:** PHP 8.2+.
- **Views:** Blade templates.
- **Auth:** Laravel's built-in authentication (e.g. Laravel Breeze with Blade, or
  hand-rolled using Laravel's `Auth`). Developer's choice, as long as it uses
  Laravel's standard auth/session/hashing — no third-party auth providers.
- **Database:** MySQL via XAMPP, database name `school_db`.
- **Server:** local XAMPP (Apache + MySQL) / `php artisan serve`.
- Note: the owner's global rule prefers "no framework unless specified"; the owner
  explicitly specified Laravel 12, so Laravel is used for this project.

## 6. Data model
**Table: `users`** (Laravel default)
| Field             | Type                | Notes                              |
|-------------------|---------------------|------------------------------------|
| id                | BIGINT UNSIGNED PK  | auto-increment                     |
| name              | VARCHAR(255)        | required                           |
| email             | VARCHAR(255)        | required, unique                   |
| password          | VARCHAR(255)        | hashed                             |
| remember_token    | VARCHAR(100)        | nullable                           |
| created_at        | TIMESTAMP           |                                    |
| updated_at        | TIMESTAMP           |                                    |

**Table: `tasks`**
| Field        | Type                | Notes                                            |
|--------------|---------------------|--------------------------------------------------|
| id           | BIGINT UNSIGNED PK  | auto-increment                                   |
| user_id      | BIGINT UNSIGNED FK  | → `users.id`, required, indexed; cascade on delete |
| title        | VARCHAR(255)        | required, non-empty                              |
| is_completed | BOOLEAN / TINYINT(1)| default 0 (false)                                |
| created_at   | TIMESTAMP           | Laravel timestamp                                |
| updated_at   | TIMESTAMP           | Laravel timestamp                                |

Relationship: a `User` has many `Task`s; a `Task` belongs to a `User`.

## 7. Interfaces
**Screens / pages**
- **Register page (`/register`)** — name, email, password, confirm password.
- **Login page (`/login`)** — email, password, (optional) remember me.
- **Task page (`/` or `/tasks`)** — requires login; add-task form, the current user's
  task list with toggle-complete and delete controls, empty-state, and a nav bar
  showing the user's name and a logout control.

**Routes / endpoints**
Auth (Laravel standard):
- `GET  /register`  — show registration form.
- `POST /register`  — create account + log in.
- `GET  /login`     — show login form.
- `POST /login`     — authenticate.
- `POST /logout`    — end session.

Tasks (all behind `auth` middleware, scoped to current user):
- `GET  /tasks` (also `/`)   — list current user's tasks.
- `POST /tasks`              — create a task for the current user.
- `PATCH/PUT /tasks/{id}`    — toggle/update completion state (own task only).
- `DELETE /tasks/{id}`       — delete (own task only).

**External integrations:** none.

## 8. Milestones (small testable tasks)
- **M1 — Project setup:** Laravel 12 app, `.env` → MySQL `school_db`, `.env.example`.
  App boots at `http://localhost`.
- **M2 — Auth scaffolding (FR-5, FR-6, FR-7):** register, login, logout with Laravel
  auth; `users` table migrated. Guests redirected to login.
- **M3 — Data layer:** `tasks` migration with `user_id` FK + `Task` model and
  `User`↔`Task` relationship. `php artisan migrate` runs clean.
- **M4 — List & empty state (FR-2):** task page shows current user's tasks / empty state.
- **M5 — Add task (FR-1):** add-task form with validation; task owned by current user.
- **M6 — Toggle complete (FR-3):** toggle control updates state; own-task authorization.
- **M7 — Delete task (FR-4):** delete control with confirmation; own-task authorization.
- **M8 — Polish:** basic styling, CSRF on forms, flash messages, nav with user + logout.

## 9. Known issues / technical debt
- New project; none yet.

## 10. Assumptions & open questions
Assumptions (used so work can proceed; owner can correct):
1. **Each user has a private task list** — tasks are scoped per account (natural
   consequence of adding login). Not a single shared list.
2. **English UI text.**
3. **No email verification / password reset** in this version (can be added later).
4. **No admin role.**
5. **No due dates, priority, categories, or search** in this version.
6. Deletion is permanent (no soft-delete / undo).

Open questions (none blocking):
- Do you want "forgot password" / password reset later?
- Should tasks have a due date and/or priority later, or keep it minimal?
