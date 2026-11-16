# QA Report — worklog_automation

**Date:** 2026-11-16 11:47
**Requirements:** v1 (as-is baseline)
**Trigger:** Trello card "QA" — "qa karo complete project ki" (@waseemiqbal21)
**Scope:** Full-project verification. Dev handoff 02 shipped **no functional code change**
(only a `DEV_NOTES.md` doc file on a branch), so this is a baseline review of the whole app.

## Verdict: **NOT READY**

All 11 functional requirements **pass** their acceptance criteria (36/36 QA tests green). The
blocker is **BUG-1**, a confirmed high-severity security defect (unauthenticated submission
identity / IDOR) — QA cannot sign off a clean READY on an exploitable auth-bypass even though it
is listed as accepted debt in the baseline. Two further issues (BUG-2 medium, BUG-3 low) are
raised for tracking. If the owner accepts the documented security debt for this maintenance
baseline, the functional verdict is READY-with-known-risks — their call.

## Test results

| Suite | Command | Result |
|-------|---------|--------|
| QA suite (this folder) | `php C:/xampp/htdocs/worklog_automation/vendor/phpunit/phpunit/phpunit -c phpunit.qa.xml` | **36 passed, 94 assertions** |
| Developer's suite | `php artisan test` (in app folder) | **1 passed, 1 failed** — `Tests\Feature\ExampleTest` fails (see BUG-3) |

## Traceability — FR → test → status

| FR | Area | Status |
|----|------|--------|
| FR-1 | Open form by UUID; 404 unknown | PASS |
| FR-2 | Cascading Project For→Project→Task (server-side enforcement) | PASS |
| FR-3 | Submit entries, validation, 201, transaction | PASS |
| FR-4 | Inline task create (firstOrCreate dedup) | PASS |
| FR-5 | Forward to n8n (secret header, payload, retry 5×, skip-if-deleted, throw on non-2xx) | PASS |
| FR-6 | Admin-only login, reject employees, logout | PASS |
| FR-7 | Admin dashboard counts + access control | PASS |
| FR-8 | Users CRUD + guards (self-delete, self-demote, used-user, unique email) | PASS |
| FR-9 | Project For CRUD + guards | PASS |
| FR-10 | Projects CRUD + guards (no re-parent when used, unique in PF) | PASS |
| FR-11 | Tasks CRUD + guards (no re-parent when used, unique in project) | PASS |

Full FR → test-case mapping is in `TEST_PLAN.md`.

## Bugs

### BUG-1 — Unauthenticated submission identity (IDOR) · severity: high
- **Files:** `app/Http/Controllers/WorklogController.php:36`,
  `app/Http/Requests/StoreWorklogRequest.php:18`, `app/Http/Controllers/TaskController.php:15`
- **Steps:** `POST /worklogs` (or `POST /tasks`) with a JSON body whose `user_id` is any other
  existing user's id. The request is unauthenticated; validation only checks
  `exists:users,id`. There is no check that the caller owns the UUID that loaded the form.
- **Expected:** a submission is bound to the identity of the UUID link that opened the page.
- **Actual:** the endpoint returns 201 and writes worklogs / creates tasks on behalf of the
  specified user. User ids are sequential integers, so any user can be targeted.
- **Verified by:** `test_store_accepts_arbitrary_user_id_idor` (WorklogFormTest).
- Documented as Known Issue #1 in REQUIREMENTS §9; QA independently confirms it is exploitable.
- **Fix direction:** resolve the acting user from the UUID (e.g. sign the form / carry the
  user via the UUID route and ignore client-supplied `user_id`), or authenticate the employee.

### BUG-2 — `GET /worklog/{uuid}` has no throttle and the UUID never rotates · severity: medium
- **File:** `routes/web.php:12`
- **Steps:** the form-loading route sits outside any `throttle:` group (only the POSTs are
  rate-limited). The UUID is the sole access control and never expires.
- **Expected:** the one secret guarding the form is rate-limited against enumeration and can be
  rotated/expired.
- **Actual:** unlimited requests to `/worklog/{uuid}`; a leaked link grants permanent access.
- Documented as Known Issue #2. Combined with BUG-1 it is the whole employee-side trust model.
- **Fix direction:** add `throttle:` to the GET route; support UUID rotation/expiry.

### BUG-3 — Failing test in the shipped suite (no root route) · severity: low
- **File:** `tests/Feature/ExampleTest.php:17`
- **Steps:** `php artisan test` → `Tests\Feature\ExampleTest` asserts `GET /` returns 200, but
  no `/` route is defined, so the response is 404.
- **Expected:** the delivered test suite is green.
- **Actual:** 1 failing test. It is the default Laravel scaffold test; `welcome.blade.php` is
  also unwired (no landing page).
- **Fix direction:** remove the scaffold `ExampleTest` (and the unused `welcome` view), or add a
  root route. Low risk, but a red suite hides future regressions.

## Code review — other confirmed known issues (report only, not raised as cards)

These match REQUIREMENTS §9 and were confirmed during review; listed so the owner has the full
picture. They are accepted technical debt for this as-is baseline:

- **Secrets:** `AdminSeeder` hardcodes a fixed admin email + password (`Password@123!`); a
  real-looking `N8N_WEBHOOK_SECRET` lives in the on-disk `.env`. Rotate before any deployment;
  never commit. (`.env.example` should hold keys only.)
- **No per-day hours cap** and **no duplicate-submission prevention** — a user can submit the
  same task/date repeatedly and total far more than 24h/day (per-entry cap is 24h).
- **No worklog edit/delete** — submitted logs cannot be corrected in-app.
- **Unreachable employee dashboard branch** in `DashboardController` (dead code: `/dashboard`
  is admin-only).
- **CDN front-end deps** — Tailwind + Alpine loaded from public CDNs at runtime
  (availability / supply-chain).
- **Mixed-language** login error ("Email ya password galat hai.") is not in English.
- **Implicit queue-worker dependency** — with no worker running, submissions are saved but never
  delivered to n8n, with no in-app visibility of the backlog.

**No new undocumented functional defects were found.** SQL access is via Eloquent/parameterised
queries (no injection), CSRF is active on browser POSTs, passwords are bcrypt-hashed, and
referential-integrity guards behave as specified.

## Recommendations
1. Address **BUG-1** before exposing the form beyond trusted users — bind identity to the UUID.
2. Add a throttle to the form GET route and plan UUID rotation (**BUG-2**).
3. Clean up the scaffold test / root route (**BUG-3**) so the suite is green and protects future work.
4. Add real automated coverage to the app repo — the shipped suite only has scaffold examples.
   The QA suite in this folder can be ported into `tests/` by the development agent.
5. Before any non-local deployment: change seeder credentials and rotate the n8n secret.

## How to re-run the QA suite
```
php C:/xampp/htdocs/worklog_automation/vendor/phpunit/phpunit/phpunit \
  -c C:/xampp/htdocs/Agents/agents/qa/projects/worklog_automation/phpunit.qa.xml
```
