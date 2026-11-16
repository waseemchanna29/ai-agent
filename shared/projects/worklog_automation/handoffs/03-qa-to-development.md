# Handoff 03 — QA → Development

**Date:** 2026-11-16 11:47
**Project:** worklog_automation
**From:** QA · **To:** Development
**Verdict:** NOT READY (3 bugs; see `agents/qa/projects/worklog_automation/QA_REPORT.md`)

## Summary
Full-project QA of the v1 as-is baseline. All 11 FRs pass their acceptance criteria
(36/36 QA tests green, `phpunit.qa.xml`). Blocker is a confirmed high-severity security defect.
No new undocumented functional bugs were found.

## Bugs to fix
- **BUG-1 (high)** — IDOR: `POST /worklogs` & `POST /tasks` accept any `user_id` from the body
  with no UUID/ownership check. `app/Http/Controllers/WorklogController.php:36`,
  `app/Http/Requests/StoreWorklogRequest.php:18`, `app/Http/Controllers/TaskController.php:15`.
  Fix: derive the acting user from the UUID route; ignore client-supplied `user_id`.
- **BUG-2 (medium)** — `GET /worklog/{uuid}` has no throttle and the UUID never rotates.
  `routes/web.php:12`. Fix: add `throttle:`; support UUID rotation/expiry.
- **BUG-3 (low)** — `tests/Feature/ExampleTest.php:17` fails (`GET /` → 404, no root route).
  Fix: remove the scaffold test (+ unused `welcome.blade.php`) or add a root route.

## Notes
- BUG-1 and BUG-2 are documented as accepted debt in REQUIREMENTS §9; QA raised them because the
  IDOR is independently confirmed exploitable. If the owner accepts the debt for this maintenance
  baseline, the functional verdict is READY-with-known-risks — confirm with the owner.
- The app ships no real tests (only scaffold). A ready-to-port QA suite lives in
  `agents/qa/projects/worklog_automation/tests/` + `phpunit.qa.xml` — consider moving it into the
  app's `tests/` so coverage travels with the code.
- Other confirmed known issues (seeder credentials, .env secret, no per-day cap, CDN deps, etc.)
  are listed in the QA report §Code review — report-only, not blocking.
