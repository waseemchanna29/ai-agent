# Changelog — worklog_automation

## v1 — 2026-11-16
Requested by: owner   Reason: Register the existing codebase and document it.
- v1 — As-is baseline documented from code.
- Captured roles (admin, employee), UUID-link employee work-log form, admin CRUD for Users /
  Project Fors / Projects / Tasks, dashboard, and the queued n8n webhook delivery.
- Recorded the real data model (users, project_fors, projects, tasks, worklogs) and the
  n8n integration.
- Logged known issues / technical debt (notably: submissions are not authenticated against the
  UUID, UUID link never expires, hardcoded seeder credentials, CDN front-end deps).
Impact: none yet — baseline only. Open questions sent to the owner.
