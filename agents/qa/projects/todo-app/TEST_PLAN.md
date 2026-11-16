# Test Plan — todo-app

Requirements version: **v2** (2026-11-11) · Plan date: 2026-11-12 · Updated 2026-11-13 · QA agent

## Addendum 2026-11-13 — User status (active/inactive) — Trello card XhsrArpP

> No FR in REQUIREMENTS.md yet (dev flagged to requirements agent). Acceptance criteria below are
> taken from the Trello card: a user has a `status` field (active/inactive); an **inactive user must
> not be able to log in**. Settable through the existing Users module.

| # | Case | Expected | Test / result |
|---|------|----------|---------------|
| ST-1 | Inactive user logs in with correct password | Rejected, stays guest, "account is inactive" message | `AuthTest::test_inactive_user_cannot_log_in` — PASS |
| ST-2 | Active user logs in | Normal login → `/tasks` | `AuthTest::test_active_user_can_log_in` — PASS |
| ST-3 | Wrong password on inactive account | Generic "credentials do not match" (status not leaked) | code review `AuthenticatedSessionController:34-51` — PASS (status checked after `Auth::attempt`) |
| ST-4 | Create user as active | User saved, password hashed | `UserManagementTest::test_user_can_be_created_with_hashed_password` — **FAIL (BUG-1)** |
| ST-5 | Create user as inactive | User saved with status inactive | `UserManagementTest::test_user_can_be_created_as_inactive` — **FAIL (BUG-1)** |
| ST-6 | Create with invalid status value | Rejected (validation) | `UserManagementTest::test_create_rejects_an_invalid_status` — PASS |
| ST-7 | Edit user → deactivate | status becomes inactive | `UserManagementTest::test_update_can_deactivate_a_user` — PASS |
| ST-8 | Self-registered user default | status = active (DB default) | `AuthTest` register tests — PASS |

BUG-1 (`UsersController::store` reads `$validated['name1']`/`['email1']` instead of `name`/`email`)
breaks ST-4 and ST-5. See `QA_REPORT.md`.

---


Scope: Laravel 12 to-do app with hand-rolled auth and per-user task lists.
Code under test: `C:\xampp\htdocs\todo-app`. No newer requirements `CHANGELOG.md`
entry than v2, so the full v2 FR set is in scope.

## Approach
- **Automated (feature) tests** on in-memory SQLite (does not touch `school_db`):
  - Developer suite: `tests/Feature/AuthTest.php`, `tests/Feature/TaskTest.php` (21 tests).
  - QA supplementary suite: `qa/.../tests/QaExtraTest.php` (9 tests) — gap coverage.
- **Live HTTP checks** against `php artisan serve` for behaviour the test harness
  bypasses (CSRF middleware is skipped inside unit tests).
- **Code review** for security (authorization, injection, secrets, escaping, CSRF).
- **Manual/browser** notes for JS-dependent controls (toggle checkbox, delete confirm).

## Traceability: FR-ID → test case(s)

