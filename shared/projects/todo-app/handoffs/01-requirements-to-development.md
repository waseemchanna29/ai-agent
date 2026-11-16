# Handoff 01 — requirements → development

Date: 2026-11-11 15:25
From: requirements agent

## What's ready
REQUIREMENTS.md v1 (draft) is complete. BRD.md and CHANGELOG.md written.
You can start building.

- Spec:  C:\xampp\htdocs\Agents\agents\requirements\projects\todo-app\REQUIREMENTS.md
- Code goes in: C:\xampp\htdocs\todo-app  (code_dir from project.json)

## Summary
A simple to-do app: **add, list, complete/uncomplete, delete** tasks.
Four functional requirements: FR-1 (add), FR-2 (list), FR-3 (toggle complete),
FR-4 (delete). See the SRS for full acceptance criteria.

## Stack (owner-specified in brief)
- Laravel 12 + Blade templates
- PHP 8.2+
- MySQL via XAMPP, database name **`school_db`**
- Local XAMPP only. Add `.env.example` (never commit real `.env`).

## Data model
One table `tasks`: `id`, `title` (VARCHAR 255, required), `is_completed`
(boolean, default false), `created_at`, `updated_at`.

## Routes (resource-style)
- `GET /tasks` (also `/`) — list
- `POST /tasks` — create
- `PATCH /tasks/{id}` — toggle complete
- `DELETE /tasks/{id}` — delete

## Build order (milestones)
M1 setup → M2 migration+model → M3 list/empty-state → M4 add+validation →
M5 toggle → M6 delete+confirm → M7 polish (CSRF, flash messages, basic styling).

## Assumptions (confirm-later, don't block)
- No authentication — single shared list.
- English UI, minimal fields (no due date/priority/categories).
- Permanent delete (no soft-delete).

Owner has two optional clarifying questions pending in inbox/owner; defaults are
stated there, so you can build against the spec as written.
