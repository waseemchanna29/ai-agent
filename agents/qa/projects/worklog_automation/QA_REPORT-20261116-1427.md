# QA Report — worklog_automation

**Date:** 2026-11-16 14:27
**Requirements:** v3 (FR-12 identity binding, FR-13 form-route throttle)
**Trigger:** Trello card "QA" — "qa again" (@waseemiqbal21)
**Scope:** Re-QA of the whole app against the current (v3) requirements and a regression check.
**Note:** No code or requirements changes since the previous (12:10) run — latest dev handoff is
still 06 (BUG-3 only); FR-12 and FR-13 remain unimplemented. Result is unchanged.

## Verdict: **NOT READY**

Of the three bugs from the previous run, only **BUG-3 is fixed**. The two requirements the owner
added specifically to close the security findings — **FR-12** (bind submission identity to the
UUID) and **FR-13** (throttle the public form route) — are **not implemented in the code**, so
**BUG-1 (high) and BUG-2 (medium) remain open**. Both are proven by failing QA tests.

## Test results

| Suite | Command | Result |
|-------|---------|--------|
| QA suite (this folder) | `php …/phpunit -c phpunit.qa.xml` | **35 passed, 2 failed** (the 2 failures = BUG-1 and BUG-2, by design) |
| Developer's suite | `php …/phpunit -c <app>/phpunit.xml` | **OK, 1 test** (green — BUG-3 scaffold test removed) |

Failing QA tests (expected until the fixes land):
- `WorklogFormTest::test_submission_must_not_write_for_a_foreign_user` → a worklog is still
  written for the foreign/body `user_id` (**BUG-1 / FR-12**).
- `FormRouteThrottleTest::test_form_route_is_rate_limited` → `GET /worklog/{uuid}` returns 200
  indefinitely, never 429 (**BUG-2 / FR-13**).

## Traceability — FR → status

| FR | Area | Status |
|----|------|--------|
| FR-1 | Open form by UUID; 404 unknown | PASS |
| FR-2 | Cascading selection (server-side enforcement) | PASS |
| FR-3 | Submit entries, validation, 201, transaction | PASS (validation); **identity binding FAIL — see FR-12** |
| FR-4 | Inline task create (firstOrCreate dedup) | PASS (dedup); **identity binding FAIL — see FR-12** |
| FR-5 | Forward to n8n (secret header, payload, retry, skip-if-deleted, throw on non-2xx) | PASS |
| FR-6 | Admin-only login, reject employees, logout | PASS |
| FR-7 | Admin dashboard counts + access control | PASS |
| FR-8 | Users CRUD + guards | PASS |
| FR-9 | Project For CRUD + guards | PASS |
| FR-10 | Projects CRUD + guards | PASS |
| FR-11 | Tasks CRUD + guards | PASS |
| **FR-12** | Submission identity bound to UUID (ignore body `user_id`) | **FAIL — not implemented** |
| **FR-13** | Throttle `GET /worklog/{uuid}` (429 beyond limit) | **FAIL — not implemented** |

## Bugs (open)

### BUG-1 — FR-12 not implemented: submission identity still trusts body `user_id` (IDOR) · high
- **Files:** `app/Http/Controllers/WorklogController.php:45` (writes `user_id => $data['user_id']`),
  `app/Http/Requests/StoreWorklogRequest.php:18` (`'user_id' => ['required','exists:users,id']`),
  `app/Http/Controllers/TaskController.php:26` (`created_by/updated_by` from body `user_id`).
- **Steps:** `POST /worklogs` with a body `user_id` for another user → still returns 201 and
  creates worklogs owned by that victim user. No UUID is required or checked at submit time.
- **Expected (v3 FR-12):** the acting user is resolved from the UUID; a foreign `user_id` is
  ignored (bound to the UUID owner) or rejected (403) — it must never write rows for another user.
- **Actual:** rows are created for the body-supplied user (unchanged from v1).
- **Verified by:** `test_submission_must_not_write_for_a_foreign_user` (fails).
- **Note:** no dev handoff addressed FR-12; the code is byte-for-byte unchanged on this path.

### BUG-2 — FR-13 not implemented: public form route is not throttled · medium
- **File:** `routes/web.php:12` — `Route::get('/worklog/{uuid}', …)` sits outside any `throttle`
  group (only the POSTs and login are throttled).
- **Steps:** request `GET /worklog/{uuid}` repeatedly — every request returns 200/404; the route
  never returns 429.
- **Expected (v3 FR-13):** throttled (suggested 30/min per IP), applied equally to valid and 404
  responses, returning 429 beyond the limit; UUID/URL unchanged.
- **Actual:** unlimited requests permitted.
- **Verified by:** `test_form_route_is_rate_limited` (fails).
- **Owner-veto caveat:** FR-13 states the owner may veto the throttle on the BUG-2 card; if vetoed,
  FR-13/this bug is withdrawn. No veto is recorded in `decisions.md`, so it stands.

## Fixed since last run (verified)

- **BUG-3 (was low)** — `tests/Feature/ExampleTest.php` (asserting `GET /` == 200) and the unused
  `resources/views/welcome.blade.php` were deleted (dev handoff 06). Confirmed: both files are
  gone and `php artisan test` is green (1 test). **Closed.**

## Not re-raised (owner decisions, per `decisions.md`)

- Public, no-login form and **permanent, non-rotating UUID** are intentional by design — not
  defects. (BUG-2's original rotation/expiry half is declined; only the throttle remains, = FR-13.)
- Other known technical debt (seeder credentials, `.env` secret, no per-day hours cap, no worklog
  edit/delete, CDN front-end deps, Roman-Urdu login message, implicit queue worker) is unchanged
  from v1 and accepted for this baseline — see the previous report / REQUIREMENTS §9.

## Recommendations
1. Implement **FR-12**: pass the UUID to `POST /worklogs` and `POST /tasks` (UUID-scoped route or
   signed field), resolve the user server-side, and drop `user_id` as a trusted input.
2. Implement **FR-13**: add `throttle:30,1` to the `GET /worklog/{uuid}` route in `routes/web.php`.
3. Re-run the QA suite — the two currently-red tests should go green, giving 37/37.
4. Consider porting the QA suite (`tests/` + `phpunit.qa.xml`) into the app so coverage ships with
   the code.

## How to re-run the QA suite
```
php C:/xampp/htdocs/worklog_automation/vendor/phpunit/phpunit/phpunit \
  -c C:/xampp/htdocs/Agents/agents/qa/projects/worklog_automation/phpunit.qa.xml
```
