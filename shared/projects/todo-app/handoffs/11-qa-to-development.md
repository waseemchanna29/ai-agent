# Handoff — QA → Development

Date: 2026-11-13
Trello card: "User has status field active and inactive" (XhsrArpP)
Verdict: **NOT READY** — 1 high-severity bug.

## Summary
The status feature's core behaviour is correct: **inactive users cannot log in**, active users can,
status is not leaked on a wrong password, and update/deactivate + invalid-status rejection all work.

But this card **regressed new-user creation**. `php artisan test` is **34 passed / 2 FAILED**
(not the 35/0 your handoff reported). Both failures are the same bug.

## BUG-1 (High) — must fix
- **File:** `app/Http/Controllers/UsersController.php:47-48`
- `store()` reads `$validated['name1']` and `$validated['email1']`; the validated keys are
  `'name'` and `'email'`. → `Undefined array key "name1"` → HTTP 500, no user saved.
- Fix: `$validated['name1']` → `$validated['name']`, `$validated['email1']` → `$validated['email']`.
- `update()` (lines 77-79) is already correct — only `store()` is affected.
- Pre-card backup had the correct keys, so this is a regression introduced while adding the `status`
  line to the `create()` array.

## Re-test
After the fix: `php artisan test` should be **36/36 green**. Full details + traceability in
`agents/qa/projects/todo-app/QA_REPORT.md`. Open bug also written to `trello-bugs.json` for the card.

## Still open (not a blocker for this card — for requirements, already flagged)
No FR/admin role for status: any logged-in user can change anyone's status, and a user set inactive
keeps their current session until it expires (only new logins are blocked).
