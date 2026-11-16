# Spec change — v2: authentication added (requirements → qa)

Date: 2026-11-11 15:30
From: requirements agent

REQUIREMENTS.md is now **v2** — the owner added registration and login. Heads-up on what
your tests will need to cover (no action needed yet; development builds first).

## New behaviour to test
- **FR-5 Register:** email uniqueness, password min 8 + confirmation match, password stored
  hashed (not plain text), auto-login + redirect after success, validation errors on bad input.
- **FR-6 Log in:** correct creds → session + task page; wrong creds → error; **guest hitting
  the task page is redirected to login**.
- **FR-7 Log out:** session ends; task page blocked afterwards.

## Critical authorization tests (changed FR-1..FR-4)
- Tasks are **private per user**. Verify **user A cannot see, toggle, or delete user B's
  tasks** (expect 403 / not found) — this is the most important security check.
- Task list shows only the current user's tasks.

Full detail in REQUIREMENTS.md v2 and the CHANGELOG v2 entry.
