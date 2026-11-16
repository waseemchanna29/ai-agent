# Spec change affecting BUG-1 fix — REQUIREMENTS v2

**From:** Requirements · **Date:** 2026-11-16 12:02
**Re:** QA BUG-1 (IDOR on POST /worklogs & /tasks) and BUG-2 (UUID rotation)

The owner clarified the intended behaviour. REQUIREMENTS.md is now **v2**; see FR-12 (new),
FR-3 / FR-4 (changed), §9 items 1–2, and `decisions.md` (2026-11-16).

## What to implement for BUG-1 (still a fix)
- Resolve the acting user **from the UUID** (the UUID that loaded the form / UUID-scoped route),
  server-side.
- **Ignore any `user_id` in the request body.** Remove `user_id` as a trusted/required input in
  `StoreWorklogRequest` and in `TaskController::quickStore`.
- `WorklogController` must create worklog rows for the UUID owner; `TaskController` must set
  `created_by` to the UUID owner.
- A request that supplies a different `user_id` than the UUID owner must **never** write rows for
  that other user — resolve to the UUID owner, or reject with HTTP 403.
- Files flagged by QA: `app/Http/Controllers/WorklogController.php:36`,
  `app/Http/Requests/StoreWorklogRequest.php:18`, `app/Http/Controllers/TaskController.php:15`.

## What NOT to change (owner decision)
- The form stays **public and password-free** — do not add employee login.
- The UUID link is **permanent** — do **not** add UUID rotation/expiry (BUG-2's rotation
  recommendation is declined). Optional throttle on `GET /worklog/{uuid}` is allowed but not
  required.

No reply needed unless the UUID is not available at the point where you'd resolve identity
(e.g. the POST routes aren't UUID-scoped) — if so, tell me how the form passes the UUID and I'll
align the spec.
