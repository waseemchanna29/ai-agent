# Business Requirements Document — worklog_automation

## 1. Purpose & Business Goals

worklog_automation is an internal web application used by Avaib to collect the daily work
logs (timesheets) of its team members and to push every submitted log into an external
automation workflow (n8n) for downstream processing — for example reporting, notifications
or productivity tracking.

Business goals:

1. Give every team member a fast, no-login way to record how many hours they spent, on which
   project and task, on a given day.
2. Give administrators a single place to manage the catalogue of clients, projects and tasks
   and the list of team members.
3. Automatically forward each submitted work log to the company's automation platform (n8n)
   so that no manual copying or re-entry is needed.

Measurable intent (assumed): reduce the manual effort of collecting and consolidating daily
timesheets to near zero, and ensure logs are captured in a single structured database.

## 2. Stakeholders & User Roles

| Role | Who | What they do |
|---|---|---|
| Administrator | Avaib management / team lead (e.g. Waseem Iqbal) | Log in, manage users, clients ("Project For"), projects and tasks, view the dashboard and all work logs. |
| Employee / Team member | Avaib staff and contractors | Open their personal work-log link, record daily hours against a project and task, add new tasks. They do not log in. |
| External automation (n8n) | Company n8n cloud instance | Receives each submitted work log via a secured webhook and runs the downstream automation. |

## 3. Current Process / Problem

The system is already in use. Today:

- Each employee is given a personal link containing a unique code (UUID). Opening the link
  shows a form pre-filled with their name, email and designation — no password needed.
- The employee selects the date, the client ("Project For"), the project, then adds one or
  more entries (task, hours, optional details) and submits.
- Administrators maintain the catalogue (clients, projects, tasks) and the user list through a
  password-protected admin area, and can see recent logs and simple totals on a dashboard.
- Every submission is sent in the background to the company's n8n webhook for automation.

The problem this solves is the manual, scattered collection of daily timesheets and the manual
hand-off of that data into the automation platform.

## 4. Scope

### In scope (as built today)

- Public, link-based (UUID) work-log entry form for employees, with multiple entries per
  submission and the ability to create a new task inline.
- Admin authentication (email + password), restricted to administrator accounts.
- Admin management (create / list / edit / delete) of Users, "Project For" (clients/buckets),
  Projects and Tasks.
- Admin dashboard with headline counts and a list of recent work logs.
- Automatic, retrying background delivery of each submission to the n8n webhook.

### Out of scope (not present today)

- Employee self-service login, password reset, or an employee portal to view their own history.
- Approval / review workflow for submitted logs (no manager approval step).
- Editing or deleting a work log after it is submitted.
- Reporting, exports (CSV/PDF), or charts beyond the dashboard counts.
- Email verification and notifications from the application itself.
- Any billing, payroll or invoicing features.

## 5. Key Business Rules

1. Only administrator accounts may log in to the admin area; a non-admin login attempt is
   rejected even with correct credentials.
2. Employees never log in; they submit work logs through their personal UUID link.
3. The catalogue is hierarchical: Project For → Project → Task. A project belongs to exactly
   one "Project For", and a task belongs to exactly one project.
4. A task title must be unique within its project.
5. A "Project For", Project or Task that is already referenced by a work log cannot be deleted,
   and a used task or project cannot be moved to a different parent.
6. Each work-log entry must record between 0.25 and 24 hours; the work date cannot be in the
   future.
7. A user who already has work logs cannot be deleted; an administrator cannot delete or
   demote their own account.
8. Every successful submission is forwarded to n8n with a shared secret header; delivery is
   retried automatically if n8n is temporarily unavailable.

## 6. Success Criteria

- Employees can submit a day's work in under a minute without a password.
- Administrators can keep the client / project / task catalogue and user list accurate.
- 100% of submitted logs are stored and (eventually) delivered to n8n, even if n8n is briefly
  down.
- No employee can lose a submission due to a transient webhook failure.

## 7. Constraints & Assumptions

- Technology: PHP 8.2, Laravel 12, MySQL (local XAMPP database `harva_avaib`), Blade templates
  with Tailwind and Alpine.js loaded from CDN. Hosting is assumed to be a company server /
  XAMPP-style environment (assumed).
- The application relies on an external n8n cloud webhook; the webhook URL and shared secret
  are configuration values.
- Background delivery requires the Laravel queue worker to be running (database queue driver).
- Company / client: Avaib (and related entities QC, TradeSora, Remoteface seen in the seed
  data). Exact organisational ownership is assumed.
- The UUID link is the only credential protecting an employee's submissions; see the Known
  Issues section of the SRS.

## 8. Document Version History

| Version | Date | Author | Description of changes |
|---|---|---|---|
| 1 | 2026-11-16 | AI Engineer | Initial BRD documented from the existing codebase (as-is baseline). |
