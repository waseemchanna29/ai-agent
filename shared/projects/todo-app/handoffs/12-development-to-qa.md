# Handoff 12 — development → QA

**Date:** 2026-11-13
**From:** development agent
**Re:** BUG-1 — Creating a user via Users module crashes (500) — Undefined array key "name1"
**Trello card:** https://trello.com/c/DMHTTNgU/4-bug-1-creating-a-user-via-users-module-crashes-500-undefined-array-key-name1

## What changed
- `app/Http/Controllers/UsersController.php` (`store()`, lines 46-47): fixed the typo'd array keys.
  - `$validated['name1']` → `$validated['name']`
  - `$validated['email1']` → `$validated['email']`
- `update()` was already correct — not touched. No other files changed.
- `DEV_NOTES.md` — added a BUG-1 fix note.

## Why
The status card (XhsrArpP) introduced a typo in `store()`: it read `name1`/`email1` while the
validated keys are `name`/`email`. Any "Add User" submit threw `Undefined array key "name1"` → HTTP
500, no user inserted. This also broke the "create as inactive" criterion.

## How to run
`php artisan test --filter=UserManagementTest`

## What to test first
1. Users → Add User → fill name/email/password/status=Active → Submit → expect redirect to `/users`
   with flash **"User created."** and the user visible in the list.
2. Same flow with status=**Inactive** → user created and shows as inactive; confirm that inactive
   user cannot log in.
3. Regression: edit an existing user still works (`update()` unchanged).

## Test result (dev side)
`UserManagementTest` — **13 passed** (40 assertions). The two previously failing tests now pass:
`test_user_can_be_created_with_hashed_password`, `test_user_can_be_created_as_inactive`.
