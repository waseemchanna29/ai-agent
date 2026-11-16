# Test Plan — worklog_automation

Requirements version: **3** (2026-11-16). v2 added **FR-12** (submission identity bound to the
UUID — fixes BUG-1 IDOR); v3 added **FR-13** (throttle the public `GET /worklog/{uuid}` route —
the actionable half of BUG-2). The plan below covers FR-1 … FR-13.

Scope: re-QA requested by Trello card "QA" — "based on current requirements, again qa". Since the
last run: development fixed **BUG-3** (handoff 06: deleted the scaffold `ExampleTest` + unused
`welcome.blade.php`), but **FR-12 and FR-13 were not implemented** (no dev handoff for them).

## Environment
- App code: `C:\xampp\htdocs\worklog_automation` (Laravel 12, PHP 8.2).
- QA suite: `agents/qa/projects/worklog_automation/tests/` run with `phpunit.qa.xml`
  (bootstraps the app autoloader, sqlite `:memory:`, does **not** copy app code).
- Command: `php C:/xampp/htdocs/worklog_automation/vendor/phpunit/phpunit/phpunit -c phpunit.qa.xml`

## Traceability — FR-ID → test case(s)

| FR | Requirement | Test(s) | File |
|----|-------------|---------|------|
| FR-1 | Open form by UUID; 404 on unknown; route throttled (v3) | `test_valid_uuid_renders_form`, `test_unknown_uuid_returns_404` (+FR-13) | WorklogFormTest |
| FR-12 | Submission identity bound to UUID; foreign `user_id` never writes for victim | `test_submission_must_not_write_for_a_foreign_user` | WorklogFormTest |
| FR-13 | `GET /worklog/{uuid}` rate-limited (429 beyond limit) | `test_form_route_is_rate_limited` | FormRouteThrottleTest |
| FR-2 | Cascading Project For → Project → Task | Client-side (Alpine) — reviewed in `worklog/create.blade.php`; server-side enforced by FR-3 validation (`test_store_rejects_project_not_in_project_for`, `test_store_rejects_task_not_in_project`) | WorklogFormTest |
| FR-3 | Submit one/more entries with validation, 201 {message,count}, txn | `test_store_valid_worklog`, `test_store_rejects_future_date`, `..._project_not_in_project_for`, `..._task_not_in_project`, `..._hours_out_of_range`, `..._requires_at_least_one_entry` | WorklogFormTest |
| FR-4 | Inline task create, firstOrCreate dedup, 201 | `test_quick_store_creates_task`, `test_quick_store_dedupes_existing_task`, `test_quick_store_validates_required_fields` | TaskQuickStoreTest |
| FR-5 | Forward to n8n: secret header, payload, retry, skip-if-deleted | `test_job_posts_payload_with_secret_header`, `test_job_retry_configuration`, `test_job_exits_quietly_when_logs_deleted`, `test_job_throws_on_non_2xx_so_it_retries` | N8nWebhookJobTest |
| FR-6 | Admin-only login, reject employees, bad creds, logout | `test_admin_can_login_and_reach_dashboard`, `test_employee_cannot_login_even_with_correct_password`, `test_wrong_password_is_rejected`, `test_logout_invalidates_session` | AuthAndAccessTest |
| FR-7 | Admin dashboard counts + recent | `test_admin_dashboard_shows_counts`, `test_guest_redirected_from_dashboard`, `test_employee_forbidden_from_dashboard` | AuthAndAccessTest |
| FR-8 | Users CRUD + guards (self-delete, self-demote, used-user, unique email) | `test_cannot_delete_own_account`, `test_cannot_remove_own_admin_role`, `test_cannot_delete_user_with_worklogs`, `test_user_email_must_be_unique`, `test_employee_cannot_reach_admin_users` | AdminCrudGuardsTest |
| FR-9 | Project For CRUD + guards (used, unique) | `test_cannot_delete_project_for_with_worklogs`, `test_project_for_name_unique` | AdminCrudGuardsTest |
| FR-10 | Projects CRUD + guards (used-delete, no re-parent, unique in PF) | `test_cannot_delete_project_with_worklogs`, `test_cannot_move_used_project_to_other_project_for`, `test_project_name_unique_within_project_for` | AdminCrudGuardsTest |
| FR-11 | Tasks CRUD + guards (used-delete, no re-parent, unique in project) | `test_cannot_delete_task_with_worklogs`, `test_cannot_move_used_task_to_other_project`, `test_task_title_unique_within_project` | AdminCrudGuardsTest |

## Security / NFR checks
- Role middleware (`role:admin`) blocks employees/guests — covered (dashboard + admin users).
- Login throttle (5/min) and submission throttle (30/min) — present in routes; not load-tested.
- IDOR on submission identity (known issue #1) — **deliberately verified** by
  `test_store_accepts_arbitrary_user_id_idor` (confirms the defect).
- Code review for injection/secrets/validation/error handling — see QA report §Review.

## Regression — developer's own suite
`php artisan test` → **OK (1 test)**. The failing `tests/Feature/ExampleTest` (old BUG-3) was
deleted along with the unused `welcome.blade.php`; the suite is now green.