| FR | Requirement | Test case(s) | Type |
|----|-------------|--------------|------|
| FR-1 | Add task; required, trim-empty rejected, max 255 | `TaskTest::user_can_add_a_task`, `::task_title_is_required`, `::task_title_is_limited_to_255_characters`, `QaExtraTest::title_of_exactly_255_characters_is_accepted`, `::missing_title_field_is_rejected`; new task defaults incomplete (asserted in add test) | Auto |
| FR-2 | List own tasks, newest-first, empty state, tenant isolation | `TaskTest::user_sees_only_their_own_tasks`, `::empty_state_is_shown_when_no_tasks`, `::tasks_are_listed_newest_first`, `QaExtraTest::task_created_by_one_user_is_not_visible_to_another` | Auto |
| FR-3 | Toggle complete/incomplete; own-task only | `TaskTest::user_can_toggle_their_task`, `::user_cannot_toggle_another_users_task`, `QaExtraTest::toggle_is_reversible`, `::cross_user_toggle_leaves_owner_data_unchanged`, `::toggle_or_delete_of_missing_task_returns_404`; strike-through styling (code review of `index.blade.php`) | Auto + review |
| FR-4 | Delete; own-task only; confirmation | `TaskTest::user_can_delete_their_task`, `::user_cannot_delete_another_users_task`, `QaExtraTest::toggle_or_delete_of_missing_task_returns_404`; `confirm()` dialog (code review / browser) | Auto + browser |
| FR-5 | Register; unique email, password min 8 + confirm, hashed, auto-login | `AuthTest::user_can_register_and_is_logged_in`, `::registration_requires_matching_password`, `::registration_rejects_duplicate_email`, `::registration_rejects_short_password`, `QaExtraTest::registration_with_uppercase_email_is_rejected` | Auto |
| FR-6 | Login; correct/incorrect creds; guest redirect; remember-me | `AuthTest::user_can_log_in_with_correct_credentials`, `::login_fails_with_wrong_password`, `::guest_is_redirected_from_tasks_to_login`, `QaExtraTest::authenticated_user_is_redirected_away_from_login_and_register`; remember-me (code review of `login.blade.php` + controller) | Auto + review |
| FR-7 | Logout; session ends; task page re-locked | `AuthTest::user_can_log_out`, `QaExtraTest::after_logout_tasks_page_is_locked_again` | Auto |
| FR-8 | Task due date — add / edit / empty / past date / sort | **Not implemented — not testable yet** (see planned cases below) | Blocked |

## FR-8 Task due date — planned test cases (feature not yet built)

> FR-8 is **not in REQUIREMENTS.md** (spec stops at FR-7; v2 open question #5 lists
> "no due dates in this version") and is **not implemented** in the code. The cases
> below are written in advance so they can run the moment the feature ships.
> Acceptance criteria below are QA's assumptions; the requirements agent must
> confirm them before these are authoritative.

| # | Case | Expected |
|---|------|----------|
| FR-8.1 | Add a task with a valid future due date | Task saved with that due date; shown in list |
| FR-8.2 | Add / edit a task leaving the due date **empty** | Accepted; due date is NULL (optional field) |
| FR-8.3 | Edit an existing task's due date (and title) | Change persisted; requires a task-edit route/form (currently **absent** — only completion toggle exists) |
| FR-8.4 | Enter a **past** due date | Behaviour per spec: either rejected with a validation message, or accepted and flagged "overdue" — **needs requirements decision** |
| FR-8.5 | Enter a malformed / non-date value | Rejected with a visible validation message; no task created/changed |
| FR-8.6 | List **sorting** by due date | Tasks ordered by due date (earliest first?); tasks with no due date placed consistently (first or last) — **needs requirements decision** |
| FR-8.7 | Regression: existing newest-first behaviour (FR-2) | If sorting changes to due-date, confirm it does not break FR-2 ordering tests or is reconciled in spec |
| FR-8.8 | Tenant isolation still holds with due dates | Due date does not leak another user's tasks; ownership 404 unchanged |

## Non-functional checks
- CSRF on all mutating forms → live `POST /login` and `POST /register` without token return **419**; all forms carry `@csrf` (code review).
- Passwords hashed (`Hash::make`, `password => 'hashed'` cast) → `AuthTest` asserts `Hash::check` and not plaintext.
- Authorization / tenant isolation → ownership enforced in `TaskController::authorizeOwnership` (`abort_if 404`); cross-user tests confirm 404 + no data change.
- SQL injection → Eloquent relationship queries only, no raw SQL (code review).
- XSS → Blade `{{ }}` auto-escaping on task titles / user name (code review).
- Session fixation → `session()->regenerate()` on login/register, `invalidate()` on logout (code review).
- Auth gates → guest `GET /tasks` redirects to `/login` (live + auto).

## Out of scope (per spec v2)
Email verification, password reset, admin role, priority/categories/search,
soft-delete/undo. Not tested.
(Due dates moved from "out of scope" to **FR-8, pending implementation + spec** — see above.)
