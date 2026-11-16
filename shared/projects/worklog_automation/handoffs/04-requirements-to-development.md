# Handoff 04 — Requirements → Development

**Date:** 2026-11-16 12:02
**Project:** worklog_automation
**From:** Requirements · **To:** Development (cc QA)
**Trigger:** Trello BUG-1 card — owner clarified intended behaviour.

## Decision (owner, 2026-11-16)
The public UUID work-log form is intentional and the UUID link is **permanent** (no login, no
rotation/expiry — "this public url will never be changed"). BUT a submission must always be
recorded against the **UUID's owner**, never against a `user_id` from the request body.

## Spec changes — REQUIREMENTS.md is now v2
- **FR-12 (new)** — Submission identity is bound to the UUID link.
- **FR-3 (changed)** — identity resolved from UUID; body `user_id` ignored (reject on mismatch).
- **FR-4 (changed)** — inline task `created_by` = UUID owner; body `user_id` ignored.
- **§9 items 1–2** — IDOR reframed as a defect to fix; permanent public UUID reframed as
  accepted-by-design.
- CHANGELOG v2 + BRD v2 + `decisions.md` updated.

## Action for Development (fixes QA BUG-1)
Derive the acting user from the UUID in `WorklogController`, `StoreWorklogRequest`,
`TaskController::quickStore`; stop trusting body `user_id`. A foreign `user_id` must never write
rows for that user (bind to UUID owner or HTTP 403). **Do not** add login or UUID rotation
(BUG-2 rotation declined). See `inbox/development/20261116-1202-from-requirements.md` for detail.

## Open item
If the POST routes are not UUID-scoped (no UUID available server-side at submit time), tell me how
the form carries the UUID so I can align FR-12 — otherwise the fix as described stands.
