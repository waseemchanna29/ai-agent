# QA Report — todo-app

Date: 2026-11-13 10:51 · QA agent
Requirements version: **v2** (no FR yet for user status — flagged by dev to requirements agent)
Trello card: **"User has status field active and inactive"** (XhsrArpP) / BUG-1 fix (DMHTTNgU)
Scope: re-run on owner request ("test this") — re-verify the BUG-1 fix (Add User, Active + Inactive) plus full regression of all suites. No application code has changed since the fix. No code changed by QA.

## Verdict: **READY**

BUG-1 is fixed. `UsersController::store()` now reads the correct validated keys `'name'` / `'email'`.
Creating a user via the Users module succeeds (Active and Inactive), redirects to `/users` with
flash "User created.", and persists the row. The two tests that failed last run now pass; all
suites are green with no new regressions.

## 1. Re-test — BUG-1 acceptance criteria

| # | Criterion | Result |
|---|-----------|--------|
| RT-1 | Add User with status=**Active** → user created, redirect to `/users`, flash "User created." | **PASS** — `UserManagementTest::test_user_can_be_created_with_hashed_password` |
| RT-2 | Add User with status=**Inactive** → user created and persisted as `inactive` | **PASS** — `UserManagementTest::test_user_can_be_created_as_inactive` |
| RT-3 | Password is hashed, never stored as plaintext | **PASS** — same test, `Hash::check` asserted |
| RT-4 | No `name1`/`email1` keys remain anywhere in app code | **PASS** — grep of `app/` returns no matches |
| RT-5 | `store()` reads `$validated['name']` / `$validated['email']` | **PASS** — code review `UsersController.php:46-47` |
| RT-6 | Regression: `update()` still correct and untouched | **PASS** — `UsersController.php:77-79`; update tests pass |

## 2. Test results (commands run + pass/fail)

| # | Command | Suite | Result |
|---|---------|-------|--------|
| 1 | `php artisan test` (in `C:\xampp\htdocs\todo-app`) | Dev Unit+Feature (Example, Auth, Task, UserManagement) | **36 passed / 0 failed** (110 assertions) |
| 2 | `php artisan test --filter=UserManagementTest` | UserManagement only | **13 passed / 0 failed** (40 assertions) |
| 3 | `php vendor/phpunit/phpunit/phpunit -c <qa>/tests/phpunit.qa.xml` | QA `QaExtraTest` | **9 passed / 0 failed** (31 assertions) |
| 4 | `php vendor/phpunit/phpunit/phpunit --bootstrap <app>/vendor/autoload.php <qa>/tests/QaSupplementalTest.php` | QA `QaSupplementalTest` | **12 passed / 0 failed** (25 assertions) |

Previously-failing tests now passing:
- `UserManagementTest::test_user_can_be_created_with_hashed_password` → **PASS**
- `UserManagementTest::test_user_can_be_created_as_inactive` → **PASS**

Dev total moved from **34 passed / 2 failed** (last run) to **36 passed / 0 failed**.

## 3. Traceability: feature → test → result

| Item | Test(s) | Result |
|------|---------|--------|
| FR-1..FR-4 Task CRUD + isolation | `TaskTest`, `QaExtraTest` | PASS (no regression) |
| FR-5 Register (active default) | `AuthTest` register tests | PASS |
| FR-6 Login + guest redirect | `AuthTest` login/guest tests | PASS |
| FR-7 Logout | `AuthTest::test_user_can_log_out` | PASS |
| Status — inactive blocked | `AuthTest::test_inactive_user_cannot_log_in` | PASS |
| Status — active allowed | `AuthTest::test_active_user_can_log_in` | PASS |
| Status — create as active (hashed pw) | `UserManagementTest::test_user_can_be_created_with_hashed_password` | **PASS (BUG-1 fixed)** |
| Status — create as inactive | `UserManagementTest::test_user_can_be_created_as_inactive` | **PASS (BUG-1 fixed)** |
| Status — reject invalid | `UserManagementTest::test_create_rejects_an_invalid_status` | PASS |
| Status — update/deactivate | `UserManagementTest::test_update_can_deactivate_a_user` | PASS |
| User delete / self-delete 403 | `UserManagementTest` delete tests | PASS |

## 4. Bugs

**None.** BUG-1 is resolved and verified. No new bugs found in this run.

### BUG-1 (CLOSED) — Creating a user via the Users module crashed (500)
- **File:** `app/Http/Controllers/UsersController.php:46-47`
- **Was:** `User::create` read `$validated['name1']` / `$validated['email1']` → `Undefined array key` → HTTP 500, no user saved.
- **Now:** reads `$validated['name']` / `$validated['email']`. Verified by re-test (RT-1..RT-5) and the two tests above.

## 5. Recommendations
1. BUG-1 is done — this card is clear to ship.
2. Still open (previously flagged by dev to requirements, **not** a blocker for this card): no FR for
   user status and no admin role — any logged-in user can change anyone's status, and a user set
   inactive while already logged in keeps their session until it expires (only *new* logins are
   blocked). Requirements should decide whether status management is admin-gated and whether active
   sessions must be force-ended.

## 6. Environment
- Laravel 12, PHP 8.2, feature tests on in-memory SQLite (`RefreshDatabase`); `school_db` untouched.
- No application or test files were modified by QA.
