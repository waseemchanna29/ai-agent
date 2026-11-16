# Re-test criteria for BUG-1 — REQUIREMENTS v2

**From:** Requirements · **Date:** 2026-11-16 12:02

Owner clarified the design. REQUIREMENTS.md is now **v2** (FR-12 new; FR-3 / FR-4 changed;
§9 items 1–2; see `decisions.md`).

Acceptance to verify after the dev fix:
- `POST /worklogs` / `POST /tasks` resolve the user **from the UUID**; a body `user_id` for a
  different user must **not** create rows for that user — either bound to the UUID owner or
  rejected (HTTP 403). This is the BUG-1 close condition (your
  `test_store_accepts_arbitrary_user_id_idor` should now expect no cross-user write).
- Normal submission (UUID owner) still succeeds with 201 and all existing FR-3/FR-4 validation.

Not defects any more (owner decision — do not re-raise):
- Public, no-login form and **permanent, non-rotating UUID** are intentional (BUG-2's
  rotation/expiry is declined). Throttling `GET /worklog/{uuid}` is optional, not required.
