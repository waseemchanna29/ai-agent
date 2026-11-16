# Handoff 01 — Requirements → Development

**Date:** 2026-11-16
**Project:** worklog_automation
**Status:** As-is baseline documented. No code changes requested yet.

## What this project is
A Laravel 12 timesheet app. Employees open a personal UUID link (`/worklog/{uuid}`), log daily
hours against Project For → Project → Task, and submit. Each submission is queued and pushed to
an external n8n webhook. Admins log in to manage the catalogue and users and view a dashboard.

## Where things live (code: C:\xampp\htdocs\worklog_automation)
- **Routes:** `routes/web.php` — public form + throttled POSTs, admin group under `role:admin`.
- **Middleware:** `app/Http/Middleware/EnsureRole.php` (alias `role`, registered in
  `bootstrap/app.php`). Roles enum: `app/Enums/Role.php` (admin, employee).
- **Employee flow:** `WorklogController` (create/store), `StoreWorklogRequest`,
  `TaskController@quickStore`, view `resources/views/worklog/create.blade.php` (Alpine.js).
- **Webhook:** `app/Jobs/SendWorklogToN8n.php`; config in `config/services.php` (`n8n.*`),
  env keys `N8N_WEBHOOK_URL`, `N8N_WEBHOOK_SECRET`. Requires a running queue worker.
- **Admin CRUD:** `app/Http/Controllers/Admin/{User,ProjectFor,Project}Controller.php` and
  `TaskController` (admin methods); `DashboardController`; views under
  `resources/views/admin/*` with layout `layouts/admin.blade.php`.
- **Models:** `User, ProjectFor, Project, Task, Worklog`.
- **Schema:** `database/migrations/*` (base tables + add designation/uuid/role to users, audit
  cols on tasks). **Seeders:** `AdminSeeder`, `UserSeeder`, `ProjectSeeder` (not all wired into
  `DatabaseSeeder` — run individually). DB: MySQL `harva_avaib`.

## Important notes before any change
- Worklog rows store `project_for_id` and `project_id` directly (not only `task_id`), which is
  why used tasks/projects cannot be re-parented — keep that invariant if you touch the catalogue.
- Deletes are guarded by "has worklogs" checks and DB cascades (ProjectFor → Project → Task).
- See REQUIREMENTS.md §9 "Known Issues" — most relevant for maintenance:
  submissions are not authenticated against the UUID (`user_id` trusted from the body); hardcoded
  seeder admin password; CDN Tailwind/Alpine on the public form.

## Next step
No implementation task yet. Waiting on the owner's answers (inbox/owner) — especially whether the
submission-identity gap (Q2) should be fixed. Hold until the owner confirms a work item.
