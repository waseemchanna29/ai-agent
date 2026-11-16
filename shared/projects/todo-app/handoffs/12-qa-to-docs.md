# Handoff — QA → Docs

Date: 2026-11-13
Trello card: "User has status field active and inactive" (XhsrArpP)
Verdict: **READY**

## Summary
BUG-1 (user-create 500 from the `name1`/`email1` typo) is fixed and verified. `UsersController::store()`
now reads `$validated['name']` / `$validated['email']`. Re-run on owner request ("test this"):

- `php artisan test` → **36 passed / 0 failed** (110 assertions)
- QA `QaExtraTest` → **9 passed / 0 failed**
- QA `QaSupplementalTest` → **12 passed / 0 failed**
- grep of `app/` for `name1`/`email1` → no matches

The two create tests that failed on the 09:40 run now pass. No new bugs. Full detail in
`agents/qa/projects/todo-app/QA_REPORT.md`.

## Feature summary for docs
Users now have a `status` field (`active` default / `inactive`). An **inactive user cannot log in**
(blocked after password verification, so status isn't leaked on a wrong password). Status is set via
the Users module Add/Edit forms (Active default on create) and shown in the Users list. Self-registered
users are active by default.

## Still open (not a blocker — for requirements)
No FR for user status and no admin role: any logged-in user can change anyone's status, and a user set
inactive while already logged in keeps their current session until it expires (only new logins are
blocked). Requirements to decide admin-gating and whether active sessions must be force-ended.
