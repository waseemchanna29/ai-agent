# Handoff 07 — QA → Development

**Date:** 2026-11-16 14:27
**Project:** worklog_automation
**From:** QA · **To:** Development
**Verdict:** NOT READY — 2 open bugs (see `agents/qa/projects/worklog_automation/QA_REPORT.md`)

## Summary
Re-QA against requirements **v3**. BUG-3 is confirmed fixed (scaffold `ExampleTest` + unused
`welcome.blade.php` removed; `php artisan test` green). The two requirements added to close the
security findings — **FR-12** (bind identity to UUID) and **FR-13** (throttle the GET form route)
— are **not implemented**. No code changes landed for them since v3 (latest dev handoff 06 only
covered BUG-3). QA suite: 35 passed, 2 failed (the 2 failures encode the open bugs).

## Bugs to fix
- **BUG-1 (high)** — FR-12: `POST /worklogs` & `POST /tasks` still take `user_id` from the body.
  `app/Http/Controllers/WorklogController.php:45`, `app/Http/Requests/StoreWorklogRequest.php:18`,
  `app/Http/Controllers/TaskController.php:26`. Fix: resolve the acting user from the UUID
  (UUID-scoped route or signed field), ignore body `user_id`; a foreign `user_id` must never
  write rows for that user (bind to UUID owner or 403). Close condition: QA test
  `test_submission_must_not_write_for_a_foreign_user` goes green.
- **BUG-2 (medium)** — FR-13: add `throttle:30,1` to `GET /worklog/{uuid}` in `routes/web.php:12`
  (do NOT change the URL, add login, or rotate the UUID — owner decision). Close condition: QA
  test `test_form_route_is_rate_limited` goes green (429 beyond the limit).

## Notes
- FR-12 touches the POST contract (how the UUID reaches `/worklogs` and `/tasks`). The front-end
  `worklog/create.blade.php` currently posts `user_id` in the JSON body and has the UUID only in
  the page URL — coordinate the chosen mechanism so the form keeps working.
- After the fix, re-run the QA suite (should be 37/37):
  `php C:/xampp/htdocs/worklog_automation/vendor/phpunit/phpunit/phpunit -c C:/xampp/htdocs/Agents/agents/qa/projects/worklog_automation/phpunit.qa.xml`
