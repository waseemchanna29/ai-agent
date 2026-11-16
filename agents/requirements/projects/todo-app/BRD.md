# Business Requirements Document — todo-app

Version: 1 · Date: 2026-11-11 · Status: draft

## 1. Purpose & business goals
A simple web-based to-do (task) application for a school context. It lets a user
capture tasks, see them in a list, mark them done, and remove them. The goal is a
small, working, easy-to-understand app — suitable as a school/learning project and
as a usable personal task tracker.

Measurable goals:
- A user can add a task and see it in the list in under 2 seconds (local XAMPP).
- All four core actions (add, list, complete, delete) work without errors.

## 2. Stakeholders and user roles
- **Owner** — defines scope, reviews the result.
- **User** — the single person using the app to manage tasks. No login/accounts in
  this version (assumed). Everyone who opens the app sees the same task list.

## 3. Current process / problem
No existing system. Tasks are currently tracked informally (paper/memory). This app
provides one simple place to record and track tasks.

## 4. Scope
**In scope**
- Add a task (with a title).
- List all tasks.
- Mark a task complete / incomplete.
- Delete a task.

**Out of scope (this version)**
- User accounts, login, roles, permissions.
- Categories, tags, due dates, reminders, priority, attachments.
- Multi-user separation, sharing, notifications, search.
- Mobile app / public internet hosting.

## 5. Key business rules
- A task must have a non-empty title to be saved.
- A task is either "complete" or "not complete".
- Deleting a task removes it permanently (no recycle bin).

## 6. Success criteria
- Owner can add, view, complete and delete tasks from the browser on XAMPP.
- No PHP errors on the four core actions.
- Code follows the agreed stack (Laravel 12 + Blade + MySQL `school_db`).

## 7. Constraints & assumptions
- **Stack (owner-specified):** Laravel 12 + Blade templates + MySQL via XAMPP.
- **Database name:** `school_db`.
- **Hosting:** local only (XAMPP / `http://localhost`).
- **Assumption:** single shared task list, no authentication, English UI.
- **Assumption:** small scale (one user, tens/hundreds of tasks).
