# Requirements — todo-app
Version: 1 · Date: 2026-11-11 · Status: draft

## 1. Overview & glossary
A simple web app to manage a single list of tasks. Users can add tasks, view them in
a list, mark them complete or incomplete, and delete them.

Glossary:
- **Task** — a single to-do item with a title and a completion state.
- **Complete / Done** — a task the user has finished.

## 2. User roles & permissions
- **User** — the only role. Can add, view, complete/uncomplete, and delete tasks.
- No authentication in this version; anyone who can open the app has full access
  (assumed — see open questions).

## 3. Functional requirements

### FR-1 Add a task `[new]`
**User story:** As a user, I want to add a task with a title so that I can record
something I need to do.
**Acceptance criteria:**
- There is an input field and an "Add" button on the main page.
- Submitting a non-empty title creates a task and shows it in the list.
- Submitting an empty/whitespace-only title is rejected with a visible validation
  message; no task is created.
- New tasks start as "not complete".
- Title length is limited to 255 characters.

### FR-2 List tasks `[new]`
**User story:** As a user, I want to see all my tasks in a list so that I know what
I need to do.
**Acceptance criteria:**
- The main page shows all tasks.
- Each row shows the title and its completion state (e.g. checkbox + strike-through
  for completed).
- Tasks are ordered newest-first (by creation time).
- When there are no tasks, an empty-state message is shown (e.g. "No tasks yet").

### FR-3 Mark a task complete / incomplete `[new]`
**User story:** As a user, I want to mark a task as done (and undo it) so that I can
track progress.
**Acceptance criteria:**
- Each task has a control (checkbox/button) to toggle its completion state.
- Toggling updates the task and the list reflects the new state.
- Completed tasks are visually distinct (e.g. strike-through / muted).

### FR-4 Delete a task `[new]`
**User story:** As a user, I want to delete a task so that I can remove things I no
longer need.
**Acceptance criteria:**
- Each task has a "Delete" control.
- Deleting removes the task permanently from the database and the list.
- A simple confirmation (e.g. confirm dialog) is shown before deletion.

## 4. Non-functional requirements
- **Security:** use Laravel's built-in CSRF protection on all forms; use Eloquent /
  query bindings (no raw unparameterized SQL) to avoid SQL injection; escape output
  in Blade (default) to avoid XSS.
- **Performance:** all pages respond in under ~1 second on local XAMPP for up to a
  few hundred tasks.
- **Availability:** local development only; no uptime guarantees.
- **Usability:** single-page experience, works in a modern desktop browser.
- **Maintainability:** follow Laravel 12 conventions and PSR-12 style.

## 5. Tech stack
- **Framework:** Laravel 12 (owner-specified).
- **Language:** PHP 8.2+.
- **Views:** Blade templates.
- **Database:** MySQL via XAMPP, database name `school_db`.
- **Server:** local XAMPP (Apache + MySQL) / `php artisan serve`.
- Note: the owner's global rule prefers "no framework unless specified"; the owner
  explicitly specified Laravel 12 in the brief, so Laravel is used for this project.

## 6. Data model
**Table: `tasks`**
| Field        | Type                | Notes                                  |
|--------------|---------------------|----------------------------------------|
| id           | BIGINT UNSIGNED PK  | auto-increment                         |
| title        | VARCHAR(255)        | required, non-empty                    |
| is_completed | BOOLEAN / TINYINT(1)| default 0 (false)                      |
| created_at   | TIMESTAMP           | Laravel timestamp                      |
| updated_at   | TIMESTAMP           | Laravel timestamp                      |

## 7. Interfaces
**Screens / pages**
- **Task list page (`/` or `/tasks`)** — add-task form at top, task list below with
  toggle-complete and delete controls per row, and empty-state when no tasks.

**Routes / endpoints (Laravel resource-style)**
- `GET  /tasks`              — list tasks (also served at `/`).
- `POST /tasks`              — create a task.
- `PATCH/PUT /tasks/{id}`    — toggle/update completion state.
- `DELETE /tasks/{id}`       — delete a task.

**External integrations:** none.

## 8. Milestones (small testable tasks)
- **M1 — Project setup:** Laravel 12 app, `.env` pointing to MySQL `school_db`,
  `.env.example` added. App boots at `http://localhost`.
- **M2 — Data layer:** `tasks` migration + `Task` model. `php artisan migrate` runs
  clean.
- **M3 — List & empty state (FR-2):** task list page renders all tasks / empty state.
- **M4 — Add task (FR-1):** add-task form with validation.
- **M5 — Toggle complete (FR-3):** toggle control updates state.
- **M6 — Delete task (FR-4):** delete control with confirmation.
- **M7 — Polish:** basic styling, CSRF on forms, flash messages.

## 9. Known issues / technical debt
- New project; none yet.

## 10. Assumptions & open questions
Assumptions (used so work can proceed; owner can correct):
1. **No authentication** — single shared task list, no login/accounts.
2. **English UI text.**
3. **No due dates, priority, categories, or search** in this version.
4. Deletion is permanent (no soft-delete / undo).

Open questions sent to the owner (see `inbox/owner`):
- Is login/multiple users needed, or is a single shared list fine?
- Should tasks have a due date and/or priority later, or keep it minimal?
