# Changelog — worklog_automation

## v3 — 2026-11-16
Requested by: owner   Reason: Owner confirmed on the QA BUG-2 card that `worklog/{uuid}` is a public URL for each employee and is permanent. UUID rotation/expiry stays declined; the remaining half of BUG-2 (no rate limit on the GET route) is resolved by throttling, without changing the URL.
- Added:    FR-13 Throttle the public work-log form route `GET /worklog/{uuid}` (30/min per IP) to limit UUID enumeration; URL unchanged, no login, no rotation.
- Changed:  FR-1 Open personal work-log form — acceptance notes the route is now throttled (valid and 404 responses alike).
- Changed:  §4 Non-Functional (security) — added the GET form route to the throttled endpoints.
- Changed:  §7 Interfaces — `GET /worklog/{uuid}` marked throttled 30/min per IP.
- Changed:  §9 Known Issues item 2 — permanence stays accepted-by-design; throttle now required (was "optional").
Impact: Development: add `throttle:30,1` (or project-standard limiter) to the `GET /worklog/{uuid}` route in `routes/web.php:12`; do NOT rotate/expire the UUID or add login. QA: re-test that the GET route returns 429 beyond the limit and that the throttle keys per IP and applies to both valid and unknown UUIDs. Owner may veto the throttle on the BUG-2 card, in which case FR-13 is withdrawn.

## v2 — 2026-11-16
Requested by: owner   Reason: Owner decision on the QA security findings (BUG-1 IDOR, BUG-2 UUID rotation). The public UUID link is intentional and permanent; submissions must belong to the UUID's owner.
- Added:    FR-12 Submission identity is bound to the UUID link (public, no-login by design; acting user resolved from the UUID, never from the request body).
- Changed:  FR-3 Submit work-log entries — removed `user_id` as a trusted required input; identity is resolved from the UUID and client-supplied `user_id` is ignored (rejected on mismatch).
- Changed:  FR-4 Add a task inline — `created_by` is the UUID owner resolved server-side; client-supplied `user_id` is ignored.
- Changed:  §9 Known Issues — item 1 (IDOR) reframed as a defect being fixed; item 2 (public non-expiring UUID) reframed as accepted-by-design per the owner.
Impact: Development must change `WorklogController`, `StoreWorklogRequest`, `TaskController` to derive the user from the UUID route and stop trusting body `user_id` (fixes QA BUG-1). QA must re-test that a submission with a foreign `user_id` is bound to the UUID owner or rejected (403), never written for the victim. BUG-2's UUID rotation/expiry recommendation is declined by the owner; the URL stays permanent.

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
