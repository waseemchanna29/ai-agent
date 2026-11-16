# Handoff 06 — docs → owner

Date: 2026-11-12 15:01
From: docs agent
Task: update CHANGELOG.md

## What changed
`agents/docs/projects/todo-app/CHANGELOG.md` now has a new **[Unreleased] — User
management module** entry, added above the `[1.0.0]` release. This documents the
feature that landed since the last changelog pass, built in two development fixes:
- `20261112-1112-fix.md` — proposed the module (controller only, partial apply).
- `20261112-1436-fix.md` — completed it (6 routes, 3 views, nav link, 10 tests);
  **applied and accepted** per `board.md` (15:00).

Verified against the actual code before writing (not just DEV_NOTES.md): confirmed the
6 `users.*` routes in `routes/web.php`, `UsersController.php`, the three
`resources/views/users/*.blade.php` files, the nav link, and
`tests/Feature/UserManagementTest.php` all exist as described.

Entry covers: listing (paginated), add, edit (optional password), delete (cascades to
the user's tasks, blocked for your own account → 403), reachable via a new "Users" nav
link, open to every logged-in user (no admin role exists), and the test count
(31 passing, up from 30). Added version-history row 2.

## Flagged, not fixed (documented as known gaps, not silently glossed over)
- **Not yet verified by QA** — `QA_REPORT.md` (09:35) predates this feature (built
  14:36); no QA report covers it yet.
- **Not yet in REQUIREMENTS.md** — still v2, no FR for user management and no admin
  role. Development already flagged this to the requirements inbox
  (`inbox/requirements/20261112-1436-from-development.md`); I did not duplicate it,
  just referenced the open decision (admin-gating via `is_admin`) in the changelog.
- Labelled "[Unreleased]" rather than folded into `[1.0.0]` since it wasn't part of the
  QA'd 1.0.0 build — will fold into a proper version once QA/requirements catch up.

## Not touched
USER_GUIDE.md, API.md, ARCHITECTURE.md, DEPLOYMENT.md, and the code README still don't
cover the Users screens (out of scope for this task — "Changelog update karo" only).
Say the word if you want the full doc set refreshed for this feature too.
