# Decisions (append-only)

## 2026-11-16 — Public UUID link & submission identity (owner, re QA BUG-1 / BUG-2)
Decided by: owner (@waseemiqbal21) · Recorded by: Requirements
- The work-log form is deliberately **public and password-free**. There is no employee
  dashboard/login and none is planned.
- The UUID link is **permanent**: it does not expire and is not rotated. The public URL will
  never be changed. (Declines QA BUG-2's rotation/expiry recommendation.)
- Whoever holds a user's UUID may submit **for that user**. Every submission (work log and inline
  task) must be recorded against the **UUID's owner** — resolved server-side from the UUID — and
  **never** against a `user_id` supplied in the request body.
- Therefore QA BUG-1 (IDOR: submitting as an arbitrary/other user) **is still a defect to fix**;
  the fix is to derive identity from the UUID and ignore body `user_id`. This does not conflict
  with the public design — it only prevents impersonating a user other than the UUID owner.
- Spec: REQUIREMENTS.md v2 — FR-12 (new), FR-3 / FR-4 (changed), §9 items 1–2.

## 2026-11-16 — QA BUG-2 resolution: throttle the public GET route, keep the URL (owner)
Decided by: owner (@waseemiqbal21, on the BUG-2 card) · Recorded by: Requirements
- Owner confirmed: `worklog/{uuid}` is a **public URL for each employee** and is permanent.
- UUID rotation / expiry: **declined** (unchanged from the 2026-11-16 decision above).
- Missing rate limit on `GET /worklog/{uuid}` (the other half of BUG-2): **resolved by throttling**
  the route (suggested 30/min per IP), which does **not** change the URL. New FR-13.
- Owner may veto the throttle on the BUG-2 card; if so, FR-13 is withdrawn and the route stays
  unthrottled.
- Spec: REQUIREMENTS.md v3 — FR-13 (new), FR-1 (changed), §4 / §7 / §9 item 2 (changed).
