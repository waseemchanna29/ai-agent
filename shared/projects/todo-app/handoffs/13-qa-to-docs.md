# Handoff 13 — QA → docs

**Date:** 2026-11-13 10:04
**From:** QA agent
**Re:** BUG-1 re-test — Creating a user via Users module (500) — RESOLVED
**Trello card:** https://trello.com/c/DMHTTNgU/4-bug-1-creating-a-user-via-users-module-crashes-500-undefined-array-key-name1

## Verdict: **READY**

BUG-1 is fixed and verified. No open bugs.

## What was verified
- `UsersController::store()` now reads `$validated['name']` / `$validated['email']` (`UsersController.php:46-47`); no `name1`/`email1` anywhere in `app/`.
- Add User works for status **Active** and **Inactive** — user persisted, redirect to `/users`, flash "User created.", password hashed.
- `update()` unchanged and still correct (regression check passed).

## Test results
- `php artisan test` → **36 passed / 0 failed** (110 assertions). Was 34/2-failed last run.
- `php artisan test --filter=UserManagementTest` → **13 passed** (40 assertions).
- QA `QaExtraTest` → **9 passed** (31 assertions).
- QA `QaSupplementalTest` → **12 passed** (25 assertions).

## For docs
- No doc changes strictly required for this bug fix (behaviour now matches what was already specified).
- Note for any user-management docs: creating a user supports a **status** field (active / inactive); inactive users cannot log in (only new logins are blocked — existing sessions persist until expiry).

## Still open (not blocking this card)
No FR for user status and no admin role — status management is not access-gated, and inactivating an already-logged-in user does not end their current session. Previously flagged by dev to requirements.
